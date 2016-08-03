<?php

/**
 * A simple helper
 */
class DebugBar extends Object
{
    /**
     * @var DebugBar\StandardDebugBar
     */
    protected static $debugbar = null;

    /**
     *
     * @var bool
     */
    public static $bufferingEnabled = false;

    /**
     * Get the Debug Bar instance
     *
     * @global array $databaseConfig
     * @return DebugBar\StandardDebugBar
     */
    public static function getDebugBar()
    {
        if (self::$debugbar !== null) {
            return self::$debugbar;
        }

        if (!Director::isDev() || !class_exists('DebugBar\\StandardDebugBar') // Is not installed
            || Director::is_cli() // Don't run in CLI mode
            || strpos(self::getRequestUrl(), '/dev/') === 0 // Don't run on dev tools
            || strpos(self::getRequestUrl(), '/admin/') === 0 // Don't run in admin
        ) {
            self::$debugbar = false; // No need to check again
            return;
        }

        // Add the controller extension programmaticaly because it might not be added properly through yml
        Controller::add_extension('DebugBarControllerExtension');

        // Add a custom logger that logs everything under the Messages tab
        SS_Log::add_writer(new DebugBarLogWriter(), SS_Log::DEBUG, '<=');

        self::$debugbar = $debugbar       = new DebugBar\DebugBar();

        $debugbar->addCollector(new DebugBar\DataCollector\PhpInfoCollector());
        $debugbar->addCollector(new DebugBar\DataCollector\MessagesCollector());
        $debugbar->addCollector(new DebugBar\DataCollector\TimeDataCollector());
        $debugbar->addCollector(new DebugBar\DataCollector\MemoryCollector());

        // On 3.1, PDO does not exist
        if (method_exists('DB', 'get_conn')) {
            if (!DB::get_conn()) {
                global $databaseConfig;
                if ($databaseConfig) {
                    DB::connect($databaseConfig);
                }
            }

            // If we use PDO, we can log the queries
            $connector = DB::get_connector();
            if ($connector instanceof PDOConnector) {
                // Use a little bit of magic to replace the pdo instance
                $refObject    = new ReflectionObject($connector);
                $refProperty  = $refObject->getProperty('pdoConnection');
                $refProperty->setAccessible(true);
                $traceablePdo = new DebugBar\DataCollector\PDO\TraceablePDO($refProperty->getValue($connector));
                $refProperty->setValue($connector, $traceablePdo);

                $debugbar->addCollector(new DebugBar\DataCollector\PDO\PDOCollector($traceablePdo));
            } else {
                DB::set_conn(new DebugBarDatabaseProxy(DB::get_conn()));
                $debugbar->addCollector(new DebugBarDatabaseCollector);
            }
        } else {
            if (!DB::getConn()) {
                global $databaseConfig;
                if ($databaseConfig) {
                    DB::connect($databaseConfig);
                }
            }
            DB::setConn(new DebugBarDatabaseProxy(DB::getConn()));
            $debugbar->addCollector(new DebugBarDatabaseCollector);
        }

        // Add some SilverStripe specific infos
        $debugbar->addCollector(new DebugBarSilverStripeCollector());

        if (self::config()->enable_storage) {
            $debugbar->setStorage(new DebugBar\Storage\FileStorage(TEMP_FOLDER.'/debugbar'));
        }

        // Since we buffer everything, why not enable all dev options ?
        if (self::config()->auto_debug) {
            $_REQUEST['debug']         = true;
            $_REQUEST['debug_request'] = true;
        }

        if (isset($_REQUEST['debug']) || isset($_REQUEST['debug_request'])) {
            self::$bufferingEnabled = true;
            ob_start(); // We buffer everything until we have called an action
        }

        return $debugbar;
    }

    /**
     * Determine why DebugBar is disabled
     * 
     * @return string
     */
    public static function WhyDisabled()
    {
        if (!Director::isDev()) {
            return 'Not in dev mode';
        }
        if (!class_exists('DebugBar\\StandardDebugBar')) {
            return 'DebugBar is not installed';
        }
        if (Director::is_cli()) {
            return 'In CLI mode';
        }
        if (strpos(self::getRequestUrl(), '/dev/') === 0) {
            return 'Dev tools';
        }
        if (strpos(self::getRequestUrl(), '/admin/') === 0) {
            return 'In admin';
        }
        return "I don't know why";
    }

    /**
     * Avoid triggering data collection for open handler
     * 
     * @return boolean
     */
    public static function IsDebugBarRequest()
    {
        if ($url = self::getRequestUrl()) {
            return strpos($url, '/__debugbar') === 0;
        }
        return true;
    }

    /**
     * Get request url
     * 
     * @return string
     */
    public static function getRequestUrl()
    {
        if (isset($_REQUEST['url'])) {
            return $_REQUEST['url'];
        }
        if (isset($_SERVER['REQUEST_URI'])) {
            return $_SERVER['REQUEST_URI'];
        }
        return '';
    }

    /**
     * Helper to make code cleaner
     *
     * @param callable $callback
     */
    public static function withDebugBar($callback)
    {
        if (self::getDebugBar() && !self::IsDebugBarRequest()) {
            $callback(self::getDebugBar());
        }
    }
}