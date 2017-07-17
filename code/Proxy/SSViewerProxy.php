<?php

namespace LeKoala\DebugBar\Proxy;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\View\SSViewer;

/**
 * The template parser proxy will monitor the templates that are used during a page request. Since the
 * use of the template parser is behind cache checks, this will only execute during a cache flush.
 */
class SSViewerProxy extends SSViewer
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
    public function process($item, $arguments = null, $inheritedScope = null)
    {
        self::trackTemplateUsed($this->chosen);
        return parent::process($item, $arguments, $inheritedScope);
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
     * Remove the base path from a template file path and track its use
     *
     * @param string $templateName
     */
    protected static function trackTemplateUsed($templateName)
    {
        static::$allTemplates[] = str_ireplace(BASE_PATH, '', $templateName);
    }
}
