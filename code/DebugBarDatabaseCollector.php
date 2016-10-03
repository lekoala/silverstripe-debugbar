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
     * @return array
     */
    public function collect()
    {
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

        $i       = 0;
        $queries = $this->db->getQueries();
        $limit   = DebugBar::config()->query_limit;

        foreach ($queries as $stmt) {
            $i++;

            $total_duration += $stmt['duration'];
            $total_mem += $stmt['memory'];

            if (!$stmt['success']) {
                $failed++;
            }

            if ($limit && $i > $limit) {
                $stmts[] = array(
                    'sql' => "Only the first $limit queries are shown"
                );
                break;
            }

            $stmts[] = array(
                'sql' => $stmt['short_query'],
                'row_count' => $stmt['rows'],
                'params' => $stmt['select'] ? $stmt['select'] : null,
                'duration' => $stmt['duration'],
                'duration_str' => $this->getDataFormatter()->formatDuration($stmt['duration']),
                'memory' => $stmt['memory'],
                'memory_str' => $this->getDataFormatter()->formatBytes($stmt['memory']),
                'is_success' => $stmt['success'],
                'database' => $stmt['database'],
                'source' => $stmt['source'],
            );

            if ($timeCollector !== null) {
                $timeCollector->addMeasure($stmt['short_query'],
                    $stmt['start_time'], $stmt['end_time']);
            }
        }

        return array(
            'nb_statements' => count($queries),
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
            'base_path' => '/'.DEBUGBAR_DIR.'/javascript',
            'base_url' => DEBUGBAR_DIR.'/javascript',
            'css' => 'sqlqueries/widget.css',
            'js' => 'sqlqueries/widget.js'
        );
    }
}