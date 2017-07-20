<?php

namespace LeKoala\DebugBar\Collector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Collects data about the partial cache hits and misses during a SilverStripe request
 */
class PartialCacheCollector extends DataCollector implements Renderable
{
    /**
     * Contains a list of all partial caches found.
     * @var array
     */
    protected static $templateCache = [];

    public function getName()
    {
        return 'partial-cache';
    }

    public function collect()
    {
        $result = self::getTemplateCache();
        return [
            'count' => count($result),
            'calls' => $result
        ];
    }

    public function getWidgets()
    {
        $widgets = [
            'Partial Cache' => [
                'icon' => 'asterisk',
                'widget' => 'PhpDebugBar.Widgets.ConfigWidget',
                'map' => 'partial-cache.calls',
                'default' => '{}'
            ]
        ];
        if (count(self::getTemplateCache()) > 0) {
            $widgets['Partial Cache:badge'] = [
                'map' => 'partial-cache.count',
                'default' => 0
            ];
        }

        return $widgets;
    }

    /**
     * @return array
     */
    public static function getTemplateCache()
    {
        return (self::$templateCache) ?: [];
    }

    /**
     * Adds an item to the templateCache array
     * @param string $key
     * @param array $item
     */
    public static function addTemplateCache($key, $item)
    {
        self::$templateCache[$key] = $item;
    }

    /**
     * @param array $templateCache
     */
    public static function setTemplateCache($templateCache)
    {
        self::$templateCache = $templateCache;
    }
}
