<?php

/**
 * A controller extension to log times and render the Debug Bar
 */
class DebugBarControllerExtension extends Extension
{

    public function onBeforeInit()
    {
        $class = get_class($this->owner);

        DebugBar::withDebugBar(function(DebugBar\DebugBar $debugbar) use ($class) {
            // We must set the current controller when it's available and before it's pushed out of stack
            $debugbar->getCollector('silverstripe')->setController(Controller::curr());

            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure('pre_request')) {
                $timeData->stopMeasure("pre_request");
            }
            $timeData->startMeasure("init", "$class init");
        });
    }

    public function onAfterInit()
    {
        DebugBar::includeRequirements();

        $class = get_class($this->owner);

        DebugBar::withDebugBar(function(DebugBar\DebugBar $debugbar) use ($class) {

            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("cms_init")) {
                $timeData->stopMeasure("cms_init");
            }
            if ($timeData->hasStartedMeasure("init")) {
                $timeData->stopMeasure("init");
            }
            $timeData->startMeasure("handle", "$class handle request");
        });
    }

    /**
     * Due to a bug, this could be called twice before 4.0,
     * see https://github.com/silverstripe/silverstripe-framework/pull/5173
     *
     * @param SS_HTTPRequest $request
     * @param string $action
     */
    public function beforeCallActionHandler($request, $action)
    {
        // This could be called twice
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
        if (!$this->owner->hasMethod($action) || ($requestAction && $requestAction
            != $action)) {
            self::clearBuffer();
        }

        $class = get_class($this->owner);
        DebugBar::withDebugBar(function(DebugBar\DebugBar $debugbar) use($class, $action) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("handle")) {
                $timeData->stopMeasure("handle");
            }
            $timeData->startMeasure("action", "$class action $action");
        });

        $this->owner->beforeCallActionHandlerCalled = true;
    }

    /**
     * Due to a bug, this is not always called before 4.0,
     * see https://github.com/silverstripe/silverstripe-framework/pull/5173
     *
     * @param SS_HTTPRequest $request
     * @param string $action
     * @param mixed $result (only in v4.0)
     */
    public function afterCallActionHandler($request, $action, $result)
    {
        self::clearBuffer();

        $class = get_class($this->owner);
        DebugBar::withDebugBar(function(DebugBar\DebugBar $debugbar) use($class, $action) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("action")) {
                $timeData->stopMeasure("action");
            }
            $timeData->startMeasure("after_action",
                "$class after action $action");
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
            DebugBarSilverStripeCollector::setDebugData($buffer);
        }
    }
}