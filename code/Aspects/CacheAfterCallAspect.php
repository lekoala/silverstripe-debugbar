<?php

namespace LeKoala\DebugBar\Aspect;

use LeKoala\DebugBar\Collector\PartialCacheCollector;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\AfterCallAspect;

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
        $result = preg_replace('/\s+/', ' ', trim($result));
        $result = Convert::raw2att($result);
        PartialCacheCollector::addTemplateCache(
            $message,
            array(
                'cache_result' =>
                    array('result' => $result)
            )
        );
    }
}
