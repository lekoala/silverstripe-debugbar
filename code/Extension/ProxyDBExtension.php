<?php

namespace LeKoala\DebugBar\Extension;

use SilverStripe\ORM\DB;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\Core\Extension;
use SilverStripe\Control\Controller;
use TractorCow\ClassProxy\Generators\ProxyGenerator;
use LeKoala\DebugBar\DebugBarUtils;

class ProxyDBExtension extends Extension
{
    const MAX_FIND_SOURCE_LEVEL = 3;

    /**
     * Store queries
     *
     * @var array<array<mixed>>
     */
    protected static $queries = [];

    /**
     * Find source toggle (set by config find_source)
     *
     * @var ?bool
     */
    protected static $findSource = null;

    /**
     * @param ProxyGenerator $proxy
     * @return void
     */
    public function updateProxy(ProxyGenerator &$proxy)
    {
        if (DebugBar::getDebugBar() === false) {
            return;
        }
        if (self::$findSource === null) {
            self::$findSource = DebugBar::config()->get('find_source');
        }

        // In the closure, $this is the proxied database
        $callback = function ($args, $next) {

            // The first argument is always the sql query
            $sql = $args[0];
            $parameters = isset($args[2]) ? $args[2] : [];

            // Sql can be an array
            if (is_array($sql)) {
                $parameters = $sql[1];
                $sql = $sql[0];
            }

            // Inline sql
            $sql = DB::inline_parameters($sql, $parameters);

            // Get time and memory for the request
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);

            // Execute all middleware
            $handle = $next(...$args);

            // Get time and memory after the request
            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            // Show query on screen
            if (DebugBar::getShowQueries()) {
                $formattedSql = DebugBarUtils::formatSql($sql);
                $rows = $handle->numRecords();

                echo '<pre>The following query took <b>' . round($endTime - $startTime, 4) . '</b>s an returned <b>' . $rows . "</b> row(s) \n";
                echo 'Triggered by: <i>' . self::findSource() . '</i></pre>';
                echo $formattedSql;

                // Preview results
                $results = iterator_to_array($handle);
                if ($rows > 0) {
                    if ($rows == 1) {
                        dump($results[0]);
                    } else {
                        $linearValues = count($results[0]);
                        if ($linearValues) {
                            dump(implode(
                                ',',
                                array_map(
                                    function ($item) {
                                        return $item[key($item)];
                                    },
                                    $results
                                )
                            ));
                        } else {
                            if ($rows < 20) {
                                dump($results);
                            } else {
                                dump("Too many results to display");
                            }
                        }
                    }
                }
                echo '<hr/>';

                $handle->rewind(); // Rewind the results
            }

            // Sometimes, ugly spaces are there
            $sql = preg_replace('/[[:blank:]]+/', ' ', trim($sql));

            // Sometimes, the select statement can be very long and unreadable
            $shortsql = $sql;
            $matches = null;
            preg_match_all('/SELECT(.+?) FROM/is', $sql, $matches);
            $select = empty($matches[1]) ? null : trim($matches[1][0]);
            if ($select !== null) {
                if (strlen($select) > 100) {
                    $shortsql = str_replace($select, '"ClickToShowFields"', $sql);
                } else {
                    $select = null;
                }
            }

            // null on the first query, since it's the select statement itself
            $db = DB::get_conn()->getSelectedDatabase();

            self::$queries[] = [
                'short_query' => $shortsql,
                'select' => $select,
                'query' => $sql,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration' => $endTime - $startTime,
                'memory' => $endMemory - $startMemory,
                'rows' => $handle ? $handle->numRecords() : null,
                'success' => $handle ? true : false,
                'database' => $db,
                'source' => self::$findSource ? self::findSource() : null
            ];

            return $handle;
        };

        // Attach to benchmarkQuery to fire on both query and preparedQuery
        $proxy = $proxy->addMethod('benchmarkQuery', $callback);
    }

    /**
     * Reset queries array
     *
     * Helpful for long running process and avoid accumulating queries
     *
     * @return void
     */
    public static function resetQueries()
    {
        self::$queries = [];
    }

    /**
     * @return array<array<mixed>>
     */
    public static function getQueries()
    {
        return self::$queries;
    }

    /**
     * @param string $str
     * @return void
     */
    public static function addCustomQuery(string $str)
    {
        self::$queries[] = [
            'short_query' => $str,
            'select' => null,
            'query' => $str,
            'start_time' => 0,
            'end_time' => 0,
            'duration' => 0,
            'memory' => 0,
            'rows' => null,
            'success' => true,
            'database' => null,
            'source' => null
        ];
    }

    /**
     * @return string
     */
    protected static function findSource()
    {
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);

        // Not relevant to determine source
        $internalClasses = [
            '',
            get_called_class(),
            // DebugBar
            DebugBar::class,
            \LeKoala\DebugBar\Middleware\DebugBarMiddleware::class,
            // Proxy
            ProxyDBExtension::class,
            \TractorCow\ClassProxy\Proxied\ProxiedBehaviour::class,
            // Orm
            \SilverStripe\ORM\Connect\Database::class,
            \SilverStripe\ORM\Connect\DBSchemaManager::class,
            \SilverStripe\ORM\Connect\MySQLDatabase::class,
            \SilverStripe\ORM\Connect\MySQLSchemaManager::class,
            \SilverStripe\ORM\DataObjectSchema::class,
            \SilverStripe\ORM\DB::class,
            \SilverStripe\ORM\Queries\SQLExpression::class,
            \SilverStripe\ORM\DataList::class,
            \SilverStripe\ORM\DataObject::class,
            \SilverStripe\ORM\DataQuery::class,
            \SilverStripe\ORM\Queries\SQLSelect::class,
            \SilverStripe\ORM\Map::class,
            \SilverStripe\ORM\ListDecorator::class,
            // Core
            \SilverStripe\Control\Director::class,
        ];

        $viewerClasses = [
            \SilverStripe\View\SSViewer_DataPresenter::class,
            \SilverStripe\View\SSViewer_Scope::class,
            \SilverStripe\View\SSViewer::class,
            \LeKoala\DebugBar\Proxy\SSViewerProxy::class,
            \SilverStripe\View\ViewableData::class
        ];

        $sources = [];
        foreach ($traces as $i => $trace) {
            // We need to be able to look ahead one item in the trace, because the class/function values
            // are talking about what is being *called* on this line, not the function this line lives in.
            if (!isset($traces[$i + 1])) {
                break;
            }

            $file = isset($trace['file']) ? pathinfo($trace['file'], PATHINFO_FILENAME) : null;
            $class = isset($traces[$i + 1]['class']) ? $traces[$i + 1]['class'] : null;
            $line = isset($trace['line']) ? $trace['line'] : null;
            $function = isset($traces[$i + 1]['function']) ? $traces[$i + 1]['function'] : null;
            $type = isset($traces[$i + 1]['type']) ? $traces[$i + 1]['type'] : '::';

            /* @var $object SSViewer */
            $object = isset($traces[$i + 1]['object']) ? $traces[$i + 1]['object'] : null;

            if (in_array($class, $internalClasses)) {
                continue;
            }

            // Viewer classes need special handling
            if (in_array($class, $viewerClasses)) {
                if ($function == 'includeGeneratedTemplate') {
                    $templates = $object->templates();

                    $template = null;
                    if (isset($templates['main'])) {
                        $template = basename($templates['main']);
                    } else {
                        $keys = array_keys($templates);
                        $key = reset($keys);
                        if (isset($templates[$key])) {
                            $template = $key . ':' . basename($templates[$key]);
                        }
                    }
                    if (!empty($template)) {
                        $sources[] = $template;
                    }
                }
                continue;
            }

            $name = $class;
            if ($class && !DebugBar::config()->get('show_namespaces')) {
                $nameArray = explode("\\", $class);
                $name = array_pop($nameArray);

                // Maybe we are inside a trait?
                if ($file && $file != $name) {
                    $name .= '(' . $file . ')';
                }
            }
            if ($function) {
                $name .= $type . $function;
            }
            if ($line) {
                // Line number could apply to a trait
                $name .= ':' . $line;
            }

            $sources[] = $name;

            if (count($sources) > self::MAX_FIND_SOURCE_LEVEL) {
                break;
            }

            // We reached a Controller, exit loop
            if ($object && $object instanceof Controller) {
                break;
            }
        }

        if (empty($sources)) {
            return 'Undefined source';
        }
        return implode(' > ', $sources);
    }
}
