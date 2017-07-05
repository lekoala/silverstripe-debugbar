<?php

/**
 * A proxy database to log queries (compatible with 3.1)
 *
 * @author Koala
 * @deprecated 1.0.0 Please upgrade to at least SilverStripe 3.2. Use DebugBarDatabaseNewProxy.
 * @codeCoverageIgnore
 */
class DebugBarDatabaseProxy extends SS_Database
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
     * @param MySQLDatabase $realConn
     */
    public function __construct($realConn)
    {
        $this->realConn   = $realConn;
        $this->queries    = array();
        $this->findSource = DebugBar::config()->find_source;
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

        if ($this->showQueries && Director::isDev()) {
            $starttime    = microtime(true);
            $result       = $callback($sql);
            $endtime      = round(microtime(true) - $starttime, 4);

            $formattedSql = JdornSqlFormatter::format($sql);
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
                        dump(implode(',', (array_map(function ($item) {
                            return $item[key($item)];
                        }, $results))));
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
            /* @var $handle MySQLQuery  */
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
            'database' => $this->currentDatabase(),
            'source' => $this->findSource ? $this->findSource() : null
        );
        return $handle;
    }

    protected function findSource()
    {
        $traces = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);

        // Not relevant to determine source
        $internalClasses = array(
            'DB', 'SQLExpression', 'DataList', 'DataObject',
            'DataQuery', 'SQLSelect', 'SQLQuery', 'SS_Map', 'SS_ListDecorator', 'Object'
        );

        $viewerClasses = array(
            'SSViewer_DataPresenter', 'SSViewer_Scope', 'SSViewer',
            'ViewableData'
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
            if (strpos($class, 'DebugBar') === 0) {
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
                    if ($template) {
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
            $self = $this;
            return $this->benchmarkQuery(
                $sql,
                function ($sql) use ($self, $errorLevel) {
                    return $self->oldQuery($sql, $errorLevel);
                }
            );
        }

        // Benchmark query
        $connector = $this->connector;
        return $this->benchmarkQuery(
            $sql,
            function ($sql) use ($connector, $errorLevel) {
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
        //return call_user_func_array([$this->realConn, __FUNCTION__],            func_get_args());
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

    public function transactionStart(
        $transaction_mode = false,
        $session_characteristics = false
    ) {

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
        return "mysql";
    }

    public function now()
    {
        return 'NOW()';
    }

    public function random()
    {
        return 'RAND()';
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

        if (!class_exists('SiteTree')) {
            throw new Exception('MySQLDatabase->searchEngine() requires "SiteTree" class');
        }
        if (!class_exists('File')) {
            throw new Exception('MySQLDatabase->searchEngine() requires "File" class');
        }

        $keywords           = $this->escapeString($keywords);
        $htmlEntityKeywords = htmlentities($keywords, ENT_NOQUOTES, 'UTF-8');

        $extraFilters = array('SiteTree' => '', 'File' => '');

        if ($booleanSearch) {
            $boolean = "IN BOOLEAN MODE";
        }

        if ($extraFilter) {
            $extraFilters['SiteTree'] = " AND $extraFilter";

            if ($alternativeFileFilter) {
                $extraFilters['File'] = " AND $alternativeFileFilter";
            } else {
                $extraFilters['File'] = $extraFilters['SiteTree'];
            }
        }

        // Always ensure that only pages with ShowInSearch = 1 can be searched
        $extraFilters['SiteTree'] .= " AND ShowInSearch <> 0";

        // File.ShowInSearch was added later, keep the database driver backwards compatible
        // by checking for its existence first
        $fields = $this->fieldList('File');
        if (array_key_exists('ShowInSearch', $fields)) {
            $extraFilters['File'] .= " AND ShowInSearch <> 0";
        }

        $limit = $start.", ".(int) $pageLength;

        $notMatch = $invertedMatch ? "NOT " : "";
        if ($keywords) {
            $match['SiteTree'] = "
				MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('$keywords' $boolean)
				+ MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('$htmlEntityKeywords' $boolean)
			";
            $match['File'] = "MATCH (Filename, Title, Content) AGAINST ('$keywords' $boolean) AND ClassName = 'File'";

            // We make the relevance search by converting a boolean mode search into a normal one
            $relevanceKeywords = str_replace(array('*', '+', '-'), '', $keywords);
            $htmlEntityRelevanceKeywords = str_replace(array('*', '+', '-'), '', $htmlEntityKeywords);
            $relevance['SiteTree']       = "MATCH (Title, MenuTitle, Content, MetaDescription) "
                ."AGAINST ('$relevanceKeywords') "
                ."+ MATCH (Title, MenuTitle, Content, MetaDescription) AGAINST ('$htmlEntityRelevanceKeywords')";
            $relevance['File']           = "MATCH (Filename, Title, Content) AGAINST ('$relevanceKeywords')";
        } else {
            $relevance['SiteTree'] = $relevance['File']     = 1;
            $match['SiteTree']     = $match['File']         = "1 = 1";
        }

        // Generate initial DataLists and base table names
        $lists       = array();
        $baseClasses = array('SiteTree' => '', 'File' => '');
        foreach ($classesToSearch as $class) {
            $lists[$class] = DataList::create($class)->where($notMatch . $match[$class] . $extraFilters[$class], "");
            $baseClasses[$class] = '"' . $class . '"';
        }

        $charset = Config::inst()->get('MySQLDatabase', 'charset');

        // Make column selection lists
        $select = array(
            'SiteTree' => array(
                "ClassName", "$baseClasses[SiteTree].\"ID\"", "ParentID",
                "Title", "MenuTitle", "URLSegment", "Content",
                "LastEdited", "Created",
                "Filename" => "_{$charset}''", "Name" => "_{$charset}''",
                "Relevance" => $relevance['SiteTree'], "CanViewType"
            ),
            'File' => array(
                "ClassName", "$baseClasses[File].\"ID\"", "ParentID",
                "Title", "MenuTitle" => "_{$charset}''", "URLSegment" => "_{$charset}''",
                "Content",
                "LastEdited", "Created",
                "Filename", "Name",
                "Relevance" => $relevance['File'], "CanViewType" => "NULL"
            ),
        );

        // Process and combine queries
        $querySQLs       = array();
        $queryParameters = array();
        $totalCount      = 0;
        foreach ($lists as $class => $list) {
            $query = $list->dataQuery()->query();

            // There's no need to do all that joining
            $replacedString = str_replace(array('"', '`'), '', $baseClasses[$class]);
            $query->setFrom(array($replacedString => $baseClasses[$class]));
            $query->setSelect($select[$class]);
            $query->setOrderBy(array());

            $querySQLs[]     = $query->sql($parameters);
            $queryParameters = array_merge($queryParameters, $parameters);

            $totalCount += $query->unlimitedRowCount();
        }
        $fullQuery = implode(" UNION ", $querySQLs)." ORDER BY $sortBy LIMIT $limit";

        // Get records
        $records = $this->preparedQuery($fullQuery, $queryParameters);

        $objects = array();

        foreach ($records as $record) {
            $objects[] = new $record['ClassName']($record);
        }

        $list = new PaginatedList(new ArrayList($objects));
        $list->setPageStart($start);
        $list->setPageLength($pageLength);
        $list->setTotalItems($totalCount);

        // The list has already been limited by the query above
        $list->setLimitItems(false);

        return $list;
    }

    public function supportsCollations()
    {
        return true;
    }
}
