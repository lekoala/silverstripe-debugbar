<?php

namespace LeKoala\DebugBar\Collector;

use DebugBar\DataCollector\TimeDataCollector as BaseTimeDataCollector;
use LeKoala\DebugBar\DebugBar;

class TimeDataCollector extends BaseTimeDataCollector
{
    /**
     * Add in a warning or danger notification if the request time is greater than the configured thresholds
     *
     * {@inheritDoc}
     */
    public function getWidgets()
    {
        $widgets = parent::getWidgets();

        $upperThreshold = DebugBar::config()->get('warn_request_time_seconds');
        $warningRatio = DebugBar::config()->get('warn_warning_ratio');

        // Can be disabled by setting the value to false
        if (!is_numeric($upperThreshold)) {
            return $widgets;
        }

        $widgets['time']['indicator'] = 'PhpDebugBar.DebugBar.WarnableRequestTimeIndicator';
        $widgets['time']['warn'] = 'ok';
        // Request duration rather than Request Duration
        $widgets['time']['tooltip'] = ucfirst(strtolower($widgets['time']['tooltip']));

        $warningThreshold = $upperThreshold * $warningRatio;
        if ($this->getRequestDuration() > $upperThreshold) {
            $widgets['time']['warn'] = 'danger';
            $widgets['time']['tooltip'] .= ' > ' . $upperThreshold . ' seconds';
        } elseif ($this->getRequestDuration() > $warningThreshold) {
            $widgets['time']['warn'] = 'warning';
            $widgets['time']['tooltip'] .= ' > ' . $warningThreshold . ' seconds';
        } else {
            $widgets['time']['tooltip'] .= ' < ' . $warningThreshold . ' seconds';
        }

        return $widgets;
    }
}
