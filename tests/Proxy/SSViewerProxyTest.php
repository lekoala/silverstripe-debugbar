<?php

namespace LeKoala\DebugBar\Test\Proxy;

use LeKoala\DebugBar\Proxy\SSViewerProxy;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStrpe\Forms\TextField;
use SilverStripe\View\SSViewer;

class SSViewerProxyTest extends FunctionalTest
{
    public function testOverloadsSSViewer()
    {
        $templateParser = Injector::inst()->create(SSViewer::class, ['SilverStripe/Forms/Includes/Form']);
        $this->assertInstanceOf(SSViewerProxy::class, $templateParser);
        $this->assertInstanceOf(SSViewer::class, $templateParser);
    }

    public function testTrackTemplatesUsed()
    {
        $this->get('/Security/login');

        $templates = SSViewerProxy::getTemplatesUsed();

        $this->assertNotEmpty($templates);
        $this->assertContains(
            '/vendor/silverstripe/framework/templates/SilverStripe/Forms/Includes/Form.ss',
            $templates
        );
        $this->assertContains(
            '/vendor/silverstripe/framework/templates/SilverStripe/Forms/TextField.ss',
            $templates
        );
    }
}
