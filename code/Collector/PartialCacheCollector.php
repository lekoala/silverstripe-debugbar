<?php

namespace LeKoala\DebugBar\Collector;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Director;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\AssetProvider;

/**
 * Collects data about the partial cache hits and misses during a SilverStripe request
 */
class PartialCacheCollector extends DataCollector implements Renderable, AssetProvider
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

    public function getAssets()
    {
        // This depends on ConfigCollector assets
        return [
            'base_path' => '/' . DebugBar::moduleResource('javascript')->getRelativePath(),
            'base_url' => Director::makeRelative(DebugBar::moduleResource('javascript')->getURL()),
            'css' => 'config/widget.css',
            'js' => 'config/widget.js'
        ];
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
