<?php
namespace Grav\Plugin\Sitemap;

class SitemapEntry
{
    public $title;
    public $route;
    public $lang;
    public $translated = false;
    public $location;
    public $lastmod;
    public $changefreq;
    public $priority;
    public $images;
    public $hreflangs = [];

    public int $timestamp;
    public string $rawroute;
    public string $longdate;
    public string $shortdate;

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

    /**
     * @param array $data
     * @return SitemapEntry
     */
    public function setData(array $data): SitemapEntry
    {
        foreach($data as $property => $value)
        {
            if (property_exists($this, $property)) {
                $this->{$property} = $value;
            }
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @param mixed $lang
     * @return SitemapEntry
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
        return $this;
    }


    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     * @return SitemapEntry
     */
    public function setTitle($title): SitemapEntry
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @param mixed $route
     * @return SitemapEntry
     */
    public function setRoute($route): SitemapEntry
    {
        $this->route = $route;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBaseLang()
    {
        return $this->base_lang;
    }

    /**
     * @param mixed $base_lang
     * @return SitemapEntry
     */
    public function setBaseLang($base_lang): SitemapEntry
    {
        $this->base_lang = $base_lang;
        return $this;
    }

    /**
     * @return bool
     */
    public function isTranslated(): bool
    {
        return $this->translated;
    }

    /**
     * @param bool $translated
     * @return SitemapEntry
     */
    public function setTranslated(bool $translated): SitemapEntry
    {
        $this->translated = $translated;
        return $this;
    }

    /**
     * @return null
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @param null $location
     * @return SitemapEntry
     */
    public function setLocation($location): SitemapEntry
    {
        $this->location = $location;
        return $this;
    }

    /**
     * @return null
     */
    public function getLastmod()
    {
        return $this->lastmod;
    }

    /**
     * @param null $lastmod
     * @return SitemapEntry
     */
    public function setLastmod($lastmod): SitemapEntry
    {
        $this->lastmod = $lastmod;
        return $this;
    }

    /**
     * @return null
     */
    public function getChangefreq()
    {
        return $this->changefreq;
    }

    /**
     * @param null $changefreq
     * @return SitemapEntry
     */
    public function setChangefreq($changefreq): SitemapEntry
    {
        $this->changefreq = $changefreq;
        return $this;
    }

    /**
     * @return null
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param null $priority
     * @return SitemapEntry
     */
    public function setPriority($priority): SitemapEntry
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return null
     */
    public function getImages()
    {
        return $this->images;
    }

    /**
     * @param null $images
     * @return SitemapEntry
     */
    public function setImages($images)
    {
        $this->images = $images;
        return $this;
    }



    /**
     * @return array
     */
    public function getHreflangs(): array
    {
        return $this->hreflangs;
    }

    /**
     * @param array $hreflang
     * @return SitemapEntry
     */
    public function addHreflangs(array $hreflang): SitemapEntry
    {
        $this->hreflangs[] = $hreflang;
        return $this;
    }

    /**
     * @param array $hreflangs
     * @return SitemapEntry
     */
    public function setHreflangs(array $hreflangs): SitemapEntry
    {
        $this->hreflangs = $hreflangs;
        return $this;
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getRawroute(): string
    {
        return $this->rawroute;
    }

    public function setRawroute(string $rawroute): void
    {
        $this->rawroute = $rawroute;
    }

    public function getLongdate(): string
    {
        return $this->longdate;
    }

    public function setLongdate(string $longdate): void
    {
        $this->longdate = $longdate;
    }

    public function getShortdate(): string
    {
        return $this->shortdate;
    }

    public function setShortdate(string $shortdate): void
    {
        $this->shortdate = $shortdate;
    }

}
