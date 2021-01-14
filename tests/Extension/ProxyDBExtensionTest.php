<?php

namespace LeKoala\DebugBar\Test\Extension;

use SilverStripe\Dev\SapphireTest;
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
        ProxyDBExtension::resetQueries();
    }
}
