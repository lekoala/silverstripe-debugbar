<?php

class DebugBarTemplateParserProxyTest extends FunctionalTest
{
    public function testOverloadsSSTemplateParser()
    {
        $templateParser = Injector::inst()->get('SSTemplateParser');
        $this->assertInstanceOf('DebugBarTemplateParserProxy', $templateParser);
        $this->assertInstanceOf('TemplateParser', $templateParser);
    }

    public function testEmptyTemplatesAndCachedByDefault()
    {
        $this->assertEmpty(DebugBarTemplateParserProxy::getTemplatesUsed());
        $this->assertTrue(DebugBarTemplateParserProxy::getCached());
    }

    public function testTrackTemplatesUsed()
    {
        $this->get('/Security/login?flush=1');

        $templates = DebugBarTemplateParserProxy::getTemplatesUsed();

        $this->assertNotEmpty($templates);
        $this->assertContains('/framework/templates/Includes/Form.ss', $templates);
        $this->assertContains('/framework/templates/forms/TextField.ss', $templates);

        $this->assertFalse(DebugBarTemplateParserProxy::getCached());
    }
}
