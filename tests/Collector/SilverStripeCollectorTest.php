<?php

namespace LeKoala\DebugBar\Test\Collector;

use LeKoala\DebugBar\DebugBar;
use LeKoala\DebugBar\Collector\SilverStripeCollector;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Manifest\VersionProvider;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

class SilverStripeCollectorTest extends SapphireTest
{
    /**
     * @var SilverStripeCollector
     */
    protected $collector;

    protected $usesDatabase = true;

    public function setUp()
    {
        parent::setUp();
        DebugBar::initDebugBar();
        $this->collector = DebugBar::getDebugBar()->getCollector('silverstripe');
    }

    public function tearDown()
    {
        DebugBar::clearDebugBar();
        $this->collector = null;

        parent::tearDown();
    }

    public function testCollectorExists()
    {
        $this->assertInstanceOf(SilverStripeCollector::class, $this->collector);
    }

    public function testCollect()
    {
        $this->logInWithPermission('ADMIN');
        Config::modify()->set(VersionProvider::class, 'modules', [
            'silverstripe/framework' => 'Framework',
            'silverstripe/cms' => 'CMS',
        ]);
        $data = $this->collector->collect();
        $this->assertArrayHasKey('debug', $data);
        $this->assertArrayHasKey('locale', $data);
        $this->assertArrayHasKey('parameters', $data);
        $this->assertArrayHasKey('templates', $data);
        // TODO: see how to make this test relevant
        // $this->assertContains('Framework', $data['version']);
        $this->assertSame(SiteConfig::class, $data['config']['ClassName']);
        $this->assertSame('User, ADMIN', $data['user']);
        $this->assertCount(0, $data['requirements']['list']);

        $this->logOut();

        $data = $this->collector->collect();
        $this->assertSame('Not logged in', $data['user']);
    }

    public function testShowRequirements()
    {
        Requirements::css('debugbar/assets/debugbar.css');
        $data = $this->collector->collect();
        $this->assertArrayHasKey('requirements', $data);
        $this->assertNotEmpty($data['requirements']['list']);
        $this->assertGreaterThan(0, $data['requirements']['count']);
        $this->assertArrayHasKey('debugbar.css', $data['requirements']['list']);
    }

    public function testShowRequestParameters()
    {
        $controller = new Controller;
        $controller->doInit();
        $controller->setRequest(
            new HTTPRequest(
                'GET',
                '/',
                array('getvar' => 'value', 'foo' => 'bar'),
                array('postvar' => 'value', 'bar' => 'baz')
            )
        );
        $controller->getRequest()->setRouteParams(array('something' => 'here'));

        $this->collector->setController($controller);
        $this->assertSame($controller, $this->collector->getController());

        $result = SilverStripeCollector::getRequestParameters();
        $this->assertSame('value', $result['GET - getvar']);
        $this->assertSame('baz', $result['POST - bar']);
        $this->assertSame('here', $result['ROUTE - something']);
    }

    public function testGetSessionData()
    {
        Controller::curr()->getRequest()->getSession()->set('DebugBarTesting', 'test value');
        $result = SilverStripeCollector::getSessionData();
        $this->assertSame('test value', $result['DebugBarTesting']);
    }

    public function testGetConfigData()
    {
        $result = SilverStripeCollector::getConfigData();
        $this->assertSame(SiteConfig::class, $result['ClassName']);
        $this->assertArrayHasKey('Title', $result);
        $this->assertArrayHasKey('ID', $result);
        $this->assertArrayHasKey('Created', $result);
    }

    public function testGetWidgets()
    {
        $this->logInWithPermission('ADMIN');
        $this->collector->collect();
        $result = $this->collector->getWidgets();

        $expectedKeys = [
            'user',
            'version',
            'locale',
            'parameters',
            'requirements',
            'templates'
        ];

        foreach ($expectedKeys as $expectedKey) {
            $this->assertArrayHasKey($expectedKey, $result, "$expectedKey not found in widgets");
        }
        $this->logOut();
    }

    public function testGetAssets()
    {
        $config = $this->collector->getAssets();

        $this->assertArrayHasKey('base_path', $config);
        $this->assertArrayHasKey('base_url', $config);
        $this->assertArrayHasKey('css', $config);
        $this->assertArrayHasKey('js', $config);
        // No CSS for this one
        $this->assertFileExists(implode(DIRECTORY_SEPARATOR, [BASE_PATH, $config['base_path'], $config['js']]));
    }
}
