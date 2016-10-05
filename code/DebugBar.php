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
     * @var DebugBar\JavascriptRenderer
     */
    protected static $renderer = null;

    /**
     *
     * @var bool
     */
    protected static $showQueries = false;

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

        if (!Director::isDev() || self::IsDisabled() || self::VendorNotInstalled()
            || self::NotLocalIp() || Director::is_cli() || self::IsDevUrl() || (self::IsAdminUrl()
            && !self::config()->enabled_in_admin)
        ) {
            self::$debugbar = false; // No need to check again
            return;
        }

        // Add the controller extension programmaticaly because it might not be added properly through yml
        Controller::add_extension('DebugBarControllerExtension');

        // Add a custom logger that logs everything under the Messages tab
        SS_Log::add_writer(new DebugBarLogWriter(), SS_Log::DEBUG, '<=');

        self::$debugbar = $debugbar       = new DebugBar\DebugBar();

        if (isset($_REQUEST['showqueries'])) {
            self::setShowQueries(true);
            echo "The queries above have been run before we started DebugBar";
            echo '<hr>' ;
            unset($_REQUEST['showqueries']);
        }

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

            $connector = DB::get_connector();
            if (!self::config()->force_proxy && $connector instanceof PDOConnector) {
                // Use a little bit of magic to replace the pdo instance
                $refObject    = new ReflectionObject($connector);
                $refProperty  = $refObject->getProperty('pdoConnection');
                $refProperty->setAccessible(true);
                $traceablePdo = new DebugBar\DataCollector\PDO\TraceablePDO($refProperty->getValue($connector));
                $refProperty->setValue($connector, $traceablePdo);

                $debugbar->addCollector(new DebugBar\DataCollector\PDO\PDOCollector($traceablePdo));
            } else {
                DB::set_conn($db = new DebugBarDatabaseNewProxy(DB::get_conn()));
                $db->setShowQueries(self::getShowQueries());
                $debugbar->addCollector(new DebugBarDatabaseCollector($db));
            }
        } else {
            if (!DB::getConn()) {
                global $databaseConfig;
                if ($databaseConfig) {
                    DB::connect($databaseConfig);
                }
            }
            DB::setConn($db = new DebugBarDatabaseProxy(DB::getConn()));
            $db->setShowQueries(self::getShowQueries());
            $debugbar->addCollector(new DebugBarDatabaseCollector($db));
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

    public static function getShowQueries()
    {
        return self::$showQueries;
    }

    public static function setShowQueries($showQueries)
    {
        self::$showQueries = $showQueries;
    }

    public static function includeRequirements()
    {
        $debugbar = self::getDebugBar();

        if (!$debugbar) {
            return;
        }

        $renderer = $debugbar->getJavascriptRenderer();

        // We don't need the true path since we are going to use Requirements API that appends the BASE_PATH
        $renderer->setBasePath(DEBUGBAR_DIR.'/assets');
        $renderer->setBaseUrl(DEBUGBAR_DIR.'/assets');

        $renderer->disableVendor('jquery');
        $renderer->setEnableJqueryNoConflict(false);

        if (DebugBar::config()->enable_storage) {
            $renderer->setOpenHandlerUrl('__debugbar');
        }

        foreach ($renderer->getAssets('css') as $cssFile) {
            Requirements::css(ltrim($cssFile, '/'));
        }

        foreach ($renderer->getAssets('js') as $jsFile) {
            Requirements::javascript(ltrim($jsFile, '/'));
        }

        self::$renderer = $renderer;
    }

    public static function renderDebugBar()
    {
        if (!self::$renderer) {
            return;
        }

        // Requirements may have been cleared (CMS iframes...)
        $js = Requirements::backend()->get_javascript();
        if (!in_array('debugbar/assets/debugbar.js', $js)) {
            return;
        }

        $initialize = true;
        if (Director::is_ajax()) {
            $initialize = false;
        }

        $script = self::$renderer->render($initialize);
        return $script;
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
        if (self::IsDisabled()) {
            return 'Disabled by a constant';
        }
        if (self::VendorNotInstalled()) {
            return 'DebugBar is not installed in vendors';
        }
        if (self::NotLocalIp()) {
            return 'Not a local ip';
        }
        if (Director::is_cli()) {
            return 'In CLI mode';
        }
        if (self::IsDevUrl()) {
            return 'Dev tools';
        }
        if (self::IsAdminUrl() && !self::config()->enabled_in_admin) {
            return 'In admin';
        }
        return "I don't know why";
    }

    public static function VendorNotInstalled()
    {
        return !class_exists('DebugBar\\StandardDebugBar');
    }

    public static function NotLocalIp()
    {
        if (!self::config()->check_local_ip) {
            return false;
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'] != '127.0.0.1';
        }
        return false;
    }

    public static function IsDisabled()
    {
        if (defined('DEBUGBAR_DISABLE')) {
            return DEBUGBAR_DISABLE;
        }
        return false;
    }

    public static function IsDevUrl()
    {
        return strpos(self::getRequestUrl(), '/dev/') === 0;
    }

    public static function IsAdminUrl()
    {
        return strpos(self::getRequestUrl(), '/admin/') === 0;
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