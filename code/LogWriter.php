<?php

namespace LeKoala\DebugBar;

use DebugBar;
use Director;


/**
 * Custom writer for SS_Log
 */
class LogWriter implements Monolog\Handler\HandlerInterface
{
    /**
     * Map SS_Log levels to MessagesCollector levels
     *
     * @var array
     */
    protected $levelsMap = array(
        'NOTICE' => 'info',
        'WARN' => 'warning',
        'ERR' => 'error'
    );

    /**
     * The default log level to use if not recognised (MessagesCollector format)
     *
     * @var string
     */
    const LOG_LEVEL_DEFAULT = 'info';

    /**
     * @param array $event
     */
    public function _write($event)
    {
        $debugbar = DebugBar::getDebugBar();

        if (!$debugbar) {
            return;
        }

        /* @var $messagesCollector DebugBar\DataCollector\MessagesCollector */
        $messagesCollector = $debugbar['messages'];
        if (!$messagesCollector) {
            return;
        }

        $level = $this->convertLogLevel($event['priorityName']);

        // Gather info
        if (isset($event['message']['errstr'])) {
            $str     = $event['message']['errstr'];
            $file    = $event['message']['errfile'];
            $line    = $event['message']['errline'];
        } else {
            $str     = $event['message']['function'];
            $file    = $event['message']['file'];
            $line    = isset($event['message']['line']) ? $event['message']['line'] : 0;
        }

        $relfile = Director::makeRelative($file);

        // Save message
        $message = "{$str} ({$relfile}:{$line})";

        // Escape \ for proper js display
        $message = str_replace('\\', '\\\\', $message);

        $messagesCollector->addMessage($message, $level);
    }

    /**
     * Convert a SilverStripe log level (see SS_Log) to a MessagesCollector level
     *
     * @param  string $input SS_Log level
     * @return string        MessagesCollector level
     */
    protected function convertLogLevel($input)
    {
        if (array_key_exists($input, $this->levelsMap)) {
            return $this->levelsMap[$input];
        }
        return static::LOG_LEVEL_DEFAULT;
    }
}
