<?php

namespace LeKoala\DebugBar\Proxy;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Config\Collections\DeltaConfigCollection;

class DeltaConfigManifestProxy extends DeltaConfigCollection implements ProxyConfigCollectionInterface
{
    /**
     * @var DeltaConfigCollection
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

    public static function createFromOriginal(DeltaConfigCollection $collection)
    {
        $newCollection = static::createFromCollection($collection, $collection->getDeltaMiddleware()->getDisableFlag());
        $newCollection->setTrackEmpty(DebugBar::config()->config_track_empty);
        return $newCollection;
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
            if (!isset($this->configCalls[$class][$name])) {
                $this->configCalls[$class][$name] = [
                    'calls' => 0,
                    'result' => null
                ];
            }
            $this->configCalls[$class][$name]['calls']++;
            $this->configCalls[$class][$name]['result'] = $result;
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
