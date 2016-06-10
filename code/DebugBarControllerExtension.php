<?php

/**
 * A controller extension to log times and render the Debug Bar
 */
class DebugBarControllerExtension extends Extension
{
    private static $allowed_actions = array(
        '_debugbar'
    );

    public function _debugbar()
    {
        if (DebugBar::config()->enable_storage) {
            die();
        }
        $debugbar = DebugBar::getDebugBar();
        if (!$debugbar) {
            return;
        }
        if (!DebugBar::IsDebugBarRequest()) {
            return;
        }
        $openHandler = new DebugBar\OpenHandler($debugbar);
        $openHandler->handle();
        exit();
    }

    public function onBeforeInit()
    {
        $class = get_class($this->owner);
        DebugBar::withDebugBar(function($debugbar) use ($class) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure('pre-request')) {
                $timeData->stopMeasure("pre-request");
            }
            $timeData->startMeasure("init", "$class initialization");
        });
    }

    public function onAfterInit()
    {
        $class = get_class($this->owner);
        DebugBar::withDebugBar(function($debugbar) use ($class) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("init")) {
                $timeData->stopMeasure("init");
            }
            $timeData->startMeasure("handle", "$class handle request");
        });
    }

    public function beforeCallActionHandler($request, $action)
    {
        $class = get_class($this->owner);
        DebugBar::withDebugBar(function($debugbar) use($class, $action) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("handle")) {
                $timeData->stopMeasure("handle");
            }
            $timeData->startMeasure("action", "$class action:$action");
        });
    }

    public function RenderDebugBar()
    {
        $debugbar = DebugBar::getDebugBar();
        if (!$debugbar) {
            return;
        }

        $initialize = true;
        if (Director::is_ajax()) {
            $initialize = false;
        }

        $renderer = $debugbar->getJavascriptRenderer();

        $renderer->setBasePath(DEBUGBAR_DIR.'/assets');
        $renderer->setBaseUrl(basename(DEBUGBAR_DIR).'/assets');

        $renderer->disableVendor('jquery');
        $renderer->setEnableJqueryNoConflict(false);

        if (DebugBar::config()->enable_storage) {
            $renderer->setOpenHandlerUrl('/home/_debugbar');
        }

        foreach ($renderer->getAssets('css') as $cssFile) {
            Requirements::css($cssFile);
        }
        foreach ($renderer->getAssets('js') as $jsFile) {
            Requirements::javascript($jsFile);
        }

        return $renderer->render($initialize);
    }
}