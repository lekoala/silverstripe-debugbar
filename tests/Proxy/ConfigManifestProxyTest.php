<?php

namespace LeKoala\DebugBar\Test\Proxy;

use LeKoala\DebugBar\DebugBar;
use LeKoala\DebugBar\Proxy\ConfigManifestProxy;
use LeKoala\DebugBar\Proxy\TemplateParserProxy;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;

class ConfigManifestProxyTest extends SapphireTest
{
    protected function setUp()
    {
        DebugBar::initDebugBar();

        parent::setUp();

        // Clear out any extra config manifests
        /** @var SilverStripe\Core\Config\ConfigLoader $configLoader */
        $configLoader = Injector::inst()->get(Kernel::class)->getConfigLoader();

        while ($configLoader->countManifests() > 1) {
            if ($configLoader->getManifest() instanceof ConfigManifestProxy) {
                continue;
            }
            $configLoader->popManifest();
        }
    }

    public function testProxyIsPushedToLoader()
    {
        $configLoader = Injector::inst()->get(Kernel::class)->getConfigLoader();
        $this->assertInstanceOf(ConfigManifestProxy::class, $configLoader->getManifest());
    }

    public function testGetCallsAreCaptured()
    {
        Config::inst()->get(TemplateParserProxy::class, 'cached');
        $result = Injector::inst()->get(Kernel::class)->getConfigLoader()->getManifest()->getConfigCalls();
        $this->assertArrayHasKey(TemplateParserProxy::class, $result);
        $this->assertArrayHasKey('cached', $result[TemplateParserProxy::class]);
        $this->assertEquals(1, $result[TemplateParserProxy::class]['cached']['calls']);

        Config::inst()->get(TemplateParserProxy::class, 'cached');
        Config::inst()->get(TemplateParserProxy::class, 'cached');
        Config::inst()->get(TemplateParserProxy::class, 'cached');
        $result = Injector::inst()->get(Kernel::class)->getConfigLoader()->getManifest()->getConfigCalls();
        $this->assertEquals(4, $result[TemplateParserProxy::class]['cached']['calls']);
    }
}
