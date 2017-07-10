<?php

namespace LeKoala\DebugBar\Messages;

use Monolog\Handler\AbstractProcessingHandler;

/**
 * The log handler class replaces {@link SilverStripe\Logging\HTTPOutputHandler} to ensure that log messages can
 * be stored and redirect back to the debug bar "Messages" tab.
 */
class LogHandler extends AbstractProcessingHandler
{
    /**
     * @var array
     */
    protected $logs = [];

    /**
     * Push the provided logs into a storage array
     *
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        if (isset($record['level_name']) && isset($record['message'])) {
            $this->logs[$record['level_name']] = $record['message'];
        }
    }
}
