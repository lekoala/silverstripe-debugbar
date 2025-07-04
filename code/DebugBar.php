<?php

namespace LeKoala\DebugBar;

use Exception;
use Monolog\Logger;
use ReflectionObject;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Kernel;
use DebugBar\JavascriptRenderer;
use DebugBar\Storage\FileStorage;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Control\Director;
use SilverStripe\Core\Environment;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\View\Requirements;
use SilverStripe\Control\Controller;
use Symfony\Component\Mailer\Mailer;
use SilverStripe\Control\HTTPRequest;
use DebugBar\DebugBar as BaseDebugBar;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\ConfigLoader;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use DebugBar\DataCollector\MemoryCollector;
use LeKoala\DebugBar\Messages\LogFormatter;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\Core\Manifest\ModuleLoader;
use DebugBar\DataCollector\MessagesCollector;
use LeKoala\DebugBar\Bridge\MonologCollector;
use Symfony\Component\Mailer\MailerInterface;
use LeKoala\DebugBar\Collector\CacheCollector;
use SilverStripe\Core\Manifest\ModuleResource;
use LeKoala\DebugBar\Collector\ConfigCollector;
use LeKoala\DebugBar\Proxy\ConfigManifestProxy;
use LeKoala\DebugBar\Collector\PhpInfoCollector;
use LeKoala\DebugBar\Extension\ProxyDBExtension;
use LeKoala\DebugBar\Collector\DatabaseCollector;
use LeKoala\DebugBar\Collector\TimeDataCollector;
use LeKoala\DebugBar\Proxy\DeltaConfigManifestProxy;
use LeKoala\DebugBar\Collector\PartialCacheCollector;
use LeKoala\DebugBar\Collector\SilverStripeCollector;
use SilverStripe\Config\Collections\DeltaConfigCollection;
use SilverStripe\Config\Collections\CachedConfigCollection;
use LeKoala\DebugBar\Bridge\SymfonyMailer\SymfonyMailerCollector;
use SilverStripe\Security\Permission;

/**
 * A simple helper
 */
class DebugBar
{
    use Configurable;
    use Injectable;

    /**
     * @var BaseDebugBar|false|null
     */
    protected static $debugbar;

    /**
     * @var bool
     */
    public static $bufferingEnabled = false;

    /**
     * @var bool
     */
    public static $suppressJquery = false;

    /**
     * @var JavascriptRenderer|null
     */
    protected static $renderer;

    /**
     * @var bool
     */
    protected static $showQueries = false;

    /**
     * @var HTTPRequest|null
     */
    protected static $request;

    /**
     * @var array<string,array<float>>
     */
    protected static $extraTimes = [];

    /**
     * Get the Debug Bar instance
     * @throws Exception
     * @global array $databaseConfig
     * @return BaseDebugBar|false
     */
    public static function getDebugBar()
    {
        if (self::$debugbar !== null) {
            return self::$debugbar;
        }

        $reasons = self::disabledCriteria();
        if (!empty($reasons)) {
            self::$debugbar = false; // no need to check again
            return false;
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
     * @return BaseDebugBar|null
     */
    public static function initDebugBar()
    {
        // Prevent multiple inits
        if (self::$debugbar) {
            return self::$debugbar;
        }

        self::$debugbar = $debugbar = new BaseDebugBar();

        if (isset($_REQUEST['showqueries']) && Director::isDev()) {
            self::setShowQueries(true);
            unset($_REQUEST['showqueries']);
        }

        $debugbar->addCollector(new PhpInfoCollector());
        $debugbar->addCollector(new TimeDataCollector());
        self::measureExtraTime();
        $debugbar->addCollector(new MemoryCollector());

        // Add config proxy replacing the core config manifest
        if (self::config()->config_collector) {
            /** @var ConfigLoader $configLoader */
            $configLoader = Injector::inst()->get(Kernel::class)->getConfigLoader();
            // There is no getManifests method on ConfigLoader
            $manifests = self::getProtectedValue($configLoader, 'manifests');
            foreach ($manifests as $manifestIdx => $manifest) {
                if ($manifest instanceof CachedConfigCollection) {
                    $manifest = new ConfigManifestProxy($manifest);
                    $manifests[$manifestIdx] = $manifest;
                }
                if ($manifest instanceof DeltaConfigCollection) {
                    $manifest = DeltaConfigManifestProxy::createFromOriginal($manifest);
                    $manifests[$manifestIdx] = $manifest;
                }
            }
            // Don't push as it may change stack order
            self::setProtectedValue($configLoader, 'manifests', $manifests);
        }

        // If enabled and available
        if (self::config()->db_collector && class_exists(\TractorCow\SilverStripeProxyDB\ProxyDBFactory::class)) {
            $debugbar->addCollector(new DatabaseCollector);
        }

        // Add message collector last so other collectors can send messages to the console using it
        $debugbar->addCollector(new MessagesCollector());

        // Aggregate monolog into messages
        $logger = Injector::inst()->get(LoggerInterface::class);
        if ($logger instanceof Logger) {
            $logCollector = new MonologCollector($logger);
            $logCollector->setFormatter(new LogFormatter);
            $debugbar['messages']->aggregate($logCollector);
        }

        // Add some SilverStripe specific infos
        $debugbar->addCollector(new SilverStripeCollector);

        if (self::config()->get('enable_storage')) {
            $debugBarTempFolder = \TEMP_PATH . '/debugbar';
            $debugbar->setStorage($fileStorage = new FileStorage($debugBarTempFolder));
            if (isset($_GET['flush']) && is_dir($debugBarTempFolder)) {
                // FileStorage::clear() is implemented with \DirectoryIterator which throws UnexpectedValueException if dir can not be opened
                $fileStorage->clear();
            }
        }

        if (self::config()->config_collector) {
            // Add the config collector
            $debugbar->addCollector(new ConfigCollector);
        }

        // Cache
        if (self::config()->cache_collector) {
            $debugbar->addCollector($cacheCollector = new CacheCollector);
            $cacheCollector->setShowGet(self::config()->cache_collector_show_get);
        }

        // Partial cache
        if (self::config()->partial_cache_collector) {
            $debugbar->addCollector(new PartialCacheCollector);
        }

        // Email logging
        if (self::config()->email_collector) {
            $mailer = Injector::inst()->get(MailerInterface::class);
            if ($mailer instanceof Mailer) {
                $debugbar->addCollector(new SymfonyMailerCollector);
            }
        }

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

    /**
     * Access a protected property when the api does not allow access
     *
     * @param object $object
     * @param string $property
     * @return mixed
     */
    protected static function getProtectedValue($object, $property)
    {
        $refObject = new ReflectionObject($object);
        $refProperty = $refObject->getProperty($property);
        $refProperty->setAccessible(true);
        return $refProperty->getValue($object);
    }

    /**
     * Set a protected property when the api does not allow access
     *
     * @param object $object
     * @param string $property
     * @param mixed $newValue
     * @return void
     */
    protected static function setProtectedValue($object, $property, $newValue)
    {
        $refObject = new ReflectionObject($object);
        $refProperty = $refObject->getProperty($property);
        $refProperty->setAccessible(true);
        $refProperty->setValue($object, $newValue);
    }

    /**
     * Clear the current instance of DebugBar
     *
     * @return void
     */
    public static function clearDebugBar()
    {
        self::$debugbar = null;
        self::$bufferingEnabled = false;
        self::$renderer = null;
        self::$showQueries = false;
        self::$request = null;
        self::$extraTimes = [];
        ProxyDBExtension::resetQueries();
    }

    /**
     * @return boolean
     */
    public static function getShowQueries()
    {
        return self::$showQueries;
    }

    /**
     * Override default showQueries mode
     *
     * @param boolean $showQueries
     * @return void
     */
    public static function setShowQueries($showQueries)
    {
        self::$showQueries = $showQueries;
    }

    /**
     * Helper to access this module resources
     *
     * @param string $path
     * @return ModuleResource
     */
    public static function moduleResource($path)
    {
        return ModuleLoader::getModule('lekoala/silverstripe-debugbar')->getResource($path);
    }

    /**
     * @param bool $flag
     * @return void
     */
    public static function suppressJquery($flag = true)
    {
        $file = "debugbar/assets/vendor/jquery/dist/jquery.min.js";
        if ($flag) {
            Requirements::block($file);
        } else {
            Requirements::unblock($file);
        }

        self::$suppressJquery = $flag;
    }

    /**
     * Include DebugBar assets using Requirements API
     * This needs to be called before the template is rendered otherwise the calls to the Requirements API are ignored
     *
     * @return bool
     */
    public static function includeRequirements()
    {
        $debugbar = self::getDebugBar();

        if (!$debugbar) {
            return false;
        }

        // Already called
        if (self::$renderer) {
            return false;
        }

        $renderer = $debugbar->getJavascriptRenderer();

        // We don't need the true path since we are going to use Requirements API that appends the BASE_PATH
        $assetsResource = self::moduleResource('assets');
        $renderer->setBasePath($assetsResource->getRelativePath());
        $renderer->setBaseUrl(Director::makeRelative($assetsResource->getURL()));

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
            Requirements::css(self::replaceAssetPath($cssFile));
        }

        foreach ($renderer->getAssets('js') as $jsFile) {
            Requirements::javascript(self::replaceAssetPath($jsFile), [
                'type' => 'application/javascript'
            ]);
        }

        self::$renderer = $renderer;

        return true;
    }

    /**
     * @param string $file
     * @return string
     */
    protected static function replaceAssetPath($file)
    {
        return Director::makeRelative(str_replace('\\', '/', ltrim($file, '/')));
    }

    /**
     * Returns the script to display the DebugBar
     *
     * @return string
     */
    public static function renderDebugBar()
    {
        if (!self::$renderer) {
            return '';
        }

        // If we have any extra time pending, add it
        if (!empty(self::$extraTimes)) {
            foreach (self::$extraTimes as $extraTime => $extraTimeData) {
                self::trackTime($extraTime);
            }
        }

        // Requirements may have been cleared (CMS iframes...) or not set
        $js = Requirements::backend()->getJavascript();
        $debugBarResource = self::moduleResource('assets/debugbar.js');
        $path = $debugBarResource->getRelativePath();

        // Url in getJavascript has a / slash, so fix if necessary
        $path = str_replace("\\", "/", $path);
        if (!array_key_exists($path, $js)) {
            return '';
        }
        $initialize = true;
        if (Director::is_ajax()) {
            $initialize = false;
        }

        // Normally deprecation notices are output in a shutdown function, which runs well after debugbar has rendered.
        // This ensures the deprecation notices which have been noted up to this point are logged out and collected by
        // the MonologCollector.
        Deprecation::outputNotices();

        $script = self::$renderer->render($initialize);

        return $script;
    }

    /**
     * Get all criteria why the DebugBar could be disabled
     *
     * @return array<string>
     */
    public static function disabledCriteria()
    {
        $reasons = [];
        if (!Director::isDev() && !self::allowAllEnvironments()) {
            $reasons[] = 'Not in dev mode';
        }
        if (self::isDisabled()) {
            $reasons[] = 'Disabled by a constant or configuration';
        }
        if (self::vendorNotInstalled()) {
            $reasons[] = 'DebugBar is not installed in vendors';
        }
        if (self::notLocalIp()) {
            $reasons[] = 'Not a local ip';
        }
        if (Director::is_cli()) {
            $reasons[] = 'In CLI mode';
        }
        if (self::isDevUrl()) {
            $reasons[] = 'Dev tools';
        }
        if (self::isAdminUrl() && !self::config()->get('enabled_in_admin')) {
            $reasons[] = 'In admin';
        }
        if (isset($_GET['CMSPreview'])) {
            $reasons[] = 'CMS Preview';
        }
        if (self::isExcludedRoute()) {
            $reasons[] = 'Route excluded';
        }
        if (!self::hasRequiredPermissions()) {
            $reasons[] = 'Not allowed';
        }
        return $reasons;
    }

    /**
     * Determine why DebugBar is disabled
     *
     * Deprecated in favor of disabledCriteria
     *
     * @return string
     */
    public static function whyDisabled()
    {
        $reasons = self::disabledCriteria();
        if (!empty($reasons)) {
            return $reasons[0];
        }
        return "I don't know why";
    }

    /**
     * @return bool
     */
    public static function vendorNotInstalled()
    {
        return !class_exists('DebugBar\\StandardDebugBar');
    }

    /**
     * @return bool
     */
    public static function notLocalIp()
    {
        if (!self::config()->get('check_local_ip')) {
            return false;
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            return !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', '1']);
        }
        return false;
    }

    /**
     * @return bool
     */
    public static function allowAllEnvironments()
    {
        // You will also need to add a debugbar-live config
        if (Environment::getEnv('DEBUGBAR_ALLOW_ALL_ENV')) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public static function isDisabled()
    {
        if (Environment::getEnv('DEBUGBAR_DISABLE') || static::config()->get('disabled')) {
            return true;
        }
        return false;
    }

    /**
     * @return bool
     */
    public static function hasRequiredPermissions()
    {
        if (static::config()->get('user_admin_only')) {
            return Permission::check('ADMIN');
        }
        return true;
    }

    /**
     * @return bool
     */
    public static function isDevUrl()
    {
        return strpos(self::getRequestUrl(), '/dev/') === 0;
    }

    /**
     * @return bool
     */
    public static function isAdminUrl()
    {
        $baseUrl = rtrim(BASE_URL, '/');
        if (class_exists(AdminRootController::class)) {
            $adminUrl = AdminRootController::config()->get('url_base');
        } else {
            $adminUrl = 'admin';
        }

        return strpos(self::getRequestUrl(), $baseUrl . '/' . $adminUrl . '/') === 0;
    }

    /**
     * @return bool
     */
    public static function isExcludedRoute()
    {
        $isExcluded = false;
        $excludedRoutes = self::config()->get('excluded_routes');
        if (!empty($excludedRoutes)) {
            $url = self::getRequestUrl();
            foreach ($excludedRoutes as $excludedRoute) {
                if (strpos($url, (string) $excludedRoute) === 0) {
                    $isExcluded = true;
                    break;
                }
            }
        }
        return $isExcluded;
    }

    /**
     * @return bool
     */
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
     * @return void
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
     * @return void
     */
    public static function setRequest(HTTPRequest $request)
    {
        self::$request = $request;
    }

    /**
     * Get the current request
     *
     * @return HTTPRequest|null
     */
    public static function getRequest()
    {
        if (self::$request) {
            return self::$request;
        }
        // Fall back to trying from the global state
        if (Controller::curr()) {
            return Controller::curr()->getRequest();
        }
        return null;
    }

    /**
     * @return TimeDataCollector|false
     */
    public static function getTimeCollector()
    {
        $debugbar = self::getDebugBar();
        if (!$debugbar) {
            return false;
        }
        //@phpstan-ignore-next-line
        return $debugbar->getCollector('time');
    }

    /**
     * @return MessagesCollector|false
     */
    public static function getMessageCollector()
    {
        $debugbar = self::getDebugBar();
        if (!$debugbar) {
            return false;
        }
        //@phpstan-ignore-next-line
        return  $debugbar->getCollector('messages');
    }

    /**
     * Start/stop time tracking (also before init)
     *
     * @param string $label
     * @return void
     */
    public static function trackTime($label)
    {
        if (!isset(self::$extraTimes[$label])) {
            self::$extraTimes[$label] = [microtime(true)];
        } else {
            self::$extraTimes[$label][] = microtime(true);

            // If we have the debugbar instance, add the measure
            if (self::$debugbar) {
                $timeData = self::getTimeCollector();
                if (!$timeData) {
                    return;
                }
                $values = self::$extraTimes[$label];
                $timeData->addMeasure(
                    $label,
                    $values[0],
                    $values[1]
                );
                unset(self::$extraTimes[$label]);
            }
        }
    }

    /**
     * Close any open extra time record
     *
     * @return void
     */
    public static function closeExtraTime()
    {
        foreach (self::$extraTimes as $label => $values) {
            if (!isset($values[1])) {
                self::$extraTimes[$label][] = microtime(true);
            }
        }
    }

    /**
     * Add extra time to time collector
     * @return void
     */
    public static function measureExtraTime()
    {
        $timeData = self::getTimeCollector();
        if (!$timeData) {
            return;
        }
        foreach (self::$extraTimes as $label => $values) {
            if (!isset($values[1])) {
                continue; // unfinished measure
            }
            $timeData->addMeasure(
                $label,
                $values[0],
                $values[1]
            );
            unset(self::$extraTimes[$label]);
        }
    }
}
