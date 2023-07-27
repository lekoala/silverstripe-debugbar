<?php

namespace LeKoala\DebugBar\Proxy;

use Symfony\Component\Cache\Psr16Cache;

class CacheProxy extends Psr16Cache
{
    protected static $data = [];

    public function set($key, $value, $ttl = null): bool
    {
        self::$data[$key] = [
            "value" => $value,
            "ttl" => $ttl
        ];
        return parent::set($key, $value, $ttl);
    }

    public static function getData(): array
    {
        return self::$data;
    }
}
