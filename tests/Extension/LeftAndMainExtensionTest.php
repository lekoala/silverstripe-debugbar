<?php

namespace LeKoala\DebugBar\Test\Extension;

use LeKoala\DebugBar\DebugBar;
use LeKoala\DebugBar\Extension\LeftAndMainExtension;
use SilverStripe\Dev\SapphireTest;

class LeftAndMainExtensionTest extends SapphireTest
{
    /**
     * @var LeftAndMainExtension
     */
    protected $extension;

    /**
     * @var DebugBar\DataCollector\TimeDataCollector
     */
    protected $timeCollector;

    public function setUp()
    {
        parent::setUp();

        $this->extension = new LeftAndMainExtension;
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
