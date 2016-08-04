<?php

/**
 * A proxy database to log queries
 *
 * @author Koala
 */
class DebugBarDatabaseProxy extends SS_Database
{
    /** @var MySQLDatabase */
    protected $realConn;

    /** @var array */
    protected $queries;
    protected $connector;
    protected $schemaManager;
    protected $queryBuilder;

    /**
     * @param MySQLDatabase $realConn
     */
    public function __construct($realConn)
    {
        $this->realConn = $realConn;
        if (method_exists($realConn, 'getConnector')) {
            $this->connector = $this->connector ? : $realConn->getConnector();
        }
        if (method_exists($realConn, 'getSchemaManager')) {
            $this->schemaManager = $this->schemaManager ? : $realConn->getSchemaManager();
        }
        if (method_exists($realConn, 'getQueryBuilder')) {
            $this->queryBuilder = $this->queryBuilder ? : $realConn->getQueryBuilder();
        }
        $this->queries = [];
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
        return $this->realConn->setConnector($connector);
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
        return $this->realConn->setSchemaManager($schemaManager);
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
        return $this->realConn->setQueryBuilder($queryBuilder);
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
     * @return mixed Result of query
     */
    protected function benchmarkQuery($sql, $callback)
    {
        $starttime   = microtime(true);
        $startmemory = memory_get_usage(true);

        if (isset($_REQUEST['showqueries']) && Director::isDev()) {
            $starttime = microtime(true);
            $result    = $callback($sql);
            $endtime   = round(microtime(true) - $starttime, 4);
            Debug::message("\n$sql\n{$endtime}s\n", false);
            $handle    = $result;
        } else {
            $handle = $callback($sql);
        }

        $endtime   = microtime(true);
        $endmemory = memory_get_usage(true);


        $rawsql = $sql;

        $select = null;

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

        $this->queries[] = [
            'raw_query' => $rawsql,
            'short_query' => $shortsql,
            'select' => $select,
            'query' => $sql,
            'start_time' => $starttime,
            'end_time' => $endtime,
            'duration' => $endtime - $starttime,
            'memory' => $endmemory - $startmemory
        ];
        return $handle;
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
            $self = $this;
            return $this->benchmarkQuery($sql,
                    function($sql) use($self, $errorLevel) {
                    return $self->oldQuery($sql, $errorLevel);
                });
        }

        // Benchmark query
        $connector = $this->connector;
        return $this->benchmarkQuery(
                $sql,
                function($sql) use($connector, $errorLevel) {
                return $connector->query($sql, $errorLevel);
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
                $sql,
                function($sql) use($connector, $parameters, $errorLevel) {
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
        //return call_user_func_array([$this->realConn, __FUNCTION__],            func_get_args());
        return call_user_func_array([$this->realConn, $name], $arguments);
    }

    public function addslashes($val)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function alterTable($table, $newFields = null, $newIndexes = null,
                               $alteredFields = null, $alteredIndexes = null,
                               $alteredOptions = null, $advancedOptions = null)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function comparisonClause($field, $value, $exact = false,
                                     $negate = false, $caseSensitive = false)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function createDatabase()
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function createField($table, $field, $spec)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function createTable($table, $fields = null, $indexes = null,
                                $options = null, $advancedOptions = null)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function datetimeDifferenceClause($date1, $date2)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function datetimeIntervalClause($date, $interval)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function enumValuesForField($tableName, $fieldName)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    protected function fieldList($table)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function formattedDatetimeClause($date, $format)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function getConnect($parameters)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function getGeneratedID($table)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function hasTable($tableName)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function isActive()
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function renameField($tableName, $oldName, $newName)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function renameTable($oldTableName, $newTableName)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function supportsTimezoneOverride()
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function supportsTransactions()
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    protected function tableList()
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function transactionEnd()
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function transactionRollback($savepoint = false)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function transactionSavepoint($savepoint)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function transactionStart($transaction_mode = false,
                                     $session_characteristics = false)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }

    public function clearTable($table)
    {
        return call_user_func_array([$this->realConn, __FUNCTION__],
            func_get_args());
    }
}