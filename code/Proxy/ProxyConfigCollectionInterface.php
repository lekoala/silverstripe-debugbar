<?php

namespace LeKoala\DebugBar\Proxy;

interface ProxyConfigCollectionInterface
{
    /**
     * Return a list of all config calls made during the request, including how many times they were called
     * and the result
     *
     * The array has the following structure
     *
     * - ClassName
     *   - Config key
     *     - count : int
     *     - result : Config value
     * ...
     *
     * @return array
     */
    public function getConfigCalls();

    /**
     * @return bool
     */
    public function getTrackEmpty();

    /**
     * @param bool $trackEmpty
     * @return $this
     */
    public function setTrackEmpty($trackEmpty);
}
