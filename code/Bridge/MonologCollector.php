<?php

namespace LeKoala\DebugBar\Bridge;

use DebugBar\DataCollector\DataCollectorInterface;
use DebugBar\DataCollector\MessagesAggregateInterface;
use DebugBar\DataCollector\Renderable;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * A monolog handler as well as a data collector. Based on DebugBar\Bridge\MonologCollector
 * Note: This class is a temporary solution to keep existing dependencies working.
 * As soon as maximebf/php-debugbar is updated and compatible with monolog/monolog:^3
 * this class should be deprecated and the DebugBar\Bridge\MonologCollector class should be used instead of this
 * <code>
 * $debugbar->addCollector(new MonologCollector($logger));
 * </code>
 */
class MonologCollector extends AbstractProcessingHandler implements DataCollectorInterface, Renderable, MessagesAggregateInterface
{
    protected $name;

    protected $records = [];

    /**
     * @param Logger $logger
     * @param int $level
     * @param boolean $bubble
     * @param string $name
     */
    public function __construct(?Logger $logger = null, $level = Logger::DEBUG, $bubble = true, $name = 'monolog')
    {
        parent::__construct($level, $bubble);
        $this->name = $name;
        if ($logger !== null) {
            $this->addLogger($logger);
        }
    }

    /**
     * Adds logger which messages you want to log
     *
     * @param Logger $logger
     */
    public function addLogger(Logger $logger)
    {
        $logger->pushHandler($this);
    }

    protected function write(LogRecord $record): void
    {
        $this->records[] = [
            'message' => $record['formatted'],
            'is_string' => true,
            'label' => strtolower($record['level_name']),
            'time' => $record['datetime']->format('U')
        ];
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->records;
    }

    /**
     * @return array
     */
    public function collect()
    {
        return [
            'count' => count($this->records),
            'records' => $this->records
        ];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getWidgets()
    {
        $name = $this->getName();
        return [
            $name => [
                "icon" => "suitcase",
                "widget" => "PhpDebugBar.Widgets.MessagesWidget",
                "map" => "$name.records",
                "default" => "[]"
            ],
            "$name:badge" => [
                "map" => "$name.count",
                "default" => "null"
            ]
        ];
    }
}
