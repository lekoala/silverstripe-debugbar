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
    protected $showGet = false;

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
        $showGet = $this->showGet || isset($_REQUEST['debug_cacheshowget']);
        foreach ($result as $k => $v) {
            $type = $v['type'] ?? null;
            if (!$showGet && $type == "get") {
                continue;
            }
            $val = $v['value'];
            if (!is_string($val)) {
                $val = json_encode($val);
            }
            // Long values are trimmed by DebugBar js
            if (!empty($v['ttl'])) {
                $val .= " - TTL: " . $v['ttl'];
            }
            if (!empty($v['caller'])) {
                $val .= " - (" . $v['caller'] . ")";
            }
            if ($type == 'set' && $showGet) {
                $val = "SET - " . $val;
            }
            $keys[$v['key']] = $val;
        }

        return [
            'count' => count($keys),
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

    /**
     * Get the value of showGet
     */
    public function getShowGet()
    {
        return $this->showGet;
    }

    /**
     * Set the value of showGet
     *
     * @param bool $showGet
     */
    public function setShowGet($showGet)
    {
        $this->showGet = $showGet;
        return $this;
    }
}
