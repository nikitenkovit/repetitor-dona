<?php

$DOCUMENT_ROOT = rtrim( getenv("DOCUMENT_ROOT"), "/\\" );
$HTTP_HOST = getenv("HTTP_HOST");

# подпапка в которой стоит NetCat
$SUB_FOLDER = '';
# Если NetCat стоит в подпапке, то раскомментируйте следующую строчку
#$SUB_FOLDER = str_replace( str_replace("\\", "/", $DOCUMENT_ROOT), "", str_replace("\\", "/", dirname(__FILE__)) );

# установка переменных окружения
error_reporting(E_ALL & ~(E_NOTICE | E_STRICT));
@ini_set("session.auto_start", "0");
@ini_set("session.use_trans_sid", "0");
@ini_set("session.use_cookies", "1");
@ini_set("session.use_only_cookies", "1");
@ini_set("url_rewriter.tags", ""); // to disable trans_sid on PHP < 5.0
@ini_set('session.cookie_domain', (strpos(str_replace("www.", "", $HTTP_HOST),'.') !== false) ? str_replace("www.", "", $HTTP_HOST) : '');
@ini_set("session.gc_probability", "1");
@ini_set("session.gc_maxlifetime", "1800");
@ini_set("session.hash_bits_per_character", "5");
@ini_set("mbstring.internal_encoding", "UTF-8");
@ini_set("default_charset", "UTF-8");
@ini_set("session.name", ini_get("session.hash_bits_per_character")>=5 ? "sid" : "ced");

# параметры доступа к базе данных
$MYSQL_HOST = "localhost";
$MYSQL_USER = "root";
$MYSQL_PASSWORD = "";
$MYSQL_DB_NAME = "repeti";
$MYSQL_CHARSET = "utf8";
$MYSQL_ENCRYPT = "MD5";

# кодировка
$NC_UNICODE = 1;
$NC_CHARSET = "utf-8";

# настройки авторизации
$AUTHORIZE_BY = "Login";
$AUTHORIZATION_TYPE = "cookie"; # 'http', 'session' or 'cookie'

#разрешить вход только по https
$NC_ADMIN_HTTPS = 0;  # 0 или 1

# серверные настройки
$PHP_TYPE = "module"; # 'module' or 'cgi'
$REDIRECT_STATUS = "on"; # 'on' or 'off'

# настройки безопасности
$SECURITY_XSS_CLEAN = false;

# инструмент "Переадресация" не доступен
$NC_REDIRECT_DISABLED = 0; # 0 или 1

# не загружать устаревшие файлы и функции
$NC_DEPRECATED_DISABLED = 1; # 0 или 1

$ADMIN_LANGUAGE = "Russian"; # Язык административной части NetCat "по-умолчанию"
$FILECHMOD = 0644; # Права на файл при добавлении через систему
$DIRCHMOD = 0755; # Права на директории для закачки пользовательских файлов
$SHOW_MYSQL_ERRORS = 'off'; # Показ ошибок MySQL на страницах сайта
$ADMIN_AUTHTIME = 2592000; # Время жизни авторизации в секундах (при $AUTHORIZATION_TYPE = session, cookie)
$ADMIN_AUTHTYPE = "manual"; # Выбор типа авторизации: 'session', 'always' or 'manual'
$use_gzip_compression = false; # Для включения сжатия вывода установите true

# настройки проекта
$DOMAIN_NAME = $HTTP_HOST; # $HTTP_HOST is server environment variable

#$DOCUMENT_ROOT = "/usr/local/etc/httpd/htdocs/www";

$HTTP_IMAGES_PATH = "/images/";
$HTTP_ROOT_PATH = "/netcat/";
$HTTP_FILES_PATH = "/netcat_files/";
$HTTP_DUMP_PATH = "/netcat_dump/";
$HTTP_CACHE_PATH = "/netcat_cache/";
$HTTP_TEMPLATE_PATH = "/netcat_template/";
$HTTP_TRASH_PATH = "/netcat_trash/";

# относительный путь в админку сайта, для ссылок
$ADMIN_PATH = $SUB_FOLDER.$HTTP_ROOT_PATH."admin/";
# относительный путь к теме админки, для изображений и .css файлов
$ADMIN_TEMPLATE = $ADMIN_PATH."skins/default/";
# полный путь к теме админки
$ADMIN_TEMPLATE_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$ADMIN_TEMPLATE;

$SYSTEM_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_ROOT_PATH."system/";
$ROOT_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_ROOT_PATH;
$FILES_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_FILES_PATH;
$DUMP_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_DUMP_PATH;
$CACHE_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_CACHE_PATH;
$TRASH_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_TRASH_PATH;
$INCLUDE_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_ROOT_PATH."require/";
$TMP_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_ROOT_PATH."tmp/";
$MODULE_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_ROOT_PATH."modules/";
$ADMIN_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_ROOT_PATH."admin/";
$EDIT_DOMAIN = $DOMAIN_NAME;
$DOC_DOMAIN = "netcat.ru/developers/docs";

$TEMPLATE_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_TEMPLATE_PATH."template/";
$CLASS_TEMPLATE_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_TEMPLATE_PATH."class/";
$WIDGET_TEMPLATE_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_TEMPLATE_PATH."widget/";
$JQUERY_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_TEMPLATE_PATH."jquery/";
$MODULE_TEMPLATE_FOLDER = $DOCUMENT_ROOT.$SUB_FOLDER.$HTTP_TEMPLATE_PATH."module/";

# set include_path: require/lib folder (PEAR and other 3rd-party libraries)
set_include_path("{$INCLUDE_FOLDER}lib/");

# название разработчика, отображаемое на странице «О программе»
#$DEVELOPER_NAME = 'ООО «НетКэт»';
#$DEVELOPER_URL = 'http://www.netcat.ru/';

