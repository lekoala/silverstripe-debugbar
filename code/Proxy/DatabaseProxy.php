<?php

namespace LeKoala\DebugBar\Proxy;

use LeKoala\DebugBar\DebugBar;
use SqlFormatter;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\Connect\Database;
use SilverStripe\ORM\Connect\DBConnector;
use SilverStripe\ORM\Connect\DBSchemaManager;
use SilverStripe\ORM\Connect\DBQueryBuilder;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\ListDecorator;
use SilverStripe\ORM\Map;
use SilverStripe\ORM\Queries\SQLExpression;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\View\SSViewer;
use SilverStripe\View\SSViewer_DataPresenter;
use SilverStripe\View\SSViewer_Scope;
use SilverStripe\View\ViewableData;

/**
 * A proxy database to log queries
 *
 * @author Koala
 */
class DatabaseProxy extends Database
{
    /** @var MySQLDatabase */
    protected $realConn;
    protected $findSource;

    /** @var array */
    protected $queries;
    protected $connector;
    protected $schemaManager;
    protected $queryBuilder;
    protected $showQueries = false;

    /**
     * @param Database|array $realConn
     */
    public function __construct($realConn)
    {
        $this->realConn      = $realConn;
        $this->connector     = $realConn->getConnector();
        $this->schemaManager = $realConn->getSchemaManager();
        $this->queryBuilder  = $realConn->getQueryBuilder();
        $this->queries       = array();
        $this->findSource    = DebugBar::config()->find_source;
    }

    public function getShowQueries()
    {
        return $this->showQueries;
    }

    public function setShowQueries($showQueries)
    {
        $this->showQueries = $showQueries;
        return $this;
    }

    /**
     * Get the current connector
     *
     * @return DBConnector
     */
    public function getConnector()
    {
        return $this->realConn->getConnector();
    }

    /**
     * Injector injection point for connector dependency
     *
     * @param DBConnector $connector
     */
    public function setConnector(DBConnector $connector)
    {
        parent::setConnector($connector);
        $this->realConn->setConnector($connector);
    }

    /**
     * Returns the current schema manager
     *
     * @return DBSchemaManager
     */
    public function getSchemaManager()
    {
        return $this->realConn->getSchemaManager();
    }

    /**
     * Injector injection point for schema manager
     *
     * @param DBSchemaManager $schemaManager
     */
    public function setSchemaManager(DBSchemaManager $schemaManager)
    {
        parent::setSchemaManager($schemaManager);
        $this->realConn->setSchemaManager($schemaManager);
    }

    /**
     * Returns the current query builder
     *
     * @return DBQueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->realConn->getQueryBuilder();
    }

    /**
     * Injector injection point for schema manager
     *
     * @param DBQueryBuilder $queryBuilder
     */
    public function setQueryBuilder(DBQueryBuilder $queryBuilder)
    {
        parent::setQueryBuilder($queryBuilder);
        $this->realConn->setQueryBuilder($queryBuilder);
    }

    /**
     * Determines if the query should be previewed, and thus interrupted silently.
     * If so, this function also displays the query via the debuging system.
     * Subclasess should respect the results of this call for each query, and not
     * execute any queries that generate a true response.
     *
     * @param string $sql The query to be executed
     * @return boolean Flag indicating that the query was previewed
     */
    protected function previewWrite($sql)
    {
        // Only preview if previewWrite is set, we are in dev mode, and
        // the query is mutable
        if (isset($_REQUEST['previewwrite']) && Director::isDev() && $this->connector->isQueryMutable($sql)
        ) {
            // output preview message
            Debug::message("Will execute: $sql");
            return true;
        } else {
            return false;
        }
    }

    /**
     * Allows the display and benchmarking of queries as they are being run
     *
     * @param string $sql Query to run, and single parameter to callback
     * @param callable $callback Callback to execute code
     * @param array $parameters
     * @return mixed Result of query
     */
    protected function benchmarkQuery($sql, $callback, $parameters = array())
    {
        $starttime   = microtime(true);
        $startmemory = memory_get_usage(true);

        if (is_array($sql)) {
            $parameters = $sql[1];
            $sql        = $sql[0];
        }

        if ($this->showQueries && Director::isDev()) {
            $starttime = microtime(true);
            $result    = $callback($sql);
            $endtime   = round(microtime(true) - $starttime, 4);

            $formattedSql = SqlFormatter::format($sql);
            $rows         = $result->numRecords();
            echo '<pre>The following query took <b>'.$endtime.'</b>s an returned <b>'.$rows."</b> row(s) \n";
            echo 'Triggered by: <i>'.$this->findSource().'</i></pre>';
            echo $formattedSql;

            $results = iterator_to_array($result);
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

            $handle = $result;
            $handle->rewind(); // Rewind the results
        } else {
            /* @var $handle PDOQuery */
            $handle = $callback($sql);
        }
        $endtime   = microtime(true);
        $endmemory = memory_get_usage(true);

        $rawsql = $sql;

        // Prepared query are not so readable
        if (!empty($parameters)) {
            foreach ($parameters as $param) {
                $pos = strpos($sql, '?');
                if ($pos !== false) {
                    $param = '"'.$param.'"';
                    $sql   = substr_replace($sql, $param, $pos, 1);
                }
            }
        }


        // Sometimes, ugly spaces are there
        $sql = preg_replace('/[[:blank:]]+/', ' ', trim($sql));

        $shortsql = $sql;

        // Sometimes, the select statement can be very long and unreadable
        $matches = null;
        preg_match_all('/SELECT(.+?) FROM/is', $sql, $matches);
        $select  = empty($matches[1]) ? null : trim($matches[1][0]);
        if (strlen($select) > 100) {
            $shortsql = str_replace($select, '"ClickToShowFields"', $sql);
        } else {
            $select = null;
        }

        $this->queries[] = array(
            'raw_query' => $rawsql,
            'short_query' => $shortsql,
            'select' => $select,
            'query' => $sql,
            'start_time' => $starttime,
            'end_time' => $endtime,
            'duration' => $endtime - $starttime,
            'memory' => $endmemory - $startmemory,
            'rows' => $handle ? $handle->numRecords() : null,
            'success' => $handle ? true : false,
            'database' => $this->getSelectedDatabase(),
            'source' => $this->findSource ? $this->findSource() : null
        );
        return $handle;
    }

    protected function findSource()
    {
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);

        // Not relevant to determine source
        $internalClasses = array(
            DB::class, SQLExpression::class, DataList::class, DataObject::class,
            DataQuery::class, SQLSelect::class, 'SQLQuery', Map::class, ListDecorator::class, 'Object'
        );

        $viewerClasses = array(
            SSViewer_DataPresenter::class, SSViewer_Scope::class, SSViewer::class,
            ViewableData::class
        );

        $sources = array();
        foreach ($traces as $trace) {
            $class    = isset($trace['class']) ? $trace['class'] : null;
            $line     = isset($trace['line']) ? $trace['line'] : null;
            $function = isset($trace['function']) ? $trace['function'] : null;
            $type     = isset($trace['type']) ? $trace['type'] : '::';

            /* @var $object SSViewer */
            $object = isset($trace['object']) ? $trace['object'] : null;

            if (!$class) {
                continue;
            }
            if ($function && $function == '{closure}') {
                continue;
            }
            if (strpos($class, DebugBar::class) === 0) {
                continue;
            }
            if (in_array($class, $internalClasses)) {
                continue;
            }
            if (in_array($class, $viewerClasses)) {
                if ($function == 'includeGeneratedTemplate') {
                    $templates = $object->templates();

                    $template = null;
                    if (isset($templates['main'])) {
                        $template = basename($templates['main']);
                    } else {
                        $keys = array_keys($templates);
                        $key  = reset($keys);
                        if (isset($templates[$key])) {
                            $template = $key.':'.basename($templates[$key]);
                        }
                    }
                    if (!empty($template)) {
                        $sources[] = $template;
                    }
                }
                continue;
            }

            $name = $class;
            if ($function) {
                $name .= $type.$function;
            }
            if ($line) {
                $name .= ':'.$line;
            }

            $sources[] = $name;

            if (count($sources) > 3) {
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

    /**
     * Execute the given SQL query.
     *
     * @param string $sql The SQL query to execute
     * @param int $errorLevel The level of error reporting to enable for the query
     * @return SS_Query
     */
    public function query($sql, $errorLevel = E_USER_ERROR)
    {
        // Check if we should only preview this query
        if ($this->previewWrite($sql)) {
            return;
        }

        if (!$this->connector) {
            return $this->benchmarkQuery(
                $sql,
                function ($sql) use ($errorLevel) {
                    return $this->oldQuery($sql, $errorLevel);
                }
            );
        }

        // Benchmark query
        return $this->benchmarkQuery(
            $sql,
            function ($sql) use ($errorLevel) {
                return $this->connector->query($sql, $errorLevel);
            }
        );
    }

    public function oldQuery($sql, $errorLevel = E_USER_ERROR)
    {
        return $this->realConn->query($sql, $errorLevel);
    }

    /**
     * Execute the given SQL parameterised query with the specified arguments
     *
     * @param string $sql The SQL query to execute. The ? character will denote parameters.
     * @param array $parameters An ordered list of arguments.
     * @param int $errorLevel The level of error reporting to enable for the query
     * @return SS_Query
     */
    public function preparedQuery($sql, $parameters, $errorLevel = E_USER_ERROR)
    {
        // Check if we should only preview this query
        if ($this->previewWrite($sql)) {
            return;
        }

        // Benchmark query
        $connector = $this->connector;
        return $this->benchmarkQuery(
            array($sql, $parameters),
            function ($sql) use ($connector, $parameters, $errorLevel) {
                return $connector->preparedQuery($sql, $parameters, $errorLevel);
            }
        );
    }

    /**
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->realConn, $name), $arguments);
    }

    public function addslashes($val)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function alterTable(
        $table,
        $newFields = null,
        $newIndexes = null,
        $alteredFields = null,
        $alteredIndexes = null,
        $alteredOptions = null,
        $advancedOptions = null
    ) {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function comparisonClause(
        $field,
        $value,
        $exact = false,
        $negate = false,
        $caseSensitive = false,
        $parameterised = false
    ) {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function createDatabase()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function createField($table, $field, $spec)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function createTable(
        $table,
        $fields = null,
        $indexes = null,
        $options = null,
        $advancedOptions = null
    ) {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function datetimeDifferenceClause($date1, $date2)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function datetimeIntervalClause($date, $interval)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function enumValuesForField($tableName, $fieldName)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function fieldList($table)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function formattedDatetimeClause($date, $format)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function getConnect($parameters)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function getGeneratedID($table)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function hasTable($tableName)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function isActive()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function renameField($tableName, $oldName, $newName)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function renameTable($oldTableName, $newTableName)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function supportsTimezoneOverride()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function supportsTransactions()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function tableList()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function transactionEnd($chain = false)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function transactionRollback($savepoint = false)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function transactionSavepoint($savepoint)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function transactionStart($transaction_mode = false, $session_characteristics = false)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function clearTable($table)
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function getDatabaseServer()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function now()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function random()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function searchEngine(
        $classesToSearch,
        $keywords,
        $start,
        $pageLength,
        $sortBy = "Relevance DESC",
        $extraFilter = "",
        $booleanSearch = false,
        $alternativeFileFilter = "",
        $invertedMatch = false
    ) {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }

    public function supportsCollations()
    {
        return call_user_func_array(
            array($this->realConn, __FUNCTION__),
            func_get_args()
        );
    }
}
