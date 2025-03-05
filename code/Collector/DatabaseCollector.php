<?php

namespace LeKoala\DebugBar\Collector;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Control\Director;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use LeKoala\DebugBar\Extension\ProxyDBExtension;

/**
 * Collects data about SQL statements executed through the proxied behaviour
 */
class DatabaseCollector extends DataCollector implements Renderable, AssetProvider
{
    /**
     * @var TimeDataCollector
     */
    protected $timeCollector;

    /**
     * @return array
     */
    public function collect()
    {
        $this->timeCollector = DebugBar::getDebugBar()->getCollector('time');

        $data = $this->collectData($this->timeCollector);

        // Check for excessive number of queries
        $dbQueryWarningLevel = DebugBar::config()->get('warn_query_limit');
        if ($dbQueryWarningLevel && $data['nb_statements'] > $dbQueryWarningLevel) {
            $helpLink = DebugBar::config()->get('performance_guide_link');
            $messages = DebugBar::getMessageCollector();
            if ($messages) {
                $messages->addMessage(
                    "This page ran more than $dbQueryWarningLevel database queries." .
                        "\nYou could reduce this by implementing caching." .
                        "\nYou can find more info here: $helpLink",
                    'warning',
                    true
                );
            }
        }

        if (isset($data['show_db']) && $data['show_db']) {
            $showDbWarning = DebugBar::config()->get('show_db_warning');
            $messages = DebugBar::getMessageCollector();
            if ($messages && $showDbWarning) {
                $messages->addMessage(
                    "Consider enabling `optimistic_connect` to avoid an extra query. You can turn this warning off by setting show_db_warning = false",
                    'warning',
                    true
                );
            }
        }

        if (isset($data['has_unclosed_transaction']) && $data['has_unclosed_transaction']) {
            $messages = DebugBar::getMessageCollector();
            if ($messages) {
                $messages->addMessage(
                    "There is an unclosed transaction on your page and some statement may not be persisted to the database",
                    'warning',
                    true
                );
            }
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
    protected function collectData(?TimeDataCollector $timeCollector = null)
    {
        $stmts = [];

        $total_duration = 0;
        $total_mem = 0;

        $failed = 0;

        $i = 0;

        // Get queries gathered by proxy
        $queries = ProxyDBExtension::getQueries();

        $limit = DebugBar::config()->get('query_limit');
        $warnDurationThreshold = DebugBar::config()->get('warn_dbqueries_threshold_seconds');

        // only show db if there is more than one database in use
        $showDb = count(array_filter(array_unique(array_map(function ($stmt) {
            return $stmt['database'];
        }, $queries)))) > 1;

        $showDb = false;
        $hasUnclosedTransaction = false;
        foreach ($queries as $stmt) {
            $i++;

            $total_duration += $stmt['duration'];
            $total_mem += $stmt['memory'];

            if (!$stmt['success']) {
                $failed++;
            }

            if (str_starts_with((string) $stmt['short_query'], 'SHOW DATABASES LIKE')) {
                $showDb = true;
            }

            if (str_contains((string) $stmt['short_query'], 'START TRANSACTION')) {
                $hasUnclosedTransaction = true;
            }
            if (str_contains((string) $stmt['short_query'], 'END TRANSACTION')) {
                $hasUnclosedTransaction = false;
            }

            if ($limit && $i > $limit) {
                $stmts[] = [
                    'sql' => "Only the first $limit queries are shown"
                ];
                break;
            }

            $stmts[] = [
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
            ];

            if ($timeCollector !== null) {
                $timeCollector->addMeasure(
                    $stmt['short_query'],
                    $stmt['start_time'],
                    $stmt['end_time']
                );
            }
        }

        // Save as CSV
        $db_save_csv = DebugBar::config()->get('db_save_csv');
        if ($db_save_csv && !empty($queries)) {
            $filename = date('Ymd_His') . '_' . count($queries) . '_' . uniqid() . '.csv';
            $isOutput = false;
            if (isset($_REQUEST['downloadqueries']) && Director::isDev()) {
                $isOutput = true;
                if (headers_sent()) {
                    die('Cannot download queries, headers are already sent');
                }
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment;filename=' . $filename);
                $fp = fopen('php://output', 'w');
            } else {
                $tempFolder = TEMP_FOLDER . '/debugbar/db';
                if (!is_dir($tempFolder)) {
                    mkdir($tempFolder, 0755, true);
                }
                $fp = fopen($tempFolder . '/' . $filename, 'w');
            }
            $headers = array_keys($queries[0]);
            fputcsv($fp, $headers);
            foreach ($queries as $query) {
                fputcsv($fp, $query);
            }
            fclose($fp);

            if ($isOutput) {
                die();
            }
        }

        return [
            'nb_statements' => count($queries),
            'nb_failed_statements' => $failed,
            'show_db' => $showDb,
            'has_unclosed_transaction' => $hasUnclosedTransaction,
            'statements' => $stmts,
            'accumulated_duration' => $total_duration,
            'accumulated_duration_str' => $this->getDataFormatter()->formatDuration($total_duration),
            'memory_usage' => $total_mem,
            'memory_usage_str' => $this->getDataFormatter()->formatBytes($total_mem),
        ];
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
        return [
            "database" => [
                "icon" => "database",
                "widget" => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map" => "db",
                "default" => "[]"
            ],
            "database:badge" => [
                "map" => "db.nb_statements",
                "default" => 0
            ]
        ];
    }

    /**
     * @return array
     */
    public function getAssets()
    {
        return [
            'base_path' => '/' . DebugBar::moduleResource('javascript')->getRelativePath(),
            'base_url' => Director::makeRelative(DebugBar::moduleResource('javascript')->getURL()),
            'css' => 'sqlqueries/widget.css',
            'js' => 'sqlqueries/widget.js'
        ];
    }
}
