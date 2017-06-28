<?php

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class DebugBarSilverStripeCollector extends DataCollector implements
    Renderable,
    AssetProvider
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
            'version' => LeftAndMain::create()->CMSVersion(),
            'cookies' => self::getCookieData(),
            'parameters' => self::getRequestParameters(),
            'requirements' => self::getRequirementsData(),
            'user' => Member::currentUserID() ? Member::currentUser()->Title : 'Not logged in',
        ];
        return $data;
    }

    public static function getRequirementsData()
    {
        ob_start();
        Requirements::debug();
        $requirements = ob_get_clean();

        $matches = null;

        preg_match_all("/<li>(.*?)<\/li>/s", $requirements, $matches);

        if (!empty($matches[1])) {
            return $matches[1];
        }
        return [];
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
        // On 3.1, Cookie::get_all does not exist
        if (!method_exists('Cookie', 'get_all')) {
            return $_COOKIE;
        }
        return Cookie::get_all();
    }

    public static function getSessionData()
    {
        $data     = Session::get_all();
        $filtered = [];

        // Filter not useful data
        foreach ($data as $k => $v) {
            if (strpos($k, 'gf_') === 0) {
                continue;
            }
            if ($k === 'PHPDEBUGBAR_STACK_DATA') {
                continue;
            }
            $filtered[$k] = $v;
        }
        return $filtered;
    }

    public static function getConfigData()
    {
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

        preg_match_all(
            "/<p class=\"message warning\">\n(.*?)<\/p>/s",
            $data,
            $matches
        );

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

        $userIcon = 'user';
        $userText = 'Current member';
        if (Session::get('Masquerade.Old.loggedInAs')) {
            $userIcon = 'user-secret';
            $userText = 'Masquerading as member';
        }

        $widgets = [
            'user' => [
                'icon' => $userIcon,
                'tooltip' => $userText,
                "default" => "",
            ],
            "version" => [
                "icon" => "desktop",
                "tooltip" => LeftAndMain::create()->CMSVersion(),
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
            "config" => [
                "icon" => "gear",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.config",
                "default" => "{}"
            ],
            "requirements" => [
                "icon" => "file-o ",
                "widget" => "PhpDebugBar.Widgets.ListWidget",
                "map" => "$name.requirements",
                "default" => "{}"
            ],
        ];


        if (!empty(self::$debug)) {
            $widgets["debug"]       = [
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
            'base_path' => '/'.DEBUGBAR_DIR.'/javascript',
            'base_url' => DEBUGBAR_DIR.'/javascript',
            'css' => [],
            'js' => 'widgets.js',
        ];
    }
}
