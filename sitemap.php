<?php
namespace Grav\Plugin;

use Grav\Common\Data;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Page\Pages;
use Grav\Component\EventDispatcher\Event;

class SitemapPlugin extends Plugin
{
    /**
     * @var array
     */
    protected $sitemap = array();

    /**
     * @return array
     */
    public static function getSubscribedEvents() {
        return [
            'onAfterInitPlugins' => ['onAfterInitPlugins', 0],
            'onCreateBlueprint' => ['onCreateBlueprint', 0]
        ];
    }

    /**
     * Enable sitemap only if url matches to the configuration.
     */
    public function onAfterInitPlugins()
    {
        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $route = $this->config->get('plugins.sitemap.route');

        if ($route && $route == $uri->path()) {
            // Turn off debugger if its on
            $this->config->set('system.debugger.enabled', false);

            $this->enable([
                'onAfterGetPages' => ['onAfterGetPages', 0],
                'onAfterTwigTemplatesPaths' => ['onAfterTwigTemplatesPaths', 0],
                'onAfterTwigSiteVars' => ['onAfterTwigSiteVars', 0]
            ]);
        }
    }

    /**
     * Generate data for the sitemap.
     */
    public function onAfterGetPages()
    {
        require_once __DIR__ . '/classes/sitemapentry.php';

        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $routes = $pages->routes();
        ksort($routes);

        foreach ($routes as $route => $path) {
            $page = $pages->get($path);

            if ($page->routable()) {

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

    /**
     * Add current directory to twig lookup paths.
     */
    public function onAfterTwigTemplatesPaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Set needed variables to display the sitemap.
     */
    public function onAfterTwigSiteVars()
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
    public function onCreateBlueprint(Event $event)
    {
        static $inEvent = false;

        /** @var Data\Blueprint $blueprint */
        $blueprint = $event['blueprint'];
        if (!$inEvent && $blueprint->get('form.fields.tabs')) {
            $inEvent = true;
            $blueprints = new Data\Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get('sitemap');
            $blueprint->extend($extends, true);
            $inEvent = false;
        }
    }
}
