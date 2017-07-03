<?php

/**
 * Tests for DebugBar
 */
class DebugBarTest extends SapphireTest
{
    public function setUp()
    {
        parent::setUp();

        // Init manually because we are running tests
        DebugBar::initDebugBar();
    }

    public function tearDown()
    {
        DebugBar::clearDebugBar();

        parent::tearDown();
    }

    public function testInitIsWorking()
    {
        // De we have a debugbar instance
        $this->assertNotEmpty(DebugBar::getDebugBar());

        // Do we have a db proxy
        if (method_exists('DB', 'get_conn')) {
            $conn = DB::get_conn();
        } else {
            $conn = DB::getConn();
        }

        $class = get_class($conn);
        $this->assertContains($class, array('DebugBarDatabaseNewProxy', 'DebugBarDatabaseProxy'));
    }

    public function testLHelper()
    {
        $msg = 'Test me';
        l($msg);

        $debugbar = DebugBar::getDebugBar();

        /* @var $messagesCollector  DebugBar\DataCollector\MessagesCollector  */
        $messagesCollector = $debugbar->getCollector('messages');
        $messages = $messagesCollector->getMessages();
        $found = false;
        foreach ($messages as $message) {
            $txt = $message['message'];
            if (strpos($txt, $msg) !== false) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testDHelper()
    {
        $this->markTestSkipped(
            'This test needs to be looked at again, the output buffering is not capturing the result'
        );

        $sql = 'SELECT * FROM Member';
        ob_start();
        // Passing a SapphireTest as first arg prevent exit
        d($this, 'test', $sql);
        $content = ob_get_clean();
        $this->assertTrue((bool) strpos($content, "Value for: 'test'"), "Value for test not found");
        $this->assertTrue((bool) strpos($content, 'sf-dump'), "Symfony dumper not found");
        $this->assertTrue((bool) strpos($content, '<span style="font-weight:bold;">SELECT</span>'), "Sql formatted query not found");
    }

    /**
     * @param callable $context
     * @param string   $expected
     * @dataProvider whyDisabledProvider
     */
    public function testWhyDisabled($context, $expected)
    {
        $context();
        $this->assertSame($expected, DebugBar::WhyDisabled());
    }

    /**
     * @return array[]
     */
    public function whyDisabledProvider()
    {
        return array(
            array(
                function () {
                    Director::set_environment_type('live');
                },
                'Not in dev mode'
            ),
            array(
                function () {
                    Config::inst()->update('DebugBar', 'disabled', true);
                },
                'Disabled by a constant or configuration'
            ),
            array(
                function () {
                    // no-op
                },
                'In CLI mode'
            )
        );
    }

    public function testNotLocalIp()
    {
        Config::inst()->update('DebugBar', 'check_local_ip', false);
        $this->assertFalse(DebugBar::NotLocalIp());

        Config::inst()->update('DebugBar', 'check_local_ip', true);
        $original = $_SERVER['REMOTE_ADDR'];
        $_SERVER['REMOTE_ADDR'] = '123.456.789.012';
        $this->assertTrue(DebugBar::NotLocalIp());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertFalse(DebugBar::NotLocalIp());

        unset($_SERVER['REMOTE_ADDR']);
        $this->assertFalse(DebugBar::NotLocalIp());

        $_SERVER['REMOTE_ADDR'] = $original;
    }
}
