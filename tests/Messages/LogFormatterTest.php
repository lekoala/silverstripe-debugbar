<?php

namespace LeKoala\DebugBar\Test\Messages;

use LeKoala\DebugBar\Messages\LogFormatter;
use SilverStripe\Dev\SapphireTest;

class LogFormatterTest extends SapphireTest
{
    public function testFormat()
    {
        $this->assertSame(
            'I am a log message',
            (new LogFormatter)->format(['foo' => 'bar', 'message_level' => 'blah', 'message' => 'I am a log message'])
        );
    }

    public function testFormatBatch()
    {
        $this->assertSame(
            "foo\nbar\nbaz",
            (new LogFormatter)->formatBatch([
                ['message' => 'foo'],
                ['message' => 'bar'],
                ['message' => 'baz'],
            ])
        );
    }
}
