<?php

/**
 * Класс для реализации поля типа "Файл"
 */
class nc_a2f_field_file extends nc_a2f_field {

    protected $upload, $filename, $filesize, $filepath, $filetype;

    public function get_subtypes() {
        return array('any', 'image');
    }

    public function render_value_field($html = true) {
        global $DOMAIN_NAME;
        $nc_core = nc_Core::get_object();
        $ret = "<input name='" . $this->get_field_name() . "' type='file' style='width:100%;'/>";
        // старый файл
        if ($this->value) {
            $filepath = $nc_core->SUB_FOLDER . $nc_core->HTTP_FILES_PATH . $this->value['path'];
            $ret .= "<input type='hidden' name='" . $this->get_field_name('old') . "' value='" . ($this->value['all']) . "' /><br/>\r\n";
            $ret .= NETCAT_MODERATION_FILES_UPLOADED . ": ";
            $ret .= "<a target='_blank' href='http://" . $DOMAIN_NAME . $this->value['path'] . "'>" . $this->value['name'] . "</a> (" . nc_bytes2size($this->value['size']) . ")";
            $ret .= " <input id='kill" . $this->name . "' type='checkbox' name='" . $this->get_field_name('kill') . "' value='1' />
               <label for='kill" . $this->name . "'>" . NETCAT_MODERATION_FILES_DELETE . "</label>\r\n";
        }
        if ($html) {
            $ret = "<div class='ncf_value'>" . $ret . "</div>\n";
        }

        return $ret;
    }

    protected function get_displayed_default_value() {
        if (!$this->default_value) {
            return '';
        }

        $file_info = null;

        if (is_string($this->default_value)) {
            $file_info = $this->file_string_to_array($this->default_value);
        }

        if (is_array($this->default_value)) {
            $file_info = $this->default_value;
        }

        if ($file_info) {
            return "<a href='" . $file_info['path'] .
                   "' target='_blank'>" .
                   $file_info['name'] .
                   "</a>";
        }


        return $this->default_value;
    }

    public function set_value($value) {
        $this->value = false;
        if (is_string($value)) {
            $this->value = $this->file_string_to_array($value);
            $this->is_set = true;
        }
        else if (is_array($value) && isset($value['path']) && isset($value['name'])) {
            $this->value = $value;
            $this->is_set = true;
        }
        return 0;
    }

    protected function file_string_to_array($value) {
        $result = array();
        list($filename, $filetype, $filesize, $filepath) = explode(':', $value);
        if (!$filepath) {
            return false;
        }
        $nc_core = nc_Core::get_object();
        $result['resultpath'] = $nc_core->SUB_FOLDER . $nc_core->HTTP_FILES_PATH . $filepath;
        $result['path'] = $result['resultpath'];
        $result['type'] = $filetype;
        $result['size'] = $filesize;
        $result['name'] = $filename;
        $result['all'] = $value;
        return $result;
    }

    public function save($value) {
        $nc_core = nc_Core::get_object();

        $array_name = $this->parent->get_array_name();

        if (!empty($value['old']) && !empty($value['kill'])) {
            list ($filename, $filetype, $filesize, $filepath) = explode(':', $value['old']);
            unlink($nc_core->FILES_FOLDER . $filepath);
            $this->value = $value['old'] = '';
        }

        if ($_FILES[$array_name]['error'][$this->name]) {
            if ($value['old']) {
                $this->value = $value['old'];
            }
            return 0;
        }

        $tmp_name = $_FILES[$array_name]['tmp_name'][$this->name];
        $filetype = $_FILES[$array_name]['type'][$this->name];
        $filename = $_FILES[$array_name]['name'][$this->name];

        // nothing was changed
        if (!empty($value['old']) && empty($value['kill']) && !$filetype) {
            if ($value['old']) {
                $this->value = $value['old'];
            }
            return 0;
        }

        $folder = $nc_core->FILES_FOLDER . 'cs/';
        $put_file_name = nc_transliterate($filename);
        $put_file_name = nc_get_filename_for_original_fs($put_file_name, $folder, array());

        $nc_core->files->create_dir($folder);
        move_uploaded_file($tmp_name, $folder . $put_file_name);
        $filesize = filesize($folder . $put_file_name);
        if ($filesize) {
            $this->value = $filename . ':' . $filetype . ':' . $filesize . ':cs/' . $put_file_name;
        }
        else {
            $this->value = '';
        }

        $this->upload = true;
        $this->filename = $filename;
        $this->filetype = $filetype;
        $this->filesize = $filesize;
        $this->filepath = $folder . $put_file_name;
    }
}

