<?php

namespace LeKoala\DebugBar\Extension;

use LeKoala\DebugBar\Collector\HeaderCollector;
use LeKoala\DebugBar\Collector\SilverStripeCollector;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\CMS\Controllers\ContentController;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Extension;
use SilverStripe\Security\Security;

/**
 * A controller extension to log times and render the Debug Bar
 */
class ControllerExtension extends Extension
{
    public function onBeforeInit()
    {
        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            // We must set the current controller when it's available and before it's pushed out of stack
            $debugbar->getCollector('silverstripe')->setController(Controller::curr());

            /** @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar->getCollector('time');
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure('pre_request')) {
                $timeData->stopMeasure("pre_request");
            }
            $timeData->startMeasure("init", get_class($this->owner) . ' init');
        });
    }

    public function onAfterInit()
    {
        // On Security, onAfterInit is called before init() in your Page method
        // jQuery is most likely not included yet
        if (!$this->owner instanceof Security && $this->owner instanceof ContentController) {
            DebugBar::includeRequirements();
        }

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            // Add the headers Collector
            if (!$debugbar->hasCollector('Headers') && DebugBar::config()->get('header_collector')) {
                $debugbar->addCollector(new HeaderCollector($this->owner));
            }
            /** @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar->getCollector('time');
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("cms_init")) {
                $timeData->stopMeasure("cms_init");
            }
            if ($timeData->hasStartedMeasure("init")) {
                $timeData->stopMeasure("init");
            }
            $timeData->startMeasure("handle", get_class($this->owner) . ' handle request');
        });
    }

    /**
     * @param HTTPRequest $request
     * @param string $action
     */
    public function beforeCallActionHandler($request, $action)
    {
        // This could be called twice
        // TODO: check if we can remove this safely
        if ($this->owner->beforeCallActionHandlerCalled) {
            return;
        }

        // If we don't have an action, getViewer will be called immediatly
        // If we have custom routes, request action is different than action
        $allParams     = $request->allParams();
        $requestAction = null;
        if (!empty($allParams['Action'])) {
            $requestAction = $allParams['Action'];
        }
        if (!$this->owner->hasMethod($action) || ($requestAction && $requestAction != $action)) {
            self::clearBuffer();
        }

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugBar) use ($action) {
            /** @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugBar->getCollector('time');
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("handle")) {
                $timeData->stopMeasure("handle");
            }
            $timeData->startMeasure("action", get_class($this->owner) . " action $action");
        });

        $this->owner->beforeCallActionHandlerCalled = true;
    }

    /**
     * @param HTTPRequest $request
     * @param string $action
     * @param mixed $result
     */
    public function afterCallActionHandler($request, $action, $result)
    {
        self::clearBuffer();

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugBar) use ($action) {
            /** @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugBar->getCollector('time');
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("action")) {
                $timeData->stopMeasure("action");
            }
            $timeData->startMeasure(
                "after_action",
                get_class($this->owner) . " after action $action"
            );
        });
    }

    protected static function clearBuffer()
    {
        if (!DebugBar::$bufferingEnabled) {
            return;
        }
        $buffer = ob_get_clean();
        if (!empty($buffer)) {
            unset($_REQUEST['debug_request']); // Disable further messages that we can't intercept
            SilverStripeCollector::setDebugData($buffer);
        }
    }
}
