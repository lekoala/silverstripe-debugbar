<?php

class DatabaseCollectorTest extends SapphireTest
{
    /**
     * @var DebugBarSilverStripeCollector
     */
    protected $collector;

    protected $usesDatabase = true;

    public function setUp()
    {
        parent::setUp();

        DebugBar::initDebugBar();
        $this->collector = DebugBar::getDebugBar()->getCollector('db');
    }

    public function testCollectorExists()
    {
        $this->assertInstanceOf('DebugBarDatabaseCollector', $this->collector);
    }

    public function testCollect()
    {
        // Deliberately high warning threshold
        Config::inst()->update('DebugBar', 'warn_dbqueries_threshold_seconds', 200);
        $result = $this->collector->collect();

        $this->assertGreaterThan(1, $result['nb_statements']);
        $this->assertEquals(0, $result['nb_failed_statements']);
        $this->assertCount($result['nb_statements'], $result['statements']);

        $statement = array_shift($result['statements']);
        $this->assertNotEmpty($statement['sql']);
        $this->assertEquals(1, $statement['is_success']);
        $this->assertContains('PHPUnit_Framework_TestCase', $statement['source']);
        $this->assertFalse($statement['warn']);

        // Deliberately low warning threshold
        Config::inst()->update('DebugBar', 'warn_dbqueries_threshold_seconds', 0.0000001);
        $result = $this->collector->collect();

        $this->assertNotEmpty($result['statements']);
        $statement = array_shift($result['statements']);
        $this->assertTrue($statement['warn']);
    }

    public function testGetWidgets()
    {
        $expected = array(
            'database' => array(
                'icon' => 'inbox',
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'db',
                'default' => '[]'
            ),
            'database:badge' => array(
                'map' => 'db.nb_statements',
                'default' => 0
            )
        );

        $this->assertSame($expected, $this->collector->getWidgets());
    }

    public function testGetAssets()
    {
        $expected = array(
            'base_path' => '/debugbar/javascript',
            'base_url' => 'debugbar/javascript',
            'css' => 'sqlqueries/widget.css',
            'js' => 'sqlqueries/widget.js'
        );

        $this->assertSame($expected, $this->collector->getAssets());
    }
}
