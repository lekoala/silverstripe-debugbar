<?php

class DebugBarSilverStripeCollector extends DebugBar\DataCollector\DataCollector implements DebugBar\DataCollector\Renderable
{
    protected static $debug = array();

    public function collect()
    {
        return array(
            'count' => count(self::$debug),
            'debug' => self::$debug
        );
    }

    /**
     * This method will try to matches all messages into a proper array
     *
     * @param string $data
     */
    public static function setDebugBar($data)
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
        return 'silverstripe';
    }

    public function getWidgets()
    {
        $name = $this->getName();
        return array(
            "$name" => array(
                "icon" => "tags",
                "widget" => "PhpDebugBar.Widgets.ListWidget",
                "map" => "silverstripe.debug",
                "default" => "[]"
            ),
            "$name:badge" => array(
                "map" => "$name.count",
                "default" => "null"
            )
        );
    }
}