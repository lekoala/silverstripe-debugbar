<?php

namespace LeKoala\DebugBar\Proxy;

use SilverStripe\Versioned\Caching\VersionedCacheAdapter;

class CacheAdapter extends VersionedCacheAdapter
{
    public function setContext(string $context): self
    {
        if (method_exists($this->pool, 'setContext')) {
            $this->pool->setContext($context);
        }

        return $this;
    }
}
