<?php

namespace LeKoala\DebugBar\Proxy;

use LeKoala\DebugBar\Collector\PartialCacheCollector;
use SilverStripe\Core\Convert;
use Symfony\Component\Cache\Psr16Cache;

class CacheProxy extends Psr16Cache
{
    public const string CONTEXT_TMP = '__TEMP__';

    protected static $data = [];

    protected string $context = '';

    public function setContext(string $context): self
    {
        $this->context = $context;

        return $this;
    }

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

        if ($this->context === self::CONTEXT_TMP) {
            $message = (empty((string)$value)) ? "Missed: {$key}" : "Hit: {$key}";
            $result = preg_replace('/\s+/', ' ', trim($value ?? ''));
            $result = Convert::raw2att($result);

            PartialCacheCollector::addTemplateCache(
                $message,
                [
                    'cache_result' =>
                        ['result' => $result]
                ]
            );
        }

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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
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
