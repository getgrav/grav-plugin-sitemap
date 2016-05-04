<?php
namespace Grav\Plugin;

use Grav\Common\Data;
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

            if ($page->published() && $page->routable() && !in_array($page->route(), $ignores)) {
                $entry = new SitemapEntry();
                $entry->location = $page->permaLink();
                $entry->lastmod = date('Y-m-d', $page->modified());

                // optional changefreq & priority that you can set in the page header
                $header = $page->header();
                if (isset($header->sitemap['changefreq'])) {
                    $entry->changefreq = $header->sitemap['changefreq'];
                }
                if (isset($header->sitemap['priority'])) {
                    $entry->priority = $header->sitemap['priority'];
                }

                $this->sitemap[$route] = $entry;
            }
        }
    }

    public function onPageInitialized()
    {
        // set a dummy page
        $home = $this->grav['page']->find('/');
        unset($this->grav['page']);
        $this->grav['page'] = $home;
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
