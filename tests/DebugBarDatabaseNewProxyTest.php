<?php

class DebugBarDatabaseNewProxyTest extends SapphireTest
{
    /**
     * @var DebugBarDatabaseNewProxy
     */
    protected $proxy;

    public function setUp()
    {
        parent::setUp();
        $this->proxy = new DebugBarDatabaseNewProxy(DB::get_conn());
    }

    public function testGetAndSetShowQueries()
    {
        $this->proxy->setShowQueries(true);
        $this->assertTrue($this->proxy->getShowQueries());
        $this->proxy->setShowQueries(false);
        $this->assertFalse($this->proxy->getShowQueries());
    }

    public function testGetAndSetConnectors()
    {
        $this->assertInstanceOf('DBConnector', $this->proxy->getConnector());
        $connector = new MySQLiConnector;
        $this->proxy->setConnector($connector);
        $this->assertSame($connector, $this->proxy->getConnector());
    }

    public function testGetAndSetDatabaseSchemaManager()
    {
        $this->assertInstanceOf('DBSchemaManager', $this->proxy->getSchemaManager());
        $manager = new MySQLSchemaManager;
        $this->proxy->setSchemaManager($manager);
        $this->assertSame($manager, $this->proxy->getSchemaManager());
    }

    public function testGetAndSetQueryBuilder()
    {
        $this->assertInstanceOf('DBQueryBuilder', $this->proxy->getQueryBuilder());
        $queryBuilder = new MySQLQueryBuilder;
        $this->proxy->setQueryBuilder($queryBuilder);
        $this->assertSame($queryBuilder, $this->proxy->getQueryBuilder());
    }

    /**
     * Test method to ensure that a set of methods are proxied through to the real connection. This test covers
     * all methods listed below:
     */
    public function testMethodsAreProxiedToRealConnection()
    {
        $proxyMethods = [
            'addslashes',
            'alterTable',
            'comparisonClause',
            'createDatabase',
            'createField',
            'createTable',
            'datetimeDifferenceClause',
            'datetimeIntervalClause',
            'enumValuesForField',
            'fieldList',
            'formattedDatetimeClause',
            'getConnect',
            'getGeneratedID',
            'hasTable',
            'isActive',
            'renameField',
            'renameTable',
            'supportsTimezoneOverride',
            'supportsTransactions',
            'tableList',
            'transactionEnd',
            'transactionRollback',
            'transactionSavepoint',
            'transactionStart',
            'clearTable',
            'getDatabaseServer',
            'now',
            'random',
            'searchEngine',
            'supportsCollations',
        ];

        $mockConnection = $this->getMockBuilder('MySQLDatabase')->setMethods($proxyMethods)->getMock();
        $proxy = new DebugBarDatabaseNewProxy($mockConnection);

        foreach ($proxyMethods as $proxyMethod) {
            $mockConnection->expects($this->once())->method($proxyMethod);
            // Pass mock arguments - the large number is in searchEngine
            $proxy->$proxyMethod(null, null, null, null, null, null, null, null);
        }
    }

    /**
     * Test whether passing an array to the constructor still produces a DBConnector instance
     */
    public function testConstructorArguments()
    {
        global $databaseConfig;
        $newProxy = new DebugBarDatabaseNewProxy($databaseConfig);
        $this->assertInstanceOf('DBConnector', $newProxy->getConnector());
    }
}
