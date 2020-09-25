<?php

if (!class_exists("nc_System")) die("Unable to load file.");

class nc_Files extends nc_System {

    protected $core;
    protected $tmp_files;
    protected $filetable_ids;

    public function __construct() {
        // load parent constructor
        parent::__construct();
        $this->core = nc_Core::get_object();
        $this->tmp_files = array();
        $this->filetable_ids = array();
    }

    public function create_dir($fullpath) {
        $nc_core = nc_Core::get_object();

        if (is_dir($fullpath)) return true;

        if (($res = mkdir($fullpath, $nc_core->DIRCHMOD, 1))) {
            chmod($fullpath, $nc_core->DIRCHMOD);
        }

        return $res;
    }

    public function delete_dir($path) {
        if (!file_exists($path) || !is_dir($path)) return false;

        if (!is_writable($path)) throw new nc_Exception_Files_Not_Rights($path);

        foreach (scandir($path) as $v) {
            if ($v == "." || $v == "..") continue;

            if (filetype($path . "/" . $v) == "dir") {
                $this->delete_dir($path . "/" . $v);
            } else {
                if (!is_writable($path . "/" . $v))
                    throw new nc_Exception_Files_Not_Rights($path . "/" . $v);
                unlink($path . "/" . $v);
            }
        }

        rmdir($path);

        return true;
    }

    public function save_file($class_id, $field, $message_id, $file) {
        $this->field_save_file('User', $field, $message_id, $file);
    }

    /**
     * Сохраняет файл в поле типа "6:Файл"
     *
     * @param string|int $class_id - ID компонента или название системной таблицы(User|Catalogue|Subdivision|Template)
     * @param string|int $field    - название или ID поля в которое сохранять
     * @param int $message_id      - ID объекта|пользователя|сайта|раздела|макета
     * @param array|string $file   - массив с данными о файле из $_FILES, либо массив с элементами:
     *      path          — путь к файлу(/netcat/tmp/foto.jpg) или ссылка(http://example.com/foto.jpg)
     *      type          — mime-тип, попытается определить автоматически если не задан, по умолчанию image/jpeg
     *      name          — имя файла, возьмется из ссылки если не задано
     *      folder        — нестандартная папка в которую сохранить файл, только для стандартной ФС
     *             альтернативный вариант - вместо массива строка со значением path
     * @param bool $noеdit         - игнорировать настройки поля изменяющие файл
     * @param bool $message_put    - режим работы внутри системы, дополнительные данные для объекта который еще не создан,
     *                              false при сохранении в существующий объект
     * @param bool $nodelete       - не удалять старый файл
     *
     * @return array               - возвращает массив с данными о сохраненном файле
     *      download_path — ссылка для скачивания под оригинальным именем (ссылка с "h_")
     *      url           — путь к файлу от корня сайта
     *      preview_url   — путь к превью файла от корня сайта
     *      name          — изначальное имя файла
     *      size          — размер
     *      type          — mime-тип
     */
    public function field_save_file($class_id, $field, $message_id, $file, $noedit = false, $message_put = false, $nodelete = false, $is_tmp = false) {
        $DOCUMENT_ROOT = nc_core('DOCUMENT_ROOT');
        $FILES_FOLDER = nc_core('FILES_FOLDER');
        $files_http_path = nc_core('SUB_FOLDER') . nc_core('HTTP_FILES_PATH');
        $db = nc_core('db');

        // *** Проверка входных данных ***

        $message_id = intval($message_id);
        $component = new nc_Component($class_id);
        $systemTableID = $component->get_system_table_id();
        $systemTableName = $systemTableID ? $class_id : '';

        if ($field == 'ncSMO_Image') {
            $fields = array(
               $component->get_smo_image_field(),
            );
        } else {
            $fields = $component->get_fields(NC_FIELDTYPE_FILE);
        }


        if (!empty($fields)) {
            foreach ($fields as $v) {
                if ($v['id'] == $field || $v['name'] == $field) {
                    $rawformat = $v['format'];
                    $field_id = $v['id'];
                    $field_name = $v['name'];
                }
            }
        } else {
            return null; //wrong class or field
        }

        if (!$systemTableID) {
            $msg = $db->get_row("SELECT `Sub_Class_ID`, `Subdivision_ID` FROM `Message{$class_id}` WHERE `Message_ID` = '{$message_id}'", ARRAY_A);
        } else {
            $msg = $db->get_row("SELECT COUNT(*) FROM `{$systemTableName}` WHERE `{$systemTableName}_ID` = {$message_id}", ARRAY_A);
        }
        if ($message_id && empty($msg)) {
            return null;  //wrong message
        }
        if (!$message_id && !is_array($message_put)){
            return null;  //no data for add
        }

        $sub = $msg['Subdivision_ID'] ? $msg['Subdivision_ID'] : (int)$message_put['sub'];
        $cc  = $msg['Sub_Class_ID']   ? $msg['Sub_Class_ID']   : (int)$message_put['cc'];

        $format = nc_field_parse_format($rawformat, NC_FIELDTYPE_FILE);

        if (!is_array($file)) {
            $file = array('path' => $file);
        }

        $fileurl = $file['path'] ? $file['path'] : $file['url'];
        $filesrc = $file['tmp_name'];

        if (!$fileurl && !$filesrc) {
            return null; //no file to save
        }

        if (!$filesrc && $fileurl //файл по ссылке
            && !(preg_match("~^[^=]+://~", $fileurl) && $buf = @file_get_contents($fileurl)) //по внешней ссылке нет
            && !file_exists($DOCUMENT_ROOT . $fileurl)
        ) { //и по внутренней нет
            return null;
        }

        // *** Удаление старого файла ***

        if (!$nodelete && $is_tmp === false) {
            require_once $this->core->INCLUDE_FOLDER . "s_files.inc.php";
            DeleteFile($field_id, $field_name, $class_id, $systemTableName, $message_id);
        }

        if ($file['name']) {
            $filename = $file['name'];
        } else {
            $filename = array_pop(explode('/', str_replace('\\', '/', $fileurl)));
            $filename = explode('?', $filename);
            $filename = $filename[0];
        }

        $filename = str_replace(array('<', '>'), '_', $filename);
        $filetype = $file['type'];
        $filesize = $file['size'];
        $folder = trim($file['folder'], '/');
        $ext = substr($filename, strrpos($filename, ".")); // расширение файла

        // *** Вычисление имени файла и папки для сохранения ***

        if (!$systemTableID) {
            $File_Path =  "$sub/$cc/";
        } elseif ($systemTableID == 1) {
            $File_Path = "c/";
        } elseif ($systemTableID == 3) {
            $File_Path = "u/";
        } elseif ($systemTableID == 4) {
            $File_Path = "t/";
        } elseif ($systemTableID == 2) {
            $File_Path = $message_id . "/";
        }

        switch ($format['fs']) {
            case NC_FS_PROTECTED: // hash
                // имя файла
                $put_file_name = md5($filename . date("H:i:s d.m.Y") . uniqid("netcat"));
                break;

            case NC_FS_ORIGINAL:
                // пользователь сам указал папку
                if ($folder && preg_match("/^[a-z0-9\/_-]+$/is", $folder) && @$this->create_dir($FILES_FOLDER . $folder . "/")) {
                    $File_Path = $folder . "/";
                }
                // сгенерировать имя файла
                $put_file_name = nc_get_filename_for_original_fs($filename, $FILES_FOLDER . $File_Path);

                $db_string_path = ":" . ($File_PathNew ? $File_PathNew : $File_Path) . $put_file_name;
                break;

            case NC_FS_SIMPLE: // FieldID_MessageID.ext
                $File_Path = ''; // в папку netcat_files
                $put_file_name = $field_id . "_" . $message_id . $ext;

                // cоздание временного файла при добавлении объекта
                // будет переименован после добавления, в field_save_file_afteraction($message)
                if (!$message_id ) {
                    $tmp_file['tmp_name'] = 'tmp_' . md5($filesrc);
                    $tmp_file['name'] = $field_id . '_%MSG' . $ext;
                    $tmp_file['fld_name'] = $field_name;
                }
                break;
        }

        // *** Сохранение файла на диск ***

        if ($is_tmp === true) {
            $put_file_name = "tmp_".$put_file_name;
        }

        //итоговый путь куда сохранить файл
        $save_name = $tmp_file['tmp_name'] ? $tmp_file['tmp_name'] : $put_file_name;
        $save_path = $FILES_FOLDER . $File_Path . $save_name;
        $save_path_preview = $FILES_FOLDER . $File_Path . 'preview_' . $save_name;

        @$this->create_dir($FILES_FOLDER . $File_Path);
        if ($filesrc) {
            @move_uploaded_file($filesrc, $save_path);
        } else if ($buf) {
            @file_put_contents($save_path, $buf);
        } else {
            @copy($DOCUMENT_ROOT . $fileurl, $save_path);
        }

        // *** Обработка файла ***

        if (!$noedit && @getimagesize($save_path)) {
            require_once $this->core->INCLUDE_FOLDER . "classes/nc_imagetransform.class.php";
            $resize_format = nc_field_parse_resize_options($rawformat);

            if ($resize_format['use_preview'] || ($resize_format['preview_width'] && $resize_format['preview_height'])) {
                @copy($save_path, $save_path_preview);
                if ($resize_format['preview_use_resize']){
                    @nc_ImageTransform::imgResize($save_path_preview, $save_path_preview, $resize_format['preview_width'], $resize_format['preview_height']);
                }
                if ($resize_format['preview_use_crop']) {
                    @nc_ImageTransform::imgCrop($save_path_preview, $save_path_preview, $resize_format['preview_crop_x0'], $resize_format['preview_crop_y0'], $resize_format['preview_crop_x1'], $resize_format['preview_crop_y1'],
                        NULL, 90, 0, 0,
                        $resize_format['preview_crop_ignore'] ? $resize_format['preview_crop_ignore_width'] : 0,
                        $resize_format['preview_crop_ignore'] ? $resize_format['preview_crop_ignore_height'] : 0,
                        $resize_format['preview_crop_mode'], $resize_format['preview_crop_width'], $resize_format['preview_crop_height']);
                }
            }

            if ($resize_format['use_resize']) {
                @nc_ImageTransform::imgResize($save_path, $save_path, $resize_format['resize_width'], $resize_format['resize_height']);
            }

            if ($resize_format['use_crop']) {
                @nc_ImageTransform::imgCrop($save_path, $save_path, $resize_format['crop_x0'], $resize_format['crop_y0'], $resize_format['crop_x1'], $resize_format['crop_y1'],
                    NULL, 90, 0, 0,
                    $resize_format['crop_ignore'] ? $resize_format['crop_ignore_width'] : 0,
                    $resize_format['crop_ignore'] ? $resize_format['crop_ignore_height'] : 0,
                    $resize_format['crop_mode'], $resize_format['crop_width'], $resize_format['crop_height']);
            }
        }
        clearstatcache();
        $filesize = @filesize($save_path);
        $filetype = $filetype ? trim($filetype, '"') : ($filetype = $this->_guess_content_type($save_path) ? $filetype : "image/jpeg");

        // *** Сохранение и выходные данные ***

        $result = array();

        // для защищенной надо добавить файл в Filetable
        if ($format['fs'] == NC_FS_PROTECTED && $is_tmp === false) {
            $query = $db->query(
                "INSERT INTO `Filetable`
                            (`Real_Name`, `File_Path`, `Virt_Name`, `File_Type`, `File_Size`, `Field_ID`, `Content_Disposition`, `Message_ID`)
                     VALUES ('" . $db->escape($filename) . "', '/" . $db->escape($File_Path) . "', '" . $db->escape($put_file_name) . "', '" . $db->escape($filetype) . "',
                            '" . intval($filesize) . "', '" . intval($field_id) . "', '" . intval($format['disposition']) . "', '" . $message_id . "')"
            );

            if ($query) {
                $result['download_path'] = $files_http_path . $File_Path . 'h_' . $put_file_name;
                if (!$message_id) {
                    //после добавления объекта нужно обновить таблицу
                    $this->filetable_ids[] = $db->insert_id;
                }
            }
        }

        $db_string = $filename . ":" . $filetype . ":" . $filesize . $db_string_path;

        if (!$message_put && $is_tmp === false) { //запишем в объект сущности
            $query = $db->query("UPDATE `" . ($systemTableID ? $systemTableName : "Message" . $class_id) . "`
                                    SET `{$field_name}` = '" . $db->escape($db_string) . "'
                                        WHERE `" . ($systemTableID ? $systemTableName : "Message") . "_ID` = {$message_id}");

        }

        $result['url']          = $files_http_path . $File_Path . $put_file_name;
        $result['preview_url']  = $files_http_path . $File_Path . 'preview_' . $put_file_name;
        $result['name']         = $filename;
        $result['size']         = $filesize;
        $result['type']         = $filetype;
        $result['fldValue']     = $db_string;

        if ($tmp_file) { //данные о временном файле
            $this->tmp_files[] = $tmp_file;
        }

        return $result;
    }

    public function field_save_file_afteraction($message) {
        $extract_fields = array();
        foreach ($this->tmp_files as $tmp_file) {
            $tmp_file['name'] = str_replace('%MSG', $message, $tmp_file['name']);

            $dest_file = nc_core('FILES_FOLDER') . $tmp_file['name'];
            $dest_file_rpeview = nc_core('FILES_FOLDER') . 'preview_' . $tmp_file['name'];

            @rename(nc_core('FILES_FOLDER') . $tmp_file['tmp_name'], $dest_file);
            @rename(nc_core('FILES_FOLDER') . 'preview_' . $tmp_file['tmp_name'], $dest_file_rpeview);
            @chmod($dest_file, nc_core('FILECHMOD'));
            @chmod($dest_file_rpeview, nc_core('FILECHMOD'));

            $files_http_path = nc_core('SUB_FOLDER') . nc_core('HTTP_FILES_PATH');
            $extract_fields['f_'.$tmp_file['fld_name'].'_url'] = $files_http_path . $tmp_file['name'];
            $extract_fields['f_'.$tmp_file['fld_name'].'_preview_url'] = $files_http_path . 'preview_' . $tmp_file['name'];
        }
        $this->tmp_files = array();

        if (!empty($this->filetable_ids)) {
            nc_db()->query("UPDATE `Filetable` SET `Message_ID` = '".$message."'
                            WHERE `ID`  IN(".join(',', $this->filetable_ids).")");
            $this->filetable_ids = array();
        }

        return $extract_fields;
    }


    /**
     * Internal check to get the proper mimetype.
     *
     * This function would go over the available PHP methods to get
     * the MIME type.
     *
     * By default it will try to use the PHP fileinfo library which is
     * available from PHP 5.3 or as an PECL extension
     * (http://pecl.php.net/package/Fileinfo).
     *
     * if fileinfo is not available it will try to use the internal
     * mime_content_type function.
     *
     * @param string $path full file path
     * @return string mimetype
     */
    function _guess_content_type($path) {
        $content_type = '';

        if (!is_file((string)$path)) return false;

        if (function_exists("finfo_open")) {
            $finfo = @finfo_open(FILEINFO_MIME);
            if ($finfo) {
                $ct = @finfo_file($finfo, $path);

                /* PHP 5.3 fileinfo display extra information like
                   charset so we remove everything after the ; since
                   we are not into that stuff */
                if ($ct) {
                    $extra_content_type_info = strpos($ct, "; ");
                    if ($extra_content_type_info)
                        $ct = substr($ct, 0, $extra_content_type_info);
                }

                if ($ct && $ct != 'application/octet-stream')
                    $content_type = $ct;

                @finfo_close($finfo);
            }
        }

        if (!$content_type && function_exists("mime_content_type")) {
            $content_type = @mime_content_type($path);
        }

        return $content_type;
    }
}