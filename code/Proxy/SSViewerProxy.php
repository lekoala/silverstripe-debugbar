<?php

namespace LeKoala\DebugBar\Proxy;

use LeKoala\DebugBar\DebugBar;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\SSViewer;
use SilverStripe\Control\Director;

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
    protected static $allTemplates = [];

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
    public function process(mixed $item, array $overlay = []): DBHTMLText
    {
        // If there is no debug bar instance, process as usual
        if (!DebugBar::getDebugBar()) {
            return parent::process($item, $overlay);
        }
        $templateName = self::normalizeTemplateName($this->getTemplateEngine()->getSelectedTemplatePath());
        self::trackTemplateUsed($templateName);

        $startTime = microtime(true);
        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugBar) use ($templateName) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $timeData */
            $timeData = $debugBar->getCollector('time');
            if (!$timeData) {
                return;
            }
            $timeData->startMeasure($templateName, $templateName);
        });

        $result = parent::process($item, $overlay);
        $endTime = microtime(true);
        $totalTime = sprintf("%.2f", $endTime - $startTime);

        DebugBar::withDebugBar(function (\DebugBar\DebugBar $debugBar) use ($templateName) {
            /** @var \DebugBar\DataCollector\TimeDataCollector $timeData */
            $timeData = $debugBar->getCollector('time');
            if (!$timeData) {
                return;
            }
            if ($timeData->hasStartedMeasure($templateName)) {
                $timeData->stopMeasure($templateName);
            }
        });

        $templateRenderWarningLevel = DebugBar::config()->get('template_rendering_warning_level');
        if ($templateRenderWarningLevel && $totalTime > $templateRenderWarningLevel) {
            $sourceFile = $this->getCacheFile($this->getTemplateEngine()->getSelectedTemplatePath());
            $messages = DebugBar::getMessageCollector();
            if ($messages) {
                $messages->addMessage(
                    "The template $templateName needed $totalTime seconds to render." .
                        "\nYou could reduce this by implementing partial caching." .
                        "\nYou can also check the cache file : $sourceFile",
                    'warning',
                    true
                );
            }
        }

        return $result;
    }

    /**
     * Get the cache file for a given template
     *
     * Useful to get to path to a slow template for example
     *
     * @param string $template
     * @return string
     */
    public function getCacheFile($template = null)
    {
        if ($template === null) {
            $template = $this->getTemplateEngine()->getSelectedTemplatePath();
        }
        return TEMP_PATH . DIRECTORY_SEPARATOR . '.cache'
            . str_replace(['\\', '/', ':'], '.', Director::makeRelative(realpath($template)));
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
        if (in_array($templateName, static::$allTemplates)) {
            return;
        }
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
        // Fallback for if the templateName is not a string or array (or anything, really)
        if (!$templateName) {
            $templateName = '';
        }
        return str_ireplace(BASE_PATH, '', $templateName);
    }
}
