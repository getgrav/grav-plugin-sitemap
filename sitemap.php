<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Data;
use Grav\Common\Page\Page;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Page\Pages;
use Grav\Common\Utils;
use Grav\Plugin\Sitemap\SitemapEntry;
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
            'onPluginsInitialized' => [
                ['autoload', 100000], // TODO: Remove when plugin requires Grav >=1.7
                ['onPluginsInitialized', 0],
            ],
            'onBlueprintCreated' => ['onBlueprintCreated', 0]
        ];
    }

    /**
     * Composer autoload.
     *is
     * @return ClassLoader
     */
    public function autoload(): ClassLoader
    {
        return require __DIR__ . '/vendor/autoload.php';
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
        // get enabled languages
        $enabled_languages = $this->grav['language']->getLanguages();
        $is_multi_language_enabled = !empty($enabled_languages);

        // get all pages
        $pages = $this->grav['pages'];
        $routes = array_unique($pages->routes());
        ksort($routes);

        $ignores = (array) $this->config->get('plugins.sitemap.ignores');
        $ignore_external = $this->config->get('plugins.sitemap.ignore_external');
        $ignore_protected = $this->config->get('plugins.sitemap.ignore_protected');

        // for each page
        foreach ($routes as $path) {
            $page = $pages->get($path);
            $header = $page->header();
            $external_url = $ignore_external ? isset($header->external_url) : false;
            $protected_page = $ignore_protected ? isset($header->access) : false;
            $page_ignored = $protected_page || $external_url || (isset($header->sitemap['ignore']) ? $header->sitemap['ignore'] : false);

            // if page is routable and not on any ignore list
            if ($page->routable() && !preg_match(sprintf("@^(%s)$@i", implode('|', $ignores)), $page->route()) && !$page_ignored) {
                // add entry to sitemap as-is if multi-language is not enabled and page is published
                if (!$is_multi_language_enabled and $page->published()) {
                    $this->addSitemapEntry($page, $page->canonical());
                }

                // add one entry to sitemap for each published translation if multi-language is enabled
                else {
                    // get all published translations of current page, and filter only enabled languages
                    $published_translations = array_filter(
                        $page->translatedLanguages(true),
                        function ($lang) use ($enabled_languages) {
                            return in_array($lang, $enabled_languages);
                        },
                        ARRAY_FILTER_USE_KEY
                    );

                    // compute canonical URL for all published translations
                    array_walk(
                        $published_translations,
                        function (&$item, $key) use ($page) {
                            $item = rtrim($this->grav['uri']->rootUrl(true) . $this->grav['language']->getLanguageURLPrefix($key) . $page->routeCanonical(), '/');
                        }
                    );

                    // add one entry for each published translation
                    foreach ($published_translations as $location) {
                        $this->addSitemapEntry($page, $location);
                    }
                }
            }
        }

        $additions = (array) $this->config->get('plugins.sitemap.additions');
        foreach ($additions as $addition) {
            if (isset($addition['location'])) {
                $location = Utils::url($addition['location'], true);
                $entry = new SitemapEntry($location,$addition['lastmod']??null,$addition['changefreq']??null, $addition['priority']??null);
                $this->sitemap[$entry->location] = $entry;
            }
        }

        $this->grav->fireEvent('onSitemapProcessed', new Event(['sitemap' => &$this->sitemap]));
    }

    public function onPageInitialized($event)
    {
        $page = $event['page'] ?? null;
        $route = $this->config->get('plugins.sitemap.route');

        if (is_null($page) || $page->route() !== $route) {
            // set a dummy page
            $page = new Page;
            $page->init(new \SplFileInfo(__DIR__ . '/pages/sitemap.md'));
            unset($this->grav['page']);
            $this->grav['page'] = $page;

            $twig = $this->grav['twig'];
            $twig->template = 'sitemap.xml.twig';
        }
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

    /**
     * Build a new SitemapEntry with given location for given page and it to sitemap.
     * 
     * @param Page $page
     * @param string $location
     */
    private function addSitemapEntry($page, $location) {
        $entry = new SitemapEntry();

        $entry->location = $location;
        $entry->lastmod = date('Y-m-d', $page->modified());

        // optional changefreq & priority that you can set in the page header
        $entry->changefreq = (isset($page->header()->sitemap['changefreq'])) ? $page->header()->sitemap['changefreq'] : $this->config->get('plugins.sitemap.changefreq');
        $entry->priority = (isset($page->header()->sitemap['priority'])) ? $page->header()->sitemap['priority'] : $this->config->get('plugins.sitemap.priority');

        $this->sitemap[$entry->location] = $entry;
    }
}
