<?php

class TemplateParserProxyTest extends FunctionalTest
{
    public function testOverloadsSSTemplateParser()
    {
        $templateParser = Injector::inst()->get('SSTemplateParser');
        $this->assertInstanceOf('TemplateParserProxy', $templateParser);
        $this->assertInstanceOf('TemplateParser', $templateParser);
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
        $this->assertContains('/framework/templates/Includes/Form.ss', $templates);
        $this->assertContains('/framework/templates/forms/TextField.ss', $templates);

        $this->assertFalse(TemplateParserProxy::getCached());
    }
}
