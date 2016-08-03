<?php
if (!defined('DEBUGBAR_DIR')) {
    define('DEBUGBAR_DIR', basename(__DIR__));
}

// Add a simple utility that leverages Symfony VarDumper and cleans buffer to avoid debug messages
if (!function_exists('d')) {

    function d()
    {
        // Clean buffer that may be in the way
        if (ob_get_contents()) ob_end_clean();

        // Display caller info
        $caller = Debug::caller();
        echo '<pre>'.$caller['file'].':'.$caller['line'].'</pre>'."\n";

        // Display data in a friendly manner
        $args   = func_get_args();
        $isAjax = Director::is_ajax();
        foreach ($args as $arg) {
            // For ajax requests, a good old print_r is much better
            if ($isAjax || !function_exists('dump')) {
                print_r($arg);
            } else {
                dump($arg);
            }
        }
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