<?php

namespace LeKoala\DebugBar\Test\Aspect;

use LeKoala\DebugBar\Aspect\CacheAfterCallAspect;
use LeKoala\DebugBar\Collector\SilverStripeCollector;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\AopProxyService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class CacheAfterCallAspectTest extends SapphireTest
{
    /**
     * Tests if an entry was added to SilverstripeCollector::$template_cache_info array
     */
    public function testAfterCall()
    {
        $proxy = new AopProxyService();
        $aspect = new CacheAfterCallAspect();
        $proxy->afterCall = array(
            'get' => $aspect
        );
        $count = count(SilverStripeCollector::getTemplateCacheInfo()['cache']);
        $proxy->proxied = Injector::inst()->get(CacheInterface::class . '.backend');
        $cacheKey = 'myCacheKey';
        $proxy->get($cacheKey);
        $this->assertCount($count + 1, SilverStripeCollector::getTemplateCacheInfo()['cache']);
    }
}
