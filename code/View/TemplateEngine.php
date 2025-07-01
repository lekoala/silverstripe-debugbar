<?php

namespace LeKoala\DebugBar\View;

use LeKoala\DebugBar\Proxy\CacheProxy;
use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\Versioned\Caching\VersionedCacheAdapter;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ThemeResourceLoader;

class TemplateEngine extends SSTemplateEngine
{
    protected ?string $selectedTemplatePath;

    public function setTemplate(string|array $templateCandidates): static
    {
        parent::setTemplate($templateCandidates);

        $themes = SSViewer::get_themes();

        $cacheAdapter = ThemeResourceLoader::inst()->getCache();
        $cacheKey = 'findTemplate_' . md5(json_encode($templateCandidates) . json_encode($themes));

        // Look for a cached result for this data set
        if ($cacheAdapter->has($cacheKey)) {
            $this->selectedTemplatePath = $cacheAdapter->get($cacheKey);
        }

        return $this;
    }

    public function getSelectedTemplatePath(): ?string
    {
        return $this->selectedTemplatePath;
    }

    public function getPartialCacheStore(): CacheInterface
    {
        $cache = parent::getPartialCacheStore();

        if (method_exists($cache, 'setContext')) {
            $cache->setContext(CacheProxy::CONTEXT_TMP);
        }

        return $cache;
    }
}
