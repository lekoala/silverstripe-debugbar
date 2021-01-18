<?php

namespace LeKoala\DebugBar\Collector;

use SilverStripe\Core\Kernel;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Director;
use DebugBar\DataCollector\Renderable;
use SilverStripe\Core\Injector\Injector;
use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use LeKoala\DebugBar\Proxy\ProxyConfigCollectionInterface;
use SilverStripe\Config\Collections\ConfigCollectionInterface;

/**
 * Collects data about the config usage during a SilverStripe request
 */
class ConfigCollector extends DataCollector implements Renderable, AssetProvider
{
    public function getName()
    {
        return 'config';
    }

    /**
     * @return ConfigCollectionInterface|ProxyConfigCollectionInterface
     */
    public function getConfigManifest()
    {
        $configLoader = Injector::inst()->get(Kernel::class)->getConfigLoader();
        $manifest = $configLoader->getManifest();
        return $manifest;
    }

    public function collect()
    {
        $manifest = $this->getConfigManifest();
        $result = [];
        if (method_exists($manifest, 'getConfigCalls')) {
            $result = $manifest->getConfigCalls();
        }
        return [
            'count' => count($result),
            'calls' => $result
        ];
    }

    public function getWidgets()
    {
        $widgets = [
            'config' => [
                'icon' => 'gear',
                'widget' => 'PhpDebugBar.Widgets.ConfigWidget',
                'map' => 'config.calls',
                'default' => '{}'
            ]
        ];

        $widgets['config:badge'] = [
            'map' => 'config.count',
            'default' => 0
        ];

        return $widgets;
    }

    public function getAssets()
    {
        $name = $this->getName();

        return [
            'base_path' => '/' . DebugBar::moduleResource('javascript')->getRelativePath(),
            'base_url' => Director::makeRelative(DebugBar::moduleResource('javascript')->getURL()),
            'css' => $name . '/widget.css',
            'js' => $name . '/widget.js'
        ];
    }
}
