<?php

namespace LeKoala\DebugBar\Test\Extension;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;
use LeKoala\DebugBar\Extension\ProxyDBExtension;
use LeKoala\DebugBar\DebugBar;

class ProxyDBExtensionTest extends SapphireTest
{
    /**
     * @var SilverStripe\ORM\Connect\Database
     */
    protected $conn;

    public function setUp(): void
    {
        parent::setUp();
        $this->conn = DB::get_conn();
        DebugBar::initDebugBar();
    }

    public function testQueriesAreCollected()
    {
        $res = DebugBar::getDebugBar();
        $this->assertTrue($res !== false);
        DB::query("SELECT 0");
        $this->assertNotEmpty(ProxyDBExtension::getQueries());
        ProxyDBExtension::resetQueries();
    }
}
