<?php


class nc_netshop_order_admin_controller extends nc_netshop_admin_controller {

    /** @var string  Должен быть задан, или должен быть переопределён метод before_action() */
    protected $ui_config_class = 'nc_netshop_order_admin_ui';

    /** @var nc_netshop_order_admin_ui */
    protected $ui_config;

    protected $order_component_id;
    protected $order_template_id;
    protected $order_subdivision_id;
    protected $order_infoblock_id;

    /**
     *
     */
    protected function before_action() {
        parent::before_action();
        $this->determine_order_infoblock_data();
    }

    /**
     *
     */
    protected function determine_order_infoblock_data() {
        $db = nc_db();
        $order_component_id = (int)$this->netshop->get_setting('OrderComponentID');

        // в качестве шаблона использовать помеченный как шаблон для режима администрирования
        $template_id = $db->get_var(
            "SELECT `Class_ID`
               FROM `Class`
              WHERE (`Class_ID` = $order_component_id OR `ClassTemplate` = $order_component_id)
              ORDER BY `Type` = 'inside_admin' DESC
              LIMIT 1");
        if (!$template_id) { $template_id = $order_component_id; }

        // нужны ID раздела, ID инфоблока с компонентом «Заказ» на выбранном сайте
        list($subdivision_id, $infoblock_id) = $db->get_row(
            "SELECT `ib`.`Subdivision_ID`, `ib`.`Sub_Class_ID`
               FROM `Sub_Class` AS `ib`,
                    `Subdivision` AS `sub`
              WHERE `ib`.`Class_ID` = $order_component_id
                AND `ib`.`Subdivision_ID` = `sub`.`Subdivision_ID`
                AND `sub`.`Catalogue_ID` = $this->site_id
              ORDER BY (`ib`.`Class_Template_ID` = $template_id OR `ib`.`Edit_Class_Template` = $template_id) DESC
              LIMIT 1",
            ARRAY_N);

        $this->order_component_id = $order_component_id;
        $this->order_template_id = $template_id;
        $this->order_subdivision_id = $subdivision_id;
        $this->order_infoblock_id = $infoblock_id;
    }

    /**
     *
     */
    protected function action_index() {
        if (!$this->order_infoblock_id) {
            return $this->view('error_message')->with('message', NETCAT_MODULE_NETSHOP_ORDER_NO_INFOBLOCK);
        }

        $nc_core = nc_core::get_object();
        // установка параметров для правильного вывода списка
        $nc_core->catalogue->set_current_by_id($this->site_id);

        $nc_core->inside_admin = 1;
        $GLOBALS['inside_admin'] = 1; // @see nc_postprocess_admin_page()
        ob_start("nc_postprocess_admin_page");

        $list_vars = array_merge(
            $nc_core->input->fetch_get_post(),
            array(
                "nc_ctpl" => $this->order_template_id,
                "isMainContent" => 1,
                "catalogue" => $this->site_id,
                "inside_netshop" => 1,
            )
        );
        $list_vars = http_build_query($list_vars, null, '&');

        // генерирование списка
        $order_list = nc_objects_list($this->order_subdivision_id, $this->order_infoblock_id, $list_vars, true);

        // восстановление UI_CONFIG
        $GLOBALS['UI_CONFIG'] = $this->ui_config = new $this->ui_config_class($this->site_id, 'index');
        $this->ui_config->locationHash = "#module.netshop.order($this->site_id)";

        return $this->view('order_list')->with('order_list', $order_list);
    }

    /**
     *
     */
    protected function action_view() {
        if (!$this->order_infoblock_id) {
            return $this->view('error_message')->with('message', NETCAT_MODULE_NETSHOP_ORDER_NO_INFOBLOCK);
        }

        $nc_core = nc_core::get_object();
        $order_id = $nc_core->input->fetch_get_post('order_id');

        if ($nc_core->input->fetch_get_post('isNaked')) {
            $this->use_layout = false;
        }

        $nc_core->subdivision->set_current_by_id($this->order_subdivision_id);
        $nc_core->sub_class->set_current_by_id($this->order_infoblock_id);

        $GLOBALS['catalogue'] = $this->site_id;
        $GLOBALS['sub'] = $this->order_subdivision_id;
        $GLOBALS['nc_ctpl'] = $this->order_template_id;
        $GLOBALS['cc'] = $this->order_infoblock_id;
        $GLOBALS['message'] = $order_id;
        $GLOBALS['isNaked'] = 1;
        $GLOBALS['inside_admin'] = 1;
        $GLOBALS['inside_netshop'] = 1;
        $GLOBALS['admin_modal'] = 0;
        extract($GLOBALS);

        ob_start();
        // div.nc_admin_mode_content нужен для обновления содержимого страницы
        // после сохранения формы в диалоге
        if ($this->use_layout) {
            echo '<div class="nc_admin_mode_content">';
        }

        require_once $nc_core->ROOT_FOLDER . "full.php";

        if ($this->use_layout) {
            echo '</div>';
        }
        $page = ob_get_clean();

        $this->ui_config->locationHash = "#module.netshop.order.view($this->site_id,$order_id)";

        return $page;
    }

    /**
     *
     */
    protected function action_duplicate() {
        $nc_core = nc_core::get_object();
        $order_id = $nc_core->input->fetch_get_post('order_id');
        $user_hash = $nc_core->input->fetch_get_post('hash');

        $order = $this->netshop->load_order($order_id);
        $real_hash = sha1("$order_id:$order[Created]:" . session_id());

        if ($user_hash != $real_hash) {
            die('Wrong input');
        }

        $duplicate_order = $order->duplicate();

        ob_get_clean();
        $params = array(
            'catalogue' => $this->site_id,
            'sub' => $duplicate_order['Subdivision_ID'],
            'cc' => $duplicate_order['Sub_Class_ID'],
            'message' => $duplicate_order->get_id(),
            'inside_admin' => 1,
            'isNaked' => 1,
            'inside_netshop' => $nc_core->input->fetch_get_post('inside_netshop'),
            'is_duplicate' => $order_id,
        );
        header("Location: /netcat/message.php?" . http_build_query($params, null, '&'));
        exit;
    }

}