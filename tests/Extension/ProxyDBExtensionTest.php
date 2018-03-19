<?php
namespace LeKoala\DebugBar\Test\Extension;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\Connect\DBConnector;
use SilverStripe\ORM\Connect\DBSchemaManager;
use SilverStripe\ORM\Connect\DBQueryBuilder;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\Connect\MySQLiConnector;
use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\ORM\Connect\MySQLQueryBuilder;
use SilverStripe\ORM\DB;
use LeKoala\DebugBar\Extension\ProxyDBExtension;

class ProxyDBExtensionTest extends SapphireTest
{
    /**
     * @var SilverStripe\ORM\Connect\Database
     */
    protected $conn;

    public function setUp()
    {
        parent::setUp();
        $this->conn = DB::get_conn();
    }

    public function testQueriesAreCollected()
    {
        $this->assertNotEmpty(ProxyDBExtension::getQueries());
    }
}
