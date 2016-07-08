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
        );
        return $data;
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
            if($k === 'PHPDEBUGBAR_STACK_DATA') {
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
        $data    = preg_match_all("/<p class=\"message warning\">\n(.*?)<\/p>/s",
            $data, $matches);
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
        return array(
            "version" => [
                "icon" => "bullseye",
                "tooltip" => "Version",
                "map" => "$name.version",
                "default" => ""
            ],
            "locale" => [
                "icon" => "flag",
                "tooltip" => "Current locale",
                "map" => "$name.locale",
                "default" => "",
            ],
            "debug" => array(
                "icon" => "list-alt",
                "widget" => "PhpDebugBar.Widgets.ListWidget",
                "map" => "$name.debug",
                "default" => "[]"
            ),
            "debug:badge" => array(
                "map" => "$name.debugcount",
                "default" => "null"
            ),
            "session" => array(
                "icon" => "archive",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.session",
                "default" => "{}"
            ),
            "config" => array(
                "icon" => "gear",
                "widget" => "PhpDebugBar.Widgets.VariableListWidget",
                "map" => "$name.config",
                "default" => "{}"
            ),
        );
    }
}