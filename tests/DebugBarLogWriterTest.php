<?php

class DebugBarLogWriterTest extends SapphireTest
{
    /**
     * Init manually because we are running tests
     */
    public function setUp()
    {
        parent::setUp();
        DebugBar::initDebugBar();
    }

    /**
     * Prevent global state from affecting each test by resetting the SS_Log writers to avoid duplicates and
     * clear the state of the debug bar
     */
    public function tearDown()
    {
        SS_Log::clear_writers();
        DebugBar::clearDebugBar();
        parent::tearDown();
    }

    /**
     * Do we have a logger?
     */
    public function testDebugBarLoggerIsAdded()
    {
        $writers = SS_Log::get_writers();
        $found = false;
        foreach ($writers as $writer) {
            if ($writer instanceof DebugBarLogWriter) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'DebugBarLogWriter was not added to SS_Log\'s log writers');
    }

    public function testFactory()
    {
        $instance = DebugBarLogWriter::factory(array('foo'));
        $this->assertInstanceOf('DebugBarLogWriter', $instance);
    }

    public function testLogLevels()
    {
        $debugBar = DebugBar::getDebugBar();

        $collector = $debugBar->getCollector('messages');
        $this->assertInstanceOf('DebugBar\DataCollector\MessagesCollector', $collector);
        $collector->clear();

        SS_Log::log('This is a notice', SS_Log::NOTICE);
        $messages = $collector->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame('info', $messages[0]['label']);
        $this->assertContains('This is a notice', $messages[0]['message']);

        SS_Log::log('This is a warning', SS_Log::WARN);
        $messages = $collector->getMessages();
        $this->assertCount(2, $messages);
        $this->assertSame('warning', $messages[1]['label']);
        $this->assertContains('This is a warning', $messages[1]['message']);

        SS_Log::log('This is an error', SS_Log::ERR);
        $messages = $collector->getMessages();
        $this->assertCount(3, $messages);
        $this->assertSame('error', $messages[2]['label']);
        $this->assertContains('This is an error', $messages[2]['message']);
    }

    public function testBackslashesAreEscaped()
    {
        $debugBar = DebugBar::getDebugBar();
        $collector = $debugBar->getCollector('messages');
        $collector->clear();

        SS_Log::log('There was an error in \Some\Namespaced\Class', SS_Log::NOTICE);
        $messages = $collector->getMessages();
        $message = array_pop($messages);
        $this->assertContains('\\\\Some\\\\Namespaced\\\\Class', $message['message']);
    }
}
