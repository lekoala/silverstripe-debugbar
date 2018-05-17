<?php

namespace LeKoala\DebugBar\Test\Proxy;

use SilverStripe\Core\Config\ConfigLoader;
use SilverStripe\Core\Kernel;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use LeKoala\DebugBar\Proxy\ConfigManifestProxy;
use LeKoala\DebugBar\Proxy\SSViewerProxy;

class ConfigManifestProxyTest extends SapphireTest
{
    protected function setUp()
    {
        parent::setUp();

        DebugBar::initDebugBar();

        /** @var ConfigLoader $configLoader */
        $configLoader = Injector::inst()->get(Kernel::class)->getConfigLoader();

        // Check top level manifest is our proxy
        // TODO: in tests, we have a DeltaConfigCollection which is not working with the proxy
        if (!($configLoader->getManifest() instanceof ConfigManifestProxy)) {
            $this->markTestSkipped("ConfigManifestProxy is not initialized");
        }
    }

    public function testGetCallsAreCaptured()
    {
        $manifest = Injector::inst()->get(Kernel::class)->getConfigLoader()->getManifest();

        Config::inst()->get(SSViewerProxy::class, 'cached');
        $result = $manifest->getConfigCalls();
        $this->assertArrayHasKey(SSViewerProxy::class, $result);
        $this->assertArrayHasKey('cached', $result[SSViewerProxy::class]);
        $this->assertEquals(1, $result[SSViewerProxy::class]['cached']['calls']);

        Config::inst()->get(SSViewerProxy::class, 'cached');
        Config::inst()->get(SSViewerProxy::class, 'cached');
        Config::inst()->get(SSViewerProxy::class, 'cached');
        $result = $manifest->getConfigCalls();
        $this->assertEquals(4, $result[SSViewerProxy::class]['cached']['calls']);
    }
}
