<?php
if (!defined('DEBUGBAR_DIR')) {
    define('DEBUGBAR_DIR', basename(__DIR__));
}

// Add a simple utility that leverages Symfony VarDumper and cleans buffer to avoid debug messages
if (!function_exists('d')) {

    /**
     * Helpful debugging helper. Pass as many arguments as you need.
     * Keep the call on one line to be able to output arguments names
     * 
     * @return void
     */
    function d()
    {
        // Clean buffer that may be in the way
        if (ob_get_contents()) ob_end_clean();

        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        // Caller
        $line = $bt[1]['line'];
        $file = $bt[1]['file'];

        // Probably best to avoid using this in live websites...
        if (Director::isLive()) {
            SS_Log::log("Please remove call to d() in $file:$line", SS_Log::WARN);
            return;
        }

        // Arguments passed to the function are stored in matches
        $src          = file($bt[0]["file"]);
        $calling_line = $src[$bt[0]['line'] - 1];
        preg_match("/d\((.+)\)/", $calling_line, $matches);

        // Find all arguments, ignore variables within parenthesis
        $arguments_name = [];
        if (!empty($matches[1])) {
            $arguments_name = array_map('trim',
                preg_split("/(?![^(]*\)),/", $matches[1]));
        }

        $isAjax = Director::is_ajax();
        $print  = function($v) use($isAjax) {
            if (!$isAjax) {
                $v = '<pre>'.$v.'</pre>';
            }
            if (is_string($v)) {
                echo $v."\n";
            } else {
                echo print_r($v, true)."\n";
            }
        };

        // Display caller info
        $print("$file:$line\n");

        // Display data in a friendly manner
        $args = func_get_args();

        $i = 0;
        foreach ($args as $arg) {
            // Echo name of the variable
            $len = 20;
            if (isset($arguments_name[$i])) {
                $print($arguments_name[$i]);
                $len = strlen($arguments_name[$i]);
            }
            // For ajax requests, a good old print_r is much better
            if ($isAjax || !function_exists('dump')) {
                $print($arg);
                // Make a nice line between variables for readability
                if (count($args) > 1) {
                    $print(str_repeat('-', $len));
                }
            } else {
                dump($arg);
            }
            $i++;
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