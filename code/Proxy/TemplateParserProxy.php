<?php

namespace LeKoala\DebugBar\Proxy;

use SSTemplateParser;
use DebugBar;


/**
 * The template parser proxy will monitor the templates that are used during a page request. Since the
 * use of the template parser is behind cache checks, this will only execute during a cache flush.
 */
class TemplateParserProxy extends SSTemplateParser
{
    /**
     * Tracks all templates used in the current request
     *
     * @var array
     */
    protected static $allTemplates = array();

    /**
     * Whether the class has been used, meaning whether the page has been cached
     *
     * @var boolean
     */
    protected static $cached = true;

    /**
     * Overloaded to track all templates used in the current request
     *
     * {@inheritDoc}
     */
    public function compileString($string, $templateName = '', $includeDebuggingComments = false, $topTemplate = true)
    {
        static::$cached = false;

        if (DebugBar::config()->force_proxy) {
            static::trackTemplateUsed($templateName);
        }

        return parent::compileString($string, $templateName, $includeDebuggingComments, $topTemplate);
    }

    /**
     * Get the templates used in the current request and the number of times they were called
     *
     * @return array
     */
    public static function getTemplatesUsed()
    {
        return static::$allTemplates;
    }

    /**
     * Determines whether the template rendering is cached or not based on whether the compileString method has been
     * called at any point.
     *
     * @return bool
     */
    public static function getCached()
    {
        return static::$cached;
    }

    /**
     * Remove the base path from a template file path and track its use
     *
     * @param string $templateName
     */
    protected static function trackTemplateUsed($templateName)
    {
        static::$allTemplates[] = str_ireplace(BASE_PATH, '', $templateName);
    }
}
