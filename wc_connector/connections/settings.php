<?php

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'db_name');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'root');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);

date_default_timezone_set('Europe/London');

define('WP_USE_THEMES', false);

$url_segments = explode('/',trim($_SERVER['REQUEST_URI'],'/'));



if($_SERVER['SERVER_ADDR'] === '192.168.1.53' || $_SERVER['SERVER_ADDR'] === '127.0.0.1' || $_SERVER['SERVER_ADDR'] == '::1' || $_SERVER['HTTP_HOST'] === 'dev') {
    $local_dir = '/' . $url_segments[0] . '/' . $url_segments[1];
    defined('DOCUMENT_ROOT') OR define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'].$local_dir);
}

// staging settings
elseif($_SERVER['HTTP_HOST'] === 'staging.juiceclients.com') {
    $local_dir = '/' . $url_segments[0];
    defined('DOCUMENT_ROOT') OR define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT'].$local_dir);
}

// live settings
else {
    defined('DOCUMENT_ROOT') OR define('DOCUMENT_ROOT', $_SERVER['DOCUMENT_ROOT']);
}

//Theses are in both here and header.php
//defined('MISSING_IMAGE') OR define('MISSING_IMAGE', get_stylesheet_directory_uri().'/_/images/awaiting-image.jpg');
//defined('MISSING_CATEGORY_IMAGE') OR define('MISSING_CATEGORY_IMAGE', get_stylesheet_directory_uri().'/_/images/awaiting-category-image.jpg');

$db = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET, DB_USER, DB_PASSWORD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));