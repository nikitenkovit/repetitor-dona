<?php


class nc_netshop_filter {

    //--------------------------------------------------------------------------

    protected $fields      = array();
    protected $filter_data = array();

    /** @var nc_netshop */
    protected $netshop;
    /** @var nc_db */
    protected $db;

    //--------------------------------------------------------------------------

    protected $options = array(
        // Фильтрует возможные значения полей для текущего установленного фильтра.
        // Т.е. фильтр предлагает выбрать только те параметры по которым можно найти товар
        'filter_values' => true,

        'list_field' => 'select',
        'bool_field' => 'checkbox',

        // Не показывает поля, для которых нет выбора (списки с одним вариантом,
        // диапазон без разбега)
        'show_fields_without_choice' => false,

        // Показывать фильтр для всех инфоблоков компонента, а не только для текущего
        'ignore_cc' => false,

        // Где хранятся параметры фильтра
        // session - в массиве $_SESSION
        // url     - в урле ?filter=...
        // 'driver' => 'session',
    );


    public $driver = 'session';

    //--------------------------------------------------------------------------

    public function __construct(nc_netshop $netshop) {
        $this->db = nc_core('db');
    }

    //--------------------------------------------------------------------------

    public function options($key = null, $set = null) {

        if ( ! is_null($key)) {

            if (is_array($key)) {
                foreach ($key as $k => $val) {
                    $this->options[$k] = $val;
                }
            }
            else {
                if ( ! is_null($set)) {
                    $this->options[$key] = $set;
                }

                return $this->options[$key];
            }

        }

        return $this->options;
    }

    //--------------------------------------------------------------------------

    /**
     * Установка полей для фильтра товаров
     * @param  array  $fields Массив полей. ['fieldA', 'fieldB', ...] ['fieldA', 'fieldB'=>['type'=>'list'], ...]
     * @return array  $fields
     */
    public function init_fields($fields = array()) {
        static $filter_fields;

        if ($fields) {
            $all_fields    = $this->get_all_fields();
            $filter_fields = array();

            foreach ($fields as $name => $field) {
                if (is_numeric($name) && is_string($field)) {
                    $filter_fields[$field] = array();
                }
                else {
                    $filter_fields[$name] = $field;
                }
            }

            foreach ($filter_fields as $name => $field) {
                if (empty($all_fields[$name])) {
                    unset($filter_fields[$name]);
                    continue;
                }

                $f = $all_fields[$name];

                $filter_fields[$name] = array(
                    'id'    => $f['id'],
                    'name'  => $name,
                    'type'  => isset($field['type']) ? $field['type'] : $this->get_default_type_by_id($f['type']),
                    'label' => isset($field['label']) ? $field['label'] : $f['description'],
                );
                $filter_fields[$name]['field'] = isset($field['field']) ? $field['field'] : $this->get_default_field_by_type($field['type']);
            }

            $this->init_filter_data($filter_fields);
        }


        return $filter_fields;
    }

    //--------------------------------------------------------------------------

    const VARIANT_VALUES_IN_SUBQUERY = 1;
    const VARIANT_VALUES_IN_JOIN = 2;
    const NO_VARIANT_VALUES = 3;

    /**
     * @param array $fields
     * @param int $lookup_type self::VARIANT_VALUES_IN_SUBQUERY, self::VARIANT_VALUES_IN_JOIN
     * @return string
     */
    protected function get_query_conditions($fields, $lookup_type) {
        $query = '';
        $component_id = $this->get_current_component_id();

        foreach ($fields as $name => $field) {
            $f_current = $this->field_value($name);
            $name = $this->db->escape($name);
            $field_query = '';

            if ($f_current) {
                $field = $this->get_field($name, $field);

                switch ($field['field']) {
                    case 'range':
                        for ($i = 0; $i < 2; $i++) {
                            if ($f_current[$i] && !ctype_digit((string)$f_current[$i])) {
                                $f_current[$i] = sprintf("%0.5F", $f_current[$i]);
                            }
                            else {
                                $f_current[$i] = (int)$f_current[$i];
                            }
                        }

                        $min = $f_current[0] ? "a.`{$name}` >= $f_current[0]" : '';
                        $max = $f_current[1] ? "a.`{$name}` <= $f_current[1]" : '';

                        if ($min || $max) {
                            $field_query .= " ($min" . ($min && $max ? ' AND ' : '') . "$max) ";
                        }
                        else {
                            continue 2;
                        }
                        break;

                    default:
                        if (!is_array($f_current)) {
                            $f_current = array($f_current);
                        }
                        foreach ($f_current as &$row) {
                            $row = "'" . $this->db->escape($row) . "'";
                        }
                        $field_query .= " a.`{$name}` IN (" . implode(', ', $f_current) . ") ";
                        break;
                }

                if ($lookup_type == self::VARIANT_VALUES_IN_SUBQUERY) { // для query_where в списке компонентов
                    $query .= " AND (
                                    $field_query
                                    OR (
                                        a.`Parent_Message_ID` = 0
                                        AND EXISTS (SELECT 1 FROM `Message{$component_id}` as `child`
                                              WHERE `child`.`Parent_Message_ID` = a.`Message_ID`
                                                AND `child`.`Checked` = 1
                                                AND (" . str_replace("a.`", "child.`", $field_query) . ")
                                              LIMIT 1)
                                    )
                                ) ";
                }
                else if ($lookup_type == self::VARIANT_VALUES_IN_JOIN) { // для получения данных в фильтре
                    $query .= " AND " . str_replace("a.`{$name}`", $this->get_variant_value_sql($name), $field_query) . " ";
                }
                else if ($lookup_type == self::NO_VARIANT_VALUES) {
                    $query .= " AND $field_query";
                }
            }
        }

        return $query;
    }

    /**
     * Возвращает запрос для получения значения поля у варианта товара,
     * @param string $field_name
     * @return string
     */
    protected function get_variant_value_sql($field_name) {
        return "IF(
                    IFNULL(
                        LENGTH(`child`.`{$field_name}`) > 0,
                        0),
                    `child`.`{$field_name}`,
                    `parent`.`{$field_name}`
                )";
    }

    //--------------------------------------------------------------------------
    /**
     * Добавляет в SQL запрос фильтрующие условия (WHERE ...)
     *
     * @param string $query_where ссылка на переменную $query_where
     * @param array $fields
     * @return null
     */
    public function query_where(&$query_where, $fields = array()) {
        if ( ! $fields) {
            $fields = $this->init_fields();
        }
        // If array as: ['fieldA', 'fieldB']
        elseif (isset($fields[0]) && is_string($fields[0])) {
            $fields     = array_flip($fields);
            $all_fields = $this->init_fields();
            foreach ($fields as $k=>$row) { $fields[$k] = $all_fields[$k]; }
        }

        $query = $this->get_query_conditions($fields, self::VARIANT_VALUES_IN_SUBQUERY);
        $query_where .= ($query_where ? ' AND ' : ' '). ' 1=1 ' . $query;

        return null;
    }

    //--------------------------------------------------------------------------

    public function init_filter_data($fields) {
        $key = $this->options('ignore_cc')
                    ? "c" . $this->get_current_component_id()
                    : "i" . $this->get_current_infoblock_id();

        if (isset($_REQUEST['nc_filter_reset'])) {
            unset($_SESSION['netshop_filter'][$key]);
            $this->filter_data = array();
            return;
        }

        if (isset($_REQUEST['nc_filter_set'])) {
            foreach ($fields as $field_name => $field_data) {
                $value = null;
                $input_name = 'filter_' . $field_name;

                if ($field_data['field'] == 'checkbox' && !isset($_REQUEST[$input_name])) {
                    $_REQUEST[$input_name] = null;
                }

                if (isset($_REQUEST[$input_name])) {
                    $value = $_REQUEST[$input_name];
                }

                // фильтр по диапазону будет сброшен, если значения равны
                // предыдущему «полному» диапазону (filter_ИМЯПОЛЯ___min,
                // filter_ИМЯПОЛЯ___max)
                $is_full_range = (
                    is_array($value) &&
                    isset($_REQUEST["{$input_name}___min"]) &&
                    isset($_REQUEST["{$input_name}___max"]) &&
                    $value[0] == $_REQUEST["{$input_name}___min"] &&
                    $value[1] == $_REQUEST["{$input_name}___max"]
                );

                $unset_field_filter_data = (
                    $value === null || // value is null
                    (is_array($value) && !strlen(implode('', $value))) || // empty array
                    (is_scalar($value) && !strlen($value)) || // empty string
                    $is_full_range // is "full range"
                );

                if ($unset_field_filter_data) {
                    unset($_SESSION['netshop_filter'][$key][$field_name]);
                }
                else {
                    $_SESSION['netshop_filter'][$key][$field_name] = $value;
                }

            }
        }

        $this->filter_data = $_SESSION['netshop_filter'][$key];
    }

    //--------------------------------------------------------------------------

    /**
     * Возвращает фильтрующее значение поля
     * @param  string $name Название поля
     * @return string       Значение поля
     */
    public function field_value($name) {
        return isset($this->filter_data[$name]) ? $this->filter_data[$name] : null;
    }

    //--------------------------------------------------------------------------

    /**
     * Генерирует html форму фильтра
     * @param  array  $fields Массив полей. ['fieldA', 'fieldB', ...] ['fieldA', 'fieldB'=>['type'=>'list'], ...]
     * @return string         html форма
     */
    public function make_form($fields = array()) {
        $fields = $this->get_fields($fields);
        if ( ! $fields) return '';

        $result = '';
        $has_fields = false;

        foreach ($fields as $name => $field) {
            $field_data = $this->get_field($name, $field);
            $type = $field_data['field'];

            $data_attr = '';
            if ($type == 'range') {
                if (isset($field_data['range'])) {
                    $range = $field_data['range'];
                }
                else {
                    $apply_filter = $this->should_apply_filter_to_range_values($name);
                    $range = $this->get_field_values_range($name, $apply_filter);
                }
                $data_attr .= ' data-min="' . htmlspecialchars($range[0]) . '"';
                $data_attr .= ' data-max="' . htmlspecialchars($range[1]) . '"';
            }

            $field_html = $this->make_field($name);

            if ($field_html) {
                $has_fields = true;
                $result .= '<div class="nc_netshop_filter_row nc_netshop_filter_row_' . $type . '"' . $data_attr . '>' .
                           '<div class="nc_netshop_filter_label"><label>' . $field['label'] . '</label></div>' .
                           '<div class="nc_netshop_filter_field">' . $field_html . '</div>' .
                           '</div>';
            }
        }

        if (!$has_fields) { return ''; }

        $result = '<div class="nc_netshop_filter_fieldset">' . $result . '</div>' .
                  '<div class="nc_netshop_filter_actions">' .
                  '<input type="hidden" name="nc_filter_set" value="1">' .
                  '<button class="nc_netshop_filter_submit" type="submit">' . NETCAT_MODULE_NETSHOP_FILTER_SHOW . '</button> ' .
                  '<button class="nc_netshop_filter_reset" type="submit" name="nc_filter_reset" value="1">' . NETCAT_MODULE_NETSHOP_FILTER_RESET . '</button>' .
                  '</div>';

        return '<form class="nc_netshop_filter" action="" method="post">' . $result . '</form>';
    }

    //--------------------------------------------------------------------------

    /**
     * Генерирует html-код фильтрующего поля
     * @param  string $name Название поля
     * @return string       html
     */
    public function make_field($name) {
        static $script_append = false;

        $field = $this->get_field($name);
        $f_current = $this->field_value($name);
        $f_name    = "filter_{$name}";
        $result    = '';

        $show_without_choice = $f_current || $this->options('show_fields_without_choice');

        switch ($field['field']) {
            // case 'list':
            case 'select':
                if (!$show_without_choice && count($field['options']) < 3) { break; }
                $result .= '<select name="' . $f_name . '">';
                foreach ($field['options'] as $id=>$name) {
                    $selected = $f_current == $id || $f_current == $name  ? ' selected="selected"' : '';
                    $result .= '<option value="' . htmlspecialchars($id) . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
                }
                $result .= '</select>';
                break;

            case 'multiple':
                if (!$show_without_choice && count($field['options']) < 3) { break; }
                $result .= '<select name="' .$f_name . '[]" multiple="multiple">';
                foreach ($field['options'] as $id=>$name) {
                    $selected = (is_array($f_current) && in_array($id, $f_current) || current($f_current) == $name)
                                    ? ' selected="selected"'
                                    : '';
                    $result .= '<option value="' . htmlspecialchars($id) . '"' . $selected . '>' . htmlspecialchars($name) . '</option>';
                }
                $result .= '</select>';
                break;

            case 'checkbox':
                if (!$show_without_choice && count($field['options']) < 3) { break; }
                // array_unshift($field['options'], array(''=>'фыв'));
                foreach ($field['options'] as $id=>$name) {
                    if ( ! $id) continue;
                    $selected = (is_array($f_current) && (in_array($id, $f_current) || current($f_current) == $name)) || $f_current==$id
                                    ? ' checked="checked"'
                                    : '';
                    $result .= '<div><label><input name="' . $f_name . '[]" type="checkbox" ' .
                               'value="' . htmlspecialchars($id) . '"' . $selected . ' /> ' .
                                htmlspecialchars($name) .
                               '</label></div>';
                }

                break;

            case 'link':
                if (!$show_without_choice && count($field['options']) < 3) { break; }

                if ( ! $script_append) {
                    $script_append = true;
                    $result .= "<script>function nc_netshop_filter_link(ob){
                        jQuery(ob.parentNode).find('input').attr('checked','checked').parents('form').submit();
                        return false;
                    }</script>";
                }

                foreach ($field['options'] as $id=>$name) {
                    if ( ! $id) continue;
                    $active = (is_array($f_current) && in_array($id, $f_current) || current($f_current) == $name)
                                    ? ' class="active"'
                                    : '';
                    $checked = $active ? ' checked="checked"' : '';
                    $result .= '<div>' .
                               '<input name="' . $f_name . '[]" type="checkbox" value="' . htmlspecialchars($id) . '"' . $checked . ' />' .
                               '<a href="#" onclick="return nc_netshop_filter_link(this)">' . htmlspecialchars($name) . '</a>' .
                               '</div>';
                }

                break;

            case 'range':

                $min = $f_current[0]
                            ? (float)$f_current[0]
                            : (isset($field['range'][0])
                                    ? (float)$field['range'][0]
                                    : null);

                $max = $f_current[1]
                            ? (float)$f_current[1]
                            : (isset($field['range'][1])
                                    ? (float)$field['range'][1]
                                    : null);

                // Диапазон после применения фильтра для указанного поля
                $apply_filter = $this->should_apply_filter_to_range_values($name);
                $result_range = $this->get_field_values_range($name, $apply_filter);

                if ($min <= $result_range[0] && $max >= $result_range[1] || (!$min && !$max && isset($result_range[0]) && isset($result_range[1]))) {
                    $min = $result_range[0];
                    $max = $result_range[1];
                }

                if (!$show_without_choice && ((strlen($min) && $min == $max) || (!$min && !$max))) {
                    break; // пропускаем поле
                }

                $min = str_replace(",", ".", $min);
                $max = str_replace(",", ".", $max);

                $result .= ' <label class="nc_netshop_filter_field_min"><span>' . NETCAT_MODULE_NETSHOP_FILTER_FROM . '</span>' .
                           ' <input type="text" name="' . $f_name . '[]" value="' . $min . '"></label>' .
                           ' <label class="nc_netshop_filter_field_max"><span>' . NETCAT_MODULE_NETSHOP_FILTER_TO . '</span>' .
                           ' <input type="text" name="' .$f_name . '[]" value="' . $max . '"></label>' .
                           '<input type="hidden" name="' . $f_name . '___min" value="' . $result_range[0] . '">' .
                           '<input type="hidden" name="' . $f_name . '___max" value="' . $result_range[1] . '">' .
                           ' ';
                break;
        }

        return "{$result}";
    }

    //--------------------------------------------------------------------------

    public function get_all_fields() {
        static $all_fields = null;

        // Все поля текущего компонента магазина
        if ( is_null($all_fields) ) {
            $component_id = $this->get_current_component_id();
            $component   = nc_core::get_object()->get_component($component_id);
            $_all_fields = $component->get_fields(0, 1);
            $all_fields  = array();
            foreach ($_all_fields as $f) {
                $all_fields[$f['name']] = $f;
            }
        }

        return $all_fields;
    }

    //--------------------------------------------------------------------------

    /**
     * Возвращает поле с установленными параметрами
     * @param string $name Название поля
     * @param array $options
     * @return array
     */
    public function get_field($name, $options = array()) {
        // Уже инициализировано
        if ( isset($this->fields[$name])) {
            return $this->fields[$name];
        }

        $init_fields = $this->init_fields();
        $all_fields  = $this->get_all_fields();

        // Поле не существует
        if ( ! isset($all_fields[$name]) ) {
            return false;
        }

        $options = array_merge($init_fields[$name], $options);

        $field = $all_fields[$name];

        $result = array(
            'id'    => $field['id'],
            'name'  => $name,
            'type'  => isset($options['type']) ? $options['type'] : $this->get_default_type_by_id($field['type']),
            'label' => isset($options['label']) ? $options['label'] : $field['description'],
        );
        $result['field'] = isset($options['field']) ? $options['field'] : $this->get_default_field_by_type($result['type']);

        // Значения для списков (options list)
        if ($result['field'] == 'checkbox' || $result['field'] == 'select' || $result['field'] == 'multiple') {

            $options = isset($options['options']) ? $options['options'] : '';
            if ( ! $options) {
                // Если поле является списком
                if ($field['format'] && $field['type'] == NC_FIELDTYPE_SELECT || $field['type'] == NC_FIELDTYPE_MULTISELECT) {
                    $format  = explode(':', $field['format']);
                    $options = $this->get_classificator_items($format[0]);
                }
                else {
                    $options = $this->get_field_possible_values($name);
                }
            }

            $result['options'] = $options;
        }

        // Min/Max для поля "диапазон" (range)
        elseif ($result['type'] == 'range') {
            $result['range'] = isset($options['range'])
                ? $options['range']
                : null;
//                : array(0,0);
        }

        $this->fields[$name] = $result;

        return $result;
    }

    //--------------------------------------------------------------------------

    /**
     * Возвращает массив полей с установленными параметрами
     * @param  array  $fields Массив полей. ['fieldA', 'fieldB', ...] ['fieldA', 'fieldB'=>['type'=>'list'], ...]
     * @return array
     */
    public function get_fields($fields = array()) {
        if (!$fields) {
            $fields = $this->init_fields();
        }

        $result = array();

        foreach ($fields as $name => $filter_options) {
            $result[$name] = $this->get_field($name, $filter_options);
        }

        return $result;
    }

    /***************************************************************************
        PROTECTED
    ***************************************************************************/

    protected function get_current_component_id() {
        static $current_component_id;

        if (is_null($current_component_id)) {
            $current_component_id = nc_Core::get_object()->sub_class->get_current('Class_ID');
        }

        return $current_component_id;
    }

    //--------------------------------------------------------------------------

    protected function get_current_infoblock_id() {
        static $current_infoblock_id;

        if (is_null($current_infoblock_id)) {
            if ($this->options('ignore_cc')) {
                $current_infoblock_id = false;
            }
            else {
                $current_infoblock_id = nc_Core::get_object()->sub_class->get_current('Sub_Class_ID');
            }
        }

        return $current_infoblock_id;
    }

    //--------------------------------------------------------------------------

    protected function get_field_values_range($field, $filtered_only = false) {
        static $range = array();

        $key = (int)$filtered_only;

        if (!isset($range[$field][$key])) {
            $component_id = (int)$this->get_current_component_id();
            $infoblock_id = (int)$this->get_current_infoblock_id();

            $table = "Message" . $component_id;
            $field = $this->db->escape($field);
            $ignore_cc = $this->options('ignore_cc');

            // Условия для запроса для «основных» товаров
            $main_item_where = ($ignore_cc ? '' : " AND `a`.`Sub_Class_ID` = $infoblock_id ");

            // Условия для запроса для вариантов товаров
            $variant_item_where = ($ignore_cc ? '' : " AND `parent`.`Sub_Class_ID` = $infoblock_id ");
            $variant_field_value = $this->get_variant_value_sql($field);

            if ($filtered_only && $this->filter_data) {
                $fields = $this->init_fields();
                unset($fields[$field]);

                $main_item_where .= $this->get_query_conditions($fields, self::NO_VARIANT_VALUES);
                $variant_item_where .= $this->get_query_conditions($fields, self::VARIANT_VALUES_IN_JOIN);
            }

            $sql = "SELECT MIN(t.`min`), MAX(t.`max`) FROM (
                        (SELECT MIN(`a`.`$field`) AS `min`, MAX(`a`.`$field`) AS `max`
                           FROM `$table` AS `a`
                          WHERE `a`.`Checked` = 1
                            AND `a`.`Parent_Message_ID` = 0
                                $main_item_where)
                        UNION
                        (SELECT MIN($variant_field_value) AS `min`, MAX($variant_field_value) AS `max`
                           FROM `$table` AS `parent`
                                LEFT JOIN `$table` AS `child`
                                ON (`child`.`Parent_Message_ID` > 0
                                    AND `child`.`Parent_Message_ID` = `parent`.`Message_ID`)
                          WHERE `child`.`Checked` = 1
                                $variant_item_where)
                    ) AS `t`";

            $result = $this->db->get_row($sql, ARRAY_N);
            $range[$field][$key] = $result;
        }

        return $range[$field][$key];
    }

    //--------------------------------------------------------------------------

    protected function get_field_possible_values($field) {
        static $list = array();

        if (!isset($list[$field])) {
            $component_id = (int)$this->get_current_component_id();
            $infoblock_id = (int)$this->get_current_infoblock_id();

            $table = "Message" . $component_id;
            $field = $this->db->escape($field);
            $ignore_cc = $this->options('ignore_cc');

            // Условия для запроса для «основных» товаров
            $main_item_where = ($ignore_cc ? '' : " AND `a`.`Sub_Class_ID` = $infoblock_id ");

            // Условия для запроса для вариантов товаров
            $variant_item_where = ($ignore_cc ? '' : " AND `parent`.`Sub_Class_ID` = $infoblock_id ");
            $variant_field_value = $this->get_variant_value_sql($field);

            // Есть фильтр и мы выводим только те значения, которые можно выбрать
            // для уточнения фильтра (для поля, по которому применён фильтр,
            // выводятся все возможные значения):
            if ($this->filter_data && $this->options('filter_values') && !$this->field_value($field)) {
                $fields = $this->init_fields();
                unset($fields[$field]);

                $main_item_where .= $this->get_query_conditions($fields, self::NO_VARIANT_VALUES);
                $variant_item_where .= $this->get_query_conditions($fields, self::VARIANT_VALUES_IN_JOIN);
            }

            $sql = "(SELECT DISTINCT `a`.`$field` AS `value`
                       FROM `$table` AS `a`
                      WHERE `a`.`Checked` = 1
                        AND `a`.`Parent_Message_ID` = 0
                            $main_item_where)
                    UNION DISTINCT
                    (SELECT DISTINCT $variant_field_value AS `value`
                       FROM `$table` AS `parent`
                            LEFT JOIN `$table` AS `child`
                            ON (`child`.`Parent_Message_ID` > 0
                                AND `child`.`Parent_Message_ID` = `parent`.`Message_ID`)
                      WHERE `child`.`Checked` = 1
                            $variant_item_where)
                    ORDER BY `value`";

            $result = $this->db->get_results($sql, ARRAY_A);

            $list[$field] = array(''=>'');
            foreach ($result as $row) {
                $id = htmlspecialchars($row['value'], ENT_QUOTES);
                $list[$field][$id] = $row['value'];
            }
        }
        return $list[$field];
    }

    //--------------------------------------------------------------------------

    protected function get_classificator_items($clft_name) {
        $clft_name = $this->db->escape($clft_name);

        if ( ! $clft_name) return array();

        $options = $this->db->get_row("SELECT * FROM `Classificator` WHERE Table_Name='" . $clft_name . "'", ARRAY_A);

        if (empty($options)) return array();

        // сортировка по полю...
        switch ($options['Sort_Type']) {
            case  1: $order_by = "`" . $clft_name . "_Name`"; break;
            case  2: $order_by = "`" . $clft_name . "_Priority`"; break;
            default: $order_by = "`" . $clft_name . "_ID`";
        }
        $order_by .= ($options['Sort_Direction'] ? " DESC" : " ASC");

        # выбор данных о списке, цикл ниже
        $result = $this->db->get_results("SELECT `{$clft_name}_ID` AS id, `{$clft_name}_Name` AS name, `{$clft_name}_Priority`
            FROM `Classificator_{$clft_name}`
            WHERE `Checked` = '1'
            ORDER BY " . $order_by, ARRAY_A);

        $list = array(''=>'');
        foreach ($result as $row) {
            $list[$row['id']] = $row['name'];
        }

        return $list;
    }

    //--------------------------------------------------------------------------

    protected function get_default_field_by_type($type) {
        switch ($type) {
            case 'list':
                return $this->options('list_field');

            case 'bool':
                return $this->options('bool_field');

            default:
                return $type;
        }
    }

    //--------------------------------------------------------------------------

    protected function get_default_type_by_id($f_type_id) {

        switch ($f_type_id) {
            case NC_FIELDTYPE_TEXT:
                return 'text';

            case NC_FIELDTYPE_STRING:
            case NC_FIELDTYPE_SELECT:
            case NC_FIELDTYPE_MULTISELECT:
                return 'list';

            case NC_FIELDTYPE_INT:
            case NC_FIELDTYPE_FLOAT:
                return 'range';

            case NC_FIELDTYPE_BOOLEAN:
                return 'bool';
        }

        return null;
    }

    // -------------------------------------------------------------------------

    protected function should_apply_filter_to_range_values($field_name) {
        return $this->filter_data && $this->options('filter_values') && !isset($this->filter_data[$field_name]);
    }

    //--------------------------------------------------------------------------

    private function __clone() {}
//    private function __wakeup() {}

    //--------------------------------------------------------------------------

}