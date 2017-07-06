<?php

namespace LeKoala\DebugBar\Test;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;

class ControllerTest extends FunctionalTest
{
    public function tearDown()
    {
        parent::tearDown();
        DebugBar::clearDebugBar();
    }

    public function testErrorWhenStorageIsDisabled()
    {
        Config::modify()->set(DebugBar::class, 'enable_storage', false);
        $result = $this->get('/__debugbar');
        $this->assertEquals(404, $result->getStatusCode());
        $this->assertEquals('Storage not enabled', (string) $result->getBody());
    }

    public function testErrorWhenModuleIsDisabled()
    {
        Config::modify()->set(DebugBar::class, 'disabled', true);
        $result = $this->get('/__debugbar');
        $this->assertEquals(404, $result->getStatusCode());
        $this->assertEquals('DebugBar not enabled', (string) $result->getBody());
    }
}
