<?php

namespace LeKoala\DebugBar;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestFilter as BaseRequestFilter;

/**
 * A request filter to log pre request time
 */
class RequestFilter implements BaseRequestFilter
{

    /**
     * Filter executed before a request processes
     *
     * {@inheritDoc}
     */
    public function preRequest(HTTPRequest $request)
    {
        DebugBar::withDebugBar(function (DebugBar\DebugBar $debugbar) {
            /* @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugbar['time'];
            if (!$timeData) {
                return;
            }
            if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                $timeData = $debugbar['time'];
                $timeData->addMeasure(
                    "framework boot",
                    $_SERVER['REQUEST_TIME_FLOAT'],
                    microtime(true)
                );
            }
            $timeData->startMeasure("pre_request", "pre request");
        });
    }

    /**
     * Filter executed AFTER a request
     *
     * {@inheritDoc}
     */
    public function postRequest(HTTPRequest $request, HTTPResponse $response)
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
            $body = str_replace('</body>', $script . '</body>', $body);
            $response->setBody($body);
        }

        // Ajax support
        if (Director::is_ajax() && !headers_sent()) {
            if (DebugBar::isAdminUrl() && !DebugBar::config()->enabled_in_admin) {
                return;
            }
            // Skip anything that is not a GET request
            if (!$request->isGET()) {
                return;
            }
            // Always enable in admin because everything is mostly loaded through ajax
            if (DebugBar::config()->ajax || DebugBar::isAdminUrl()) {
                $headers = $debugbar->getDataAsHeaders();

                // Prevent throwing js errors in case header size is too large
                if (is_array($headers)) {
                    $debugbar->sendDataInHeaders();
                }
            }
        }
    }
}
