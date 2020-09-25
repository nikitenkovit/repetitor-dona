<?php

if (!isset($NETCAT_FOLDER)) {
    $NETCAT_FOLDER = realpath(dirname(__FILE__) . '/..') . DIRECTORY_SEPARATOR;
}

// unset for security reasons
$SYSTEM_FOLDER = "";
// connect config file
@include_once($NETCAT_FOLDER . "vars.inc.php");

// if vars.inc.php not updated set default value for $SYSTEM_FOLDER
if (!$SYSTEM_FOLDER) {
    global $SYSTEM_FOLDER;
    $SYSTEM_FOLDER = $ROOT_FOLDER . "system/";
}

// PHP version must be >= 5.2
if (version_compare(phpversion(), '5.2', '<')) {
    echo "<b style='color:#A00'>PHP 5.2 or higher required!</b>";
    exit;
}

// short_open_tag must be on
if (!ini_get('short_open_tag')) {
    echo "<b style='color:#A00'>short_open_tag value must be on!</b>";
    exit;
}

// include all new system classes and get nc_core object
require_once($INCLUDE_FOLDER . "unicode.inc.php");
require_once($SYSTEM_FOLDER . "index.php");

// set db for compatibility
$db = $nc_core->db;
