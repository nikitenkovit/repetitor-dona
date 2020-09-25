<?php

class nc_netshop_order_listener {

    public static function register() {
        $listener = new self;
        /** @var nc_event $event_manager */
        $event_manager = nc_core('event');
        $event_manager->bind($listener, array('updateMessage' => 'on_object_update'));
    }

    /**
     * Слушатель событий updateMessage
     * (addMessage обрабатывается отдельно в
     *
     * Для записей компонента «Заказ», определённого в настройках модуля,
     * при изменении статуса заказа изменяет в соответствии с настройками
     * SubtractFromStockStatusID и ReturnToStockStatusID значение
     *
     * @param $site_id
     * @param $folder_id
     * @param $infoblock_id
     * @param $component_id
     * @param $object_ids
     */
    public function on_object_update($site_id, $folder_id, $infoblock_id, $component_id, $object_ids) {
        $netshop = nc_netshop::get_instance($site_id);

        // Нужно ли на этом сайте обновлять StockUnits при смене статуса?
        $process_stock_units_change = !$netshop->get_setting('IgnoreStockUnitsValue') &&
                                      strlen($netshop->get_setting('SubtractFromStockStatusID'));

        // Это компонент «Заказ»?
        if ($process_stock_units_change && $component_id == $netshop->get_setting('OrderComponentID')) {
            foreach ((array)$object_ids as $order_id) {
                $netshop->load_order($order_id)->update_item_stock_units_on_order_change();
            }
        }
    }

}