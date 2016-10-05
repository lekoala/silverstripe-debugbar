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

        DebugBar::withDebugBar(function(DebugBar\DebugBar $debugbar) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                $timeData = $debugbar['time'];
                $timeData->addMeasure("framework boot",
                    $_SERVER['REQUEST_TIME_FLOAT'], microtime(true));
            }
            $timeData->startMeasure("pre_request", "pre request");
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
        $debugbar = DebugBar::getDebugBar();
        if (!$debugbar) {
            return;
        }

        // All queries have been displayed
        if (DebugBar::getShowQueries()) {
            exit();
        }

        $script = DebugBar::renderDebugBar();

        // If the bar is not renderable, return early
        if (!$script) {
            return;
        }

        // Inject init script into the HTML response
        $body = $response->getBody();
        if (strpos($body, '</body>') !== false) {
            $body = str_replace('</body>', $script.'</body>', $body);
            $response->setBody($body);
        }

        // Ajax support
        if (Director::is_ajax() && !headers_sent()) {
            if (DebugBar::IsAdminUrl() && !DebugBar::config()->enabled_in_admin) {
                return;
            }
            // Skip anything that is not a GET request
            if (!$request->isGET()) {
                return;
            }
            // Always enable in admin because everything is mostly loaded through ajax
            if (DebugBar::config()->ajax || DebugBar::IsAdminUrl()) {
                $headers = $debugbar->getDataAsHeaders();

                // Prevent throwing js errors in case header size is too large
                if (is_array($headers)) {
                    $debugbar->sendDataInHeaders();
                }
            }
        }
    }
}