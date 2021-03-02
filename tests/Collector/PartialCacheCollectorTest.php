<?php

namespace LeKoala\DebugBar\Test\Collector;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\Dev\SapphireTest;
use LeKoala\DebugBar\Collector\PartialCacheCollector;

class PartialCacheCollectorTest extends SapphireTest
{
    /**
     * @var PartialCacheCollector
     */
    private $collector;

    protected function setUp()
    {
        parent::setUp();
        $this->collector = new PartialCacheCollector();
    }

    public function tearDown()
    {
        DebugBar::clearDebugBar();
        $this->collector = null;

        parent::tearDown();
    }

    public function testGetName()
    {
        $this->assertNotEmpty($this->collector->getName());
    }

    /**
     * Tests that the tab is returned and that the badge is optional
     */
    public function testGetWidgets()
    {
        PartialCacheCollector::setTemplateCache(array());
        $widgets = $this->collector->getWidgets();
        $this->assertArrayHasKey('Partial Cache', $widgets);
        $this->assertArrayNotHasKey('Partial Cache:badge', $widgets);

        PartialCacheCollector::addTemplateCache('test', array('test'));
        $this->assertArrayHasKey('Partial Cache:badge', $this->collector->getWidgets());
    }

    /**
     * Tests that an array is returned with specific indexes set
     */
    public function testCollect()
    {
        $result = $this->collector->collect();
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('calls', $result);
    }

    /**
     * Tests that adding an item to the cache increases its count
     */
    public function testAddTemplateCache()
    {
        $count = count(PartialCacheCollector::getTemplateCache());
        PartialCacheCollector::addTemplateCache('test1234', array('test'));
        $this->assertCount($count + 1, PartialCacheCollector::getTemplateCache());
    }

    /**
     * Tests that the setter works
     */
    public function testSetTemplateCache()
    {
        PartialCacheCollector::setTemplateCache(array());
        $this->assertCount(0, PartialCacheCollector::getTemplateCache());
    }
}
