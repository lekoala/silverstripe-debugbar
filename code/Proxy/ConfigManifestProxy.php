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
     * @var boolean
     */
    protected $trackEmpty = false;

    /**
     * @param CachedConfigCollection $parent
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

        // Only track not empty values by default
        if ($result || $this->trackEmpty) {
            if (!isset(self::$configCalls[$class][$name])) {
                self::$configCalls[$class][$name] = [
                    'calls' => 0,
                    'result' => null
                ];
            }
            self::$configCalls[$class][$name]['calls']++;
            self::$configCalls[$class][$name]['result'] = $result;
        }

        return $result;
    }

    /**
     * Return a list of all config calls made during the request, including how many times they were called
     * and the result
     *
     * @return array
     */
    public static function getConfigCalls()
    {
        return self::$configCalls;
    }

    /**
     * Get the value of trackEmpty
     *
     * @return boolean
     */
    public function getTrackEmpty()
    {
        return $this->trackEmpty;
    }

    /**
     * Set the value of trackEmpty
     *
     * @param boolean $trackEmpty
     *
     * @return self
     */
    public function setTrackEmpty($trackEmpty)
    {
        $this->trackEmpty = $trackEmpty;
        return $this;
    }
}
