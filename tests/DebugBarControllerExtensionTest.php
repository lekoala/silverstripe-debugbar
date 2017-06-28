<?php

class DebugBarControllerExtensionTest extends FunctionalTest
{
    protected $requiredExtensions = [
        'Controller' => ['DebugBarControllerExtension']
    ];

    /**
     * @var Controller
     */
    protected $controller;

    public function setUp()
    {
        parent::setUp();

        $this->controller = new Controller;
        $this->controller->pushCurrent();

        DebugBar::$bufferingEnabled = false;
        DebugBar::initDebugBar();
    }

    public function testOnBeforeInit()
    {
        $this->controller->extend('onBeforeInit');

        $self = $this;
        DebugBar::withDebugBar(function (DebugBar\DebugBar $debugbar) use ($self) {
            $controller = $debugbar->getCollector('silverstripe')->getController();
            $self->assertInstanceOf('Controller', $controller);
            $self->assertSame(Controller::curr(), $controller);

            $timeData = $debugbar['time'];
            $self->assertInstanceOf('DebugBar\DataCollector\TimeDataCollector', $timeData);

            $self->assertFalse($timeData->hasStartedMeasure('pre_request'));
            $self->assertTrue($timeData->hasStartedMeasure('init'));
        });
    }

    public function testOnAfterInit()
    {
        $this->controller->extend('onAfterInit');

        $self = $this;
        DebugBar::withDebugBar(function (DebugBar\DebugBar $debugbar) use ($self) {
            $timeData = $debugbar->getCollector('time');
            $self->assertInstanceOf('DebugBar\DataCollector\TimeDataCollector', $timeData);

            $self->assertFalse($timeData->hasStartedMeasure('cms_init'));
            $self->assertFalse($timeData->hasStartedMeasure('init'));
            $self->assertTrue($timeData->hasStartedMeasure('handle'));
        });
    }

    public function testBeforeCallActionHandler()
    {
        $request = new SS_HTTPRequest('GET', '/');
        $action = 'someaction';
        $this->controller->extend('beforeCallActionHandler', $request, $action);

        $self = $this;
        DebugBar::withDebugBar(function (DebugBar\DebugBar $debugbar) use ($self) {
            $timeData = $debugbar->getCollector('time');
            $self->assertInstanceOf('DebugBar\DataCollector\TimeDataCollector', $timeData);

            $self->assertFalse($timeData->hasStartedMeasure('handle'));
            $self->assertTrue($timeData->hasStartedMeasure('action'));
        });

        $this->assertTrue($this->controller->beforeCallActionHandlerCalled);
    }

    public function testAfterCallActionHandler()
    {
        $request = new SS_HTTPRequest('GET', '/');
        $action = 'someaction';
        $result = null;
        $this->controller->extend('afterCallActionHandler', $request, $action, $result);

        $self = $this;
        DebugBar::withDebugBar(function (DebugBar\DebugBar $debugbar) use ($self) {
            $timeData = $debugbar->getCollector('time');
            $self->assertInstanceOf('DebugBar\DataCollector\TimeDataCollector', $timeData);

            $self->assertFalse($timeData->hasStartedMeasure('action'));
            $self->assertTrue($timeData->hasStartedMeasure('after_action'));
        });
    }
}
