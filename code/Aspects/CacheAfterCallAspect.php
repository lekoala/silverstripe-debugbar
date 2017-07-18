<?php

namespace LeKoala\DebugBar\Aspect;

use LeKoala\DebugBar\Collector\SilverStripeCollector;
use Monolog\Logger;
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
        $message = "Your partial cache named {$args[0]} ";
        $message .= (empty($result)) ? 'didn’t find any existing caches' : $message .= 'found an existing cache';
        SilverStripeCollector::addTemplateCacheInfo($message);
    }
}
