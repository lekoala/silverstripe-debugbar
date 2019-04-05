<?php

namespace LeKoala\DebugBar\Collector;

/**
 * Extends the default PhpInfoCollector to trim long PHP version numbers
 */
class PhpInfoCollector extends \DebugBar\DataCollector\PhpInfoCollector
{
    public function collect()
    {
        $metrics = parent::collect();
        $metrics['version'] = $this->trimVersion($metrics['version']);
        return $metrics;
    }

    /**
     * Given a PHP_VERSION constant value, trim any "extra" meta information from the end, returning the major, minor
     * and patch release of the version. Will fall back to returning the input if the matching fails.
     *
     * @param string $version
     * @return string
     */
    protected function trimVersion($version)
    {
        preg_match('/^\d+\.\d+\.\d+/', $version, $matches);
        return !empty($matches[0]) ? $matches[0] : $version;
    }
}
