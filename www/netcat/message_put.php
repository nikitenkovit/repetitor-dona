<?php

if (!class_exists("nc_System")) {
    die("Unable to load file.");
}

$updateString = "";
$fieldString = "";
$valueString = "";

$SQL_multifield = array();

// $i - счетчик полей
// $j - счетчик закаченных файлов

$multiple_changes = +$_POST['multiple_changes'];
$nc_multiple_changes = (array)$_POST['nc_multiple_changes'];
$updateStrings_tmp = array();


/**
 * @var int $fldCount
 * @var array $fld
 * @var array $fldType
 * @var array $fldNotNull
 * @var array $fldFmt
 * @var array $fldTypeOfEdit
 * @var array $fldDefault
 * @var array $fldID
 * @var array $fldFS
 * @var array $fldDisposition
 * @var nc_core $nc_core
 * @var nc_db $db
 */

do {
    if ($multiple_changes) {
        if (list($msg_id, $multiple_changes_fields) = each($nc_multiple_changes)) {
            foreach ($multiple_changes_fields as $multiple_changes_key => $multiple_changes_value) {
                $fldValue[array_search($multiple_changes_key, $fld)] = $multiple_changes_value;
            }
        } else {
            break; // выход из цикла do() если были перебраны все записи в $nc_multiple_changes
        }

        foreach (array('Priority', 'Keyword') as $system_field) {
            if (isset($nc_multiple_changes[$msg_id][$system_field])) {
                $updateStrings_tmp[] = "`$system_field` = '" . $db->escape($nc_multiple_changes[$msg_id][$system_field]) . "'";
            }
        }
    }

    $KeywordDefined = $KeywordNewValue = null;

    for ($i = 0, $j = 0; $i < $fldCount; $i++) {
        if (!(
                ($fldType[$i] == NC_FIELDTYPE_BOOLEAN && $fldNotNull[$i]) ||
                $fldType[$i] == NC_FIELDTYPE_RELATION ||
                $fldType[$i] == NC_FIELDTYPE_DATETIME ||
                ($fldType[$i] == NC_FIELDTYPE_MULTISELECT && !$multiple_changes))
            && !isset($_REQUEST["f_" . $fld[$i]])
            && !isset(${"f_" . $fld[$i]})
            && !isset($multiple_changes_fields[$fld[$i]]
            )
        ) {
            $fldValue[$i] = '""';
            continue;
        }

        // set zero value for checkbox, if not checked - not in $_REQUEST
        if ($fldType[$i] == NC_FIELDTYPE_BOOLEAN && $fldNotNull[$i] && !isset($_REQUEST["f_" . $fld[$i]]) && !isset(${"f_" . $fld[$i]})) {
            $fldValue[$i] = 0;
            ${"f_" . $fld[$i]} = 0;
        }

        // для даты персонально
        if ($fldType[$i] == NC_FIELDTYPE_DATETIME) {
            $format = nc_field_parse_format($fldFmt[$i], NC_FIELDTYPE_DATETIME);
            switch ($format['type']) {
                case "event":
                    if (!(isset($_REQUEST["f_" . $fld[$i] . "_day"]) && isset($_REQUEST["f_" . $fld[$i] . "_month"]) && isset($_REQUEST["f_" . $fld[$i] . "_year"]) && isset($_REQUEST["f_" . $fld[$i] . "_hours"]) && isset($_REQUEST["f_" . $fld[$i] . "_minutes"]) && isset($_REQUEST["f_" . $fld[$i] . "_seconds"]))) {
                        continue 2;
                    }
                    break;
                case "event_date":
                    if (!(isset($_REQUEST["f_" . $fld[$i] . "_day"]) && isset($_REQUEST["f_" . $fld[$i] . "_month"]) && isset($_REQUEST["f_" . $fld[$i] . "_year"]))) {
                        continue 2;
                    }
                    break;
                case "event_time":
                    if (!(isset($_REQUEST["f_" . $fld[$i] . "_hours"]) && isset($_REQUEST["f_" . $fld[$i] . "_minutes"]) && isset($_REQUEST["f_" . $fld[$i] . "_seconds"]))) {
                        continue 2;
                    }
                    break;
                default: // В общем случае - меняем только если прислали хотя бы одно поле
                    if (!(isset($_REQUEST["f_" . $fld[$i] . "_day"]) || isset($_REQUEST["f_" . $fld[$i] . "_month"]) || isset($_REQUEST["f_" . $fld[$i] . "_year"]) || isset($_REQUEST["f_" . $fld[$i] . "_hours"]) || isset($_REQUEST["f_" . $fld[$i] . "_minutes"]) || isset($_REQUEST["f_" . $fld[$i] . "_seconds"]))) {
                        continue 2;
                    }
                    break;
            }
        }

        if ($fldType[$i] == NC_FIELDTYPE_STRING || $fldType[$i] == NC_FIELDTYPE_TEXT || $fldType[$i] == NC_FIELDTYPE_DATETIME || $fldType[$i] == NC_FIELDTYPE_MULTISELECT) {
            if (NC_FIELDTYPE_TEXT == $fldType[$i]) {
                $format = nc_field_parse_format($fldFmt[$i], NC_FIELDTYPE_TEXT);
            }

            //транслитерация
            if (NC_FIELDTYPE_STRING == $fldType[$i]) {
                //транслитерируем только, если пользователь сам не ввел значение поля, чтобы позволить ему вводить свои собственные
                if ($format_string[$i]['use_transliteration'] == 1 && empty($_REQUEST['f_' . $format_string[$i]['transliteration_field']])) {
                    $fieldValue = nc_transliterate($fldValue[$i], ($format_string[$i]['use_url_rules'] == 1 ? true : false));
                    if ($format_string[$i]['transliteration_field'] == 'Keyword') {
                        $fieldValue = nc_check_keyword_name($message, $fieldValue, $classID);
                    }
                    $updateString .= "`" . $format_string[$i]['transliteration_field'] . "` = \"" . $fieldValue . "\", ";
                    ${$format_string[$i]['transliteration_field'] . 'Defined'} = true;
                    ${$format_string[$i]['transliteration_field'] . 'NewValue'} = "\"" . $fieldValue . "\"";
                }
            }
            $fldValue[$i] = str_replace("\\'", "'", addslashes($fldValue[$i]));
            if ($fldType[$i] == 8 && empty($fldValue[$i])) {
                $fldValue[$i] = "NULL";
            } else {
                $fldValue[$i] = "\"" . $fldValue[$i] . "\"";
            }
        }

        if ($fldValue[$i] == "" && ($fldType[$i] == NC_FIELDTYPE_INT || $fldType[$i] == NC_FIELDTYPE_FLOAT || $fldType[$i] == NC_FIELDTYPE_SELECT || $fldType[$i] == NC_FIELDTYPE_RELATION)) {

            if ($fldNotNull[$i]) {
                if ($fldTypeOfEdit[$i] == 1) {
                    $fldValue[$i] = "NULL";
                }
                if ($fldTypeOfEdit[$i] > 1 && $fldDefault[$i] != "") {
                    $fldValue[$i] = "\"\"";
                }
            } else {
                if ($fldTypeOfEdit[$i] > 1 && $fldDefault[$i] != "") {
                    $fldValue[$i] = "\"\"";
                } // int
                elseif ($fldType[$i] == NC_FIELDTYPE_INT && $fldDefault[$i] != "" && $fldDefault[$i] == strval(intval($fldDefault[$i]))) {
                    $fldValue[$i] = "\"" . $fldDefault[$i] . "\"";
                } // float
                elseif ($fldType[$i] == NC_FIELDTYPE_FLOAT && $fldDefault[$i] != "" && $fldDefault[$i] == strval(str_replace(",", ".", floatval($fldDefault[$i])))) {
                    $fldValue[$i] = "\"" . $fldDefault[$i] . "\"";
                } // list
                elseif ($fldType[$i] == NC_FIELDTYPE_SELECT && $fldValue[$i] !== false) {
                    $fldValue[$i] = 0;
                } else {
                    $fldValue[$i] = "NULL";
                }
            }
        }

        if (NC_FIELDTYPE_MULTIFILE == $fldType[$i]) {
            $settings = $_POST['settings_' . $fld[$i]];

            if (!function_exists('nc_message_put_set_if')) {

                function nc_message_put_set_if($array, $key = 0) {
                    return "IF(ID = $array[$key], $key, " . (isset($array[++$key]) ? nc_message_put_set_if($array, $key) : --$key) . ")";
                }

            }

            $priority_array = array_map('intval', (array)$_POST['priority_multifile'][$fldID[$i]]);

            if ($priority_array[0]) {
                $SQL = "UPDATE `Multifield`
                           SET `Priority` = " . nc_message_put_set_if($priority_array) . "
                         WHERE `ID` IN (" . join(', ', $priority_array) . ")";
                $db->query($SQL);
            }

            if (!$settings['path']) {
                $settings['http_path'] = nc_standardize_path_to_folder($nc_core->HTTP_FILES_PATH . "/multifile/{$fldID[$i]}/");
            } else {
                $settings['http_path'] = $db->escape(nc_standardize_path_to_folder($settings['path']));
            }
            $settings['path'] = nc_standardize_path_to_folder($nc_core->DOCUMENT_ROOT . '/' . $nc_core->SUB_FOLDER . '/' . $settings['http_path']);

            if (!is_dir($settings['path'])) {
                $folders = explode('/', rtrim($settings['path'], '/'));

                for ($all = $end = count($folders) - 1; $all > 0; --$all) {
                    $folder_tmp[] = array_pop($folders);
                    if (is_dir(join('/', $folders))) {
                        break;
                    }
                }

                $folder_tmp = array_reverse($folder_tmp);

                for ($start = 0; $all <= $end; ++$start, ++$all) {
                    $folders[] = $folder_tmp[$start];
                    mkdir(join('/', $folders));
                }
            }

            if (!function_exists('nc_message_put_clear_file_name')) {

                function nc_message_put_clear_file_name($file_name) {
                    return nc_Core::get_object()->db->escape(strip_tags($file_name));
                }

            }

            $files_name = array_map('nc_message_put_clear_file_name', (array)$_FILES["f_{$fld[$i]}_file"]['name']);
            foreach ($files_name as $index => $filename) {
                if (is_uploaded_file($_FILES["f_{$fld[$i]}_file"]['tmp_name'][$index]) && !$_FILES["f_{$fld[$i]}_file"]['error'][$index]) {
                    $files_name[$index] = nc_get_filename_for_original_fs($files_name[$index], $settings['path']);
                    move_uploaded_file($_FILES["f_{$fld[$i]}_file"]['tmp_name'][$index], $settings['path'] . $files_name[$index]);
                }
            }

            if (is_array($settings['resize']) || is_array($settings['preview']) || is_array($settings['crop'])) {
                require_once $nc_core->INCLUDE_FOLDER . "classes/nc_imagetransform.class.php";
                foreach ($files_name as $file_name) {
                    if (!is_file($settings['path'] . $file_name)) {
                        continue;
                    }

                    if ($settings['use_preview']) {
                        $mfs_preview_path = $settings['path'] . 'preview_' . $file_name;
                        @copy($settings['path'] . $file_name, $mfs_preview_path);
                        if ($settings['preview']['width'] && $settings['preview']['height']) {
                            @nc_ImageTransform::imgResize($mfs_preview_path, $mfs_preview_path, $settings['preview']['width'], $settings['preview']['height'], $settings['preview']['mode']);
                        }
                        $mfs_crop = $settings['preview']['crop'];
                        if  (($mfs_crop['x1'] && $mfs_crop['y1']) || ($mfs_crop['mode'] == 1 && $mfs_crop['width'] && $mfs_crop['height'])) {
                            $preview_crop_ignore = $settings['preview']['crop_ignore']['width'] && $settings['preview']['crop_ignore']['height'];
                            @nc_ImageTransform::imgCrop($mfs_preview_path, $mfs_preview_path, $mfs_crop['x0'], $mfs_crop['y0'], $mfs_crop['x1'], $mfs_crop['y1'],
                                NULL, 90, 0, 0,
                                $preview_crop_ignore ? $settings['preview']['crop_ignore']['width'] : 0, $preview_crop_ignore  ? $settings['preview']['crop_ignore']['height'] : 0,
                                $mfs_crop['mode'], $mfs_crop['width'], $mfs_crop['height']
                            );
                        }
                    }

                    if ($settings['resize']['width'] && $settings['resize']['height']) {
                        @nc_ImageTransform::imgResize($settings['path'] . $file_name, $settings['path'] . $file_name, $settings['resize']['width'], $settings['resize']['height'], $settings['resize']['mode']);
                    }

                    $mfs_crop = $settings['crop'];
                    if (($mfs_crop['x1'] && $mfs_crop['y1']) || ($mfs_crop['mode'] && $mfs_crop['width'] && $mfs_crop['height'])) {
                        $crop_ignore = $settings['crop_ignore']['width'] && $settings['crop_ignore']['height'];
                        @nc_ImageTransform::imgCrop($settings['path'] . $file_name, $settings['path'] . $file_name, $mfs_crop['x0'], $mfs_crop['y0'], $mfs_crop['x1'], $mfs_crop['y1'],
                            NULL, 90, 0, 0,
                            $crop_ignore ? $settings['crop_ignore']['width'] : 0, $crop_ignore ? $settings['crop_ignore']['height'] : 0,
                            $mfs_crop['mode'], $mfs_crop['width'], $mfs_crop['height']
                        );
                    }
                }
            }

            foreach ($files_name as $index => $filename) {
                $SQL_multifield[] = "($fldID[$i], %msgID%, '" . $db->escape($_POST["f_{$fld[$i]}_name"][$index]) . "', {$_FILES["f_{$fld[$i]}_file"]['size'][$index]}, '{$settings['http_path']}{$filename}', '" . ($settings['preview'] ? "{$settings['http_path']}preview_{$filename}" : "") . "', " . ((int)$index) . ")";
            }
            $fldValue[$i] = '""';
        }



        if ($fldType[$i] == NC_FIELDTYPE_FILE) {
            $fldValue[$i] = $_FILES["f_" . $fld[$i]]["tmp_name"];

            if ($fldValue[$i] && $fldValue[$i] != "none" && is_uploaded_file($fldValue[$i])) {
                $_FILES["f_" . $fld[$i]]["name"] = str_replace(array('<', '>'), '_', $_FILES["f_" . $fld[$i]]["name"]);

                if ($user_table_mode && $action != "add" && !$message) {
                    $message = $AUTH_USER_ID;
                }
                if ($systemTableID == 4) {
                    $message = $TemplateID;
                }

                //перехват альтернативной папки из условий добавления/изменения
                $_FILES["f_" . $fld[$i]]['folder'] = ${"f_" . $fld[$i]}['folder'];

                $file_info = $nc_core->files->field_save_file(
                        $systemTableID ? nc_Component::get_system_table_name_by_id($systemTableID) : $classID,
                        $fldID[$i], $message, $_FILES["f_" . $fld[$i]], false,
                        array('sub' => $sub, 'cc' => $cc), false, false);

                //строка для записи в БД
                $fldValue[$i]           = $file_info['fldValue'];

                // save file path in the $f_Field_url
                ${"f_" . $fld[$i] . "_url"}         = $file_info['url'];
                ${"f_" . $fld[$i] . "_preview_url"} = $file_info['preview_url'];
                ${"f_" . $fld[$i] . "_name"}        = $file_info['name'];
                ${"f_" . $fld[$i] . "_size"}        = $file_info['size'];
                ${"f_" . $fld[$i] . "_type"}        = $file_info['type'];

                $j++;
            } elseif ($fldValue[$i] == "" || $fldValue[$i] == "none") {
                eval("\$fldValue[\$i] = \$f_" . $fld[$i] . "_old;");
            }

            $fldValue[$i] = "\"" . $db->escape($fldValue[$i]) . "\"";

        }

        if (($fldTypeOfEdit[$i] == 1 || (nc_field_check_admin_perm() && $fldTypeOfEdit[$i] == 2)) && empty(${$fld[$i] . "Defined"})) {
            $fieldString .= "`" . $fld[$i] . "`,";
            $valueString .= $fldValue[$i] . ",";
            if ($action == "change" && !($user_table_mode && $fld[$i] == $AUTHORIZE_BY && !($nc_core->get_settings('allow_change_login', 'auth') || in_array($current_user['UserType'], array('fb', 'vk', 'twitter', 'openid'))))) {
                $updateString .= "`" . $fld[$i] . "` = " . $fldValue[$i] . ", ";
            }
        }

        if ($multiple_changes) {
            $updateStrings_tmp[] = "`{$fld[$i]}` = {$fldValue[$i]}";
        }
    }

    $updateStrings[$msg_id] = join(', ', $updateStrings_tmp);
    $updateStrings_tmp = array();

} while ($multiple_changes);

if (!$user_table_mode && $cc && is_object($perm) && $perm->isSubClass($cc, MASK_MODERATE)) {
    $nc_fields_seo = array('ncTitle', 'ncKeywords', 'ncDescription', 'ncSMO_Title', 'ncSMO_Description');
    foreach ($nc_fields_seo as $nc_field) {
        if (!$nc_multiple_changes || isset($_REQUEST["f_$nc_field"])) {
            $nc_field_value = $db->escape(${"f_$nc_field"});
            $updateString .= "`$nc_field` = '$nc_field_value', ";
            $fieldString .= "`$nc_field`,";
            $valueString .= "'$nc_field_value',";
        }
    }
}

/**
 *
 * Функция проверки Keywords на уникальность, в случае совпадения
 * возвращает с числовым постфиксом "-номер", увеличенным на 1
 *
 * @param type $Message_ID
 * @param type $string
 * @param type $tablePostfix
 * @return string
 */
function nc_check_keyword_name($Message_ID = 0, $string = "", $tablePostfix = "", $sub = 0) {

    if (!$string) return;

    global $db;
    $sql = "SELECT COUNT(*) as matches FROM Message" . $tablePostfix . " "
      . "WHERE Keyword = '" . $db->escape($string) . "' AND Message_ID <> " . intval($Message_ID) . " ";
    $sqlsub = $sub ? " AND Subdivision_ID = " . intval($sub) : "";
    $result = $db->get_row($sql . $sqlsub, ARRAY_A);

    if ($result['matches'] > 0) {
        // если уже заканчивается на "-число"
        if (preg_match('/(-\d+)$/', $string, $match)) {
            $clean = str_replace("-".$match, "", $string);
        } else {
            $clean = $string;
        }

        // выбираем последнее слово с таким кейвордом
        $sql = "SELECT Keyword FROM Message" . $tablePostfix . " WHERE Keyword LIKE '" . $db->escape($clean) . "-%' AND Message_ID <> " . intval($Message_ID) . " " . $sqlsub . " ORDER BY Message_ID DESC LIMIT 1";
        $result = $db->get_row($sql, ARRAY_A);
        if (!empty($result['Keyword']))  {
            preg_match('/(-\d+)$/', $result['Keyword'], $match);
            $old_num = intval(str_replace("-", "", end($match)));
            $string = $clean."-".($old_num + 1);
        } else {
            $string = $clean."-1";
        }
    }
    return $string;
}
