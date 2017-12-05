<?php

namespace LeKoala\DebugBar\Collector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use LeKoala\DebugBar\DebugBar;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\ORM\DB;

/**
 * Collects data about SQL statements executed through the DatabaseProxy
 */
class DatabaseCollector extends DataCollector implements Renderable, AssetProvider
{
    /**
     * @var TimeDataCollector
     */
    protected $timeCollector;

    /**
     * @var SS_Database
     */
    protected $db;

    /**
     * @return array
     */
    public function collect()
    {
        // Gather the database connector at the last minute in case it has been replaced by other modules
        $this->db = DB::get_conn();
        $this->timeCollector = DebugBar::getDebugBar()->getCollector('time');

        $data = $this->collectData($this->timeCollector);

        // Check for excessive number of queries
        $dbQueryWarningLevel = DebugBar::config()->get('warn_query_limit');
        if ($dbQueryWarningLevel && $data['nb_statements'] > $dbQueryWarningLevel) {
            $helpLink = DebugBar::config()->get('performance_guide_link');
            $messages = DebugBar::getDebugBar()->getCollector('messages');
            $messages->info(
                'This page ran more than ' . $dbQueryWarningLevel . ' database queries. You could reduce this by '
                    . 'implementing caching. For more information, <a href="' . $helpLink . '" target="_blank">'
                    . 'click here.</a>'
            );
        }

        return $data;
    }

    /**
     * Explode comma separated elements not within parenthesis or quotes
     *
     * @param string $str
     * @return array
     */
    protected static function explodeFields($str)
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

        $limit   = DebugBar::config()->get('query_limit');
        $warnDurationThreshold = DebugBar::config()->get('warn_dbqueries_threshold_seconds');

        $showDb = count(array_unique(array_map(function ($stmt) {
                return $stmt['database'];
        }, $queries))) > 1;

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
                'database' => $showDb ? $stmt['database'] : null,
                'source' => $stmt['source'],
                'warn' => $stmt['duration'] > $warnDurationThreshold
            );

            if ($timeCollector !== null) {
                $timeCollector->addMeasure(
                    $stmt['short_query'],
                    $stmt['start_time'],
                    $stmt['end_time']
                );
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
            'base_path' => '/' . ModuleLoader::getModule('lekoala/silverstripe-debugbar')->getResource('javascript')->getRelativePath(),
            'base_url' => Director::makeRelative(ModuleLoader::getModule('lekoala/silverstripe-debugbar')->getResource('javascript')->getURL()),
            'css' => 'sqlqueries/widget.css',
            'js' => 'sqlqueries/widget.js'
        );
    }
}
