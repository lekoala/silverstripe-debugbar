<?php

use LeKoala\DebugBar\DebugBar;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;

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
        $args = func_get_args();

        $doExit = true;
        $isPlain = Director::is_ajax() || Director::is_cli();

        // Allow testing the helper
        if (isset($args[0]) && $args[0] instanceof \SilverStripe\Dev\SapphireTest) {
            $doExit = false;
            array_shift($args);
        } else {
            // Clean buffer that may be in the way
            if (ob_get_contents()) {
                ob_end_clean();
            }
        }

        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);

        // Where is d called is the first element of the backtrace
        $line = $bt[0]['line'];
        $file = $bt[0]['file'];

        // Caller
        $caller_function = isset($bt[1]['function']) ? $bt[1]['function'] : null;
        $caller_class = isset($bt[1]['class']) ? $bt[1]['class'] : null;
        $caller = $caller_function;
        if ($caller_class) {
            $caller = $caller_class . '::' . $caller_function;
        }

        // Probably best to avoid using this in live websites...
        if (Director::isLive()) {
            Injector::inst()->get(LoggerInterface::class)->info("Please remove call to d() in $file:$line");
            return;
        }

        // Arguments passed to the function are stored in matches
        $src = file($file);
        $src_line = $src[$line - 1];
        preg_match("/d\((.+)\)/", $src_line, $matches);

        // Find all arguments, ignore variables within parenthesis
        $arguments_name = array();
        if (!empty($matches[1])) {
            $arguments_name = array_map('trim', preg_split("/(?![^(]*\)),/", $matches[1]));
        }

        // Display data nicely according to context
        $print = function () use ($isPlain) {
            $args = func_get_args();
            if (!$isPlain) {
                echo '<pre>';
            }
            foreach ($args as $arg) {
                if ($isPlain && $arg === "") {
                    $arg = "(empty)";
                } elseif ($isPlain && $arg === null) {
                    $arg = "(null)";
                } elseif (!is_string($arg)) {
                    // Avoid print_r on object as it can cause massive recursion
                    if (is_object($arg)) {
                        $arg = get_class($arg);
                    } else {
                        $arg = json_encode($arg, JSON_PRETTY_PRINT, 5);
                    }
                }
                $arg = trim($arg);
                if (strlen($arg) > 255) {
                    $arg = substr($arg, 0, 252) . "...";
                }
                echo $arg . "\n";
            }
            if (!$isPlain) {
                echo '</pre>';
            }
        };

        // Display caller info
        $fileline = "$file:$line";
        if (!$isPlain) {
            // Allow opening in ide
            // TODO: make ide configurable
            $idePlaceholder = 'vscode://file/{file}:{line}';
            $ideLink = str_replace(['{file}', '{line}'], [$file, $line], $idePlaceholder);
            $fileline = "<a href=\"$ideLink\">$fileline</a>";
        }
        $print("$fileline ($caller)");

        // Display data in a friendly manner
        if (empty($args)) {
            $arguments_name = array();
            foreach ($bt as $trace) {
                if (!empty($trace['object'])) {
                    $line = isset($trace['line']) ? $trace['line'] : 0;
                    $function = isset($trace['function']) ? $trace['function'] : 'unknown function';
                    $arguments_name[] = $function . ':' . $line;
                    $args[] = $trace['object'];
                }
            }
        }

        $i = 0;
        foreach ($args as $arg) {
            // Echo name of the variable
            $len = 20;
            $varname = isset($arguments_name[$i]) ? $arguments_name[$i] : null;
            if ($varname) {
                $print('Value for: ' . $varname);
                $len = strlen($varname);
            }
            // For ajax and cli requests, a good old print_r is much better
            if ($isPlain || !function_exists('dump')) {
                $print($arg);
                // Make a nice line between variables for readability
                if (count($args) > 1) {
                    $print(str_repeat('-', $len));
                }
            } else {
                if ($varname && is_string($arg) && strpos($varname, 'sql') !== false) {
                    echo SqlFormatter::format($arg);
                } else {
                    dump($arg);
                }
            }
            $i++;
        }
        if ($doExit) {
            exit();
        }
    }
}

// Add a simple log helper that provides a default priority
if (!function_exists('l')) {
    function l($message, $priority = Logger::DEBUG, $extras = [])
    {
        if (!is_string($message)) {
            $message = json_encode((array) $message);
        }
        if (is_array($priority)) {
            $extras = $priority;
            $priority = Logger::DEBUG;
        }
        Injector::inst()->get(LoggerInterface::class)->log($priority, $message, $extras);
    }
}
