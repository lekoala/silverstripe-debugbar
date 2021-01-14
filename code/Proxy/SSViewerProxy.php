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
        $templateName = self::normalizeTemplateName($this->chosen);
        self::trackTemplateUsed($templateName);

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugBar) use ($templateName) {
            /** @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugBar->getCollector('time');
            if (!$timeData) {
                return;
            }
            $timeData->startMeasure($templateName, $templateName);
        });

        $result = parent::process($item, $arguments, $inheritedScope);

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugBar) use ($templateName) {
            /** @var $timeData DebugBar\DataCollector\TimeDataCollector */
            $timeData = $debugBar->getCollector('time');
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure($templateName)) {
                $timeData->stopMeasure($templateName);
            }
        });

        return $result;
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
     * Reset the array
     *
     * @return void
     */
    public static function resetTemplatesUsed()
    {
        static::$allTemplates = [];
    }

    /**
     * Helps tracking the use of templates
     *
     * @param string $templateName
     */
    protected static function trackTemplateUsed($templateName)
    {
        static::$allTemplates[] = $templateName;
    }

    /**
     * Remove base path from template
     *
     * @param string $templateName
     * @return string
     */
    protected static function normalizeTemplateName($templateName)
    {
        return  str_ireplace(BASE_PATH, '', $templateName);
    }
}
