<?php

namespace LeKoala\DebugBar;

use DebugBar\OpenHandler;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;

/**
 * A open handler controller for DebugBar
 *
 * @author Koala
 */
class DebugBarController extends Controller
{
    public function index(HTTPRequest $request)
    {
        if (!DebugBar::config()->get('enable_storage')) {
            return $this->httpError(404, 'Storage not enabled');
        }
        $debugbar = DebugBar::getDebugBar();
        if (!$debugbar) {
            return $this->httpError(404, 'DebugBar not enabled');
        }
        $openHandler = new OpenHandler($debugbar);
        $openHandler->handle();
        exit(); // Handle will echo and set headers
    }
}
