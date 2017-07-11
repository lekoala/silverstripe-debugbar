<?php

namespace LeKoala\DebugBar;

use DebugBar\OpenHandler;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Controller as BaseController;
use SilverStripe\Control\HTTPRequest;

/**
 * A open handler controller for DebugBar
 *
 * @author Koala
 */
class Controller extends BaseController
{
    public function index(HTTPRequest $request)
    {
        if (!DebugBar::config()->enable_storage) {
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
