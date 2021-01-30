<?php
namespace Grav\Plugin;

use Composer\Autoload\ClassLoader;
use Grav\Common\Cache;
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
use Twig\TwigFunction;

class SitemapPlugin extends Plugin
{
    /**
     * @var array
     */
    protected $sitemap = false;
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
    protected $ignore_redirect = true;

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
                'onTwigInitialized' => ['onTwigInitialized', 0],
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
        /** @var Cache $cache */
        $cache = $this->grav['cache'];

        /** @var Pages $pages */
        $pages = $this->grav['pages'];

        $cache_id = md5('sitemap-data-'.$pages->getPagesCacheId());
        $this->sitemap = $cache->fetch($cache_id);

        if ($this->sitemap === false) {
            /** @var Language $language */
            $language = $this->grav['language'];
            $default_lang = $language->getDefault() ?: 'en';
            $languages = $language->enabled() ? $language->getLanguages() : [$default_lang];

            $this->multilang_skiplang_prefix = $this->config->get('system.languages.include_default_lang') ?  '' : $language->getDefault();
            $this->multilang_include_fallbacks = $this->config->get('system.languages.pages_fallback_only') || !empty($this->config->get('system.languages.content_fallback'));

            $this->datetime_format = $this->config->get('plugins.sitemap.short_date_format') ? 'Y-m-d' : 'Y-m-d\TH:i:sP';
            $this->include_change_freq = $this->config->get('plugins.sitemap.include_changefreq');
            $this->default_change_freq = $this->config->get('plugins.sitemap.changefreq');
            $this->include_priority = $this->config->get('plugins.sitemap.include_priority');
            $this->default_priority = $this->config->get('plugins.sitemap.priority');
            $this->ignores = (array) $this->config->get('plugins.sitemap.ignores');
            $this->ignore_external = $this->config->get('plugins.sitemap.ignore_external');
            $this->ignore_protected = $this->config->get('plugins.sitemap.ignore_protected');
            $this->ignore_redirect = $this->config->get('plugins.sitemap.ignore_redirect');

            // Gather data
            foreach ($languages as $lang) {
                $language->init();
                $language->setActive($lang);
                $pages->reset();
                $this->addRouteData($pages, $lang);
            }

            // Build sitemap
            foreach ($languages as $lang) {
                foreach($this->route_data as $route => $route_data) {
                    if ($data = $route_data[$lang] ?? null) {
                        $entry = new SitemapEntry();
                        $entry->setData($data);
                        if ($language->enabled()) {
                            foreach ($route_data as $l => $l_data) {
                                $entry->addHreflangs(['hreflang' => $l, 'href' => $l_data['location']]);
                                if ($l === $default_lang) {
                                    $entry->addHreflangs(['hreflang' => 'x-default', 'href' => $l_data['location']]);
                                }
                            }
                        }
                        $this->sitemap[$data['route']] = $entry;
                    }
                }
            }

            $additions = (array) $this->config->get('plugins.sitemap.additions');
            foreach ($additions as $addition) {
                if (isset($addition['location'])) {
                    $location = Utils::url($addition['location'], true);
                    $entry = new SitemapEntry($location,$addition['lastmod'] ?? null,$addition['changefreq'] ?? null, $addition['priority'] ?? null);
                    $this->sitemap[$location] = $entry;
                }
            }
            $cache->save($cache_id, $this->sitemap);
        }

        $this->grav->fireEvent('onSitemapProcessed', new Event(['sitemap' => &$this->sitemap]));
    }

    public function onPageInitialized($event)
    {
        $page = $event['page'] ?? null;
        $route = $this->config->get('plugins.sitemap.route');

        if (is_null($page) || $page->route() !== $route) {
            $extension = $this->grav['uri']->extension() ?? 'xml';

            // set a dummy page
            $page = new Page;
            $page->init(new \SplFileInfo(__DIR__ . '/pages/sitemap.md'));
            $page->templateFormat($extension);
            unset($this->grav['page']);
            $this->grav['page'] = $page;
            $twig = $this->grav['twig'];
            $twig->template = "sitemap.$extension.twig";
        }
    }

    // Access plugin events in this class
    public function onTwigInitialized()
    {
        $this->grav['twig']->twig()->addFunction(
            new TwigFunction('sort_sitemap_entries_by_language', [$this, 'sortSitemapEntriesByLanguage'])
        );
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

    public function sortSitemapEntriesByLanguage()
    {
        $entries = [];

        foreach ((array) $this->sitemap as $route => $entry) {
            $lang = $entry->getLang();
            unset($entry->hreflangs);
            unset($entry->image);
            if ($lang === null) {
                $lang = $this->grav['language']->getDefault() ?: 'en';
            }
            $entries[$lang][$route] = $entry;
        }
        return $entries;
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
            $redirect_page = $this->ignore_redirect ? isset($header->redirect) : false;
            $config_ignored = preg_match(sprintf("@^(%s)$@i", implode('|', $this->ignores)), $page->route());
            $page_ignored = $protected_page || $external_url || $redirect_page || (isset($header->sitemap['ignore']) ? $header->sitemap['ignore'] : false);

            if ($page->routable() && $page->published() && !$config_ignored && !$page_ignored) {
                $page_languages = array_keys($page->translatedLanguages());
                $include_lang = $this->multilang_skiplang_prefix !== $lang;
                $location = $page->canonical($include_lang);
                $page_route = $page->url(false, $include_lang);

                $lang_route = [
                    'title' => $page->title(),
                    'route' => $page_route,
                    'lang' => $lang,
                    'translated' => in_array($lang, $page_languages),
                    'location' => $location,
                    'lastmod' => date($this->datetime_format, $page->modified()),
                ];

                if ($this->include_change_freq) {
                    $lang_route['changefreq'] = $header->sitemap['changefreq'] ?? $this->default_change_freq;
                }
                if ($this->include_priority) {
                    $lang_route['priority']  = $header->sitemap['priority'] ?? $this->default_priority;
                }

                // optional add image
                $images = $header->sitemap['images'] ?? $this->config->get('plugins.sitemap.images') ?? [];

                if (isset($images)) {
                    foreach ($images as $image => $values) {
                        if (isset($values['loc'])) {
                            $images[$image]['loc'] = $page->media()[$values['loc']]->url();
                        } else {
                            unset($images[$image]);
                        }
                    }
                    $lang_route['images'] = $images;
                }



                $this->route_data[$route][$lang] = $lang_route;
            }
        }
    }
}
