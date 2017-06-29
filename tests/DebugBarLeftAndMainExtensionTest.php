<?php

class DebugBarLeftAndMainExtensionTest extends SapphireTest
{
    /**
     * @var DebugBarLeftAndMainExtension
     */
    protected $extension;

    /**
     * @var DebugBar\DataCollector\TimeDataCollector
     */
    protected $timeCollector;

    public function setUp()
    {
        parent::setUp();

        $this->extension = new DebugBarLeftAndMainExtension;
        DebugBar::initDebugBar();
        $this->timeCollector = DebugBar::getDebugBar()->getCollector('time');
    }

    public function tearDown()
    {
        DebugBar::clearDebugBar();

        parent::tearDown();
    }

    public function testAccessedCms()
    {
        $this->extension->accessedCMS();
        $this->assertFalse($this->timeCollector->hasStartedMeasure('init'));
        $this->assertTrue($this->timeCollector->hasStartedMeasure('cms_accessed'));
    }

    public function testInit()
    {
        $this->extension->init();
        $this->assertFalse($this->timeCollector->hasStartedMeasure('cms_accessed'));
        $this->assertTrue($this->timeCollector->hasStartedMeasure('cms_init'));
    }
}
