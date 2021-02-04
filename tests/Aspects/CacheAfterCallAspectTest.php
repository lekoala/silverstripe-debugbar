<?php

namespace LeKoala\DebugBar\Test\Aspects;

use LeKoala\DebugBar\Aspects\CacheAfterCallAspect;
use LeKoala\DebugBar\Collector\PartialCacheCollector;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\AopProxyService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

class CacheAfterCallAspectTest extends SapphireTest
{
    /**
     * Tests if an entry was added to PartialCacheCollector::$template_cache_info array
     */
    public function testAfterCall()
    {
        $proxy = new AopProxyService();
        $aspect = new CacheAfterCallAspect();
        $proxy->afterCall = array(
            'get' => $aspect
        );
        $count = count(PartialCacheCollector::getTemplateCache());
        $proxy->proxied = Injector::inst()->get(CacheInterface::class . '.backend');
        $cacheKey = 'myCacheKey';
        $proxy->get($cacheKey);
        $this->assertCount($count + 1, PartialCacheCollector::getTemplateCache());
    }
}
