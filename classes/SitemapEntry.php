<?php
namespace Grav\Plugin\Sitemap;

class SitemapEntry
{
    public $location;
    public $lastmod;
    public $changefreq;
    public $priority;
    public $images;

    /**
     * SitemapEntry constructor.
     *
     * @param null $location
     * @param null $lastmod
     * @param null $changefreq
     * @param null $priority
     * @param null $images
     */
    public function __construct($location = null, $lastmod = null, $changefreq = null, $priority = null, $images = null)
    {
        $this->location = $location;
        $this->lastmod = $lastmod;
        $this->changefreq = $changefreq;
        $this->priority = $priority;
        $this->images = $images;
    }
}
