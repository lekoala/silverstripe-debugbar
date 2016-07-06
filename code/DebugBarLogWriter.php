<?php
require_once 'Zend/Log/Writer/Abstract.php';

/**
 * Custom writer for SS_Log
 */
class DebugBarLogWriter extends Zend_Log_Writer_Abstract
{

    /**
     * @param array|\Zend_Config $config
     * @return DebugBarLogWriter
     */
    public static function factory($config)
    {
        return new DebugBarLogWriter();
    }

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

        $level = $event['priorityName'];

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
        $message = "$level - {$str} ({$relfile}:{$line})";

        // Escape \ for proper js display
        $message = str_replace('\\', '\\\\', $message);

        $messagesCollector->addMessage($message, false);
    }
}