<?php

/**
 * A request filter to log pre request time
 */
class DebugBarRequestFilter implements \RequestFilter
{

    /**
     * Filter executed before a request processes
     *
     * @param SS_HTTPRequest $request Request container object
     * @param Session $session        Request session
     * @param DataModel $model        Current DataModel
     * @return boolean Whether to continue processing other filters. Null or true will continue processing (optional)
     */
    public function preRequest(SS_HTTPRequest $request, Session $session,
                               DataModel $model)
    {
        DebugBar::withDebugBar(function($debugbar) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                $timeData = $debugbar['time'];
                $timeData->addMeasure("framework-boot", $_SERVER['REQUEST_TIME_FLOAT'], microtime(true));
            }
            $timeData->startMeasure("pre-request", "pre request");
        });
    }

    /**
     * Filter executed AFTER a request
     *
     * @param SS_HTTPRequest $request   Request container object
     * @param SS_HTTPResponse $response Response output object
     * @param DataModel $model          Current DataModel
     * @return boolean Whether to continue processing other filters. Null or true will continue processing (optional)
     */
    public function postRequest(SS_HTTPRequest $request,
                                SS_HTTPResponse $response, DataModel $model)
    {
        DebugBar::withDebugBar(function($debugbar) {
            if (Director::is_ajax()) {
                $debugbar->sendDataInHeaders();
                return;
            }
        });
    }
}