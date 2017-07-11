<?php

namespace LeKoala\DebugBar\Test\Collector;

use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use LeKoala\DebugBar\Collector\TimeDataCollector;
use LeKoala\DebugBar\DebugBar;

class TimeDataCollectorTest extends SapphireTest
{
    /**
     * @var DebugBarTimeDataCollector
     */
    protected $collector;

    public function setUp()
    {
        parent::setUp();
        $this->collector = new TimeDataCollector(microtime(true));
    }

    public function testCollectorTooltip()
    {
        $result = $this->collector->getWidgets();
        $this->assertContains('Request duration', $result['time']['tooltip']);
    }

    /**
     * Deliberately low threshold - should return a danger result
     */
    public function testDangerOnVerySlowRequest()
    {
        Config::modify()->set(DebugBar::class, 'warn_request_time_seconds', '0.00001');
        $result = $this->collector->getWidgets();
        $this->assertSame('danger', $result['time']['warn']);
        $this->assertContains('>', $result['time']['tooltip']);
    }

    /**
     * Deliberately high threshold and low ratio - should return a warning result
     */
    public function testWarningOnSlowRequest()
    {
        Config::modify()->set(DebugBar::class, 'warn_request_time_seconds', '100');
        Config::modify()->set(DebugBar::class, 'warn_warning_ratio', '0.000000001');
        $result = $this->collector->getWidgets();
        $this->assertSame('warning', $result['time']['warn']);
        $this->assertContains('>', $result['time']['tooltip']);
    }

    /**
     * Deliberately high threshold and high ratio - should return an "ok" result
     */
    public function testOkOnNormalRequest()
    {
        Config::modify()->set(DebugBar::class, 'warn_request_time_seconds', '100');
        Config::modify()->set(DebugBar::class, 'warn_warning_ratio', '1');
        $result = $this->collector->getWidgets();
        $this->assertSame('ok', $result['time']['warn']);
        $this->assertContains('<', $result['time']['tooltip']);
    }

    public function testWarningCanBeDisabled()
    {
        Config::modify()->set(DebugBar::class, 'warn_request_time_seconds', false);
        $result = $this->collector->getWidgets();
        $this->assertArrayNotHasKey('warn', $result['time']);
    }
}
