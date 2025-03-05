<?php

namespace LeKoala\DebugBar\Collector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use LeKoala\DebugBar\DebugBar;
use LeKoala\DebugBar\Proxy\SSViewerProxy;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

class SilverStripeCollector extends DataCollector implements Renderable, AssetProvider
{
    protected static $debug = [];
    protected static $controller;

    public function collect()
    {
        $data = [
            'debugcount' => count(self::$debug),
            'debug' => self::$debug,
            'session' => self::getSessionData(),
            'config' => self::getConfigData(),
            'locale' => i18n::get_locale(),
            'version' => class_exists(LeftAndMain::class) ? LeftAndMain::create()->CMSVersion() : 'unknown',
            'cookies' => self::getCookieData(),
            'parameters' => self::getRequestParameters(),
            'requirements' => self::getRequirementsData(),
            'user' => Security::getCurrentUser() ? Security::getCurrentUser()->Title : 'Not logged in',
            'templates' => self::getTemplateData(),
            'middlewares' => self::getMiddlewares(),
        ];
        return $data;
    }

    /**
     * Get all middlewares executed on this request
     *
     * @return array
     */
    public static function getMiddlewares()
    {
        $middlewares = Director::singleton()->getMiddlewares();
        if (!$middlewares) {
            return [
                'list' => [],
                'count' => 0,
            ];
        }
        return [
            'list' => array_keys($middlewares),
            'count' => count($middlewares)
        ];
    }

    /**
     * Returns the names of all the templates rendered.
     *
     * @return array
     */
    public static function getTemplateData()
    {
        $templates = SSViewerProxy::getTemplatesUsed();
        return [
            'templates' => $templates,
            'count' => count($templates)
        ];
    }

    public static function getRequirementsData()
    {
        $backend = Requirements::backend();

        $requirements = array_merge(
            $backend->getCSS(),
            $backend->getJavascript()
        );

        $output = [];
        foreach ($requirements as $asset => $specs) {
            // Get the filename only
            $fileNames = explode('/', $asset);
            $fileName = end($fileNames);
            $specs['href'] = $asset;
            $output[$fileName] = json_encode($specs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return [
            'list' => $output,
            'count' => count($requirements)
        ];
    }

    public static function getRequestParameters()
    {
        if (!self::$controller) {
            return [];
        }
        $request = self::$controller->getRequest();

        $p = [];
        foreach ($request->getVars() as $k => $v) {
            $p["GET - $k"] = $v;
        }
        foreach ($request->postVars() as $k => $v) {
            $p["POST - $k"] = $v;
        }
        foreach ($request->params() as $k => $v) {
            $p["ROUTE - $k"] = $v;
        }
        return $p;
    }

    public static function getCookieData()
    {
        return Cookie::get_all();
    }

    public static function getSessionData()
    {
        $data = DebugBar::getRequest()->getSession()->getAll();

        if (empty($data)) {
            return [];
        }

        $filtered = [];

        // Filter not useful data
        foreach ($data as $k => $v) {
            if (strpos($k, 'gf_') === 0) {
                continue;
            }
            if ($k === 'PHPDEBUGBAR_STACK_DATA') {
                continue;
            }
            if (is_array($v)) {
                $v = json_encode($v, JSON_PRETTY_PRINT);
            }
            $filtered[$k] = $v;
        }
        return $filtered;
    }

    public static function getConfigData()
    {
        if (!class_exists(SiteConfig::class)) {
            return [];
        }
        return SiteConfig::current_site_config()->toMap();
    }

    /**
     * This method will try to matches all messages into a proper array
     *
     * @param string $data
     */
    public static function setDebugData($data)
    {
        $matches = null;

        preg_match_all("/<p class=\"message warning\">\n(.*?)<\/p>/s", $data, $matches);

        if (!empty($matches[1])) {
            self::$debug = $matches[1];
        }
    }

    /**
     * @param Controller $controller
     * @return $this
     */
    public function setController($controller)
    {
        self::$controller = $controller;
        return $this;
    }

    /**
     * @return Controller
     */
    public function getController()
    {
        return self::$controller;
    }

    public function getName()
    {
        return 'silverstripe';
    }

    public function getWidgets()
    {
        $name = $this->getName();

        $userIcon = 'user-times';
        $userText = 'Not logged in';
        if ($member = Security::getCurrentUser()) {
            $memberTag = $member->getTitle() . ' (#' . $member->ID . ')';

            $userIcon = 'user';
            $userText = 'Logged in as ' . $memberTag;

            // TODO: upgrade to newer version of the module
            // Masquerade integration
            if (DebugBar::getRequest()->getSession()->get('Masquerade.Old.loggedInAs')) {
                $userIcon = 'user-secret';
                $userText = 'Masquerading as member ' . $memberTag;
            }
        }

        $widgets = [
            'user' => [
                'icon' => $userIcon,
                'tooltip' => $userText,
                "default" => "",
            ],
            "version" => [
                "icon" => "hashtag",
                "tooltip" => class_exists(LeftAndMain::class) ? LeftAndMain::create()->CMSVersion() : 'unknown',
                "default" => ""
            ],
            "locale" => [
                "icon" => "globe",
                "tooltip" => i18n::get_locale(),
                "default" => "",
            ],
            "session" => [
                "icon" => "archive",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.session",
                "default" => "{}"
            ],
            "cookies" => [
                "icon" => "asterisk",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.cookies",
                "default" => "{}"
            ],
            "parameters" => [
                "icon" => "arrow-right",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.parameters",
                "default" => "{}"
            ],
            "requirements" => [
                "icon"    => "file-text-o",
                "widget"  => "PhpDebugBar.Widgets.VariableListWidget",
                "map"     => "$name.requirements.list",
                "default" => "{}"
            ],
            "requirements:badge" => [
                "map"     => "$name.requirements.count",
                "default" => 0
            ],
            "middlewares" => [
                "icon" => "file-text-o",
                "widget" => "PhpDebugBar.Widgets.ListWidget",
                "map" => "$name.middlewares.list",
                "default" => "{}"
            ],
            "middlewares:badge" => [
                "map" => "$name.middlewares.count",
                "default" => 0
            ],
            'templates' => [
                'icon' => 'file-code-o',
                'widget' => 'PhpDebugBar.Widgets.ListWidget',
                'map' => "$name.templates.templates",
                'default' => '{}'
            ],
            'templates:badge' => [
                'map' => "$name.templates.count",
                'default' => 0
            ]
        ];

        if (!empty(static::getConfigData())) {
            $widgets["SiteConfig"] = [
                "icon" => "sliders",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.config",
                "default" => "{}"
            ];
        }

        if (!empty(self::$debug)) {
            $widgets["debug"] = [
                "icon" => "list-alt",
                "widget" => "PhpDebugBar.Widgets.ListWidget",
                "map" => "$name.debug",
                "default" => "[]"
            ];
            $widgets["debug:badge"] = [
                "map" => "$name.debugcount",
                "default" => "null"
            ];
        }

        return $widgets;
    }

    /**
     * @return array
     */
    public function getAssets()
    {
        return [
            'base_path' => '/' . DebugBar::moduleResource('javascript')->getRelativePath(),
            'base_url' => Director::makeRelative(DebugBar::moduleResource('javascript')->getURL()),
            'css' => [],
            'js' => 'widgets.js',
        ];
    }
}
