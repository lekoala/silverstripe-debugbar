<?php

namespace LeKoala\DebugBar\Test\Proxy;

use SilverStripe\Core\Kernel;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use LeKoala\DebugBar\Proxy\ConfigManifestProxy;
use LeKoala\DebugBar\Proxy\TemplateParserProxy;

class ConfigManifestProxyTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();

        DebugBar::initDebugBar();

        // Clear out any extra config manifests
        /** @var SilverStripe\Core\Config\ConfigLoader $configLoader */
        $configLoader = Injector::inst()->get(Kernel::class)->getConfigLoader();

        $found = false;
        while ($configLoader->countManifests() > 1) {
            if ($configLoader->getManifest() instanceof ConfigManifestProxy) {
                $found = true;
                continue;
            }
            $configLoader->popManifest();
        }
        if (!$found) {
            $this->markTestSkipped("ConfigManifestProxy is not initialized");
        }
    }

    public function testProxyIsPushedToLoader()
    {
        $configLoader = Injector::inst()->get(Kernel::class)->getConfigLoader();
        $this->assertInstanceOf(ConfigManifestProxy::class, $configLoader->getManifest());
    }

    public function testGetCallsAreCaptured()
    {
        $manifest = Injector::inst()->get(Kernel::class)->getConfigLoader()->getManifest();

        Config::inst()->get(TemplateParserProxy::class, 'cached');
        $result = $manifest->getConfigCalls();
        $this->assertArrayHasKey(TemplateParserProxy::class, $result);
        $this->assertArrayHasKey('cached', $result[TemplateParserProxy::class]);
        $this->assertEquals(1, $result[TemplateParserProxy::class]['cached']['calls']);

        Config::inst()->get(TemplateParserProxy::class, 'cached');
        Config::inst()->get(TemplateParserProxy::class, 'cached');
        Config::inst()->get(TemplateParserProxy::class, 'cached');
        $result = $manifest->getConfigCalls();
        $this->assertEquals(4, $result[TemplateParserProxy::class]['cached']['calls']);
    }
}
