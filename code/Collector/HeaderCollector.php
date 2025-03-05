<?php

namespace LeKoala\DebugBar\Collector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use SilverStripe\Control\Controller;

class HeaderCollector extends DataCollector implements Renderable, AssetProvider
{
    /**
     * @var \SilverStripe\Control\Controller;
     */
    protected $controller;

    /**
     * HeaderCollector constructor.
     * @param Controller $controller
     */
    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return array
     */
    public function getWidgets()
    {
        return [
            'Headers'       => [
                'icon'    => 'gear',
                'widget'  => 'PhpDebugBar.Widgets.VariableListWidget',
                'map'     => 'Headers.list',
                'default' => '{}'
            ],
            'Headers:badge' => [
                'map'     => 'Headers.count',
                'default' => 0
            ]
        ];
    }

    /**
     * @return array
     */
    public function collect()
    {
        $result = $this->controller->getResponse()->getHeaders();

        foreach ($result as $key => &$value) {
            $value = trim(implode(PHP_EOL, explode('; ', (string) $value)));
            $value = implode(PHP_EOL . ' - ', explode(' ', $value));
        }

        return [
            'count' => count($result),
            'list'  => $result
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Headers';
    }

    public function getAssets()
    {
        return [];
    }
}
