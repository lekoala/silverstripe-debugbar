<?php

namespace LeKoala\DebugBar\Test;

use DebugBar\DataCollector\MessagesCollector;
use LeKoala\DebugBar\Collector\DatabaseCollector;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;

/**
 * Tests for DebugBar
 */
class DebugBarTest extends SapphireTest
{
    public function setUp(): void
    {
        parent::setUp();

        // Init manually because we are running tests
        DebugBar::initDebugBar();
    }

    public function tearDown(): void
    {
        DebugBar::clearDebugBar();

        parent::tearDown();
    }

    public function testInitIsWorking()
    {
        // De we have a debugbar instance
        $this->assertNotEmpty(DebugBar::getDebugBar());
    }

    public function testLHelper()
    {
        $msg = 'Test me';
        l($msg);

        $debugbar = DebugBar::getDebugBar();

        /** @var DebugBar\Bridge\MonologCollector $messagesCollector */
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
        $sql = 'SELECT * FROM Member';
        ob_start();
        d($this, 'test', $sql);
        $content = ob_get_clean();
        $this->assertStringContainsString("Value for: 'test'", $content, "Value for test not found");
    }

    /**
     * @param callable $context
     * @param string   $expected
     * @dataProvider whyDisabledProvider
     */
    public function testWhyDisabled($context, $expected)
    {
        $context();
        $this->assertSame($expected, DebugBar::whyDisabled());
    }

    /**
     * @return array[]
     */
    public static function whyDisabledProvider()
    {
        return array(
            array(
                function () {
                    Injector::inst()->get(Kernel::class)->setEnvironment('live');
                },
                'Not in dev mode'
            ),
            array(
                function () {
                    Config::modify()->set(DebugBar::class, 'disabled', true);
                },
                'Disabled by a constant or configuration'
            ),
            // array(
            //     function () {
            //         // no-op
            //     },
            //     'In CLI mode'
            // )
        );
    }

    public function testNotLocalIp()
    {
        Config::modify()->set(DebugBar::class, 'check_local_ip', false);
        $this->assertFalse(DebugBar::notLocalIp());

        Config::modify()->set(DebugBar::class, 'check_local_ip', true);
        $original = $_SERVER['REMOTE_ADDR'];
        $_SERVER['REMOTE_ADDR'] = '123.456.789.012';
        $this->assertTrue(DebugBar::notLocalIp());
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->assertFalse(DebugBar::notLocalIp());

        unset($_SERVER['REMOTE_ADDR']);
        $this->assertFalse(DebugBar::notLocalIp());

        $_SERVER['REMOTE_ADDR'] = $original;
    }

    /**
     * For the database collector to be able to push messages to the message collector, it must be loaded
     * before the message collector. This test ensures that won't accidentally change in future.
     */
    public function testMessageCollectorIsLoadedAfterDatabaseCollector()
    {
        $bar = DebugBar::getDebugBar();

        $passedDatabaseCollector = false;
        foreach ($bar->getCollectors() as $collector) {
            if ($collector instanceof DatabaseCollector) {
                $passedDatabaseCollector = true;
            }
            if ($collector instanceof MessagesCollector) {
                $this->assertTrue($passedDatabaseCollector, 'Message collector must be after database collector');
                break;
            }
        }
    }
}
