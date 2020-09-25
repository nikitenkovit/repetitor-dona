<?php if (!class_exists('nc_core')) { die; } ?>

<?= $ui->controls->site_select($catalogue_id) ?>

<form class='nc-form' method='post'>
<input type='hidden' name='action' value='settings_save'>
<input type='hidden' name='next_action' value='module'>
<?php

    $fields = array(

        'OrderComponentID' => array(
            'caption' => NETCAT_MODULE_NETSHOP_ORDERS_COMPONENT,
            'type' => 'select',
            'subtype' => 'sql',
            'sqlquery' => "SELECT c.`Class_ID` as `id`, CONCAT(c.`Class_ID`, '. ', c.`Class_Name`) AS `name`
                             FROM `Class` AS c, `Field` as f
                            WHERE f.`Field_Name` = 'PaymentMethod'
                              AND c.`Class_ID` = f.`Class_ID`",
        ),

        'PaidOrderStatusID' => array(
            'caption' => NETCAT_MODULE_NETSHOP_PAID_ORDER_STATUS,
            'type' => 'select',
            'subtype' => 'classificator',
            'classificator' => 'ShopOrderStatus',
            'default_value' => "3",
        ),

        'PrevOrdersSumStatusID' => array(
            'caption' => NETCAT_MODULE_NETSHOP_ORDERS_SUM_STATUS,
            'type' => 'select',
            'subtype' => 'classificator',
            'classificator' => 'ShopOrderStatus',
            'multiple' => true,
            'default_value' => "3,4",
        ),

        '1cExportOrdersStatusID' => array(
            'caption' => NETCAT_MODULE_NETSHOP_1C_EXPORT_ORDERS_STATUS,
            'type' => 'select',
            'subtype' => 'classificator',
            'classificator' => 'ShopOrderStatus',
            'multiple' => true,
            'values' => array(
                0 => NETCAT_MODULE_NETSHOP_ORDER_NEW,
            ),
            'default_value' => "0",
        ),

        '1cSecretName' => array(
            'caption' => NETCAT_MODULE_NETSHOP_1C_SECRET_NAME,
            'type' => 'string',
            'required' => false,
        ),

        '1cSecretKey' => array(
            'caption' => NETCAT_MODULE_NETSHOP_1C_SECRET_KEY,
            'type' => 'string',
            'required' => false,
        ),

        'SecretKey' => array(
            'caption' => NETCAT_MODULE_NETSHOP_SECRET_KEY,
            'type' => 'string',
            'required' => false,
        ),

        'IgnoreStockUnitsValue' => array(
            'type' => 'custom',
            'html' => '<div class="ncf_row">' .
                      '<input type="hidden" name="settings[IgnoreStockUnitsValue]" value="0">' .
                      '<label><input type="checkbox" name="settings[IgnoreStockUnitsValue]" value="1"' .
                      ($netshop->get_setting('IgnoreStockUnitsValue') ? ' checked' : '') . "> &nbsp;" .
                      NETCAT_MODULE_NETSHOP_IGNORE_STOCK_UNITS_VALUE .
                      "</label></div>\n",
            'wrap' => false,
        ),

        'SubtractFromStockStatusID' => array(
            'caption' => NETCAT_MODULE_NETSHOP_STOCK_RESERVE_STATUS,
            'type' => 'select',
            'subtype' => 'classificator',
            'classificator' => 'ShopOrderStatus',
            'multiple' => true,
            'values' => array(
                0 => NETCAT_MODULE_NETSHOP_ORDER_NEW,
            ),
            'default_value' => "0",
        ),

        'ReturnToStockStatusID' => array(
            'caption' => NETCAT_MODULE_NETSHOP_STOCK_RETURN_STATUS,
            'type' => 'select',
            'subtype' => 'classificator',
            'classificator' => 'ShopOrderStatus',
            'multiple' => true,
            'values' => array(
                0 => NETCAT_MODULE_NETSHOP_ORDER_NEW,
            ),
            'default_value' => "0",
        ),

        'ItemIndexFields' => array(
            'caption' => NETCAT_MODULE_NETSHOP_ITEM_INDEX_FIELDS,
            'type' => 'string',
        ),

        'ExportAsHttps' => array(
            'type' => 'custom',
            'html' => '<div class="ncf_row">' .
                      '<input type="hidden" name="settings[ExportAsHttps]" value="0">' .
                      '<label><input type="checkbox" name="settings[ExportAsHttps]" value="1"' .
                      ($netshop->get_setting('ExportAsHttps') ? ' checked' : '') . "> &nbsp;" .
                      NETCAT_MODULE_NETSHOP_EXPORT_AS_HTTPS .
                      "</label></div>\n",
            'wrap' => false,
        ),
    );

    $values = array();
    foreach ($fields as $name => $field_settings) {
        $values[$name] = $netshop->get_setting($name);
    }

    $form = new nc_a2f($fields, 'settings');
    $form->set_field_defaults('string', array('size' => 64))
         ->show_default_values(false)
         ->show_header(false)
         ->set_values($values);

    echo $form->render();

?>

</form>