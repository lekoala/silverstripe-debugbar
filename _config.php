<?php
if (!defined('DEBUGBAR_DIR')) {
    define('DEBUGBAR_DIR', basename(__DIR__));
}

// Add a simple utility that leverages Symfony VarDumper and cleans buffer to avoid debug messages
if (!function_exists('d')) {

    function d($var)
    {
        ob_clean();
        $caller = Debug::caller();
        echo '<pre>'.$caller['file'].':'.$caller['line'].'</pre>';
        dump($var);
        exit();
    }
}

// Add a simple log helper that provides a default priority
if (!function_exists('l')) {

    function l($message, $priority = 7, $extras = null)
    {
        SS_Log::log($message, $priority, $extras);
    }
}