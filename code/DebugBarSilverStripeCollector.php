<?php

class DebugBarSilverStripeCollector extends DebugBar\DataCollector\DataCollector implements DebugBar\DataCollector\Renderable
{
    protected static $debug = array();

    public function collect()
    {
        $data = array(
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
        );
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
        return array();
    }

    public static function getRequestParameters()
    {
        if (!Controller::has_curr()) {
            return array();
        }
        $ctrl    = Controller::curr();
        $request = $ctrl->getRequest();

        $p = array();
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
        $filtered = array();

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

        preg_match_all("/<p class=\"message warning\">\n(.*?)<\/p>/s", $data,
            $matches);

        if (!empty($matches[1])) {
            self::$debug = $matches[1];
        }
    }

    public function getName()
    {
        return 'silvertripe';
    }

    public function getWidgets()
    {
        $name = $this->getName();

        $widgets = array(
            'user' => array(
                'icon' => 'user',
                'tooltip' => 'Current member',
                "map" => "$name.user",
                "default" => "",
            ),
            "version" => array(
                "icon" => "bullseye",
                "tooltip" => "Version",
                "map" => "$name.version",
                "default" => ""
            ),
            "locale" => array(
                "icon" => "flag",
                "tooltip" => "Current locale",
                "map" => "$name.locale",
                "default" => "",
            ),
            "session" => array(
                "icon" => "archive",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.session",
                "default" => "{}"
            ),
            "cookies" => array(
                "icon" => "asterisk",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.cookies",
                "default" => "{}"
            ),
            "parameters" => array(
                "icon" => "arrow-right",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.parameters",
                "default" => "{}"
            ),
            "config" => array(
                "icon" => "gear",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.config",
                "default" => "{}"
            ),
            "requirements" => array(
                "icon" => "file-o ",
                "widget" => "PhpDebugBar.Widgets.ListWidget",
                "map" => "$name.requirements",
                "default" => "{}"
            ),
        );


        if (!empty(self::$debug)) {
            $widgets["debug"]       = array(
                "icon" => "list-alt",
                "widget" => "PhpDebugBar.Widgets.ListWidget",
                "map" => "$name.debug",
                "default" => "[]"
            );
            $widgets["debug:badge"] = array(
                "map" => "$name.debugcount",
                "default" => "null"
            );
        }

        return $widgets;
    }
}