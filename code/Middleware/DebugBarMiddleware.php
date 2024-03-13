<?php

namespace LeKoala\DebugBar\Middleware;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\View\Requirements;

class DebugBarMiddleware implements HTTPMiddleware
{
    public function process(HTTPRequest $request, callable $delegate)
    {
        $this->beforeRequest($request);
        $response = $delegate($request);
        if ($response) {
            $this->afterRequest($request, $response);
        }
        return $response;
    }

    /**
     * Track the start up of the framework boot
     *
     * @param HTTPRequest $request
     * @return void
     */
    protected function beforeRequest(HTTPRequest $request)
    {
        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugbar) {
            /** @var \DebugBar\DataCollector\TimeDataCollector|null $timeData */
            $timeData = $debugbar->getCollector('time');

            if (!$timeData) {
                return;
            }

            // Allows a more realistic view
            // Define FRAMEWORK_BOOT_TIME in your index.php after composer autoload
            if (defined('FRAMEWORK_BOOT_TIME')) {
                if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                    $timeData = $debugbar['time'];
                    $timeData->addMeasure(
                        'php boot',
                        $_SERVER['REQUEST_TIME_FLOAT'],
                        constant('FRAMEWORK_BOOT_TIME')
                    );
                    $timeData->addMeasure(
                        'framework boot',
                        constant('FRAMEWORK_BOOT_TIME'),
                        microtime(true)
                    );
                } else {
                    $timeData = $debugbar['time'];
                    $timeData->addMeasure(
                        'framework boot',
                        constant('FRAMEWORK_BOOT_TIME'),
                        microtime(true)
                    );
                }
            } elseif (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
                $timeData = $debugbar['time'];
                $timeData->addMeasure(
                    'framework boot',
                    $_SERVER['REQUEST_TIME_FLOAT'],
                    microtime(true)
                );
            }

            DebugBar::closeExtraTime();
            $timeData->startMeasure('pre_request', 'pre request');
        });
    }

    /**
     * Inject DebugBar requirements for the frontend
     *
     * @param HTTPRequest  $request
     * @param HTTPResponse $response
     * @return void
     */
    protected function afterRequest(HTTPRequest $request, HTTPResponse $response)
    {
        $debugbar = DebugBar::getDebugBar();
        if (!$debugbar) {
            return;
        }
        DebugBar::setRequest($request);

        // All queries have been displayed
        if (DebugBar::getShowQueries()) {
            exit();
        }

        $script = DebugBar::renderDebugBar();

        // If the bar is not renderable, return early
        if (!$script) {
            return;
        }

        // Use module to make sure it's deferred and does not cause side effects
        $script = str_replace('<script type="text/javascript">', '<script type="module">', $script);

        // Inject init script into the HTML response
        $body = (string)$response->getBody();
        if (strpos($body, '</body>') !== false) {
            $customScripts = '';
            if (DebugBar::$suppressJquery) {
                // Move scripts after the latest script
                $matches = [];
                preg_match_all('/<script(.*) src="(.*)\/debugbar\/(.*)"><\/script>/', $body, $matches);
                $body = preg_replace('/<script(.*) src="(.*)\/debugbar\/(.*)"><\/script>\n/', '', $body);
                $customScripts = implode("\n", $matches[0]);
            }

            $scriptsToInsert = $customScripts . "\n" . $script;
            // You can use a placeholder if somehow you have a </body> string somewhere else in the body
            // @link https://github.com/lekoala/silverstripe-debugbar/issues/154
            $debugbarPlaceholder = "<!-- debugbar -->";
            if (strpos($body, $debugbarPlaceholder) !== false) {
                $body = str_replace($debugbarPlaceholder, $scriptsToInsert, $body);
            } else {
                if (Requirements::get_write_js_to_body()) {
                    // Replace the last occurence of </body>
                    $pos = strrpos($body, '</body>');
                    if ($pos !== false) {
                        $body = substr_replace($body, $scriptsToInsert . '</body>', $pos, strlen('</body>'));
                    }
                } else {
                    // Replace the first occurence of </head>
                    $pos = strpos($body, '</head>');
                    if ($pos !== false) {
                        $body = substr_replace($body, $scriptsToInsert . '</head>', $pos, strlen('</head>'));
                    }
                }
            }
            $response->setBody($body);
        }

        // Ajax support
        if (Director::is_ajax() && !headers_sent()) {
            if (DebugBar::isAdminUrl() && !DebugBar::config()->get('enabled_in_admin')) {
                return;
            }
            // Skip anything that is not a GET request
            if (!$request->isGET()) {
                return;
            }
            // Always enable in admin because everything is mostly loaded through ajax
            if (DebugBar::config()->get('ajax') || DebugBar::isAdminUrl()) {
                $headers = $debugbar->getDataAsHeaders();

                // Prevent throwing js errors in case header size is too large
                if (is_array($headers)) {
                    $maxHeaderLength = DebugBar::config()->get('max_header_length');
                    $debugbar->sendDataInHeaders(null, 'phpdebugbar', $maxHeaderLength);
                }
            }
        }
    }
}
