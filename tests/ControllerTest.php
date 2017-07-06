<?php

class DebugBarControllerTest extends FunctionalTest
{
    public function tearDown()
    {
        parent::tearDown();
        DebugBar::clearDebugBar();
    }

    public function testErrorWhenStorageIsDisabled()
    {
        Config::inst()->update('DebugBar', 'enable_storage', false);
        $result = $this->get('/__debugbar');
        $this->assertEquals(404, $result->getStatusCode());
        $this->assertEquals('Storage not enabled', (string) $result->getBody());
    }

    public function testErrorWhenModuleIsDisabled()
    {
        Config::inst()->update('DebugBar', 'disabled', true);
        $result = $this->get('/__debugbar');
        $this->assertEquals(404, $result->getStatusCode());
        $this->assertEquals('DebugBar not enabled', (string) $result->getBody());
    }
}
