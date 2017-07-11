<?php

namespace LeKoala\DebugBar\Test\Extension;

use DebugBar\DataCollector\TimeDataCollector;
use LeKoala\DebugBar\DebugBar;
use LeKoala\DebugBar\Extension\ControllerExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\FunctionalTest;

class ControllerExtensionTest extends FunctionalTest
{
    protected static $required_extensions = [
        Controller::class => [ControllerExtension::class]
    ];

    /**
     * @var Controller
     */
    protected $controller;

    public function setUp()
    {
        parent::setUp();

        $this->controller = Controller::curr();
        // $this->controller->pushCurrent();

        DebugBar::$bufferingEnabled = false;
        DebugBar::initDebugBar();
    }

    public function testOnBeforeInit()
    {
        $this->controller->extend('onBeforeInit');

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            $controller = $debugbar->getCollector('silverstripe')->getController();
            $this->assertInstanceOf(Controller::class, $controller);
            $this->assertSame(Controller::curr(), $controller);

            $timeData = $debugbar->getCollector('time');
            $this->assertInstanceOf(TimeDataCollector::class, $timeData);

            $this->assertFalse($timeData->hasStartedMeasure('pre_request'));
            $this->assertTrue($timeData->hasStartedMeasure('init'));
        });
    }

    public function testOnAfterInit()
    {
        $this->controller->extend('onAfterInit');

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            $timeData = $debugbar->getCollector('time');
            $this->assertInstanceOf(TimeDataCollector::class, $timeData);

            $this->assertFalse($timeData->hasStartedMeasure('cms_init'));
            $this->assertFalse($timeData->hasStartedMeasure('init'));
            $this->assertTrue($timeData->hasStartedMeasure('handle'));
        });
    }

    public function testBeforeCallActionHandler()
    {
        $request = new HTTPRequest('GET', '/');
        $action = 'someaction';
        $this->controller->extend('beforeCallActionHandler', $request, $action);

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            $timeData = $debugbar->getCollector('time');
            $this->assertInstanceOf(TimeDataCollector::class, $timeData);

            $this->assertFalse($timeData->hasStartedMeasure('handle'));
            $this->assertTrue($timeData->hasStartedMeasure('action'));
        });

        $this->assertTrue($this->controller->beforeCallActionHandlerCalled);
    }

    public function testAfterCallActionHandler()
    {
        $request = new HTTPRequest('GET', '/');
        $action = 'someaction';
        $result = null;
        $this->controller->extend('afterCallActionHandler', $request, $action, $result);

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            $timeData = $debugbar->getCollector('time');
            $this->assertInstanceOf(TimeDataCollector::class, $timeData);

            $this->assertFalse($timeData->hasStartedMeasure('action'));
            $this->assertTrue($timeData->hasStartedMeasure('after_action'));
        });
    }
}
