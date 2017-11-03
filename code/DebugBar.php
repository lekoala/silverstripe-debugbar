<?php

namespace LeKoala\DebugBar;

use DebugBar\Bridge\MonologCollector;
use DebugBar\DebugBar as BaseDebugBar;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\PDO\PDOCollector;
use DebugBar\DataCollector\PDO\TraceablePDO;
use DebugBar\DataCollector\PhpInfoCollector;
use DebugBar\Storage\FileStorage;
use Exception;
use LeKoala\DebugBar\Collector\ConfigCollector;
use LeKoala\DebugBar\Collector\DatabaseCollector;
use LeKoala\DebugBar\Collector\PartialCacheCollector;
use LeKoala\DebugBar\Collector\SilverStripeCollector;
use LeKoala\DebugBar\Collector\TimeDataCollector;
use LeKoala\DebugBar\Messages\LogFormatter;
use LeKoala\DebugBar\Proxy\ConfigManifestProxy;
use LeKoala\DebugBar\Proxy\DatabaseProxy;
use Psr\Log\LoggerInterface;
use Monolog\Logger;
use ReflectionObject;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\Config\Collections\CachedConfigCollection;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\ORM\Connect\PDOConnector;
use SilverStripe\ORM\DB;
use SilverStripe\View\Requirements;

/**
 * A simple helper
 */
class DebugBar
{
    use Configurable;
    use Injectable;

    /**
     * @var DebugBar\DebugBar
     */
    protected static $debugbar;

    /**
     * @var bool
     */
    public static $bufferingEnabled = false;

    /**
     * @var DebugBar\JavascriptRenderer
     */
    protected static $renderer;

    /**
     * @var bool
     */
    protected static $showQueries = false;

    /**
     * @var HTTPRequest
     */
    protected static $request;

    /**
     * Get the Debug Bar instance
     * @return \DebugBar\StandardDebugBar
     * @throws Exception
     * @global array $databaseConfig
     */
    public static function getDebugBar()
    {
        if (self::$debugbar !== null) {
            return self::$debugbar;
        }

        if (!Director::isDev() || self::isDisabled() || self::vendorNotInstalled() ||
            self::notLocalIp() || Director::is_cli() || self::isDevUrl() ||
            (self::isAdminUrl() && !self::config()->get('enabled_in_admin'))
        ) {
            self::$debugbar = false; // no need to check again
            return;
        }

        self::initDebugBar();

        if (!self::$debugbar) {
            throw new Exception("Failed to initialize the DebugBar");
        }

        return self::$debugbar;
    }

    /**
     * Init the debugbar instance
     *
     * @global array $databaseConfig
     * @return DebugBar\StandardDebugBar
     */
    public static function initDebugBar()
    {
        // Prevent multiple inits
        if (self::$debugbar) {
            return self::$debugbar;
        }

        self::$debugbar = $debugbar = new BaseDebugBar;

        if (isset($_REQUEST['showqueries'])) {
            self::setShowQueries(true);
            echo "The queries above have been run before we started DebugBar";
            echo '<hr>';
            unset($_REQUEST['showqueries']);
        }

        $debugbar->addCollector(new PhpInfoCollector);
        $debugbar->addCollector(new TimeDataCollector);
        $debugbar->addCollector(new MemoryCollector);

        // Add config proxy replacing the core config manifest
        /** @var SilverStripe\Core\Config\ConfigLoader $configLoader */
        $configLoader = Injector::inst()->get(Kernel::class)->getConfigLoader();
        $configManifest = false;
        while ($configLoader->hasManifest()) {
            $eachManifest = $configLoader->popManifest();
            if ($eachManifest instanceof CachedConfigCollection) {
                $configManifest = $eachManifest;
                break;
            }
        }
        if ($configManifest) {
            $configProxy = new ConfigManifestProxy($configManifest);
            $configLoader->pushManifest($configProxy);
        }

        $connector = DB::get_connector();
        if (!self::config()->get('force_proxy') && $connector instanceof PDOConnector) {
            // Use a little bit of magic to replace the pdo instance
            $refObject = new ReflectionObject($connector);
            $refProperty = $refObject->getProperty('pdoConnection');
            $refProperty->setAccessible(true);
            $traceablePdo = new TraceablePDO($refProperty->getValue($connector));
            $refProperty->setValue($connector, $traceablePdo);

            $debugbar->addCollector(new PDOCollector($traceablePdo));
        } else {
            DB::set_conn($db = new DatabaseProxy(DB::get_conn()));
            $db->setShowQueries(self::getShowQueries());
            $debugbar->addCollector(new DatabaseCollector);
        }

        // Add message collector last so other collectors can send messages to the console using it
        $logger = Injector::inst()->get(LoggerInterface::class);
        $logCollector = new MonologCollector($logger, Logger::DEBUG, true, 'messages');
        $logCollector->setFormatter(new LogFormatter);

        $debugbar->addCollector($logCollector);

        // Add some SilverStripe specific infos
        $debugbar->addCollector(new SilverStripeCollector);

        if (self::config()->get('enable_storage')) {
            $debugbar->setStorage(new FileStorage(TEMP_FOLDER . '/debugbar'));
        }

        if ($configManifest) {
            // Add the config collector
            $debugbar->addCollector(new ConfigCollector);
        }
        $debugbar->addCollector(new PartialCacheCollector);

        // Since we buffer everything, why not enable all dev options ?
        if (self::config()->get('auto_debug')) {
            $_REQUEST['debug'] = true;
            $_REQUEST['debug_request'] = true;
        }

        if (isset($_REQUEST['debug']) || isset($_REQUEST['debug_request'])) {
            self::$bufferingEnabled = true;
            ob_start(); // We buffer everything until we have called an action
        }

        return $debugbar;
    }

    public static function clearDebugBar()
    {
        self::$debugbar = null;
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

        // Already called
        if (self::$renderer) {
            return;
        }

        $renderer = $debugbar->getJavascriptRenderer();

        // We don't need the true path since we are going to use Requirements API that appends the BASE_PATH
        $renderer->setBasePath(DEBUGBAR_DIR . '/assets');
        $renderer->setBaseUrl(DEBUGBAR_DIR . '/assets');

        $includeJquery = self::config()->get('include_jquery');
        // In CMS, jQuery is already included
        if (self::isAdminController()) {
            $includeJquery = false;
        }
        // If jQuery is already included, set to false
        $js = Requirements::backend()->getJavascript();
        foreach ($js as $url => $args) {
            $name = basename($url);
            if ($name == 'jquery.js' || $name == 'jquery.min.js') {
                $includeJquery = false;
                break;
            }
        }

        if ($includeJquery) {
            $renderer->setEnableJqueryNoConflict(true);
        } else {
            $renderer->disableVendor('jquery');
            $renderer->setEnableJqueryNoConflict(false);
        }

        if (DebugBar::config()->get('enable_storage')) {
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

        // Requirements may have been cleared (CMS iframes...) or not set (Security...)
        $js = Requirements::backend()->getJavascript();
        if (!array_key_exists('debugbar/assets/debugbar.js', $js)) {
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
    public static function whyDisabled()
    {
        if (!Director::isDev()) {
            return 'Not in dev mode';
        }
        if (self::isDisabled()) {
            return 'Disabled by a constant or configuration';
        }
        if (self::vendorNotInstalled()) {
            return 'DebugBar is not installed in vendors';
        }
        if (self::notLocalIp()) {
            return 'Not a local ip';
        }
        if (Director::is_cli()) {
            return 'In CLI mode';
        }
        if (self::isDevUrl()) {
            return 'Dev tools';
        }
        if (self::isAdminUrl() && !self::config()->get('enabled_in_admin')) {
            return 'In admin';
        }
        return "I don't know why";
    }

    public static function vendorNotInstalled()
    {
        return !class_exists('DebugBar\\StandardDebugBar');
    }

    public static function notLocalIp()
    {
        if (!self::config()->get('check_local_ip')) {
            return false;
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return !in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1', '1'));
        }
        return false;
    }

    public static function isDisabled()
    {
        if (getenv('DEBUGBAR_DISABLE') || static::config()->get('disabled')) {
            return true;
        }
        return false;
    }

    public static function isDevUrl()
    {
        return strpos(self::getRequestUrl(), '/dev/') === 0;
    }

    public static function isAdminUrl()
    {
        $baseUrl = rtrim(BASE_URL, '/');
        $adminUrl = AdminRootController::config()->get('url_base');

        return strpos(self::getRequestUrl(), $baseUrl . '/' . $adminUrl . '/') === 0;
    }

    public static function isAdminController()
    {
        if (Controller::curr()) {
            return Controller::curr() instanceof LeftAndMain;
        }
        return self::isAdminUrl();
    }

    /**
     * Avoid triggering data collection for open handler
     *
     * @return boolean
     */
    public static function isDebugBarRequest()
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
        if (self::getDebugBar() && !self::isDebugBarRequest()) {
            $callback(self::getDebugBar());
        }
    }

    /**
     * Set the current request. Is provided by the DebugBarMiddleware.
     *
     * @param HTTPRequest $request
     */
    public static function setRequest(HTTPRequest $request)
    {
        self::$request = $request;
    }

    /**
     * Get the current request
     *
     * @return HTTPRequest
     */
    public static function getRequest()
    {
        if (self::$request) {
            return self::$request;
        }
        // Fall back to trying from the global state
        if (Controller::has_curr()) {
            return Controller::curr()->getRequest();
        }
    }
}
