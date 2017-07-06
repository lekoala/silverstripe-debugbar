<?php

namespace LeKoala\DebugBar\Test\Proxy;

use LeKoala\DebugBar\Proxy\TemplateParserProxy;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\View\SSTemplateParser;
use SilverStripe\View\TemplateParser;

class TemplateParserProxyTest extends FunctionalTest
{
    public function testOverloadsSSTemplateParser()
    {
        $templateParser = Injector::inst()->get(SSTemplateParser::class);
        $this->assertInstanceOf(TemplateParserProxy::class, $templateParser);
        $this->assertInstanceOf(TemplateParser::class, $templateParser);
    }

    public function testEmptyTemplatesAndCachedByDefault()
    {
        $this->assertEmpty(TemplateParserProxy::getTemplatesUsed());
        $this->assertTrue(TemplateParserProxy::getCached());
    }

    public function testTrackTemplatesUsed()
    {
        $this->get('/Security/login?flush=1');

        $templates = TemplateParserProxy::getTemplatesUsed();

        $this->assertNotEmpty($templates);
        $this->assertContains('/framework/templates/SilverStripe/Forms/Includes/Form.ss', $templates);
        $this->assertContains('/framework/templates/SilverStripe/Forms/TextField.ss', $templates);

        $this->assertFalse(TemplateParserProxy::getCached());
    }
}
