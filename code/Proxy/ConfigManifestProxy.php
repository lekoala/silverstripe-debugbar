<?php

namespace LeKoala\DebugBar\Proxy;

use SilverStripe\Config\Collections\CachedConfigCollection;

class ConfigManifestProxy extends CachedConfigCollection
{
    /**
     * @var CachedConfigCollection
     */
    protected $parent;

    /**
     * @var array
     */
    protected static $configCalls = [];

    /**
     * @param ConfigCollectionInterface $parent
     */
    public function __construct(CachedConfigCollection $parent)
    {
        $this->parent = $parent;

        $this->collection = $this->parent->getCollection();
        $this->cache = $this->parent->getCache();
        $this->flush = $this->parent->getFlush();
        $this->collectionCreator = $this->parent->getCollectionCreator();
    }

    /**
     * Monitor calls made to get configuration during a request
     *
     * {@inheritDoc}
     */
    public function get($class, $name = null, $excludeMiddleware = 0)
    {
        $result = parent::get($class, $name, $excludeMiddleware);

        if (!isset(self::$configCalls[$class][$name])) {
            self::$configCalls[$class][$name] = [
                'calls' => 0,
                'result' => null
            ];
        }
        self::$configCalls[$class][$name]['calls']++;
        self::$configCalls[$class][$name]['result'] = $result;

        return $result;
    }

    /**
     * Return a lsit of all config calls made during the request, including how many times they were called
     * and the result
     *
     * @return array
     */
    public static function getConfigCalls()
    {
        return self::$configCalls;
    }
}
