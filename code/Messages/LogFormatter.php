<?php

namespace LeKoala\DebugBar\Messages;

use Monolog\Formatter\FormatterInterface;

/**
 * Formats incoming log messages for display in the debug bar
 */
class LogFormatter implements FormatterInterface
{
    public function format(array $record)
    {
        return $record['message'];
    }

    public function formatBatch(array $records)
    {
        return implode("\n", array_map(array($this, 'format'), $records));
    }
}
