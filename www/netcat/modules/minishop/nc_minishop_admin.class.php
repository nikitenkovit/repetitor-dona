<?php
/* $Id: nc_minishop_admin.class.php 5240 2011-08-30 10:04:15Z denis $ */

class nc_minishop_admin {

    protected $db, $UI_CONFIG;
    protected $MODULE_FOLDER, $MODULE_PATH, $ADMIN_TEMPLATE;
    protected $settings;

    public function __construct() {
        // system superior object
        $nc_core = nc_Core::get_object();

        // global variables
        global $UI_CONFIG;

        // global variables to internal
        $this->db = & $nc_core->db;
        $this->UI_CONFIG = $UI_CONFIG;
        $this->ADMIN_TEMPLATE = $nc_core->ADMIN_TEMPLATE;
        $this->MODULE_FOLDER = $nc_core->MODULE_FOLDER;
        $this->MODULE_PATH = str_replace($nc_core->DOCUMENT_ROOT, "", $nc_core->MODULE_FOLDER) . "minishop/";

        $this->settings = $this->get_all_settings();
        //$this->create_module_settings();
    }

    public function get_mainsettings_url() {
        return "#module.minishop.settings";
    }

    /**
     * Показ информации о всех заказах
     * @global <type> $nc_minishop
     */
    public function info_show() {
        $db = nc_Core::get_object()->db;
        global $nc_minishop;
        $html = '';

        $cname = 'MinishopStatus';
        $class_id = $nc_minishop->order_class_id();
        $url = $nc_minishop->order_url();

        // возможные статусы заказов
        $res = $db->get_results("SELECT `" . $cname . "_ID` AS `id`, 	`" . $cname . "_Name` AS `name` FROM `Classificator_" . $cname . "` WHERE `Checked` = 1 ORDER BY `" . $cname . "_Priority`", ARRAY_A);
        foreach ($res as $v) {
            $order_status[$v['id']] = $v['name'];
        }

        // выборка за текущий день
        $res = $db->get_results("SELECT COUNT(`Message_ID`) as `cnt`, `Status` FROM `Message" . $class_id . "` WHERE UNIX_TIMESTAMP(`Created`) > UNIX_TIMESTAMP(CURDATE()) GROUP BY `Status`", ARRAY_A);
        $stat['today'] = array();
        if ($res)
            foreach ($res as $v) {
                $stat['today'][$v['Status']] = $v['cnt'];
            }

        // выборка за предыдущий день
        $stat['yesterday'] = array();
        $res = $db->get_results("SELECT COUNT(`Message_ID`) as `cnt`, `Status` FROM `Message" . $class_id . "` WHERE UNIX_TIMESTAMP(`Created`) BETWEEN UNIX_TIMESTAMP(CURDATE()-86400) and UNIX_TIMESTAMP(CURDATE()) GROUP BY `Status`", ARRAY_A);
        if ($res)
            foreach ($res as $v) {
                $stat['yesterday'][$v['Status']] = $v['cnt'];
            }

        // выборка за текущий месяц
        $stat['month'] = array();
        $res = $db->get_results("SELECT COUNT(`Message_ID`) as `cnt`, `Status` FROM `Message" . $class_id . "` WHERE MONTH(`Created`) = MONTH(NOW()) AND YEAR(`Created`) = YEAR(NOW()) GROUP BY `Status`", ARRAY_A);
        if ($res)
            foreach ($res as $v) {
                $stat['month'][$v['Status']] = $v['cnt'];
            }

        // выборка за предыдущий
        $stat['lastmonth'] = array();
        if (date("m") == 1) {
            $month_where = "12";
            $year_where = "YEAR(NOW()) - 1";
        } else {
            $month_where = "MONTH(NOW())-1";
            $year_where = "YEAR(NOW())";
        }
        $res = $db->get_results("SELECT COUNT(`Message_ID`) as `cnt`, `Status` FROM `Message" . $class_id . "` WHERE MONTH(`Created`) = " . $month_where . " AND YEAR(`Created`) = " . $year_where . " GROUP BY `Status`", ARRAY_A);
        if ($res)
            foreach ($res as $v) {
                $stat['lastmonth'][$v['Status']] = $v['cnt'];
            }

        // все
        $stat['total'] = array();
        $res = $db->get_results("SELECT COUNT(`Message_ID`) as `cnt`, `Status` FROM `Message" . $class_id . "` GROUP BY `Status`", ARRAY_A);
        if ($res)
            foreach ($res as $v) {
                $stat['total'][$v['Status']] = $v['cnt'];
            }

        $html .= '<fieldset><legend>' . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_INFO_TAB_TABLENAME . '</legend>';
        $html .= '<table cellspacing="1" cellpadding="5" class="admin_table stat">';
        $html .= '<col style="width: 25%;"><col style="width: 15%;"><col style="width: 15%;"><col style="width: 15%;"><col style="width: 15%;"><col style="width: 15%;">';
        $html .= '<tbody>';
        // шапка
        $html .= '<tr><th></th>';
        foreach ($order_status as $v) {
            $html .= '<th>' . $v . '</th>';
        }
        $html .= '<th>' . NETCAT_MODELE_MINISHOP_ADMIN_TEMPLATE_INFO_TAB_TOTALCOL . '</th></tr>';

        // каждая строка
        foreach ($stat as $type => $v) {
            $html .= '<tr><td class="type">' . constant("NETCAT_MODELE_MINISHOP_ADMIN_TEMPLATE_INFO_TAB_" . strtoupper($type) . "ROW") . '</td>';
            $s = 0;
            foreach ($order_status as $status_id => $status) {
                $s += $stat[$type][$status_id];
                if ($type <> 'total' || !$stat[$type][$status_id]) {
                    $html .= '<td class="info">' . ($stat[$type][$status_id] + 0) . '</td>';
                } else {
                    $html .= '<td class="info"><a target="_blank" href="' . $url . '?srchPat[0]=' . $status_id . '"><b>' . ($stat[$type][$status_id] + 0) . '</b></a></td>';
                }
            }
            // колонка "всего"
            if ($type <> 'total' || !$s) {
                $html .= '<td class="info"><b>' . ($s + 0) . '</b></td></tr>';
            } else {
                $html .= '<td class="info"><a target="_blank" href="' . $url . '"><b>' . $s . '</b></td></tr>';
            }
        }

        $html .= '</tbody></table></fieldset>';

        echo $html;
    }

    public function info_save() {
        return;
    }

    /**
     * Вкладка "Общие настройки"
     */
    public function settings_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();
        $nc_core = nc_Core::get_object();
        $payment_clft = $nc_core->db->get_var("SELECT `Classificator_ID` FROM `Classificator` WHERE `Table_Name` = 'MinishopPayment'");
        $delivery_clft = $nc_core->db->get_var("SELECT `Classificator_ID` FROM `Classificator` WHERE `Table_Name` = 'MinishopDelivery'");
        $html = '';

        $html .= "<form method='post' action='admin.php' id='MainSettigsForm' style='padding:0; margin:0;'>\n" .
            "<input type='hidden' name='view' value='settings' />\n" .
            "<input type='hidden' name='act' value='save' />\n" .
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_TITLE . "\n" .
            "</legend>\n" .
            "<div style='margin:10px 0; _padding:0;'>
    " . nc_admin_input(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_SHOPNAME, 'shopname', $this->settings, 40) . "
      </div>
      <div style='margin:10px 0; _padding:0;'>\n
        " . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_SHOP_CURRENCY . "<br/>
        <input id='currency0' type='radio' name='currency' value='0' " . (!$this->settings['currency'] ? " checked='checked' " : "") . " />
        <label for='currency0'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_CURRENCY_RUB . "</label><br/>
        <input id='currency1' type='radio' name='currency' value='1' " . ($this->settings['currency'] ? " checked='checked' " : "") . " />
        <label for='currency1'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_CURRENCY_ALT . "</label>,
        " . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_CURRENCY_ALT_NAME . ": <input type='text' name='currency_name' value='" . $this->settings['currency_name'] . "' />
        </div>\n" .
            "</fieldset>\n" .
            "<div style='margin:10px 0; _padding:0;'>\n
    <fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_AUTHORIZE_ONORDER . "\n" .
            "</legend>\n" .
            "<div style='margin:10px 0; _padding:0;'>\n
    <input id='auth0' type='radio' name='auth' value='0' " . (!$this->settings['auth'] ? " checked='checked' " : "") . " />
        <label for='auth0'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_AUTHORIZE_NO . "</label><br/>
        <input id='auth1' type='radio' name='auth' value='1' " . ($this->settings['auth'] == 1 ? " checked='checked' " : "") . " />
        <label for='auth1'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_AUTHORIZE_OFFER . "</label><br/>
        <input id='auth2' type='radio' name='auth' value='2' " . ($this->settings['auth'] == 2 ? " checked='checked' " : "") . " />
        <label for='auth2'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_AUTHORIZE_YES . "</label><br/>
        </div>\n" .
            "</fieldset>\n" .
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_DELIVERY_PAYMENT . "\n" .
            "</legend>\n" .
            nc_admin_checkbox(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_DELIVERY . ' (<a target="_blank" href="' . $nc_core->ADMIN_PATH . '#classificator.edit(' . $delivery_clft . ')">' . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_DELIVERY_SETTINGS . '</a>)', 'delivery_allow', $this->settings['delivery_allow']) .
            nc_admin_checkbox(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_PAYMENT . ' (<a target="_blank" href="' . $nc_core->ADMIN_PATH . '#classificator.edit(' . $payment_clft . ')">' . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_DELIVERY_SETTINGS . '</a>)', 'payment_allow', $this->settings['payment_allow']) .
            "</fieldset>\n" .
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_NOTIFY_TITLE . "\n" .
            "</legend>\n" .
            nc_admin_checkbox(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_NOTIFYCLIENT, 'notify_mail', $this->settings) .
            nc_admin_input(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_SHOPEMAIL . " (" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_DEFAULT_EMAIL . " " . $nc_core->get_settings('SpamFromEmail') . ")", 'shop_email', $this->settings) .
            nc_admin_input(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_ADMINEMAIL . " (" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_SETTINGS_TAB_DEFAULT_EMAIL . " " . $nc_core->get_settings('SpamFromEmail') . ")", 'admin_email', $this->settings) .
            "</fieldset>\n" .
            "</form>\n";

        // admin buttons
        $UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_SAVE,
            "action" => "mainView.submitIframeForm('MainSettingsForm')"
        );

        echo $html;
    }

    public function settings_save() {
        $nc_core = nc_Core::get_object();
        $params = array('shopname', 'currency', 'currency_name', 'auth',
            'delivery_allow', 'payment_allow', 'notify_mail',
            'shop_email', 'admin_email');
        foreach ($params as $v) {
            $nc_core->set_settings($v, $nc_core->input->fetch_get_post($v), 'minishop');
        }
        // доступ на добавление заказа
        $aid = ($nc_core->input->fetch_get_post('auth') == nc_minishop::AUTH_REQUIRE) ? 2 : 1;
        $class_id = intval($this->settings['order_class_id']);
        $nc_core->db->query("UPDATE `Sub_Class` SET `Write_Access_ID` = '" . $aid . "' WHERE `Class_ID` = '" . $class_id . "' OR `Class_Template_ID` = '" . $class_id . "'  ");

        $this->settings = $this->get_all_settings();
    }

    public function discount_show() {
        global $UI_CONFIG, $nc_core;
        $UI_CONFIG->add_settings_toolbar();

        $d = array();
        $d_str = $this->settings['discounts'];
        if ($d_str)
            $d = unserialize($d_str);

        echo "
      <div id='value_null' class='status_error'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_DISCOUNT_ERROR_VALUE . "</div>
      <div id='error_from_to' class='status_error'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_DISCOUNT_ERROR_LIMIT . "</div>
      <div id='error_range' class='status_error'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_DISCOUNT_ERROR_LOWER . "</div>
      <div id='error_overlap' class='status_error'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_DISCOUNT_ERROR_RANGE . "</div>
      <form method='post' action='admin.php' id='adminForm' class='nc-form'>
      <input type='hidden' name='view' value='discount' />
      <input type='hidden' name='act' value='save' />
    <div style='padding-bottom: 5px; padding-top: 5px;'>
        " . nc_admin_checkbox(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_DISCOUNT_ONORDERSUM, 'discount_enabled', $this->settings) . "
    </div>
    <div id='all'>
        <div id='discounts'></div>
    <div>
        <span id='add' style='color: #1A87C2;'>
         " . NETCAT_MODULE_SEARCH_ADMIN_ADD . "
        </span>
     </div>
     </div>
     <style> div.icon_delete { position: relative;top: -3px; } </style>";
        ?>
        <script type="text/javascript">
            function check_form() { // elem.css('border', '2pt solid red');
                jQuery('.status_error').hide();
                var params = {adminact: 'check_discount'};
                jQuery('#adminForm').find(".val").each(function () {
                    var elem = jQuery(this);
                    elem.removeClass('error_input');
                    params[elem.attr("name")] = elem.val();
                });

                jQuery.ajax({ url: '<?php echo $nc_core->SUB_FOLDER . $nc_core->HTTP_ROOT_PATH; ?>modules/minishop/index.php',
                    async: false,
                    data: params,
                    dataType: 'json',
                    type: 'POST',
                    success: function (data) {
                        if (data.status == 'ok') {
                            document.getElementById('adminForm').submit();
                            return true;
                        }
                        var i;
                        for (i = 0; i < data.res.length; i++) {
                            if (data.res[i].type == 1) {
                                jQuery(document.getElementById("value[" + data.res[i].id + "]")).addClass('error_input');
                                jQuery("#value_null").show();
                            }
                            if (data.res[i].type == 2) {
                                jQuery(document.getElementById("from[" + data.res[i].id + "]")).addClass('error_input');
                                jQuery(document.getElementById("to[" + data.res[i].id + "]")).addClass('error_input');
                                jQuery("#error_from_to").show();
                            }
                            if (data.res[i].type == 3) {
                                jQuery(document.getElementById("from[" + data.res[i].id + "]")).addClass('error_input');
                                jQuery(document.getElementById("to[" + data.res[i].id + "]")).addClass('error_input');
                                jQuery("#error_range").show();
                            }
                            if (data.res[i].type == 4) {
                                jQuery("#error_overlap").show();
                            }
                        }
                    }
                });
            }

            $nc("#discount_enabled").on('change', function () {
                var $this = $nc(this);
                var $form = $nc('#all');

                if ($this.is(':checked')) {
                    $form.show();
                } else {
                    $form.hide();
                }

                return true;
            });

            (function (discounts) {
                var tpl = '<div class="discount index%x">' +
                    '<?php echo NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_ORDER_SUM . " " . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_ORDER_FROM; ?>' +
                    '<input type="text" id="from[%x]" name="from[%x]" class="from val" size="5" value="0"/>' +
                    '<?php echo NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_ORDER_TO; ?>' +
                    '<input type="text" id="to[%x]" name="to[%x]" class="to val" size="5" value="0"/>' +
                    ' &#8212;&nbsp; <?php echo NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISCOUNT_TAB_DISCOUNTSUM; ?> ' +
                    '<input type="text" id="value[%x]" name="value[%x]" class="value val" size="3" value="0"/>' +
                    ' % ' +
                    "<?= nc_admin_img("delete", NETCAT_MODULE_SEARCH_ADMIN_DELETE) ?>" +
                    '</div>';
                var last = 0;


                function add(index, disc) {
                    var div = jQuery(tpl.replace(/%x/g, index));
                    div.find("input.from").val(disc.from);
                    div.find("input.to").val(disc.to);
                    div.find("input.value").val(disc.value);
                    div.find("div.icon_delete").click(function () {
                        jQuery(this).parent().remove();
                    });
                    div.appendTo('#discounts');
                }

                jQuery('#add').click(function () {
                    add(last++, { from: 0, to: 0, value: 0})
                });
                for (var i in discounts) {
                    add(last++, discounts[i]);
                }

            })(<?= nc_array_json($d) ?>);

            jQuery("#discount_enabled").change();
        </script>
        <?php
        echo "</form>";
        $UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_SAVE,
            "action" => "document.getElementById('mainViewIframe').contentWindow.check_form()"
        );
    }

    public function discount_save() {
        $nc_core = nc_Core::get_object();
        $nc_core->set_settings('discount_enabled', $nc_core->input->fetch_get_post('discount_enabled'), 'minishop');
        $d = $this->make_discount();
        if ($d['status'] == 'error') {
            return false;
        }
        $nc_core->set_settings('discounts', serialize($d['res']), 'minishop');
        $this->settings = $this->get_all_settings();
        //dump($a);
    }

    public function make_discount() {
        $nc_core = nc_Core::get_object();
        $discount = array();
        $error = array();

        $values = $nc_core->input->fetch_get_post('value');
        $froms = $nc_core->input->fetch_get_post('from');
        $toes = $nc_core->input->fetch_get_post('to');

        if (!empty($values))
            foreach ($values as $i => $value) {
                $value = intval($value);
                $from = intval($froms[$i]);
                $to = intval($toes[$i]);
                $error_flag = false;
                if (!$value) {
                    $error[] = array('type' => 1, 'id' => $i);
                    $error_flag = true;
                }
                if (!$from && !$to || $from < 0 || $to < 0) {
                    $error[] = array('type' => 2, 'id' => $i);
                    $error_flag = true;
                }
                if ($from > $to) {
                    $error[] = array('type' => 3, 'id' => $i);
                    $error_flag = true;
                }
                if (!$error_flag) {
                    foreach ($discount as $d) {
                        $from1 = $d['from'];
                        $to1 = $d['to'];
                        if ($from < $from1 && $to > $from1 || $from < $to1 && $to > $to1 ||
                            $from1 < $from && $to1 > $from || $from1 < $to && $to1 > $to
                        ) {
                            $error[] = array('type' => 4, 'id' => $i);
                            $error_flag = true;
                        }
                    }
                }

                if (!$error_flag) {
                    $discount[] = array('from' => $from, 'to' => $to, 'value' => $value);
                }
            }


        return empty($error) ? array('status' => 'ok', 'res' => $discount) : array('status' => 'error', 'res' => $error);
    }

    public function display_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();
        $result = '';
        $html = '';

        $html .= "<form method='post' action='admin.php' id='DisplaySettingsForm' style='padding:0; margin:0;'>\n" .
            "<input type='hidden' name='view' value='display' />\n" .
            "<input type='hidden' name='act' value='save' />\n" .
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CARTPUT_ACTION . "\n" .
            "</legend>\n" .
            "<div style='margin:10px 0; _padding:0;'>\n" .
            "<input id='ajax1' type='radio' name='ajax' value='1'" . ($this->settings['ajax'] ? " checked" : "") . " />
      <label for='ajax1'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CARTPUT_NORELOAD . "</label>
       <br/>
       <div style='padding-left: 15px;'>
         <input id='notify2' type='radio' name='notify' value='" . nc_minishop::NOTIFY_DIV . "' " . ($this->settings['notify'] == nc_minishop::NOTIFY_DIV ? "checked='checked'" : "") . " />
         <label for='notify2'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CARTPUT_NOTIFY_DIV . "</label><br/>
         <input id='notify1' type='radio' name='notify' value='" . nc_minishop::NOTIFY_ALERT . "' " . ($this->settings['notify'] == nc_minishop::NOTIFY_ALERT ? "checked='checked'" : "") . " />
         <label for='notify1'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CARTPUT_NOTIFY_ALERT . "</label><br/>
         <input id='notify0' type='radio' name='notify' value='" . nc_minishop::NOTIFY_NONE . "' " . ($this->settings['notify'] == nc_minishop::NOTIFY_NONE ? "checked='checked'" : "") . "  />
         <label for='notify0'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CARTPUT_NOTIFY_NONE . "</label><br/>
       </div>
      <input id='ajax0' type='radio' name='ajax' value='0'" . (!$this->settings['ajax'] ? " checked" : "") . " />
      <label for='ajax0'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CARTPUT_RELOAD . "</label>" .
            "</div>\n" .
            "</fieldset>" .
            "<fieldset>\n" .
            "<legend>\n" .
            "" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_PUT_BUTTON . "\n" .
            "</legend>\n" .
            "<div style='margin:10px 0; padding:0;'>\n";
        $c = array(nc_minishop::PUT_TEXT, nc_minishop::PUT_IMG, nc_minishop::PUT_TEXTIMG, nc_minishop::PUT_BUTTON, nc_minishop::PUT_FORM);
        foreach ($c as $v) {
            $html .= "<input id='putbutton" . $v . "' type='radio' name='put_button' value='" . $v . "' " . ($this->settings['put_button'] == $v ? "checked='checked'" : "") . " />
         <label for='putbutton" . $v . "'>" . constant("NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_PUT_AS" . $v) . "</label><br/>";
        }
        $html .= "</div>";
        $html .= "<legend style='padding-top: 0px;'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ALREADY_INCART . "</legend>
                        <div style='margin:10px 0; padding:0;'>
                        <input id='already_in_cart0' type='radio' name='already_in_cart' value='0' " . (!$this->settings['already_in_cart'] ? "checked='checked'" : "") . " />
                        <label for='already_in_cart0'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ALREADY_ASTEXT . "</label><br/>
                        <input id='already_in_cart1' type='radio' name='already_in_cart' value='1' " . ($this->settings['already_in_cart'] ? "checked='checked'" : "") . " />
                        <label for='already_in_cart1'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ALREADY_ASIMG . "</label><br/>";
        $html .= "</div>";
        $html .= nc_admin_checkbox(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ORDERFORM_INLINE, 'orderform_inline', $this->settings['orderform_inline']) .
            nc_admin_checkbox(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ORDERFORM_USECAPTCHA, 'orderform_captcha', $this->settings['orderform_captcha']);

        foreach ($this->settings['file_settings'] as $type => $settings) {
            $pref = ($type == 'old' ? '' : $type . '_');
            $html .= "
                <fieldset>
                    <legend><strong>" . constant("TITLE_" . strtoupper($type)) . "</strong></legend><br/>
                    <div style='margin:10px 0; padding:0;'>
                        " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_PUT_ALTERNATE . " (<a href='#' onclick='gen(\"put_button_alternate\",jQuery(\"input[name=\\\"put_button\\\"]:checked\").val(), \"{$pref}\");return false;'>" . NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_GENERATE_TEMPLATE . "</a>)", $pref . 'put_button_alternate', $settings['put_button_alternate'], 1) . "
                        " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_MASSPUT_ALTERNATE . " (<a href='#' onclick='gen(\"mass_put_alternate\", 0, \"{$pref}\");return false;'>" . NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_GENERATE_TEMPLATE . "</a>)", $pref . 'mass_put_alternate', $settings['mass_put_alternate'], 1) . "
                        " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_NO_PRICE, $pref . 'no_price_text', $settings['no_price_text'], 1) . "
                    </div>
                </fieldset>

                <fieldset>
                    <legend style='padding-top: 0px;'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ALREADY_INCART . "</legend>
                    <div style='margin:10px 0; padding:0;'>
                        " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ALREADY_ALTERNATE . " (<a href='#' onclick='gen(\"already_in_cart_alternate\",jQuery(\"input[name=\\\"already_in_cart\\\"]:checked\").val(), \"{$pref}\");return false;'>" . NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_GENERATE_TEMPLATE . "</a>)", $pref . 'already_in_cart_alternate', $settings['already_in_cart_alternate'], 1) . "
                    </div>
                </fieldset>

                <fieldset>
                    <legend style='padding-top: 0px;'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CART . "</legend>
                    <div style='margin:10px 0; padding:0;'>
                        " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CART_NONEMPTY . " (<a href='#' onclick='gen(\"cart_full\", 0, \"{$pref}\");return false;'>" . NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_GENERATE_TEMPLATE . "</a>)", $pref . 'cart_full', $settings['cart_full'], 1) . "
                        " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CART_EMPTY . " (<a href='#' onclick='gen(\"cart_empty\", 0, \"{$pref}\");return false;'>" . NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_GENERATE_TEMPLATE . "</a>)", $pref . 'cart_empty', $settings['cart_empty'], 1) . "
                        " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_CART_AFTERTEXT, $pref . 'cart_after', $settings['cart_after'], 1) . "
                    </div>
                </fieldset>

                <fieldset>
                    <legend style='padding-top: 0px;'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_NOTIFY . "</legend>
                    " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_NOTIFY_ALERT_TEXT, $pref . 'notify_alert', $settings['notify_alert'], 1) . "
                    " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_NOTIFY_DIV_TEXT, $pref . 'notify_div', $settings['notify_div'], 1) . "
                </fieldset>
                <fieldset>
                    <legend style='padding-top: 0px;'>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ORDERFORM . "</legend>
                    " . nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_DISPLAY_TAB_ORDERFORM_AFTERTEXT, $pref . 'orderfrom_text', $settings['orderfrom_text'], 1) . "
                </fieldset>
                <br />";
        }

        $html .= "</form>";
        $html .= nc_admin_js_resize();

        // admin buttons
        $UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_SAVE,
            "action" => "mainView.submitIframeForm('DisplaySettingsForm')"
        );

        echo $html;
    }

    public function display_save() {
        $nc_core = nc_Core::get_object();
        $params = array(
            'put_button_alternate',
            'mass_put_alternate',
            'no_price_text',
            'already_in_cart_alternate',
            'cart_full',
            'cart_empty',
            'cart_after',
            'notify_alert',
            'notify_div',
            'orderfrom_text',
        );
        $data = array();

        foreach ($params as $v) {
            $data[$v] = $nc_core->input->fetch_get_post($v);
        }

        $module_editor = new nc_module_editor();
        $module_editor->load('minishop')->save($_POST);

        $params = array_merge($params, array(
            'put_button',
            'already_in_cart',
            'notify',
            'ajax',
            'orderform_inline',
            'orderform_captcha'
        ));

        foreach ($params as $v) {
            $nc_core->set_settings($v, $nc_core->input->fetch_get_post($v), 'minishop');
        }

        $this->settings = $this->get_all_settings();
        return true;
    }

    public function mails_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();

        $result = "<form action='admin.php' method='post' enctype='multipart/form-data'>
               <input type='hidden' name='view' value='mails' />
               <input type='hidden' name='act' value='save' /> ";

        // письмо покупателю
        $result .= "<fieldset><legend>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_CLIENTMAIL . "</legend>";
        $result .= nc_admin_input(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_ADMINMAIL_SUBJECT, 'mail_subject_customer', $this->settings, 0, 'width:100%');
        $result .= nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_ADMINMAIL_TEXT, 'mail_body_customer', $this->settings, 1, 0, 'height: 15em;');
        $result .= nc_admin_checkbox(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_ADMINMAIL_HTML, 'mail_ishtml_customer', $this->settings, 1);
        $result .= "</fieldset>";
        $result .= nc_mail_attachment_form('minishop_customer');

        // письмо админку
        $result .= "<fieldset><legend>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_ADMINMAIL . "</legend>";
        $result .= nc_admin_input(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_ADMINMAIL_SUBJECT, 'mail_subject_admin', $this->settings, 0, 'width:100%');
        $result .= nc_admin_textarea(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_ADMINMAIL_TEXT, 'mail_body_admin', $this->settings, 1, 0, 'height: 15em;');
        $result .= nc_admin_checkbox(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_ADMINMAIL_HTML, 'mail_ishtml_admin', $this->settings, 1);
        $result .= "</fieldset>";
        $result .= nc_mail_attachment_form('minishop_admin');


        $result .= "</fieldset></form>";
        $result .= nc_admin_js_resize();


        $UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_SAVE,
            "action" => "mainView.submitIframeForm('SettingsForm')"
        );

        echo $result;
    }

    public function mails_save() {
        $nc_core = nc_Core::get_object();
        $params = array('mail_subject_customer', 'mail_body_customer', 'mail_ishtml_customer',
            'mail_subject_admin', 'mail_body_admin', 'mail_ishtml_admin');
        foreach ($params as $v) {
            $nc_core->set_settings($v, $nc_core->input->fetch_get_post($v), 'minishop');
        }

        nc_mail_attachment_form_save('minishop_customer');
        nc_mail_attachment_form_save('minishop_admin');
        $this->settings = $this->get_all_settings();
    }

    public function system_show() {
        global $UI_CONFIG;
        $UI_CONFIG->add_settings_toolbar();
        $settings = nc_Core::get_object()->get_settings('', 'minishop');

        echo "<form  action='admin.php' method='post' >
    <input type='hidden' name='view' value='system' />
    <input type='hidden' name='act' value='save' />
    <fieldset><legend>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_SYSTEM . "</legend>
    <table id='systemSettings'>
    <tr><td>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_SYSTEM_CART_COMPONENT . ":</td><td>" . nc_admin_select_component('', 'cart_class_id', $settings['cart_class_id']) . "</td></tr>
    <tr><td>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_SYSTEM_ORDER_COMPONENT . ":</td><td>" . nc_admin_select_component('', 'order_class_id', $settings['order_class_id']) . "</td></tr>
    <tr><td>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_SYSTEM_ORDERADD_LINK . ": </td>" . nc_admin_input_in_text('<td>%input</td>', 'addorder_url', $settings) . "</tr>
    <tr><td>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_SYSTEM_ORDER_LINK . ": </td>" . nc_admin_input_in_text('<td>%input</td>', 'order_url', $settings) . "</tr>
    <tr><td>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_SYSTEM_CART_LINK . ": </td>" . nc_admin_input_in_text('<td>%input</td>', 'cart_url', $settings) . "</tr>
    <tr><td>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_SYSTEM_ORDER_CC . ": </td>" . nc_admin_input_in_text('<td>%input</td>', 'order_cc', $settings) . "</tr>
    <tr><td>" . NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_MAILS_TAB_SYSTEM_CART_CC . ": </td>" . nc_admin_input_in_text('<td>%input</td>', 'cart_cc', $settings) . "</tr>
    </table>
    </fieldset></form>";
        $UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_MINISHOP_ADMIN_SETTINGS_SAVE,
            "action" => "mainView.submitIframeForm('SettingsForm')"
        );
    }

    public function system_save() {
        $nc_core = nc_Core::get_object();
        $params = array('cart_class_id', 'order_class_id', 'addorder_url', 'order_url', 'cart_url', 'order_cc', 'cart_cc');
        foreach ($params as $v) {
            $nc_core->set_settings($v, $nc_core->input->fetch_get_post($v), 'minishop');
        }
        $this->settings = $this->get_all_settings();
    }

    private function get_all_settings() {
        $nc_core = nc_Core::get_object();

        $all_settings_old = $nc_core->get_settings('', 'minishop');
        $all_settings = $nc_core->get_settings('', 'minishop', 1);

        $shop_editor = new nc_module_editor();
        $shop_editor->load('minishop')->fill();
        $file_settings = $shop_editor->get_all_fields();

        $all_settings['file_settings'] = $file_settings;
        $all_settings['file_settings']['old'] = $all_settings_old;

        return $all_settings;
    }

    public function converter_show() {
        global $UI_CONFIG;

        $GLOBALS['AJAX_SAVER'] = false;

        $nc_core = nc_core();
        $input = $nc_core->input;

        $post = $input->fetch_post();
        if ($post) {
            if (!$this->convert_minishop($post)) {
                return;
            }
        }

        $source_catalogue = (int)$post['Source_Catalogue'];
        $destination_catalogue = (int)$post['Destination_Catalogue'];

        $catalogues = array(
            0 => NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SELECT_SITE,
        );
        foreach ($nc_core->catalogue->get_all() as $catalogue) {
            $catalogues[$catalogue['Catalogue_ID']] = $catalogue['Catalogue_ID'] . ". " . $catalogue['Catalogue_Name'];
        }
        ?>
        <script>
            $nc(function () {
                $nc('SELECT[name=Source_Catalogue], SELECT[name=Destination_Catalogue]').on('change', function () {
                    var source = parseInt($nc('SELECT[name=Source_Catalogue]').val());
                    var destination = parseInt($nc('SELECT[name=Destination_Catalogue]').val());

                    if (source && destination) {
                        var $form = $nc('.nc-minishop-converter-form');
                        $form.html('');
                        nc.process_start('converter_form_loading');
                        $nc.get('<?= nc_module_path('minishop'); ?>/admin.php?view=converter_form', {
                            source: source,
                            destination: destination
                        }, function (data) {
                            $form.html(data);
                            nc.process_stop('converter_form_loading');
                        });
                    }

                    return true;
                });

                $nc(document).on('click', 'A.nc-converter-remove-row', function () {
                    var $this = $nc(this);
                    var $row = $this.closest('.nc-converter-row');

                    if ($row.siblings('.nc-converter-row').length > 0) {
                        $row.remove();
                    }

                    return false;
                });

                $nc(document).on('click', 'A.nc-converter-add-row', function () {
                    var $this = $nc(this);
                    var $row = $this.prevAll('.nc-converter-row').eq(0).clone();
                    $row.find('[type=checkbox]').attr('checked', false);
                    $row.find('SELECT').val(0);

                    var new_index = null;

                    $row.find('[name$="]"]').each(function () {
                        var $element = $nc(this);
                        var name = $element.attr('name');
                        if (new_index === null) {
                            var index = /\[(\d+)\]/.exec(name);
                            index = index[1];
                            index++;
                            new_index = index;
                        }

                        name = name.replace(/\[\d+\]/, '[' + new_index + ']');
                        $element.attr('name', name);

                        return true;
                    });

                    $row.insertBefore($this);
                    return false;
                });

                $nc(document).on('change', 'INPUT[name=Convert_Goods], INPUT[name=Convert_Orders]', function () {
                    var $this = $nc(this);
                    var $next_row = $this.closest('.ncf_row').next('.ncf_row');
                    if ($this.is(':checked')) {
                        $next_row.show();
                    } else {
                        $next_row.hide();
                    }

                    return true;
                });

                $nc('INPUT[name=Convert_Goods], INPUT[name=Convert_Orders]').trigger('change');
            });
        </script>
        <form action="admin.php?view=converter&act=show" method="post" id="MainConverterForm" class="nc-form">
            <div class="ncf_row">
                <div class="ncf_caption">
                    <?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SOURCE_CATALOGUE; ?>
                </div>
                <div class="ncf_value">
                    <?= nc_admin_select_simple('', 'Source_Catalogue', $catalogues, $source_catalogue); ?>
                </div>
            </div>
            <div class="ncf_row">
                <div class="ncf_caption">
                    <?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_DESTINATION_CATALOGUE; ?>
                </div>
                <div class="ncf_value">
                    <?= nc_admin_select_simple('', 'Destination_Catalogue', $catalogues, $destination_catalogue); ?>
                </div>
            </div>
            <div class="nc-minishop-converter-form">
                <?= $this->converter_form_show($post); ?>
            </div>
        </form>
        <?php
        // admin buttons
        $UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_BUTTON,
            "action" => "mainView.submitIframeForm('MainConverterForm')"
        );
    }

    public function converter_save() {

    }

    public function converter_form_show($data = null) {
        $nc_core = nc_core();
        $db = $nc_core->db;
        $input = $nc_core->input;

        if ($data) {
            $source = (int)$data['Source_Catalogue'];
            $destination = (int)$data['Destination_Catalogue'];
        } else {
            $source = (int)$input->fetch_get('source');
            $destination = (int)$input->fetch_get('destination');
        }

        if (!$source || !$destination) {
            return;
        }

        $sql = "SELECT `Subdivision_ID` AS value, CONCAT(Subdivision_ID, '. ', Subdivision_Name) AS description, " .
            "`Parent_Sub_ID` AS parent " .
            "FROM `Subdivision` " .
            "WHERE `Catalogue_ID` = {$source} " .
            "ORDER BY `Subdivision_ID`";
        $source_subdivisions = (array)$db->get_results($sql, ARRAY_A);

        $sql = "SELECT `Subdivision_ID` AS value, CONCAT(Subdivision_ID, '. ', Subdivision_Name) AS description, " .
            "`Parent_Sub_ID` AS parent " .
            "FROM `Subdivision` " .
            "WHERE `Catalogue_ID` = {$destination} " .
            "ORDER BY `Subdivision_ID`";
        $destination_subdivisions = (array)$db->get_results($sql, ARRAY_A);

        $netshop = nc_netshop::get_instance();
        $goods_class_ids = $netshop->get_goods_components_ids();

        if (count($goods_class_ids) > 0) {
            $sql = "SELECT `Class_ID` AS value, CONCAT(Class_ID, '. ', Class_Name) AS description " .
                "FROM `Class` " .
                "WHERE `Class_ID` IN (" . implode(',', $goods_class_ids) . ") " .
                "ORDER BY `Class_ID`";
            $goods_classes = (array)$db->get_results($sql, ARRAY_A);
        } else {
            $goods_classes = array();
        }

        $goods_mapping_rows = array();
        $i = 0;
        foreach ((array)$data['Goods_Source_Subdivision'] as $index => $row) {
            $array = array(
                'Goods_Source_Subdivision' => $data['Goods_Source_Subdivision'][$index],
                'Goods_Class_ID' => $data['Goods_Class_ID'][$index],
                'Goods_Destination_Subdivision' => $data['Goods_Destination_Subdivision'][$index],
                'Goods_Create_Subdivision' => isset($data['Goods_Create_Subdivision'][$index]),
            );

            if ($array['Goods_Source_Subdivision'] &&
                $array['Goods_Class_ID'] &&
                $array['Goods_Destination_Subdivision']
            ) {
                $goods_mapping_rows[$i] = $array;
                $i++;
            }
        }

        if (count($goods_mapping_rows) == 0) {
            $goods_mapping_rows[] = array(
                'Goods_Source_Subdivision' => 0,
                'Goods_Class_ID' => 0,
                'Goods_Destination_Subdivision' => 0,
                'Goods_Create_Subdivision' => false,
            );
        }

        if ($data === null) {
            ob_end_clean();
            ob_start();
        }
        ?>
        <div class="ncf_row">
            <div class="ncf_caption">
                <?= nc_admin_checkbox_simple('Convert_Settings', 'Convert_Settings', NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_SETTINGS, isset($data['Convert_Settings'])); ?>
            </div>
        </div>
        <div class="ncf_row">
            <div class="ncf_caption">
                <?= nc_admin_checkbox_simple('Convert_Goods', 'Convert_Goods', NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_GOODS, isset($data['Convert_Goods'])); ?>
            </div>
        </div>
        <div class="ncf_row nc-converter-goods" style="display: none;">
            <div class="nc-converter-goods-mapping">
                <?php foreach ($goods_mapping_rows as $i => $row) { ?>
                    <div class="nc-converter-row">
                        <a href="#" class="nc-converter-remove-row"><i class="nc-icon nc--remove"></i></a>
                        <select name="Goods_Source_Subdivision[<?= $i; ?>]" style="width: 150px;">
                            <option value="0"><?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SOURCE_SUBDIVISION; ?></option>
                            <?= nc_select_options($source_subdivisions, $row['Goods_Source_Subdivision']); ?>
                        </select>

                        <select name="Goods_Class_ID[<?= $i; ?>]" style="width: 160px;">
                            <option value="0"><?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_GOODS_CLASS; ?></option>
                            <?= nc_select_options($goods_classes, $row['Goods_Class_ID']); ?>
                        </select>
                        <select name="Goods_Destination_Subdivision[<?= $i; ?>]" style="width: 160px;">
                            <option value="0"><?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_DESTINATION_SUBDIVISION; ?></option>
                            <?= nc_select_options($destination_subdivisions, $row['Goods_Destination_Subdivision']); ?>
                        </select>

                        <label><input type="checkbox" name="Goods_Create_Subdivision[<?= $i; ?>]" <?= $row['Goods_Create_Subdivision'] ? 'checked="checked"' : ''; ?>/> <?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CREATE_NEW_SUBDIVISION; ?>
                        </label>
                    </div>
                <?php } ?>
                <a href="#" class="nc-converter-add-row"><?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_ADD_ROW; ?></a>
            </div>
        </div>
        <?php
        $minishop_settings = $nc_core->get_settings('', 'minishop');
        try {
            $orders_subclass = $nc_core->sub_class->get_by_id($minishop_settings['order_cc']);
            $orders_subdivision = $nc_core->subdivision->get_by_id($orders_subclass['Subdivision_ID']);
        } catch (Exception $e) {
            $orders_subdivision = null;
        }
        ?>
        <?php if ($orders_subdivision) { ?>
            <div class="ncf_row">
                <div class="ncf_caption">
                    <?= nc_admin_checkbox_simple('Convert_Orders', 'Convert_Orders', NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_ORDERS, isset($data['Convert_Orders'])); ?>
                </div>
            </div>
            <div class="ncf_row nc-converter-orders-mapping" style="display: none;">
                <?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_ORDERS_WILL_BE_LOADED_FROM; ?> <?= $orders_subdivision['Subdivision_ID']; ?>. <?= $orders_subdivision['Subdivision_Name']; ?>
                <br>
                <?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_ORDERS_INTO; ?>
                <select name="Orders_Destination_Subdivision" style="width: 160px;">
                    <option value="0"><?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_DESTINATION_SUBDIVISION; ?></option>
                    <?= nc_select_options($destination_subdivisions, $data['Orders_Destination_Subdivision']); ?>
                </select>

                <label><input type="checkbox" name="Orders_Create_Subdivision" <?= isset($data['Orders_Create_Subdivision']) ? 'checked="checked"' : ''; ?>/> <?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CREATE_NEW_SUBDIVISION; ?>
                </label>
            </div>
        <?php
        } else {
            nc_print_status(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_ORDER_SUBCLASS_ERROR, 'info');
        } ?>


        <div class="ncf_row">
            <div class="ncf_caption">
                <?= nc_admin_checkbox_simple('Convert_Discounts', 'Convert_Discounts', NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_DISCOUNTS, isset($data['Convert_Discounts'])); ?>
            </div>
        </div>
        <?php
        if ($data === null) {
            echo ob_get_clean();
            exit();
        }
    }

    public function converter_form_save() {

    }

    public function convert_minishop(&$data) {
        $nc_core = nc_core();
        $db = $nc_core->db;

        $validation = $this->validate_convert_form($data);

        //show mapping form
        if (isset($data['Convert_Goods']) &&
            ((isset($data['Goods_Fields_Mapping']) && !$validation) ||
                (!isset($data['Goods_Fields_Mapping']) && $validation))
        ) {
            $this->convert_mapping_form($data);
            return false;
        }

        if ($validation) {
            $minishop_settings = $nc_core->get_settings('', 'minishop');

            if (isset($data['Convert_Settings'])) {
                $nc_core->set_settings('ShopName', $minishop_settings['shopname'], 'netshop', $data['Destination_Catalogue']);

                $types = array(
                    'customer' => 'customer',
                    'admin' => 'manager',
                );

                $replace = array(
                    "%SHOP_NAME" => "{shop.ShopName}",
                    "%USER_NAME" => "{order.ContactName}",
                    "%ORDER_NUM" => "{order.Message_ID}",
                    "%SITE_URL" => "{site.Domain}",
                    "%CONTENT" => "",
                    "%DISCOUNT" => "{order.DiscountSumF}",
                    "%FINAL_COST" => "{order.TotalPriceF}",
                    "\r\n" => "<br>"
                );

                foreach ($types as $type1 => $type2) {
                    $src_subject = $minishop_settings['mail_subject_' . $type1];
                    $src_body = $minishop_settings['mail_body_' . $type1];

                    foreach ($replace as $str1 => $str2) {
                        $src_subject = str_replace($str1, $str2, $src_subject);
                        $src_body = str_replace($str1, $str2, $src_body);
                    }

                    if ($src_subject && $src_body) {
                        $sql = "DELETE FROM `Netshop_MailTemplate` WHERE `Catalogue_ID` = {$data['Destination_Catalogue']} AND `type` = '{$type2}_order'";
                        $db->query($sql);

                        $template = new nc_netshop_mailer_template(array(
                            'catalogue_id' => $data['Destination_Catalogue'],
                            'type' => $type2 . '_order',
                            'subject' => $src_subject,
                            'body' => $src_body,
                        ));
                        $template->save();
                    }
                }
            }

            //convert goods
            if (isset($data['Convert_Goods'])) {
                $goods_mapping_rows = array();
                $i = 0;
                foreach ((array)$data['Goods_Source_Subdivision'] as $index => $row) {
                    $array = array(
                        'Goods_Source_Subdivision' => $data['Goods_Source_Subdivision'][$index],
                        'Goods_Class_ID' => $data['Goods_Class_ID'][$index],
                        'Goods_Destination_Subdivision' => $data['Goods_Destination_Subdivision'][$index],
                        'Goods_Create_Subdivision' => isset($data['Goods_Create_Subdivision'][$index]),
                    );

                    if ($array['Goods_Source_Subdivision'] &&
                        $array['Goods_Class_ID'] &&
                        $array['Goods_Destination_Subdivision']
                    ) {
                        $goods_mapping_rows[$i] = $array;
                        $i++;
                    }
                }

                foreach ($goods_mapping_rows as $row) {
                    $source_subdivision_id = $row['Goods_Source_Subdivision'];
                    $destination_subdivision_id = $row['Goods_Destination_Subdivision'];

                    $fields_map = $data['Goods_Fields_Mapping'][$row['Goods_Source_Subdivision']];
                    $fiels_map_named = array();

                    $mapped = false;

                    foreach ($fields_map as $source => $destination) {
                        $sql = "SELECT `Field_Name` FROM `Field` WHERE `Field_ID` = {$source}";
                        $source = $db->get_var($sql);

                        if ($destination) {
                            $sql = "SELECT `Field_Name` FROM `Field` WHERE `Field_ID` = {$destination}";
                            $destination = $db->get_var($sql);
                            $mapped = true;
                        } else {
                            $destination = '';
                        }

                        $fiels_map_named[$source] = $destination;
                    }

                    if (!$mapped) {
                        continue;
                    }

                    if ($row['Goods_Create_Subdivision']) {
                        $source_subdivision = $nc_core->subdivision->get_by_id($row['Goods_Source_Subdivision']);

                        $sql = "SELECT MAX(`Priority`) FROM `Subdivision` WHERE `Parent_Sub_ID` = {$row['Goods_Destination_Subdivision']}";
                        $priority = (int)$db->get_var($sql);
                        $priority++;

                        $subdivision_params = array(
                            'CatalogueID' => $data['Destination_Catalogue'],
                            'ParentSubID' => $row['Goods_Destination_Subdivision'],
                            'Template_ID' => 0,
                            'Subdivision_Name' => $source_subdivision['Subdivision_Name'],
                            'EnglishName' => $source_subdivision['EnglishName'],
                            'Checked' => 1,
                            'Priority' => $priority,
                            'Favorite' => 0,
                            'UseEditDesignTemplate' => 0,
                            'DisplayType' => 'traditional',
                            'Class_ID' => $row['Goods_Class_ID'],
                            'Class_Template_ID' => 0,
                        );

                        require_once($nc_core->ADMIN_FOLDER . 'subdivision/function.inc.php');

                        $destination_subdivision_id = nc_subdivision_add($subdivision_params);
                    }

                    $sql = "SELECT `Sub_Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = {$destination_subdivision_id} AND `Class_ID` = {$row['Goods_Class_ID']} LIMIT 1";
                    $destination_sub_class_id = (int)$db->get_var($sql);

                    $sql = "SELECT `Sub_Class_ID`, `Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = {$source_subdivision_id} ORDER BY `Priority` LIMIT 1";
                    $result = $db->get_row($sql, ARRAY_A);

                    $source_sub_class_id = (int)$result['Sub_Class_ID'];
                    $source_class_id = (int)$result['Class_ID'];

                    $sql = "SELECT `Message_ID` FROM `Message{$source_class_id}` WHERE `Sub_Class_ID` = {$source_sub_class_id} AND `Parent_Message_ID` = 0 ORDER BY `Priority`";
                    $messages = (array)$db->get_col($sql);

                    foreach ($messages as $message) {
                        nc_copy_message($source_class_id, $message, $destination_sub_class_id, $fiels_map_named, $row['Goods_Class_ID']);
                    }
                }
            }

            if (isset($data['Convert_Orders'])) {
                $netshop = nc_netshop::get_instance();
                $dst_order_class_id = $netshop->get_setting('OrderComponentID');

                $src_orders_subclass = $nc_core->sub_class->get_by_id($minishop_settings['order_cc']);
                $src_orders_subdivision = $nc_core->subdivision->get_by_id($src_orders_subclass['Subdivision_ID']);
                $src_order_class_id = $minishop_settings['order_class_id'];

                $dst_orders_subdivision_id = $data['Orders_Destination_Subdivision'];

                if (isset($data['Orders_Create_Subdivision'])) {
                    $sql = "SELECT MAX(`Priority`) FROM `Subdivision` WHERE `Parent_Sub_ID` = {$dst_orders_subdivision_id}";
                    $priority = (int)$db->get_var($sql);
                    $priority++;

                    $subdivision_params = array(
                        'CatalogueID' => $data['Destination_Catalogue'],
                        'ParentSubID' => $dst_orders_subdivision_id,
                        'Template_ID' => 0,
                        'Subdivision_Name' => $src_orders_subdivision['Subdivision_Name'],
                        'EnglishName' => $src_orders_subdivision['EnglishName'],
                        'Checked' => 1,
                        'Priority' => $priority,
                        'Favorite' => 0,
                        'UseEditDesignTemplate' => 0,
                        'DisplayType' => 'traditional',
                        'Class_ID' => $dst_order_class_id,
                        'Class_Template_ID' => 0,
                    );

                    require_once($nc_core->ADMIN_FOLDER . 'subdivision/function.inc.php');

                    $dst_orders_subdivision_id = nc_subdivision_add($subdivision_params);
                }

                $sql = "SELECT `Sub_Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = {$dst_orders_subdivision_id} AND `Class_ID` = {$dst_order_class_id} LIMIT 1";
                $destination_sub_class_id = (int)$db->get_var($sql);

                $sql = "SELECT `Message_ID` FROM `Message{$src_order_class_id}` WHERE `Sub_Class_ID` = {$src_orders_subclass['Sub_Class_ID']} AND `Parent_Message_ID` = 0 ORDER BY `Priority`";
                $messages = (array)$db->get_col($sql);

                $fields_map = array(
                    'Name' => 'ContactName',
                    'Phone' => 'Phone',
                    'Address' => 'Address',
                    'Email' => 'Email',
                    'Note' => 'Comments',
                    'Cost' => 'TotalPrice',
                    'Status' => 'Status',
                    'Delivery' => '',
                    'Payment' => '',
                    'FinalCost' => '',
                    'Discount' => '',
                );

                foreach ($messages as $message) {
                    $new_message_id = nc_copy_message($src_order_class_id, $message, $destination_sub_class_id, $fields_map, $dst_order_class_id);

                    if (!$new_message_id) {
                        continue;
                    }

                    $total_items = 0;
                    $sql = "SELECT * FROM `Minishop_OrderGoods` WHERE `Order_ID` = {$message}";
                    foreach ((array)$db->get_results($sql, ARRAY_A) as $item) {
                        $total_items++;

                        $price = (float)$item['Price'];
                        $quantity = (float)$item['Quantity'];

                        $sql = "INSERT INTO `Netshop_OrderGoods` (`Order_ID`, `Qty`, `OriginalPrice`, `ItemPrice`, `Catalogue_ID`) " .
                            "VALUES ({$new_message_id}, {$quantity}, {$price}, {$price}, {$data['Destination_Catalogue']})";
                        $db->query($sql);
                    }


                    $sql = "UPDATE `Message{$dst_order_class_id}` SET `TotalGoods` = {$total_items} WHERE `Message_ID` = {$new_message_id}";
                    $db->query($sql);
                }

            }

            if (isset($data['Convert_Discounts'])) {
                $discounts = @unserialize($minishop_settings['discounts']);
                if ($discounts) {
                    foreach ($discounts as $discount) {
                        $condition = array(
                            'type' => 'and',
                            'conditions' => array(
                                array('type' => 'cart_totalprice', 'op' => 'ge', 'value' => $discount['from']),
                                array('type' => 'cart_totalprice', 'op' => 'le', 'value' => $discount['to']),
                            )
                        );

                        $new_discount = new nc_netshop_promotion_discount_cart(array(
                            'catalogue_id' => $data['Destination_Catalogue'],
                            'name' => NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_DISCOUNT_TITLE . " {$data['Source_Catalogue']} ({$discount['from']}...{$discount['to']} = {$discount['value']}%)",
                            'amount' => $discount['value'],
                            'amount_type' => nc_netshop_promotion_discount::TYPE_RELATIVE,
                            'description' => '',
                            'condition' => json_encode($condition),
                        ));

                        $new_discount->save();
                    }
                }
            }

            nc_print_status(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_SUCCESSFUL, 'ok');
            $data = null;
        }

        return true;
    }


    public function validate_convert_form($data) {
        $db = nc_core('db');

        $errors = array();

        $source_catalogue = (int)$data['Source_Catalogue'];
        $destination_catalogue = (int)$data['Destination_Catalogue'];

        if (!$source_catalogue) {
            $errors[] = NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SELECT_SOURCE_CATALOGUE;
        }

        if (!$destination_catalogue) {
            $errors[] = NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SELECT_DESTINATION_CATALOGUE;
        }

        if (isset($data['Convert_Goods'])) {
            $goods_mapping_rows = array();
            $i = 0;
            foreach ((array)$data['Goods_Source_Subdivision'] as $index => $row) {
                $array = array(
                    'Goods_Source_Subdivision' => $data['Goods_Source_Subdivision'][$index],
                    'Goods_Class_ID' => $data['Goods_Class_ID'][$index],
                    'Goods_Destination_Subdivision' => $data['Goods_Destination_Subdivision'][$index],
                    'Goods_Create_Subdivision' => isset($data['Goods_Create_Subdivision'][$index]),
                );

                if ($array['Goods_Source_Subdivision'] &&
                    $array['Goods_Class_ID'] &&
                    $array['Goods_Destination_Subdivision']
                ) {
                    $goods_mapping_rows[$i] = $array;
                    $i++;
                }
            }

            if (count($goods_mapping_rows) > 0) {
                foreach ($goods_mapping_rows as $row) {
                    if (!$row['Goods_Create_Subdivision']) {
                        $sql = "SELECT `Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = {$row['Goods_Destination_Subdivision']} ORDER BY `Priority` LIMIT 1";
                        $class_id = (int)$db->get_var($sql);
                        if ($class_id != $row['Goods_Class_ID']) {
                            $errors[] = NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_GOODS_CLASS_ERROR;
                            break;
                        }
                    }
                }
            } else {
                $errors[] = NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SET_GOODS_MAPPING;
            }
        }

        if (isset($data['Convert_Orders'])) {
            $destination = $data['Orders_Destination_Subdivision'];
            if ($destination) {
                if (!isset($data['Orders_Create_Subdivision'])) {
                    $netshop = nc_netshop::get_instance();
                    $order_class_id = $netshop->get_setting('OrderComponentID');

                    $sql = "SELECT `Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = {$destination} ORDER BY `Priority` LIMIT 1";
                    $destination_class_id = (int)$db->get_var($sql);

                    if ($destination_class_id != $order_class_id) {
                        $errors[] = NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SOURCE_SUBDIVISION_ORDERS_SUBCLASS_ERROR . ' (' . $order_class_id . ')';
                    }
                }
            } else {
                $errors[] = NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SELECT_ORDERS_SUBDIVISION;
            }
        }

        if ($errors) {
            nc_print_status(NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_ERRORS_OCCURED . ":<br>" . implode("<br>", $errors), 'error');
            return false;
        }

        return true;
    }

    public function convert_mapping_form($data) {
        global $UI_CONFIG;

        $nc_core = nc_core();
        $db = $nc_core->db;

        ?>
        <form action="admin.php?view=converter&act=show" method="post" id="MainConverterForm" class="nc-form">
            <input type="hidden" name="Source_Catalogue" value="<?= $data['Source_Catalogue']; ?>"/>
            <input type="hidden" name="Destination_Catalogue" value="<?= $data['Destination_Catalogue']; ?>"/>
            <?php if (isset($data['Convert_Settings'])) { ?>
                <input type="hidden" name="Convert_Settings" value="1"/>
            <?php } ?>
            <?php if (isset($data['Convert_Goods'])) { ?>
                <input type="hidden" name="Convert_Goods" value="1"/>
                <?php foreach ((array)$data['Goods_Source_Subdivision'] as $index => $row) { ?>
                    <input type="hidden" name="Goods_Source_Subdivision[<?= $index; ?>]" value="<?= $row; ?>"/>
                    <input type="hidden" name="Goods_Class_ID[<?= $index; ?>]" value="<?= $data['Goods_Class_ID'][$index]; ?>"/>
                    <input type="hidden" name="Goods_Destination_Subdivision[<?= $index; ?>]" value="<?= $data['Goods_Destination_Subdivision'][$index]; ?>"/>
                    <?php if (isset($data['Goods_Create_Subdivision'][$index])) { ?>
                        <input type="hidden" name="Goods_Create_Subdivision[<?= $index; ?>]" value="1"/>
                    <?php } ?>
                <? } ?>
            <?php } ?>
            <?php if (isset($data['Convert_Orders'])) { ?>
                <input type="hidden" name="Convert_Orders" value="1"/>
                <input type="hidden" name="Orders_Destination_Subdivision" value="<?= $data['Orders_Destination_Subdivision']; ?>"/>
                <?php if (isset($data['Orders_Create_Subdivision'])) { ?>
                    <input type="hidden" name="Orders_Create_Subdivision" value="1"/>
                <?php } ?>
            <?php } ?>
            <?php if (isset($data['Convert_Discounts'])) { ?>
                <input type="hidden" name="Convert_Discounts" value="1"/>
            <?php } ?>
            <?php
            foreach ((array)$data['Goods_Source_Subdivision'] as $index => $subdivision_id) {
                $source_subdivision = $nc_core->subdivision->get_by_id($subdivision_id);

                $sql = "SELECT `Class_ID` FROM `Sub_Class` WHERE `Subdivision_ID` = {$subdivision_id} ORDER BY `Priority` LIMIT 1";
                $class_id = (int)$db->get_var($sql);
                $destination_class_id = $data['Goods_Class_ID'][$index];

                $sql = "SELECT * FROM `Field` WHERE `Class_ID` = {$class_id} ORDER BY `Priority`";
                $source_fields = $db->get_results($sql, ARRAY_A);

                $sql = "SELECT * FROM `Field` WHERE `Class_ID` = {$destination_class_id} ORDER BY `Priority`";
                $destination_fields = $db->get_results($sql, ARRAY_A);
                ?>
                <div class="ncf_row">
                    <div class="ncf_caption">
                        <?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_FIELDS_MAPPING; ?>
                        <b>"<?= $source_subdivision['Subdivision_Name']; ?> [<?= $subdivision_id; ?>]"</b>
                    </div>
                </div>
                <div class="ncf_row">
                    <table cellpadding="0" border="0">
                        <?php foreach ($source_fields as $source_field) { ?>
                            <tr>
                                <td>
                                    <?= $source_field['Field_ID']; ?>. <?= $source_field['Field_Name']; ?>
                                </td>
                                <td>=>
                                    <select name="Goods_Fields_Mapping[<?= $subdivision_id; ?>][<?= $source_field['Field_ID']; ?>]">
                                        <option value="0"><?= NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_SELECT_FIELD; ?></option>
                                        <?php foreach ($destination_fields as $destination_field) { ?>
                                            <?php if ($source_field['TypeOfData_ID'] == $destination_field['TypeOfData_ID']) { ?>
                                                <option value="<?= $destination_field['Field_ID']; ?>" <?= $data['Goods_Fields_Mapping'][$subdivision_id][$source_field['Field_ID']] == $destination_field['Field_ID'] ? 'selected="selected"' : ''; ?>><?= $destination_field['Field_ID']; ?>. <?= $destination_field['Field_Name']; ?></option>
                                            <?php } ?>
                                        <?php } ?>
                                    </select>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>

            <?php } ?>
        </form>
        <?php

        // admin buttons
        $UI_CONFIG->actionButtons[] = array(
            "id" => "submit",
            "caption" => NETCAT_MODULE_MINISHOP_ADMIN_TEMPLATE_CONVERTER_TAB_CONVERT_BUTTON,
            "action" => "mainView.submitIframeForm('MainConverterForm')"
        );
    }
}