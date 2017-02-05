<?
define('AUTOLOADER_PATH',  DIR . '..\src\vendor\develnext.bundle.jurl.jURLBundle\\');

spl_autoload_register(function($class) {
    include AUTOLOADER_PATH.$class.'.php';
});