<?php
if (!defined('DEBUGBAR_DIR')) {
    define('DEBUGBAR_DIR', basename(__DIR__));
}

// Add a simple utility that leverages Symfony VarDumper and cleans buffer to avoid debug messages
if (!function_exists('d')) {

    /**
     * Helpful debugging helper. Pass as many arguments as you need.
     * Keep the call on one line to be able to output arguments names
     * Without arguments, it will display all object instances in the backtrace
     * 
     * @return void
     */
    function d()
    {
        // Clean buffer that may be in the way
        if (ob_get_contents()) ob_end_clean();

        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);

        // Where is d called is the first element of the backtrace
        $line = $bt[0]['line'];
        $file = $bt[0]['file'];

        // Caller
        $caller_function = isset($bt[1]['function']) ? $bt[1]['function'] : null;
        $caller_class    = isset($bt[1]['class']) ? $bt[1]['class'] : null;
        $caller          = $caller_function;
        if ($caller_class) {
            $caller = $caller_class.'::'.$caller_function;
        }

        // Probably best to avoid using this in live websites...
        if (Director::isLive()) {
            SS_Log::log("Please remove call to d() in $file:$line", SS_Log::WARN);
            return;
        }

        // Arguments passed to the function are stored in matches
        $src      = file($file);
        $src_line = $src[$line - 1];
        preg_match("/d\((.+)\)/", $src_line, $matches);

        // Find all arguments, ignore variables within parenthesis
        $arguments_name = [];
        if (!empty($matches[1])) {
            $arguments_name = array_map('trim',
                preg_split("/(?![^(]*\)),/", $matches[1]));
        }

        $isAjax = Director::is_ajax();

        // Display data nicely according to context
        $print = function() use($isAjax) {
            $args = func_get_args();
            if (!$isAjax) {
                echo '<pre>';
            }
            foreach ($args as $arg) {
                if (!$arg) {
                    continue;
                }
                if (is_string($arg)) {
                    echo $arg;
                } else {
                    print_r($arg);
                }
                echo "\n";
            }
            if (!$isAjax) {
                echo '</pre>';
            }
        };

        // Display caller info
        $print("$file:$line ($caller)");

        // Display data in a friendly manner
        $args = func_get_args();
        if (empty($args)) {
            $arguments_name = [];
            foreach ($bt as $trace) {
                if (!empty($trace['object'])) {
                    $line             = isset($trace['line']) ? $trace['line'] : 0;
                    $function         = isset($trace['function']) ? $trace['function']
                            : 'unknown function';
                    $arguments_name[] = $function.':'.$line;
                    $args[]           = $trace['object'];
                }
            }
        }

        $i = 0;
        foreach ($args as $arg) {
            // Echo name of the variable
            $len = 20;
            if (isset($arguments_name[$i])) {
                $print('Value for: '.$arguments_name[$i]);
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
        if (!is_string($message)) {
            $message = json_encode((array) $message);
        }
        SS_Log::log($message, $priority, $extras);
    }
}