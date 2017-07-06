<?php

namespace LeKoala\DebugBar\Test\Proxy;

use LeKoala\DebugBar\Proxy\DatabaseProxy;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Connect\DBConnector;
use SilverStripe\ORM\Connect\DBSchemaManager;
use SilverStripe\ORM\Connect\DBQueryBuilder;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\Connect\MySQLiConnector;
use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\ORM\Connect\MySQLQueryBuilder;
use SilverStripe\ORM\DB;

class DatabaseProxyTest extends SapphireTest
{
    /**
     * @var DebugBarDatabaseProxy
     */
    protected $proxy;

    public function setUp()
    {
        parent::setUp();
        $this->proxy = new DatabaseProxy(DB::get_conn());
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
        $this->assertInstanceOf(DBConnector::class, $this->proxy->getConnector());
        $connector = new MySQLiConnector;
        $this->proxy->setConnector($connector);
        $this->assertSame($connector, $this->proxy->getConnector());
    }

    public function testGetAndSetDatabaseSchemaManager()
    {
        $this->assertInstanceOf(DBSchemaManager::class, $this->proxy->getSchemaManager());
        $manager = new MySQLSchemaManager;
        $this->proxy->setSchemaManager($manager);
        $this->assertSame($manager, $this->proxy->getSchemaManager());
    }

    public function testGetAndSetQueryBuilder()
    {
        $this->assertInstanceOf(DBQueryBuilder::class, $this->proxy->getQueryBuilder());
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
        $proxyMethods = array(
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
        );

        $mockConnection = $this->getMockBuilder(MySQLDatabase::class)->setMethods($proxyMethods)->getMock();
        $proxy = new DatabaseProxy($mockConnection);

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
        $newProxy = new DatabaseProxy($databaseConfig);
        $this->assertInstanceOf(DBConnector::class, $newProxy->getConnector());
    }
}
