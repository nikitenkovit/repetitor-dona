<?php

class nc_netshop_order_admin_ui extends nc_netshop_admin_ui {

    /**
     * @param $catalogue_id
     * @param string $current_action
     */
    public function __construct($catalogue_id, $current_action = "index") {
        parent::__construct('order', NETCAT_MODULE_NETSHOP_ORDERS);

        $this->catalogue_id = $catalogue_id;
        $this->activeTab = $current_action;
    }

}