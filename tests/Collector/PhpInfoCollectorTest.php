<?php

namespace LeKoala\DebugBar\Test\Collector;

use LeKoala\DebugBar\Collector\PhpInfoCollector;
use PHPUnit_Framework_TestCase;
use ReflectionClass;

class PhpInfoCollectorTest extends PHPUnit_Framework_TestCase
{
    /**
     * Note: testing with reflection since it's tidier than moving parent::collect() into its own method just
     * so we can mock it.
     *
     * @dataProvider longPhpVersionProvider
     * @param string $phpVersion
     * @param string $expected
     * @throws \ReflectionException
     */
    public function testTrimLongPhpVersionNumbers($phpVersion, $expected)
    {
        $reflection = new ReflectionClass(PhpInfoCollector::class);
        $method = $reflection->getMethod('trimVersion');
        $method->setAccessible(true);

        $collector = new PhpInfoCollector();
        $result = $method->invokeArgs($collector, [$phpVersion]);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array[]
     */
    public function longPhpVersionProvider()
    {
        return [
            ['5.6.13', '5.6.13'],
            ['7.3.0', '7.3.0'],
            ['7.3.0dev', '7.3.0'],
            ['7.3.0-dev123+00', '7.3.0'],
            ['7.1.25-1+0~2017093290850938459043.8+jessie~1.gbpebe5d6', '7.1.25'],
        ];
    }
}
