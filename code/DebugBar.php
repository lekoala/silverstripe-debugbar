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

        if (!Director::isDev() || !class_exists('DebugBar\\StandardDebugBar') || Director::is_cli() // Don't run in CLI mode
            || strpos(self::getRequestUrl(), '/dev/build') === 0 // Don't run on dev build
        ) {
            self::$debugbar = false; // No need to check again
            return;
        }

        // Add the controller extension programmaticaly because it might not be added properly through yml
        Controller::add_extension('DebugBarControllerExtension');

        // Add a custom logger that logs everything under the Messages tab
        SS_Log::add_writer(new DebugBarLogWriter(), SS_Log::DEBUG, '<=');

        self::$debugbar = $debugbar       = new DebugBar\StandardDebugBar();

        if (!DB::get_conn()) {
            global $databaseConfig;
            if ($databaseConfig) {
                DB::connect($databaseConfig);
            }
        }

        // If we use PDO, we can log the queries
        $connector = DB::get_connector();
        if ($connector instanceof PDOConnector) {
            $refObject    = new ReflectionObject($connector);
            $refProperty  = $refObject->getProperty('pdoConnection');
            $refProperty->setAccessible(true);
            $traceablePdo = new DebugBar\DataCollector\PDO\TraceablePDO($refProperty->getValue($connector));
            $refProperty->setValue($connector, $traceablePdo);
            $debugbar->addCollector(new DebugBar\DataCollector\PDO\PDOCollector($traceablePdo));
        }

        // Add config collector
        $debugbar->addCollector(new DebugBar\DataCollector\ConfigCollector(SiteConfig::current_site_config()->toMap()),
            'SiteConfig');

        // Add some SilverStripe specific infos
        $debugbar->addCollector(new DebugBarSilverStripeCollector());

        if (self::config()->enable_storage) {
            $debugbar->setStorage(new DebugBar\Storage\FileStorage(TEMP_FOLDER.'/debugbar'));
        }

        ob_start(); // We buffer everything until we have called an action

        return $debugbar;
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