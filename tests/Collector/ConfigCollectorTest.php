<?php

namespace LeKoala\DebugBar\Test\Collector;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\View\SSViewer;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use LeKoala\DebugBar\Collector\ConfigCollector;
use LeKoala\DebugBar\Proxy\ProxyConfigCollectionInterface;

class ConfigCollectorTest extends SapphireTest
{
    /**
     * @var ConfigCollector
     */
    private $collector;

    protected function setUp()
    {
        parent::setUp();

        // We need to init DebugBar in order to get our proxied manifests
        DebugBar::clearDebugBar();
        DebugBar::initDebugBar();
        $this->collector = new ConfigCollector();
    }

    public function testGetName()
    {
        $this->assertNotEmpty($this->collector->getName());
    }

    public function testManifestIsProxied()
    {
        $manifest = $this->collector->getConfigManifest();
        $this->assertInstanceOf(ProxyConfigCollectionInterface::class, $manifest);
    }

    public function testGetCallsAreCaptured()
    {
        $manifest = $this->collector->getConfigManifest();

        Config::inst()->get(SSViewer::class, 'themes');
        $result = $manifest->getConfigCalls();

        // Note : depending on class hierarchy, another class can be stored instead of the one being called
        $this->assertArrayHasKey(SSViewer::class, $result, "Available keys are : " . implode(",", array_keys($result)));
        $this->assertEquals(1, $result[SSViewer::class]['themes']['calls']);

        // Make 3 more calls, it should make 4
        Config::inst()->get(SSViewer::class, 'themes');
        Config::inst()->get(SSViewer::class, 'themes');
        Config::inst()->get(SSViewer::class, 'themes');
        $result = $manifest->getConfigCalls();
        $this->assertEquals(4, $result[SSViewer::class]['themes']['calls']);
    }
}
