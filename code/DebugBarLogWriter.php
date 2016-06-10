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
        $errstr     = $event['message']['errstr'];
        $errfile    = $event['message']['errfile'];
        $errline    = $event['message']['errline'];
        $errcontext = $event['message']['errcontext'];
        $relfile    = Director::makeRelative($errfile);

        // Save message
        $message = "{$errstr} ({$relfile}:{$errline})";

        $messagesCollector->addMessage($level." - ".$message, false);
    }
}