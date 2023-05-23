<?php

namespace LeKoala\DebugBar\Test\Extension;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Controller;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Control\HTTPRequest;
use DebugBar\DataCollector\TimeDataCollector;
use LeKoala\DebugBar\Extension\ControllerExtension;
use LeKoala\DebugBar\Collector\SilverStripeCollector;

class ControllerExtensionTest extends FunctionalTest
{
    protected static $required_extensions = [
        Controller::class => [ControllerExtension::class]
    ];

    /**
     * @var Controller
     */
    protected $controller;

    public function setUp(): void
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
            /** @var SilverStripeCollector $ssCollector */
            $ssCollector = $debugbar->getCollector('silverstripe');
            $controller = $ssCollector->getController();
            $this->assertInstanceOf(Controller::class, $controller);
            $this->assertSame(Controller::curr(), $controller);

            /** @var TimeDataCollector $timeData */
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
            /** @var TimeDataCollector $timeData */
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
            /** @var TimeDataCollector $timeData */
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
            /** @var TimeDataCollector $timeData */
            $timeData = $debugbar->getCollector('time');
            $this->assertInstanceOf(TimeDataCollector::class, $timeData);

            $this->assertFalse($timeData->hasStartedMeasure('action'));
            $this->assertTrue($timeData->hasStartedMeasure('after_action'));
        });
    }
}
