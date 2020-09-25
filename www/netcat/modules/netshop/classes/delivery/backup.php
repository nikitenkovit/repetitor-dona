<?php

/**
 *
 */
class nc_netshop_delivery_backup extends nc_netshop_backup {

    /**
     * @param string $type
     * @param int $id
     */
    public function export($type, $id) {
        if ($type != 'site') { return; }

        $this->export_classificator('ShopDeliveryService');

        $delivery_methods = nc_db_table::make('Netshop_DeliveryMethod')->where('Catalogue_ID', $id)->get_result();
        $this->dumper->export_data('Netshop_DeliveryMethod', 'DeliveryMethod_ID', $delivery_methods);
    }

    /**
     * @param string $type
     * @param int $id
     */
    public function import($type, $id) {
        if ($type != 'site') { return; }

        $mapping_params = array(
            'Condition' => array($this, 'update_condition_string'),
            'ShopDeliveryService_Mapping' => array($this, 'map_delivery_fields'),
        );

        $this->dumper->import_data('Netshop_DeliveryMethod', null, $mapping_params);
    }

    /**
     * Map values of the 'ShopDeliveryService_Mapping' field
     * @param array $row
     * @param string $field
     * @return string
     */
    public function map_delivery_fields($row, $field) {
        $value = nc_array_value($row, $field);
        if (!$value) { return $value; }

        $mapping = json_decode($value, true);
        if (!$mapping) { return $value; }

        foreach ($mapping as $k => $v) {
            list ($entity, $field) = explode("_", $v);
            if ($entity == "order") {
                $mapping[$k] = "order_" . $this->dumper->get_dict('Field_ID', $field);
            }
        }
        $value = json_encode($mapping);

        return $value;
    }

    /**
     * @param $row
     * @return array|false
     */
    public function event_before_insert_netshop_deliverymethod($row) {
        if (!$this->can_insert_row_with_condition(nc_array_value($row, 'Condition'))) {
            return false;
        }
        return $row;
    }

}