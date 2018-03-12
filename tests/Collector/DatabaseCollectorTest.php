<?php

namespace LeKoala\DebugBar\Test\Collector;

use LeKoala\DebugBar\Collector\DatabaseCollector;
use LeKoala\DebugBar\DebugBar;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

class DatabaseCollectorTest extends SapphireTest
{
    /**
     * @var DatabaseCollector
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
        $this->assertInstanceOf(DatabaseCollector::class, $this->collector);
    }

    public function testCollect()
    {
        // Update the limit
        Config::modify()->set(DebugBar::class, 'query_limit', 500);

        // Deliberately high warning threshold
        Config::modify()->set(DebugBar::class, 'warn_dbqueries_threshold_seconds', 200);
        $result = $this->collector->collect();

        $this->assertGreaterThan(1, $result['nb_statements']);
        $this->assertEquals(0, $result['nb_failed_statements']);

        // This should be equal if below the limit
        $this->assertCount($result['nb_statements'], $result['statements']);

        // Make sure each statement has all its required details
        $statement = array_shift($result['statements']);
        $this->assertNotEmpty($statement['sql']);
        $this->assertEquals(1, $statement['is_success']);
        $this->assertNotEmpty($statement['source']);
        $this->assertFalse($statement['warn']);

        // Deliberately low warning threshold
        Config::modify()->set(DebugBar::class, 'warn_dbqueries_threshold_seconds', 0.0000001);
        $result = $this->collector->collect();

        $this->assertNotEmpty($result['statements']);
        $statement = array_shift($result['statements']);
        $this->assertTrue($statement['warn']);
    }

    public function testGetWidgets()
    {
        $expected = array(
            'database' => array(
                'icon' => 'database',
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
        $config = $this->collector->getAssets();

        $this->assertArrayHasKey('base_path', $config);
        $this->assertArrayHasKey('base_url', $config);
        $this->assertArrayHasKey('css', $config);
        $this->assertArrayHasKey('js', $config);

        $this->assertFileExists(implode(DIRECTORY_SEPARATOR, [BASE_PATH, $config['base_path'], $config['css']]));
        $this->assertFileExists(implode(DIRECTORY_SEPARATOR, [BASE_PATH, $config['base_path'], $config['js']]));
    }
}
