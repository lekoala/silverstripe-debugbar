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

    public function testInitIsWorking()
    {
        // De we have a debugbar instance
        $this->assertNotEmpty(DebugBar::getDebugBar());

        // Do we have a logger?

        /* @var $logger SS_ZendLog */
        $logger = SS_Log::get_logger();
        $found = false;
        foreach ($logger->getWriters() as $writer) {
            if ($writer instanceof DebugBarLogWriter) {
                $found = true;
            }
        }
        $this->assertTrue($found);

        // Do we have a db proxy
        if (method_exists('DB', 'get_conn')) {
            $conn = DB::get_conn();
        } else {
            $conn = DB::getConn();
        }

        $class = get_class($conn);
        $this->assertContains($class, ['DebugBarDatabaseNewProxy', 'DebugBarDatabaseProxy']);
    }

    public function testLHelper()
    {
        $msg = 'Test me';
        l($msg);

        $debugbar = DebugBar::getDebugBar();

        /* @var $messagesCollector  DebugBar\DataCollector\MessagesCollector  */
        $messagesCollector = $debugbar['messages'];
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

        // Passing a SapphireTest as first arg prevent exit
        d($this, 'test', $sql);

        $content = ob_get_clean();

        $this->assertTrue((bool) strpos($content, "Value for: 'test'"), "Value for test not found");
        $this->assertTrue((bool) strpos($content, 'sf-dump'), "Symfony dumper not found");
        $this->assertTrue((bool) strpos($content, '<span style="font-weight:bold;">SELECT</span>'), "Sql formatted query not found");
    }

    public function testShowOnHomepage()
    {
        $content = file_get_contents(Director::absoluteBaseURL());

        $this->assertTrue((bool) strpos($content, '"/debugbar/assets/debugbar.js'), "Base script not found");
        $this->assertTrue((bool) strpos($content, 'var phpdebugbar = new PhpDebugBar.DebugBar();'), "Init script not found");
    }
}
