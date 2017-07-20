<?php

namespace LeKoala\DebugBar\Test\Collector;

use LeKoala\DebugBar\DebugBar;
use LeKoala\DebugBar\Collector\SilverStripeCollector;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\Requirements;

class SilverStripeCollectorTest extends SapphireTest
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
        $this->collector = DebugBar::getDebugBar()->getCollector('silverstripe');
    }

    public function testCollectorExists()
    {
        $this->assertInstanceOf(SilverStripeCollector::class, $this->collector);
    }

    public function testCollect()
    {
        $data = $this->collector->collect();

        $this->assertArrayHasKey('debug', $data);
        $this->assertArrayHasKey('locale', $data);
        $this->assertArrayHasKey('parameters', $data);
        $this->assertArrayHasKey('templates', $data);
        $this->assertContains('Framework', $data['version']);
        $this->assertSame(SiteConfig::class, $data['config']['ClassName']);
        $this->assertSame('User, ADMIN', $data['user']);
        $this->assertCount(0, $data['requirements']);

        Member::currentUser()->logOut();
        $data = $this->collector->collect();
        $this->assertSame('Not logged in', $data['user']);
    }

    public function testShowRequirements()
    {
        Requirements::css('debugbar/assets/debugbar.css');
        $data = $this->collector->collect();
        $this->assertArrayHasKey('requirements', $data);
        $this->assertNotEmpty($data['requirements']);
        $this->assertContains('assets/debugbar.css', $data['requirements'][0]);
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
        $this->collector->collect();
        $result = $this->collector->getWidgets();
        // Stub out the dynamic data
        $result['version']['tooltip'] = 'Stub';
        $result['locale']['tooltip'] = 'Stub';
        $result['user']['tooltip'] = 'Current member';

        $expected = array(
            'user' => array(
                'icon' => 'user',
                'tooltip' => 'Current member',
                'default' => '',
            ),
            'version' => array(
                'icon' => 'desktop',
                'tooltip' => 'Stub',
                'default' => '',
            ),
            'locale' => array(
                'icon' => 'globe',
                'tooltip' => 'Stub',
                'default' => '',
            ),
            'session' => array(
                'icon' => 'archive',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'silverstripe.session',
                'default' => '{}',
            ),
            'cookies' => array(
                'icon' => 'asterisk',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'silverstripe.cookies',
                'default' => '{}',
            ),
            'parameters' => array(
                'icon' => 'arrow-right',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'silverstripe.parameters',
                'default' => '{}',
            ),
            'SiteConfig' => array(
                'icon' => 'gear',
                'widget' => 'PhpDebugBar.Widgets.VariableListWidget',
                'map' => 'silverstripe.config',
                'default' => '{}',
            ),
            'requirements' => array(
                'icon' => 'file-o ',
                'widget' => 'PhpDebugBar.Widgets.ListWidget',
                'map' => 'silverstripe.requirements',
                'default' => '{}',
            ),
            'templates' => array(
                'icon' => 'edit',
                'widget' => 'PhpDebugBar.Widgets.ListWidget',
                'map' => "silverstripe.templates.templates",
                'default' => '{}'
            ),
            'templates:badge' => array(
                'map' => 'silverstripe.templates.count',
                'default' => 0
            ),
            'partialCache' => array(
                'icon' => 'asterisk',
                'widget' => 'PhpDebugBar.Widgets.ConfigWidget',
                'map' => "silverstripe.partialCache.calls",
                'default' => '{}'
            ),
            'partialCache:badge' => array(
                'map' => 'silverstripe.partialCache.count',
                'default' => 0
            )
        );

        $this->assertSame($expected, $result);
    }

    public function testGetAssets()
    {
        $expected = array(
            'base_path' => '/debugbar/javascript',
            'base_url' => 'debugbar/javascript',
            'css' => array(),
            'js' => 'widgets.js',
        );
        $this->assertSame($expected, $this->collector->getAssets());
    }
}
