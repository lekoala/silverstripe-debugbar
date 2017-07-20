<?php

namespace LeKoala\DebugBar\Aspect;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\AfterCallAspect;
use SilverStripe\Core\Injector\Injector;

class CacheAfterCallAspect implements AfterCallAspect
{
    /**
     * Logs all hits/misses after a CacheInterface::get call is made.
     *
     * {@inheritdoc}
     */
    public function afterCall($proxied, $method, $args, $result)
    {
        $message = (empty($result)) ? "Missed: {$args[0]}" : "Hit: {$args[0]}";
        if ($cache = Injector::inst()->get(CacheInterface::class . '.backend')) {
            $cache->templateCache[$message] = array('cache_result' => array('result' => $result));
        }
    }
}
