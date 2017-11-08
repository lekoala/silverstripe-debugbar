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

    /**
     * @var SilverStripe\ORM\Connect\Database
     */
    protected $realConnection;

    public function setUp()
    {
        parent::setUp();
        $this->realConnection = DB::get_conn();

        // TODO: improve testing on other databases
        if (!$this->realConnection instanceof MySQLDatabase) {
            $this->markTestIncomplete();
        }

        $this->proxy = new DatabaseProxy($this->realConnection);
    }

    public function testGetAndSetShowQueries()
    {
        $this->proxy->setShowQueries(true);
        $this->assertTrue($this->proxy->getShowQueries());
        $this->proxy->setShowQueries(false);
        $this->assertFalse($this->proxy->getShowQueries());
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
}
