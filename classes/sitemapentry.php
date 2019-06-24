<?php
namespace Grav\Plugin;

use Grav\Common\Grav;

class SitemapEntry
{
    public $url;
    public $lastmod;
    public $changefreq;
    public $priority;
    public $language;
    public $translations;

    public static function fromPage($page) {
        $url = $page->canonical();
        $lastmod = date('Y-m-d', $page->modified());
        $language = $page->language();
        $header = $page->header();
        $changefreq = isset($header->sitemap['changefreq']) ? $header->sitemap['changefreq'] : null;
        $priority = isset($header->sitemap['priority']) ? $header->sitemap['priority'] : null;

        return new static(
            $url,
            $lastmod,
            $language,
            $changefreq,
            $priority
        );
    }

    function __construct($url, $lastmod, $language = 'en', $changefreq = null, $priority = null) {
        $config = Grav::instance()['config'];
        $this->url = $url;
        $this->lastmod = $lastmod;
        $this->language = $language;
        $this->changefreq = $changefreq ?: $config->get('plugins.sitemap.changefreq');
        $this->priority = $priority ?: $config->get('plugins.sitemap.priority');
    }
}
