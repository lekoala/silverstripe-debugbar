<?php

class DebugBarTimeDataCollectorTest extends SapphireTest
{
    /**
     * @var DebugBarTimeDataCollector
     */
    protected $collector;

    public function setUp()
    {
        parent::setUp();
        $this->collector = new DebugBarTimeDataCollector;
    }

    public function testCollectorTooltip()
    {
        $result = $this->collector->getWidgets();
        $this->assertContains('Request duration', $result['time']['tooltip']);
    }

    public function testWarning()
    {
        // Deliberately low threshold
        Config::inst()->update('DebugBar', 'warn_request_time_seconds', '0.00001');
        $result = $this->collector->getWidgets();
        $this->assertSame('danger', $result['time']['warn']);
        $this->assertContains('>', $result['time']['tooltip']);

        // Deliberately high threshold and low ratio
        Config::inst()->update('DebugBar', 'warn_request_time_seconds', '100');
        Config::inst()->update('DebugBar', 'warn_warning_ratio', '0.000000001');
        $result = $this->collector->getWidgets();
        $this->assertSame('warning', $result['time']['warn']);
        $this->assertContains('>', $result['time']['tooltip']);

        // Deliberately high threshold and high ratio
        Config::inst()->update('DebugBar', 'warn_request_time_seconds', '100');
        Config::inst()->update('DebugBar', 'warn_warning_ratio', '1');
        $result = $this->collector->getWidgets();
        $this->assertSame('ok', $result['time']['warn']);
        $this->assertContains('<', $result['time']['tooltip']);
    }

    public function testWarningCanBeDisabled()
    {
        Config::inst()->update('DebugBar', 'warn_request_time_seconds', false);
        $result = $this->collector->getWidgets();
        $this->assertArrayNotHasKey('warn', $result['time']);
    }
}
