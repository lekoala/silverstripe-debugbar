<?php

namespace LeKoala\DebugBar\Proxy;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Config\Collections\CachedConfigCollection;

class ConfigManifestProxy extends CachedConfigCollection implements ProxyConfigCollectionInterface
{
    /**
     * @var CachedConfigCollection
     */
    protected $parent;

    /**
     * @var array
     */
    protected $configCalls = [];

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

        $this->setTrackEmpty(DebugBar::config()->config_track_empty);
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
            $key = $name ?? '';
            if (!isset($this->configCalls[$class][$key])) {
                $this->configCalls[$class][$key] = [
                    'calls' => 0,
                    'result' => null
                ];
            }
            $this->configCalls[$class][$key]['calls']++;
            $this->configCalls[$class][$key]['result'] = $result;
        }

        return $result;
    }

    /**
     * Return a list of all config calls made during the request, including how many times they were called
     * and the result
     *
     * @return array
     */
    public function getConfigCalls()
    {
        return $this->configCalls;
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
