<?php
if (!defined('DEBUGBAR_DIR')) {
    define('DEBUGBAR_DIR', basename(__DIR__));
}

// Add a simple utility that leverages Symfony VarDumper and cleans buffer to avoid debug messages
if (!function_exists('d')) {

    function d($var)
    {
        ob_clean();
        echo '<pre>'.__FILE__.':'.__LINE__.'</pre>';
        dump($var);
        exit();
    }
}