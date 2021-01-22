<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Grav;
use Grav\Common\Data;
use Grav\Common\Language\Language;
use Grav\Common\Page\Interfaces\PageInterface;
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
    protected $sitemap = [];
    protected $route_data = [];

    protected $multilang_skiplang_prefix = null;
    protected $multilang_include_fallbacks = false;
    protected $datetime_format = null;
    protected $include_change_freq = true;
    protected $default_change_freq = null;
    protected $include_priority = true;
    protected $default_priority = null;
    protected $ignores = null;
    protected $ignore_external = true;
    protected $ignore_protected = true;

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
        $grav = Grav::instance();

        /** @var Pages $pages */
        $pages = $grav['pages'];

        /** @var Language $language */
        $language = $grav['language'];
        $default_lang = $language->getDefault() ?: 'en';
        $languages = $language->enabled() ? $language->getLanguages() : [$default_lang];

        $this->multilang_skiplang_prefix = $this->config->get('system.languages.include_default_lang') ?  '' : $language->getDefault();
        $this->multilang_include_fallbacks = $this->config->get('plugins.sitemap.multilang.include_fallbacks');

        $this->datetime_format = $this->config->get('plugins.sitemap.short_date_format') ? 'Y-m-d' : 'Y-m-d\TH:i:sP';
        $this->include_change_freq = $this->config->get('plugins.sitemap.include_changefreq');
        $this->default_change_freq = $this->config->get('plugins.sitemap.changefreq');
        $this->include_priority = $this->config->get('plugins.sitemap.include_priority');
        $this->default_priority = $this->config->get('plugins.sitemap.priority');

        $this->ignores = (array) $this->config->get('plugins.sitemap.ignores');
        $this->ignore_external = $this->config->get('plugins.sitemap.ignore_external');
        $this->ignore_protected = $this->config->get('plugins.sitemap.ignore_protected');

        // Gather data
        foreach ($languages as $lang) {
            $language->init();
            $language->setActive($lang);
            $pages->reset();
            $this->addRouteData($pages, $lang);
        }

        // Build sitemap
        foreach ($languages as $lang) {
            foreach($this->route_data as $data) {

            }
        }

        $someit = true;


//        /** @var Pages $pages */
//        $pages = $this->grav['pages'];
//        $routes = array_unique($pages->routes());
//        ksort($routes);
//
//        $ignores = (array) $this->config->get('plugins.sitemap.ignores');
//        $ignore_external = $this->config->get('plugins.sitemap.ignore_external');
//        $ignore_protected = $this->config->get('plugins.sitemap.ignore_protected');
//
//        foreach ($routes as $route => $path) {
//            $page = $pages->get($path);
//            $header = $page->header();
//            $external_url = $ignore_external ? isset($header->external_url) : false;
//            $protected_page = $ignore_protected ? isset($header->access) : false;
//            $page_ignored = $protected_page || $external_url || (isset($header->sitemap['ignore']) ? $header->sitemap['ignore'] : false);
//            $page_languages = $page->translatedLanguages();
//            $lang_available = (empty($page_languages) || array_key_exists($current_lang, $page_languages));
//
//
//            if ($page->published() && $page->routable() && !preg_match(sprintf("@^(%s)$@i", implode('|', $ignores)), $page->route()) && !$page_ignored && $lang_available ) {
//
//                $entry = new SitemapEntry();
//                $entry->location = $page->canonical();
//                $entry->lastmod = date('Y-m-d', $page->modified());
//
//                // optional changefreq & priority that you can set in the page header
//                $entry->changefreq = (isset($header->sitemap['changefreq'])) ? $header->sitemap['changefreq'] : $this->config->get('plugins.sitemap.changefreq');
//                $entry->priority = (isset($header->sitemap['priority'])) ? $header->sitemap['priority'] : $this->config->get('plugins.sitemap.priority');
//
//                if (count($this->config->get('system.languages.supported', [])) > 0) {
//                    $entry->translated = $page->translatedLanguages(true);
//
//                    foreach($entry->translated as $lang => $page_route) {
//                        $page_route = $page->rawRoute();
//                        if ($page->home()) {
//                            $page_route = '';
//                        }
//
//                        $entry->translated[$lang] = $page_route;
//                    }
//                }
//
//                $this->sitemap[$route] = $entry;
//            }
//        }
//
        $additions = (array) $this->config->get('plugins.sitemap.additions');
        foreach ($additions as $addition) {
            if (isset($addition['location'])) {
                $location = Utils::url($addition['location'], true);
                $entry = new SitemapEntry($location,$addition['lastmod'] ?? null,$addition['changefreq'] ?? null, $addition['priority'] ?? null);
                $this->sitemap[$location] = $entry;
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

    protected function addRouteData($pages, $lang)
    {
        $routes = array_unique($pages->routes());
        ksort($routes);

        foreach ($routes as $route => $path) {
            /** @var PageInterface $page */
            $page = $pages->get($path);
            $header = $page->header();
            $external_url = $this->ignore_external ? isset($header->external_url) : false;
            $protected_page = $this->ignore_protected ? isset($header->access) : false;
            $config_ignored = preg_match(sprintf("@^(%s)$@i", implode('|', $this->ignores)), $page->route());
            $page_ignored = $protected_page || $external_url || (isset($header->sitemap['ignore']) ? $header->sitemap['ignore'] : false);


            if ($page->routable() && $page->visible() && !$config_ignored && !$page_ignored) {
                $page_language = $page->language();
                $page_languages = array_keys($page->translatedLanguages());

                $location = $page->canonical($this->multilang_skiplang_prefix !== $lang);

                $lang_route = [
                    'title' => $page->title(),
                    'base_language' => $page_language,
                    'translated' => in_array($lang, $page_languages),
                    'entry' => $this->addSitemapEntry($page, $location),
                ];
                $this->route_data[$route][$lang] = $lang_route;
            }
        }
    }

    protected function addSitemapEntry($page, $location): SitemapEntry
    {
        $entry = new SitemapEntry();

        $entry->location = $location;
        $entry->lastmod = date($this->datetime_format, $page->modified());

        if ($this->include_change_freq) {
            $entry->changefreq = $page->header()->sitemap['changefreq'] ?? $this->default_change_freq;
        }
        if ($this->include_priority) {
            $entry->priority = $page->header()->sitemap['priority'] ?? $this->default_priority;
        }

        return $entry;
    }
}
