<?php

namespace LeKoala\DebugBar\Proxy;

use Symfony\Component\Cache\Psr16Cache;

class CacheProxy extends Psr16Cache
{
    protected static $data = [];

    public function set($key, $value, $ttl = null): bool
    {
        self::$data[__FUNCTION__ . $key] = [
            "key" => $key,
            "type" => __FUNCTION__,
            "value" => $value,
            "ttl" => $ttl,
            "caller" => $this->getCaller(),
        ];
        return parent::set($key, $value, $ttl);
    }

    public function get($key, $default = null): mixed
    {
        $value = parent::get($key, $default);
        self::$data[__FUNCTION__ . $key] = [
            "key" => $key,
            "type" => __FUNCTION__,
            "value" => $value,
            "caller" => $this->getCaller(),
        ];
        return $value;
    }

    private function getCaller(): string
    {
        $trace = debug_backtrace();
        $caller = "";
        $ignore = ["set", "get", "setCacheValue", "getCacheValue", "getCaller"];
        foreach ($trace as $t) {
            if (in_array($t['function'], $ignore)) {
                continue;
            }
            if (isset($t['file'])) {
                $caller = basename($t['file']) . ':' . $t['line'];
            } elseif (isset($t['class'])) {
                $caller = $t['class'];
            }
            break;
        }
        return $caller;
    }

    public static function getData(): array
    {
        return self::$data;
    }
}
