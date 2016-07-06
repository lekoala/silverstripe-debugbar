<?php

/**
 * A controller extension to log times and render the Debug Bar
 */
class DebugBarControllerExtension extends Extension
{

    public function onBeforeInit()
    {
        $class = get_class($this->owner);
        DebugBar::withDebugBar(function($debugbar) use ($class) {
            // Add config collector
            $debugbar->addCollector(new DebugBar\DataCollector\ConfigCollector(SiteConfig::current_site_config()->toMap()),
                'SiteConfig');

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
        if (!$this->owner->hasMethod($action)) {
            self::clearBuffer();
        }

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
        DebugBar::withDebugBar(function($debugbar) use($class, $action) {
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
        $buffer = ob_get_clean();
        if (!empty($buffer)) {
            unset($_REQUEST['debug_request']); // Disable further messages that we can't intercept
            DebugBarSilverStripeCollector::setDebugBar($buffer);
        }
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
            $renderer->setOpenHandlerUrl('__debugbar');
        }

        foreach ($renderer->getAssets('css') as $cssFile) {
            Requirements::css($cssFile);
        }
        foreach ($renderer->getAssets('js') as $jsFile) {
            Requirements::javascript($jsFile);
        }

        $script = $renderer->render($initialize);
        $script = str_replace("<script type=\"text/javascript\">\n", "", $script);
        $script = str_replace("\n</script>\n", "", $script);
        Requirements::customScript($script, "PhpDebugBar");
    }
}