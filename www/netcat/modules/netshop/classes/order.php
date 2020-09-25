<?php

/**
 * nc_netshop_order
 *
 * Внимание: для загрузки заказа используйте метод nc_netshop->load_order().
 * Стандартный для nc_record вызов конструктора — new nc_netshop_order($order_id) —
 * вызовет ошибку, так как имя таблицы MessageX, в которой хранятся данные,
 * зависит от настроек интернет-магазина на конкретном сайте.
 */
class nc_netshop_order extends nc_record {

    protected $strict_property_mode = false;

    protected $table_name;  // устанавливается при вызове set_catalogue_id()
    protected $primary_key = "Message_ID";
    protected $order_component_id;

    protected $mapping = false; // имена колонок и имена опций совпадают

    /** @var  nc_netshop_item_collection */
    protected $items;

    /** @var  int */
    protected $catalogue_id;

    /** @var  array */
    protected $cart_discounts;

    /** @var  nc_netshop_delivery_estimate */
    protected $delivery_estimate;

    protected $order_items_table_name = "Netshop_OrderGoods";
    protected $order_discounts_table_name = "Netshop_OrderDiscounts";

    protected $order_data_is_loaded = false;

    /**
     * Дублирование заказа
     * @return nc_netshop_order
     */
    public function duplicate() {
        $this->load($this->get_id());

        $priority = 1 + nc_db()->get_var(
            "SELECT MAX(`Priority`)
               FROM `{$this->table_name}`
              WHERE `Sub_Class_ID` = " . (int)$this['Sub_Class_ID']
        );

        $duplicate = clone $this;
        $duplicate->set_values(array(
            $this->primary_key => null,
            'Created' => strftime('%Y-%m-%d %H:%M:%S'),
            'User_ID' => nc_core::get_object()->user->get_current('User_ID'),
            'IP' => $_SERVER['REMOTE_ADDR'],
            'Priority' => $priority,
            'LastUser_ID' => 0,
            'LastIP' => null,
            'Status' => null,
        ));

        if ($this->has_property('StockReserved')) {
            $duplicate->set_values(array(
                'StockReserved' => 0,
                'StockReturned' => 0,
            ));
        }

        $duplicate->save();
        $duplicate->items = new nc_netshop_item_collection();
        $duplicate->save_items($this->get_items());
        return $duplicate;
    }

    /**
     * Создание объекта order на основании данных из POST (где поля компонента
     * имеют префикс "f_") на этапе оформления заказа.
     * Устанавливает ID сайта из настроек магазина, список товаров (items) — из
     * содержимого текущей корзины.
     *
     * @param array $post_data
     * @param nc_netshop $netshop
     * @return nc_netshop_order
     */
    static public function from_post_data(array $post_data, nc_netshop $netshop) {
        $data = array();
        foreach ($post_data as $field => $value) {
            if (strpos($field, "f_") === 0) {
                $data[substr($field, 2)] = $value;
            }
        }

        $order = new self($data);
        $order->set_catalogue_id($netshop->get_catalogue_id());
        $order->set_items($netshop->get_cart_contents());

        return $order;
    }

    /**
     * @param $catalogue_id
     * @return $this
     */
    public function set_catalogue_id($catalogue_id) {
        $this->catalogue_id = $catalogue_id;
        $this->order_component_id = (int)$this->get_netshop()->get_setting('OrderComponentID');
        $this->table_name = "Message" . $this->get_order_component_id();
        return $this;
    }

    /**
     *
     */
    public function get_order_component_id() {
        return $this->order_component_id;
    }

    /**
     *
     */
    public function get_catalogue_id() {
        if (!$this->catalogue_id) {
            $subdivision_id = $this->get('Subdivision_ID');
            if ($subdivision_id) {
                $this->catalogue_id = nc_core::get_object()->subdivision->get_by_id($subdivision_id, "Catalogue_ID");
            }
        }
        return $this->catalogue_id;
    }

    /**
     * Метод set_id у nc_netshop_order загружает данные из БД!
     * @param int|string $value
     * @return static
     */
    public function set_id($value) {
        parent::set_id($value);
        // set_id() ошибочно использовался в шаблоне заказа вместо netshop::load_order()
        return $this->load($value);
    }

    /**
     * @param mixed $id
     * @return static
     */
    public function load($id) {
        // в действии после добавления заказа могут быть установлены дополнительные
        // свойства; чтобы не было необходимости сохранять заказ лишний раз,
        // проверяем флаг order_data_is_loaded
        if (!$this->order_data_is_loaded) {
            parent::load($id);
            if ($this->get_id()) { $this->order_data_is_loaded = true; }
        }
        return $this;
    }


    /**
     * @return nc_netshop
     */
    protected function get_netshop() {
        return nc_netshop::get_instance($this->get_catalogue_id());
    }

    /**
     * @param $price
     * @param null $currency
     * @return string
     */
    protected function format_price($price, $currency = null) {
        return $this->get_netshop()->format_price($price, $currency);
    }

    /**
     * @param nc_netshop_item_collection $items
     * @return $this
     */
    public function set_items(nc_netshop_item_collection $items) {
        $this->items = $items;
        return $this;
    }

    /**
     * Сохранение товаров в заказе в базу данных.
     * Если в заказе ранее были товары, производит обработку изменений (добавление,
     * изменение, удаление товаров в заказе), а также:
     *  — списание или возврат на склад (свойство StockUnits товаров) при необходимости;
     *  — если есть шаблон писем «изменение состава заказа», и изменён состав заказа,
     *    высылает письмо
     * @param nc_netshop_item_collection $new_items
     * @return bool
     */
    public function save_items(nc_netshop_item_collection $new_items) {
        $old_items = $this->load_items();
        $new_items->set_index_property('_ItemKey');

        $old_items_keys = $old_items->each('get', '_ItemKey');
        $new_items_keys = $new_items->each('get', '_ItemKey');

        $deleted_items_keys = array_diff($old_items_keys, $new_items_keys);
        $added_items_keys = array_diff($new_items_keys, $old_items_keys);
        $same_items_keys = array_intersect($old_items_keys, $new_items_keys);

        $has_qty_changes = ($deleted_items_keys || $added_items_keys);

        // Удаление товара из заказа
        foreach ($deleted_items_keys as $key) {
            $this->remove_item_from_database($old_items->offsetGet($key));
        }

        // Новый товар в заказе
        foreach ($added_items_keys as $key) {
            /** @var nc_netshop_item $new_item */
            $new_item = $new_items->offsetGet($key);
            if ($new_item['Qty'] > 0) {
                $this->save_item_in_database($new_item);
            }
        }

        // Изменение параметров товара (цена, количество, скидка)
        // Скидка: если указана напрямую, удалить информацию об автоматически применённых скидках
        foreach ($same_items_keys as $key) {
            /** @var nc_netshop_item $new_item */
            /** @var nc_netshop_item $old_item */
            $new_item = $new_items->offsetGet($key);
            $old_item = $old_items->offsetGet($key);

            if ($new_item['Qty'] == 0) { // удалить запись, если количество == 0
                $this->remove_item_from_database($new_item);
                $has_qty_changes = true;
            }
            else {
                if ($old_item['Qty'] != $new_item['Qty']) {
                    $has_qty_changes = true;
                }

                if ($new_item['ItemDiscount'] != $old_item['ItemDiscount']) {
                    if (!$new_item['ItemDiscount']) {
                        $new_item['Discounts'] = array();
                    }
                    else {
                        $new_item['Discounts'] = array(
                            array(
                                'type' => 'item',
                                'id' => 0,
                                'name' => NETCAT_MODULE_NETSHOP_DISCOUNT_MANUAL,
                                'description' => '',
                                'sum' => $new_item['ItemDiscount'],
                                'price_minimum' => 0
                            )
                        );
                    }
                }

                $this->save_item_in_database($new_item);
            }
        }

        $this->items = $new_items;

        if ($has_qty_changes) {
            $this->get_netshop()->mailer->send_order_messages($this, 'change_items');
        }

        return true;
    }

    /**
     * @param array $discounts
     */
    public function save_cart_discounts(array $discounts) {
        $this->save_discount_info_in_database(0, 0, $discounts);
    }

    /**
     * @param $new_discount_sum
     */
    public function update_cart_discount($new_discount_sum) {
        $this->remove_discount_info_from_database(0, 0);
        $this->save_cart_discounts(array(
            array(
                'type' => 'cart',
                'id' => 0,
                'name' => NETCAT_MODULE_NETSHOP_DISCOUNT_MANUAL,
                'description' => '',
                'sum' => $new_discount_sum,
                'price_minimum' => false,
            )
        ));
    }

    /**
     * @param nc_netshop_item $item
     */
    protected function save_item_in_database(nc_netshop_item $item, $is_new_item = false, $update_discount_info = false) {
        $db = nc_db();

        $old_item = $this->get_items()->get_item_by_id($item['Class_ID'], $item['Message_ID']);

        $query = "SELECT `Catalogue_ID` FROM `Sub_Class` AS `sc` " .
            "LEFT JOIN `Message{$item["Class_ID"]}` AS `m` ON `m`.`Sub_Class_ID` = `sc`.`Sub_Class_ID` " .
            "WHERE `Message_ID` = {$item["Message_ID"]}";

        $catalogue_id = (int)$db->get_var($query);

        $values = array(
            "Item_Type" => $item["Class_ID"],
            "Item_ID" => $item["Message_ID"],
            "Qty" => str_replace(',', '.', $item["Qty"]),
            "OriginalPrice" => str_replace(',', '.', $item["OriginalPrice"]),
            "ItemPrice" => str_replace(',', '.', $item["ItemPrice"]),
            "Catalogue_ID" => $catalogue_id,
        );

        if (!$old_item && $item->offsetExists('OrderParameters')) {
            // ($item->offsetExists() использует для проверки array_key_exists, поэтому здесь null тоже считается существующим значением)
            $values['OrderParameters'] = $item['OrderParameters'] ? serialize($item["OrderParameters"]) : '';
        }

        $set_clause = '';
        foreach ($values as $key => $value) {
            $set_clause .= ($set_clause ? ", " : "") . "`$key` = '" . $db->escape($value) . "'";
        }

        if (!$old_item) {
            $db->query("INSERT INTO `$this->order_items_table_name`
                           SET `Order_Component_ID` = " . (int)$this->get_order_component_id() . ",
                               `Order_ID` = " . (int)$this->get_id() . ",
                               $set_clause");
        }
        else {
            $db->query("UPDATE `$this->order_items_table_name`
                           SET $set_clause
                         WHERE `Order_Component_ID` = " . (int)$this->get_order_component_id() . "
                           AND `Order_ID` = " . (int)$this->get_id() . "
                           AND `Item_Type` = " . (int)$item['Class_ID'] . "
                           AND `Item_ID` = " . (int)$item['Message_ID']
                      );
        }

        if (!$old_item || $old_item['ItemDiscount'] != $item['ItemDiscount']) {
            if ($old_item) {
                $this->remove_discount_info_from_database($item['Class_ID'], $item['Message_ID']);
            }

            $this->save_discount_info_in_database($item['Class_ID'], $item['Message_ID'], $item['Discounts']);
        }

        if ($old_item && $old_item['Qty'] != $item['Qty'] && $this->should_update_stock_units()) {
            $this->update_item_stock_units($item, $old_item['Qty'] - $item['Qty']);
        }
    }

    /**
     * @param nc_netshop_item $item
     */
    protected function remove_item_from_database(nc_netshop_item $item) {
        if ($this->should_update_stock_units()) {
            $this->update_item_stock_units($item, -$item['Qty']);
        }

        nc_db()->query("DELETE FROM `$this->order_items_table_name`
                         WHERE `Order_Component_ID` = " . (int)$this->get_order_component_id() . "
                           AND `Order_ID` = " . (int)$this->get_id() . "
                           AND `Item_Type` = " . (int)$item['Class_ID'] . "
                           AND `Item_ID` = " . (int)$item['Message_ID']);

        $this->remove_discount_info_from_database($item['Class_ID'], $item['Message_ID']);

        $this->get_items()->remove_item_by_id($item['Class_ID'], $item['Message_ID']);
    }

    /**
     * @param $component_id
     * @param $object_id
     * @param array $discounts
     */
    protected function save_discount_info_in_database($component_id, $object_id, array $discounts) {
        $db = nc_db();
        foreach ($discounts as $discount_info) {
            if (!is_array($discount_info)) { continue; }
            $query = "INSERT INTO `$this->order_discounts_table_name`
                         SET `Order_Component_ID` = " . (int)$this->get_order_component_id() . ",
                             `Order_ID` = " . (int)$this->get_id() .",
                             `Item_Type` = " . (int)$component_id . ",
                             `Item_ID` = " . (int)$object_id . ",
                             `Discount_Type` = '" . $db->escape(nc_array_value($discount_info, 'type', '')) . "',
                             `Discount_ID` = " . (int)$discount_info['id'] . ",
                             `Discount_Name` = '" . $db->escape($discount_info['name']) . "',
                             `Discount_Description` = '" . $db->escape($discount_info['description']) . "',
                             `Discount_Sum` = '" . $db->escape(str_replace(',', '.', $discount_info['sum'])) . "',
                             `PriceMinimum` = " . intval($discount_info['price_minimum']) . ",
                             `IsComponentBased` = 0";

            $db->query($query);
        }
    }

    /**
     * @param $component_id
     * @param $object_id
     */
    protected function remove_discount_info_from_database($component_id, $object_id) {
        nc_db()->query("DELETE FROM `$this->order_discounts_table_name`
                         WHERE `Order_Component_ID` = " . (int)$this->get_order_component_id() . "
                           AND `Order_ID` = " . (int)$this->get_id() . "
                           AND `Item_Type` = " . (int)$component_id . "
                           AND `Item_ID` = " . (int)$object_id);
    }

    /**
     * @param nc_netshop_delivery_estimate $estimate
     */
    public function set_delivery_estimate(nc_netshop_delivery_estimate $estimate) {
        $this->delivery_estimate = $estimate;
    }

    /**
     *
     * @return nc_netshop_delivery_estimate|null
     */
    public function get_delivery_estimate() {
        if ($this->delivery_estimate == null && $this->get('DeliveryMethod')) {
            $estimate = $this->get_netshop()->delivery->get_estimate($this->get('DeliveryMethod'), $this);
            $this->delivery_estimate = $estimate;
        }
        return $this->delivery_estimate;
    }

    /**
     *
     */
    public function get_items() {
        if (!$this->items) {
            $this->items = $this->load_items();
        }
        return $this->items;
    }

    /**
     *
     */
    protected function load_items() {
        $items = new nc_netshop_item_collection();
        $items->set_index_property('_ItemKey');

        $raw_item_data = (array)nc_db()->get_results(
            "SELECT `Item_Type` AS `Class_ID`,
                    `Item_ID` AS `Message_ID`,
                    `Qty`,
                    `OriginalPrice`,
                    `ItemPrice`,
                    `OriginalPrice` - `ItemPrice` AS `ItemDiscount`,
                    `OrderParameters`
               FROM `$this->order_items_table_name`
              WHERE `Order_Component_ID` = " . (int)$this->get_order_component_id() . "
                AND `Order_ID` = " . (int)$this->get_id(),
            ARRAY_A);

        foreach ($raw_item_data as $row) {
            $row['Catalogue_ID'] = $this->catalogue_id;
            $row['OrderParameters'] = unserialize($row['OrderParameters']);
            $row['Discounts'] = $this->load_discount_info($row['Class_ID'], $row['Message_ID']);
            $items->add(new nc_netshop_item($row));
        }

        return $items;
    }

    /**
     * @param $component_id
     * @param $object_id
     * @return array
     */
    protected function load_discount_info($component_id, $object_id) {
        return (array)nc_db()->get_results(
            "SELECT `Discount_Type` AS `type`,
                    `Discount_ID` AS `id`,
                    `Discount_Name` AS `name`,
                    `Discount_Description` AS `description`,
                    `Discount_Sum` AS `sum`,
                    `PriceMinimum` AS `price_minimum`
               FROM `{$this->order_discounts_table_name}`
              WHERE `Order_Component_ID` = " . (int)$this->get_order_component_id() . "
                AND `Order_ID` = " . (int)$this->get_id() . "
                AND `Item_Type` = " . (int)$component_id . "
                AND `Item_ID` = " . (int)$object_id,
            ARRAY_A);
    }

    /**
     * @param array $discount_info
     */
    public function add_cart_discount(array $discount_info) {
        if (!is_array($this->cart_discounts)) {
            $this->cart_discounts = array();
        }
        $this->cart_discounts[] = $discount_info;
    }

    /**
     * Возвращает массив с информацией об общих скидках на заказ (на содержимое
     * заказа, на доставку)
     * @param null|string $type  Тип скидки: null — все типы; строка 'delivery', 'cart' — только скидки указанного типа
     * @return array
     */
    public function get_cart_discounts($type = null) {
        if ($this->cart_discounts === null) {
            $this->cart_discounts = $this->load_discount_info(0, 0);
        }

        if ($type) {
            $result = array();
            foreach ($this->cart_discounts as $discount) {
                if (isset($discount['type']) && $discount['type'] == $type) {
                    $result[] = $discount;
                }
            }
            return $result;
        }
        else {
            return $this->cart_discounts;
        }
    }

    /**
     *
     * @param null|string $type  Тип скидки: null — все типы; строка 'delivery', 'cart' — только скидки указанного типа
     * @return float|int
     */
    protected function calculate_discount_sum($type = null) {
        $sum = 0;
        foreach ($this->get_cart_discounts($type) as $discount) {
            $sum += $discount['sum'];
        }
        return $sum;
    }

    /**
     * Возвращает сумму общих скидок на заказ (на содержимое заказа, на доставку)
     * @return float|int
     */
    public function get_order_discount_sum() {
        return $this->calculate_discount_sum(null);
    }

    /**
     * Возвращает массив с информацией о скидках заказ (на содержимое заказа, на доставку)
     * @return array
     */
    public function get_order_discount_info() {
        return $this->get_cart_discounts(null);
    }

    /**
     * Возвращает сумму скидок на содержимое заказа (без скидок на доставку)
     */
    public function get_cart_discount_sum() {
        return $this->calculate_discount_sum('cart');
    }

    /**
     * Возвращает массив с информацией о скидках на состав заказа
     * @return array
     */
    public function get_cart_discount_info() {
        return $this->get_cart_discounts('cart');
    }

    /**
     * Возвращает сумму скидок на доставку (без скидок на содержимое заказа)
     */
    public function get_delivery_discount_sum() {
        return $this->calculate_discount_sum('delivery');
    }

    /**
     * Возвращает массив с информацией о скидках на доставку
     * @return array
     */
    public function get_delivery_discount_info() {
        return $this->get_cart_discounts('delivery');
    }

    /**
     * Сумма скидок на товары
     * @return int|float
     */
    public function get_item_discount_sum() {
        return $this->get_items()->sum('TotalDiscount');
    }

    /**
     * Сумма стоимости товаров (с учётом скидок как на товары, так и на состав заказа целиком)
     * @return int|float
     */
    public function get_item_totals_without_cart_discounts() {
        return $this->get_items()->sum('TotalPrice');
    }

    /**
     * Сумма стоимости товаров (с учётом скидок как на товары, так и на состав заказа целиком)
     * @return int|float
     */
    public function get_item_totals() {
        return $this->get_items()->sum('TotalPrice') - $this->get_cart_discount_sum();
    }

    /**
     * Сумма всех скидок (на товары + на корзину)
     * @return int|float
     */
    public function get_discount_sum() {
        return $this->get_item_discount_sum() + $this->get_order_discount_sum();
    }

    /**
     * Сумма к оплате (стоимость товаров + стоимость доставки + наценка за оплату
     * @return int|float
     */
    public function get_totals() {
        return $this->get_item_totals() + $this->get('DeliveryCost') + $this->get('PaymentCost');
    }

    // --- StockUnits-related ---
    /**
     *
     */
    public function update_item_stock_units_on_order_change() {
        $netshop = $this->get_netshop();

        // Если установлено IgnoreStockUnitsValue — выходим
        if ($netshop->get_setting('IgnoreStockUnitsValue')) { return; }

        // Если не выбраны SubtractFromStockStatusID — выходим
        $reserved_status = $netshop->get_setting('SubtractFromStockStatusID');
        if (!strlen($reserved_status)) { return; }

        $reserved_status = explode(',', $reserved_status);

        // Проверка наличия полей StockReserved, StockReturned в компоненте
        $this->check_stock_fields();

        $returned_status = explode(',', $netshop->get_setting('ReturnToStockStatusID'));
        $order_status_id = (int)$this->get('Status'); // "0" for the new order

        // Смотрим, нужно ли обновлять StockUnits...
        if (in_array($order_status_id, $reserved_status) && !$this->get('StockReserved')) {
            // SUBTRACT STOCKUNITS; SET RESERVED=1, RETURNED=0
            foreach ($this->get_items() as $item) {
                $this->update_item_stock_units($item, -$item['Qty']);
            }
            $this->set('StockReserved', 1)->set('StockReturned', 0)->save();
        }
        elseif (in_array($order_status_id, $returned_status) && $this->get('StockReserved') && !$this->get('StockReturned')) {
            // ADD STOCKUNITS; SET RESERVED=0, RETURNED=1
            foreach ($this->get_items() as $item) {
                $this->update_item_stock_units($item, +$item['Qty']);
            }
            $this->set('StockReserved', 0)->set('StockReturned', 1)->save();
        }
    }

    /**
     * @param nc_netshop_item $item
     * @param $qty
     */
    protected function update_item_stock_units(nc_netshop_item $item, $qty) {
        if (!preg_match('/^-?\d+$/', $qty)) { // float?
            $qty = sprintf('%.5F', $qty);
        }
        else {
            $qty = (int)$qty;
        }

        if ($qty < 0) { // subtract from StockUnits (reserve/ship)
            $stock_units_expression = "IF(`StockUnits`$qty < 0, 0, `StockUnits`$qty)"; // minus is already there
        }
        elseif ($qty > 0) { // add to StockUnits (restock)
            $stock_units_expression = "`StockUnits`+$qty";
        }
        else {
            return;
        }

        nc_db()->query("UPDATE `Message{$item['Class_ID']}`
                           SET `StockUnits` = $stock_units_expression
                         WHERE `Message_ID` = $item[Message_ID]
                           AND `StockUnits` IS NOT NULL
                           AND LENGTH(`StockUnits`) > 0");
    }


    /**
     * Проверяет, есть ли у компонента заказов поле StockReserved (StockReturned),
     * и создаёт такие поля, если их нет
     */
    protected function check_stock_fields() {
        if (!$this->has_property('StockReserved') && !nc_core::get_object()->get_component($this->order_component_id)->get_field('StockReserved', 'id')) {
            $this->create_stock_fields();
        }
    }

    /**
     * Создаёт поля StockReserved, StockReturned у компонента заказа
     */
    protected function create_stock_fields() {
        $db = nc_db();
        $netshop = $this->get_netshop();

        $site_id = $this->catalogue_id;
        $order_component_id = $this->order_component_id;
        $priority = $db->get_var("SELECT MAX(`Priority`) FROM `Field` WHERE `Class_ID` = $order_component_id") + 1;

        $db->query("INSERT INTO `Field`
                    SET `Class_ID` = $order_component_id,
                        `Field_Name` = 'StockReserved',
                        `Description` = '" . $db->escape(NETCAT_MODULE_NETSHOP_STOCK_RESERVE_FIELD)  ."',
                        `DefaultState` = '0',
                        `TypeOfData_ID` = 5,    -- boolean
                        `Priority` = $priority,
                        `Checked` = 1,
                        `TypeOfEdit_ID` = 3,    -- not available to anyone by default
                        `Extension` = '',
                        `NotNull` = 0,
                        `DoSearch` = 0
                   ");

        $db->query("INSERT INTO `Field`
                    SET `Class_ID` = $order_component_id,
                        `Field_Name` = 'StockReturned',
                        `Description` = '" . $db->escape(NETCAT_MODULE_NETSHOP_STOCK_RETURN_FIELD)  ."',
                        `DefaultState` = '0',
                        `TypeOfData_ID` = 5,
                        `Priority` = $priority + 1,
                        `Checked` = 1,
                        `TypeOfEdit_ID` = 3,
                        `Extension` = '',
                        `NotNull` = 0,
                        `DoSearch` = 0
                   ");

        $db->query("ALTER TABLE `Message{$order_component_id}` ADD `StockReserved` tinyint(4) NOT NULL DEFAULT '0'");
        $db->query("ALTER TABLE `Message{$order_component_id}` ADD `StockReturned` tinyint(4) NOT NULL DEFAULT '0'");

        $reserved_status = $netshop->get_setting('SubtractFromStockStatusID');
        if (strlen($reserved_status)) {
            $db->query("UPDATE `Message{$order_component_id}` AS `o`
                               JOIN `Subdivision` AS `s` USING (`Subdivision_ID`)
                           SET `o`.`StockReserved`=1
                         WHERE `o`.`Status` IN ($reserved_status)
                           AND `s`.`Catalogue_ID` = $site_id");
        }

        $returned_status = $netshop->get_setting('ReturnToStockStatusID');
        if (strlen($returned_status)) {
            $db->query("UPDATE `Message{$order_component_id}` AS `o`
                               JOIN `Subdivision` AS `s` USING (`Subdivision_ID`)
                           SET `o`.`StockReturned`=1
                         WHERE `o`.`Status` IN ($returned_status)
                           AND `s`.`Catalogue_ID` = $site_id");
        }

    }

    /**
     * Проверяет, нужно ли изменить StockUnits при изменении состава заказа
     * @return bool
     */
    protected function should_update_stock_units() {
        return !$this->get_netshop()->get_setting('IgnoreStockUnitsValue') && // StockUnits учитываются
               $this->get('StockReserved'); // и товары в заказе уже «списан»

    }


    /**
     * Доступ к «вычисляемым» свойствам заказа через ArrayInterface (для
     * упрощения работы с шаблонами писем).
     *
     * Полный список «вычисляемых» свойств:
     *    Class_ID                  равно настройке магазина OrderComponentID
     *    Date                      отформатированная дата
     *    TotalItemPriceF           стоимость товаров с учётом скидок
     *    TotalItemPrice
     *    TotalItemOriginalPriceF   стоимость товаров без скидок
     *    TotalItemOriginalPrice
     *    TotalItemDiscountSumF     сумма скидок на товар
     *    TotalItemDiscountSum
     *    TotalItemPriceWithoutCartDiscountF   сумма скидок на товары (не включая скидку на корзину)
     *    TotalItemPriceWithoutCartDiscount
     *    OrderDiscountSumF         сумма общих скидок на заказ (включая скидки на доставку)
     *    OrderDiscountSum
     *    CartDiscountSumF          сумма скидки на корзину (на стоимость товаров к корзине)
     *    CartDiscountSum
     *    DeliveryDiscountSumF      сумма скидки на доставку
     *    DeliveryDiscountSum
     *    TotalPriceF               сумма к оплате
     *    TotalPrice
     *    DiscountSumF              сумма всех скидок (на товары и на заказ)
     *    DiscountSum
     *    DeliveryMethodName        название метода доставки
     *    DeliveryDates             отформатированная дата (или диапазон дат) доставки
     *    DeliveryPriceF            стоимость доставки
     *    DeliveryPrice
     *    PaymentMethodName         название способа оплаты
     *    PaymentPriceF             наценка за способ оплаты
     *    PaymentPrice
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset) {
        switch ($offset) {
            case 'Class_ID': // нужно в nc_netshop_mailer_template
                return $this->get_netshop()->get_setting('OrderComponentID');

            case 'Date':
                return date(NETCAT_MODULE_NETSHOP_DATE_FORMAT, strtotime($this->get('Created')));

            case 'TotalItemPriceWithoutCartDiscountF':
                return $this->format_price($this->get_item_totals_without_cart_discounts());

            case 'TotalItemPriceWithoutCartDiscount':
                return $this->get_item_totals_without_cart_discounts();

            case 'TotalItemPriceF':
                return $this->format_price($this->get_item_totals());

            case 'TotalItemPrice':
                return $this->get_item_totals();

            case 'TotalItemOriginalPriceF':
                return $this->format_price($this->get_items()->get_field_sum('OriginalPrice'));

            case 'TotalItemOriginalPrice':
                return $this->get_items()->get_field_sum('OriginalPrice');

            case 'TotalItemDiscountSumF':
                return $this->format_price($this->get_item_discount_sum());

            case 'TotalItemDiscountSum':
                return $this->get_item_discount_sum();

            case 'OrderDiscountSumF':
                return $this->format_price($this->get_order_discount_sum());

            case 'OrderDiscountSum':
                return $this->get_order_discount_sum();

            case 'CartDiscountSumF':
                return $this->format_price($this->get_cart_discount_sum());

            case 'CartDiscountSum':
                return $this->get_cart_discount_sum();

            case 'DeliveryDiscountSumF':
                return $this->format_price($this->get_delivery_discount_sum());

            case 'DeliveryDiscountSum':
                return $this->get_delivery_discount_sum();

            case 'TotalPriceF':
                return $this->format_price($this->get_totals());

            case 'TotalPrice':
                return $this->get_totals();

            case 'DiscountSumF':
                return $this->format_price($this->get_discount_sum());

            case 'DiscountSum':
                return $this->get_discount_sum();

            case 'DeliveryMethodName':
                $estimate = $this->get_delivery_estimate();
                return ($estimate ? $estimate->get('delivery_method_name') : null);

            case 'DeliveryDates':
                $estimate = $this->get_delivery_estimate();
                return ($estimate ? $estimate->get_dates_string() : null);

            case 'DeliveryPriceF':
                return $this->format_price($this->get('DeliveryCost'));

            case 'DeliveryPrice':
                return $this->get('DeliveryCost');

            case 'PaymentMethodName':
                $method_id = (int)$this->get('PaymentMethod');
                if ($method_id) {
                    try {
                        $method = new nc_netshop_payment_method($method_id);
                        return $method->get('name');
                    }
                    catch (Exception $e) {}
                }
                return null;

            case 'PaymentPriceF':
                return $this->format_price($this->get('PaymentCost'));

            case 'PaymentPrice':
                return $this->get('PaymentCost');

            default:
                return parent::offsetGet($offset);
        }
   }


}