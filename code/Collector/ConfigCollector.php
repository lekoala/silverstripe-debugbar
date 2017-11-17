<?php

namespace LeKoala\DebugBar\Collector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use SilverStripe\Control\Director;
use SilverStripe\Core\Manifest\ModuleLoader;
use LeKoala\DebugBar\Proxy\ConfigManifestProxy;

/**
 * Collects data about the config usage during a SilverStripe request
 */
class ConfigCollector extends DataCollector implements Renderable, AssetProvider
{
    public function getName()
    {
        return 'config';
    }

    public function collect()
    {
        $result = ConfigManifestProxy::getConfigCalls();
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

        if (count(ConfigManifestProxy::getConfigCalls()) > 0) {
            $widgets['config:badge'] = [
                'map' => 'config.count',
                'default' => 0
            ];
        }

        return $widgets;
    }

    public function getAssets()
    {
        $name = $this->getName();

        return [
            'base_path' => '/' . ModuleLoader::getModule('lekoala/silverstripe-debugbar')->getResource('javascript')->getRelativePath(),
            'base_url' => Director::makeRelative(ModuleLoader::getModule('lekoala/silverstripe-debugbar')->getResource('javascript')->getURL()),
            'css' => $name . '/widget.css',
            'js' => $name . '/widget.js'
        ];
    }
}
