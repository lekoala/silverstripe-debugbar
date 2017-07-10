<?php

namespace LeKoala\DebugBar\Collector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use LeKoala\DebugBar\DebugBar;
use LeKoala\DebugBar\Proxy\TemplateParserProxy;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Session;
use SilverStripe\Core\Convert;
use SilverStripe\i18n\i18n;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

class SilverStripeCollector extends DataCollector implements Renderable, AssetProvider
{
    protected static $debug = [];
    protected static $controller;

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
            'templates' => self::getTemplateData(),
        );
        return $data;
    }

    /**
     * Returns the names of all the templates rendered.
     *
     * @return array
     */
    public static function getTemplateData()
    {
        if (TemplateParserProxy::getCached()) {
            return array(
                'templates' => array(
                    'NOTE: Rendered templates will not display when cached, please flush to view the list.'
                ),
                'count' => '-'
            );
        }

        $templates = TemplateParserProxy::getTemplatesUsed();
        return array(
            'templates' => $templates,
            'count' => count($templates)
        );
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
            $output[] = $asset . ': ' . Convert::raw2json($specs);
        }
        return $output;
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
            "/<p class=\"message warning\">\n(.*?)<\/p>/s", $data, $matches
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

        $userIcon = 'user-times';
        $userText = 'Not logged in';
        if (Member::currentUserID()) {
            $member = Member::currentUser();
            $memberTag = $member->getTitle() . ' (#' . $member->ID . ')';

            $userIcon = 'user';
            $userText = 'Logged in as ' . $memberTag;

            // Masquerade integration
            if (DebugBar::getRequest()->getSession()->get('Masquerade.Old.loggedInAs')) {
                $userIcon = 'user-secret';
                $userText = 'Masquerading as member ' . $memberTag;
            }
        }

        $widgets = array(
            'user' => array(
                'icon' => $userIcon,
                'tooltip' => $userText,
                "default" => "",
            ),
            "version" => array(
                "icon" => "desktop",
                "tooltip" => LeftAndMain::create()->CMSVersion(),
                "default" => ""
            ),
            "locale" => array(
                "icon" => "globe",
                "tooltip" => i18n::get_locale(),
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
            'templates' => array(
                'icon' => 'edit',
                'widget' => 'PhpDebugBar.Widgets.ListWidget',
                'map' => "$name.templates.templates",
                'default' => '{}'
            ),
            'templates:badge' => array(
                'map' => "$name.templates.count",
                'default' => 0
            )
        );

        if (!empty(self::$debug)) {
            $widgets["debug"] = array(
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

    /**
     * @return array
     */
    public function getAssets()
    {
        return array(
            'base_path' => '/' . DEBUGBAR_DIR . '/javascript',
            'base_url' => DEBUGBAR_DIR . '/javascript',
            'css' => [],
            'js' => 'widgets.js',
        );
    }
}
