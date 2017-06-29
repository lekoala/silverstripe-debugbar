<?php
/**
 * @coversDefaultClass DebugBarDatabaseNewProxy
 */
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
     *
     * @covers ::addslashes
     * @covers ::alterTable
     * @covers ::comparisonClause
     * @covers ::createDatabase
     * @covers ::createField
     * @covers ::createTable
     * @covers ::datetimeDifferenceClause
     * @covers ::datetimeIntervalClause
     * @covers ::enumValuesForField
     * @covers ::fieldList
     * @covers ::formattedDatetimeClause
     * @covers ::getConnect
     * @covers ::getGeneratedID
     * @covers ::hasTable
     * @covers ::isActive
     * @covers ::renameField
     * @covers ::renameTable
     * @covers ::supportsTimezoneOverride
     * @covers ::supportsTransactions
     * @covers ::tableList
     * @covers ::transactionEnd
     * @covers ::transactionRollback
     * @covers ::transactionSavepoint
     * @covers ::transactionStart
     * @covers ::clearTable
     * @covers ::getDatabaseServer
     * @covers ::now
     * @covers ::random
     * @covers ::searchEngine
     * @covers ::supportsCollations
     */
    public function testMethodsAreProxiedToRealConnection()
    {
        $mockConnection = $this->getMockBuilder('MySQLDatabase')
            ->setMethods(array('fieldList', 'addslashes', 'random'))
            ->getMock();

        $mockConnection->expects($this->once())->method('fieldList');
        $mockConnection->expects($this->once())->method('addslashes');
        $mockConnection->expects($this->once())->method('random');

        $proxy = new DebugBarDatabaseNewProxy($mockConnection);

        $proxy->fieldList('foo');
        $proxy->addslashes('bar');
        $proxy->random();
    }
}
