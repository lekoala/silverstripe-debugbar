<?php

namespace LeKoala\DebugBar\Collector;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Director;
use DebugBar\DataCollector\Renderable;
use LeKoala\DebugBar\Proxy\CacheProxy;
use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;

/**
 * Collects data about the cache
 */
class CacheCollector extends DataCollector implements Renderable, AssetProvider
{
    public function getName()
    {
        return 'cache';
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
        $result = CacheProxy::getData();


        $keys = [];
        foreach ($result as $k => $v) {
            $val = $v['value'];
            if (!is_string($val)) {
                $val = json_encode($val);
            }
            if (strlen($val) > 150) {
                $val = substr($val, 0, 150) . "...";
            }
            if (!empty($v['ttl'])) {
                $val .= " - TTL: " . $v['ttl'];
            }
            $keys[$k] = $val;
        }

        return [
            'count' => count($result),
            'keys' => $keys
        ];
    }

    public function getWidgets()
    {
        $widgets = [
            'Cache' => [
                'icon' => 'puzzle-piece',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'cache.keys',
                'default' => '{}'
            ]
        ];
        $widgets['Cache:badge'] = [
            'map' => 'cache.count',
            'default' => 0
        ];

        return $widgets;
    }
}
