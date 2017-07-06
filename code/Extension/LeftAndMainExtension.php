<?php

namespace LeKoala\DebugBar\Extension;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Admin\LeftAndMainExtension as BaseLeftAndMainExtension;

/**
 * Description of DebugBarLeftAndMainExtension
 *
 * @author Koala
 */
class LeftAndMainExtension extends BaseLeftAndMainExtension
{
    public function accessedCMS()
    {
        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar->getCollector('time');
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("init")) {
                $timeData->stopMeasure("init");
            }
            $timeData->startMeasure("cms_accessed", "cms accessed");
        });
    }

    public function init()
    {
        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar->getCollector('time');
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure("cms_accessed")) {
                $timeData->stopMeasure("cms_accessed");
            }
            $timeData->startMeasure("cms_init", "cms init");
        });
    }
}
