<?php

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\TimeDataCollector;

/**
 * Collects data about SQL statements executed through the DatabaseProxy
 */
class DebugBarDatabaseCollector extends DataCollector implements Renderable, AssetProvider
{
    protected $timeCollector;
    protected $renderSqlWithParams = false;
    protected $sqlQuotationChar    = '<>';
    protected $db;

    /**
     * @param TimeDataCollector $timeCollector
     */
    public function __construct(SS_Database $db,
                                TimeDataCollector $timeCollector = null)
    {
        $this->db            = $db;
        $this->timeCollector = $timeCollector;
    }

    /**
     * Renders the SQL of traced statements with params embeded
     *
     * @param boolean $enabled
     */
    public function setRenderSqlWithParams($enabled = true,
                                           $quotationChar = '<>')
    {
        $this->renderSqlWithParams = $enabled;
        $this->sqlQuotationChar    = $quotationChar;
    }

    /**
     * @return bool
     */
    public function isSqlRenderedWithParams()
    {
        return $this->renderSqlWithParams;
    }

    /**
     * @return string
     */
    public function getSqlQuotationChar()
    {
        return $this->sqlQuotationChar;
    }

    /**
     * @return array
     */
    public function collect()
    {
        $data = array(
            'nb_statements' => 0,
            'statements' => array()
        );

        $data = $this->collectData($this->timeCollector);

        return $data;
    }

    /**
     * Explode comma separated elements not within parenthesis or quotes
     *
     * @param string $str
     * @return array
     */
    protected static function explode_fields($str)
    {
        return preg_split("/(?![^(]*\)),/", $str);
    }

    /**
     * Collects data
     *
     * @param TimeDataCollector $timeCollector
     * @return array
     */
    protected function collectData(TimeDataCollector $timeCollector = null)
    {
        $stmts = array();

        $total_duration = 0;
        $total_mem      = 0;

        $failed = 0;

        foreach ($this->db->getQueries() as $stmt) {
            $total_duration += $stmt['duration'];
            $total_mem += $stmt['memory'];

            $stmts[] = array(
                'sql' => $stmt['short_query'],
                'row_count' => $stmt['rows'],
                'params' => $stmt['select'] ? self::explode_fields($stmt['select'])
                        : null,
                'duration' => $stmt['duration'],
                'duration_str' => $this->getDataFormatter()->formatDuration($stmt['duration']),
                'memory' => $stmt['memory'],
                'memory_str' => $this->getDataFormatter()->formatBytes($stmt['memory']),
                'is_success' => $stmt['success'],
                'connection' => $stmt['database'],
                'stmt_id' => $stmt['stmt_id'],
            );

            if (!$stmt['success']) {
                $failed++;
            }

            if ($timeCollector !== null) {
                $timeCollector->addMeasure($stmt['short_query'],
                    $stmt['start_time'], $stmt['end_time']);
            }
        }

        return array(
            'nb_statements' => count($stmts),
            'nb_failed_statements' => $failed,
            'statements' => $stmts,
            'accumulated_duration' => $total_duration,
            'accumulated_duration_str' => $this->getDataFormatter()->formatDuration($total_duration),
            'memory_usage' => $total_mem,
            'memory_usage_str' => $this->getDataFormatter()->formatBytes($total_mem),
        );
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'db';
    }

    /**
     * @return array
     */
    public function getWidgets()
    {
        return array(
            "database" => array(
                "icon" => "inbox",
                "widget" => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map" => "db",
                "default" => "[]"
            ),
            "database:badge" => array(
                "map" => "db.nb_statements",
                "default" => 0
            )
        );
    }

    /**
     * @return array
     */
    public function getAssets()
    {
        return array(
            'css' => 'widgets/sqlqueries/widget.css',
            'js' => 'widgets/sqlqueries/widget.js'
        );
    }
}