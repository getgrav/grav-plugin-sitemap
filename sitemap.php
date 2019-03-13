<?php
namespace Grav\Plugin;

use Grav\Common\Data;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Page\Pages;
use RocketTheme\Toolbox\Event\Event;

class SitemapPlugin extends Plugin
{
    /**
     * @var array
     */
    protected $sitemap = array();

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onBlueprintCreated' => ['onBlueprintCreated', 0]
        ];
    }

    /**
     * Enable sitemap only if url matches to the configuration.
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $route = $this->config->get('plugins.sitemap.route');

        if ($route && $route == $uri->path()) {

            $this->enable([
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
                'onPagesInitialized' => ['onPagesInitialized', 0],
                'onPageInitialized' => ['onPageInitialized', 0],
                'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
            ]);
        }
    }

    protected function pageIgnored(Page $page)
    {
        $header = $page->header();
        $page_ignored = isset($header->sitemap['ignore']) ? $header->sitemap['ignore'] : false;
        return $page_ignored;
    }
    
    protected function determineLastModifiedTimestamp(Page $page)
    {
        // Get the initial value from the given page.
        $retval = $page->modified();
        
        // Use either collection() with either the default parameters or the given modular collection names.
        $modular_collections = [];
        if (isset($header->sitemap['modular_collections'])) {
            foreach ($header->sitemap['modular_collections'] as $mc_name)
                $modular_collections[] = $page->collection($mc_name, false);
        }
        else {
            $modular_collections = [$page->collection()];
        }
        
        // Traverse the modular pages in the collections.
        foreach ($modular_collections as $coll) {
            $modular = $coll->modular();
            foreach ($modular as $modular_page)
                $retval = max($retval, $this->determineLastModifiedTimestamp($modular_page));
        }
        
        return $retval;
    }
    
    /**
     * Determine the translated path of the given page.
     */
    protected function translatedPath(Page $page, string $lang)
    {
        $components = [];
        while (true) {
            // If a parent is not published, don’t include the page in the sitemap.
            if (!($page->published() && $page->visible()))
                return null;

            $slugs = $page->translatedLanguages(true);
            
            if (!isset($slugs[$lang]))
                return null;

            if (!$page->home()) {
                $slug = $slugs[$lang];
                $components[] = $slug;
            }
            
            $page = $page->parent();
            
            // Don’t handle the root.
            if (is_null($page->parent()))
                break;
        }
        return array_reverse($components);
    }
    
    protected function translatedPage(Page $page, string $lang)
    {
        // I wasn’t able to find any other way to get a translated page instance
        // than by determining the file path and instantiating a Page by myself.
        $filename = substr($page->name(), 0, -(strlen($page->extension())));
        $path = sprintf("%s/%s/%s.%s.md", $page->path(), $page->folder(), $filename, $lang);
        if (file_exists($path)) {
            $translatedPage = new Page();
            $translatedPage->init(new \SplFileInfo($path), $lang . '.md');
            return $translatedPage;
        }
        return null;
    }

    /**
     * Generate data for the sitemap.
     */
    public function onPagesInitialized()
    {
        require_once __DIR__ . '/classes/sitemapentry.php';

        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $routes = array_unique($pages->routes());
        ksort($routes);

        $ignores = (array) $this->config->get('plugins.sitemap.ignores');

        foreach ($routes as $route => $path) {
            $page = $pages->get($path);
            if ($page->published() && $page->routable() && $page->visible() && !$this->pageIgnored($page) && !preg_match(sprintf("@^(%s)$@i", implode('|', $ignores)), $page->route())) {
                $entry = new SitemapEntry();
                $entry->location = $page->canonical();
                $lastmod = $this->determineLastModifiedTimestamp($page);

                // optional changefreq & priority that you can set in the page header
                $header = $page->header();
                $entry->changefreq = (isset($header->sitemap['changefreq'])) ? $header->sitemap['changefreq'] : $this->config->get('plugins.sitemap.changefreq');
                $entry->priority = (isset($header->sitemap['priority'])) ? $header->sitemap['priority'] : $this->config->get('plugins.sitemap.priority');

                if (count($this->config->get('system.languages.supported', [])) > 0) {
                    $entry->translated = [];
                    foreach ($page->translatedLanguages(true) as $lang => $page_route) {
                        if ($page->home())
                            $page_route = '';
                        else {
                            $page_route = $this->translatedPath($page, $lang);
                            if (!is_null($page_route))
                                $page_route = '/' . join('/', $page_route);
                        }

                        if (!is_null($page_route)) {
                            $entry->translated[$lang] = $page_route;

                            // Since the last modification date is set per entry, not per URL,
                            // consider the translations.
                            $translated_page = $this->translatedPage($page, $lang);
                            if (!is_null($translated_page))
                                $lastmod = max($lastmod, $this->determineLastModifiedTimestamp($translated_page));
                        }
                    }
                }

                $entry->lastmod = date('Y-m-d', $lastmod);
                $this->sitemap[$route] = $entry;
            }
        }

        $rootUrl = $this->grav['uri']->rootUrl(true) . $pages->base();
        $additions = (array) $this->config->get('plugins.sitemap.additions');

        foreach ($additions as $addition) {
            $entry = new SitemapEntry();
            $entry->location = $rootUrl . $addition['location'];
            $entry->lastmod = $addition['lastmod'];

            $this->sitemap[] = $entry;
        }
    }

    public function onPageInitialized()
    {
        // set a dummy page
        $page = new Page;
        $page->init(new \SplFileInfo(__DIR__ . '/pages/sitemap.md'));

        unset($this->grav['page']);
        $this->grav['page'] = $page;
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Set needed variables to display the sitemap.
     */
    public function onTwigSiteVariables()
    {
        $twig = $this->grav['twig'];
        $twig->template = 'sitemap.xml.twig';
        $twig->twig_vars['sitemap'] = $this->sitemap;
    }

    /**
     * Extend page blueprints with feed configuration options.
     *
     * @param Event $event
     */
    public function onBlueprintCreated(Event $event)
    {
        static $inEvent = false;

        /** @var Data\Blueprint $blueprint */
        $blueprint = $event['blueprint'];
        if (!$inEvent && $blueprint->get('form/fields/tabs', null, '/')) {
            if (!in_array($blueprint->getFilename(), array_keys($this->grav['pages']->modularTypes()))) {
                $inEvent = true;
                $blueprints = new Data\Blueprints(__DIR__ . '/blueprints/');
                $extends = $blueprints->get('sitemap');
                $blueprint->extend($extends, true);
                $inEvent = false;
            }
        }
    }
}
