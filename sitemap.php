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
            $header = $page->header();
            $page_ignored = isset($header->sitemap['ignore']) ? $header->sitemap['ignore'] : false;

            if ($page->published() && $page->routable() && !preg_match(sprintf("@^(%s)$@i", implode('|', $ignores)), $page->route()) && !$page_ignored) {
                $entry = new SitemapEntry();
                $entry->location = $page->canonical();
                $entry->lastmod = date('Y-m-d', $page->modified());

                // optional changefreq & priority that you can set in the page header
                $entry->changefreq = (isset($header->sitemap['changefreq'])) ? $header->sitemap['changefreq'] : $this->config->get('plugins.sitemap.changefreq');
                $entry->priority = (isset($header->sitemap['priority'])) ? $header->sitemap['priority'] : $this->config->get('plugins.sitemap.priority');

                if (count($this->config->get('system.languages.supported', [])) > 0) {
                    $entry->translated = $page->translatedLanguages();

                    foreach($entry->translated as $lang => $page_route) {
                        $page_route = $page->rawRoute();
                        if ($page->home()) {
                            $page_route = '/';
                        }

                        $entry->translated[$lang] = $page_route;
                    }
                }

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
            $inEvent = true;
            $blueprints = new Data\Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get('sitemap');
            $blueprint->extend($extends, true);
            $inEvent = false;
        }
    }
}
