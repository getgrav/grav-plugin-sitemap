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
use Grav\Common\Twig\Twig;
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
    protected $multilang_enabled = true;
    protected $datetime_format = null;
    protected $include_change_freq = true;
    protected $default_change_freq = null;
    protected $include_priority = true;
    protected $default_priority = null;
    protected $ignores = null;
    protected $ignore_external = true;
    protected $ignore_protected = true;
    protected $ignore_redirect = true;

    protected $news_route = null;
    protected $llms_route = null;
    protected $llms_built = false;
    protected $sitemap_cache_id = null;

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
        $route = $this->config()['route'];
        $uri_route = $uri->route();
        $news_page = false;

        if ($this->config()['include_news_tags'] &&
            $this->config()['standalone_sitemap_news'] &&
            Utils::endsWith($uri->uri(), $this->config()['sitemap_news_path']) &&
            in_array(dirname($uri->route()), $this->config()['news_enabled_paths'])) {
            $this->news_route = dirname($uri->route());
        }


        // /llms.txt and /llms-full.txt: the site as Markdown for AI agents.
        if ($uri->extension() === 'txt') {
            if ($uri_route === '/llms' && $this->config()['llms_txt']) {
                $this->llms_route = 'llms';
            } elseif ($uri_route === '/llms-full' && $this->config()['llms_full_txt']) {
                $this->llms_route = 'llms-full';
            }
        }

        if ($route === $uri->route() || !empty($this->news_route) || !empty($this->llms_route)) {

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

        // The cache key covers everything that decides what the sitemap
        // contains, not just the pages.
        //
        // `getPagesCacheId()` alone was not enough, and that is why the fetch
        // below spent a while commented out: this method reads a dozen of the
        // plugin's own settings and four of Grav's language settings, and bakes
        // the configured `additions` straight into the result. Keyed on the
        // pages alone, changing `ignores`, `changefreq`, `priority`,
        // `multilang_enabled` or adding an extra URL kept serving the old
        // sitemap until something in pages/ happened to change, which is
        // indistinguishable from the setting not working.
        $cache_id = md5('sitemap-data-' . $pages->getPagesCacheId() . serialize([
            $this->config->get('plugins.sitemap'),
            $this->config->get('system.languages.supported'),
            $this->config->get('system.languages.include_default_lang'),
            $this->config->get('system.languages.pages_fallback_only'),
            $this->config->get('system.languages.content_fallback'),
        ]));

        $this->sitemap_cache_id = $cache_id;
        $this->sitemap = $cache->fetch($cache_id);

        // Everything the build sets on $this — the date format, the ignore
        // rules, the changefreq and priority defaults, the language prefixes —
        // is read only by addRouteData(), which is only called from inside this
        // branch. A cache hit skipping them is safe.
        if ($this->sitemap === false) {
            $this->multilang_enabled = $this->config->get('plugins.sitemap.multilang_enabled');

            /** @var Language $language */
            $language = $this->grav['language'];
            $default_lang = $language->getDefault() ?: 'en';
            $active_lang = $language->getActive() ?? $default_lang;
            $languages = $this->multilang_enabled && $language->enabled() ? $language->getLanguages() : [$default_lang];
            $include_default_lang = $this->config->get('system.languages.include_default_lang');

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

            // Gather data for all languages
            foreach ($languages as $lang) {
                $language->init();
                $language->setActive($lang);
                $pages->reset();
                $this->addRouteData($pages, $lang);
            }

            // Reset back to active language
            if ($language->enabled() && $language->getActive() !== $active_lang) {
                $language->init();
                $language->setActive($active_lang);
                $pages->reset();
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
                                if ($include_default_lang === false && $l == $default_lang) {
                                    $entry->addHreflangs(['hreflang' => 'x-default', 'href' => $l_data['location']]);
                                }
                            }
                        }
                        $this->sitemap[$data['url']] = $entry;
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
        $uri = $this->grav['uri'];
        $html_support = $this->config->get('plugins.sitemap.html_support', false);
        $extension = $this->grav['uri']->extension() ?? ($html_support ? 'html': 'xml');

        if (!empty($this->llms_route)) {
            // Grav 2.1 knows `md` as a page type and answers it with
            // `text/markdown`; older cores serve the file as plain text.
            $format = Utils::getMimeByExtension('md', false) === 'text/markdown' ? 'md' : 'txt';

            $page = new Page;
            $page->init(new \SplFileInfo(__DIR__ . '/pages/llms.md'));
            $page->templateFormat($format);
            unset($this->grav['page']);
            $this->grav['page'] = $page;
            // The template is chosen in onTwigSiteVariables: Twig hands out a
            // preset template once, and building llms-full.txt renders every
            // page first, which would use it up on a module.

            return;
        }

        if (is_null($page) || $uri->route() === $route || !empty($this->news_route)) {

            // set a dummy page
            $page = new Page;
            $page->init(new \SplFileInfo(__DIR__ . '/pages/sitemap.md'));
            $page->templateFormat($extension);
            unset($this->grav['page']);
            $this->grav['page'] = $page;
            $twig = $this->grav['twig'];

            if (!empty($this->news_route)) {
                $header = $page->header();
                $header->sitemap['news_route'] = $this->news_route;
                $page->header($header);
                $twig->template = "sitemap-news.$extension.twig";
            } else {
                $twig->template = "sitemap.$extension.twig";
            }

        }
    }

    // Access plugin events in this class
    public function onTwigInitialized()
    {
        $this->grav['twig']->twig()->addFunction(
            new TwigFunction('sort_sitemap_entries_by_language', [$this, 'sortSitemapEntriesByLanguage'])
        );
        $this->grav['twig']->twig()->addFunction(
            new TwigFunction('timestamp_within_days', [$this, 'timestampWithinDays'])
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

        // Once only: building llms-full.txt renders every page through the
        // theme, and each of those renders fires this event again.
        if ($this->llms_route && !$this->llms_built) {
            $this->llms_built = true;
            if ($this->llms_route === 'llms') {
                $twig->twig_vars['llms_sections'] = $this->llmsSections();
            } else {
                $twig->twig_vars['llms_full'] = $this->llmsFull();
            }
            $twig->template = "{$this->llms_route}.txt.twig";
        }
    }

    /**
     * The sitemap entries of the active language that have a Markdown URL,
     * grouped for `llms.txt`: the home page and every top-level page under
     * "Pages", everything deeper under the title of its top-level ancestor.
     *
     * @return array<string, array{title: string, entries: SitemapEntry[]}>
     */
    protected function llmsSections(): array
    {
        /** @var Language $language */
        $language = $this->grav['language'];
        $lang = $language->enabled() ? ($language->getActive() ?: $language->getDefault()) : null;

        $entries = [];
        foreach ((array)$this->sitemap as $entry) {
            if (!$entry instanceof SitemapEntry || empty($entry->markdown)) {
                continue;
            }
            if ($lang !== null && $entry->getLang() !== null && $entry->getLang() !== $lang) {
                continue;
            }
            $entries[] = $entry;
        }

        $titles = [];
        foreach ($entries as $entry) {
            $segments = explode('/', trim((string)$entry->route, '/'));
            if (count($segments) === 1 && $segments[0] !== '') {
                $titles[$segments[0]] = $entry->title ?: ucfirst($segments[0]);
            }
        }

        $sections = ['' => ['title' => 'Pages', 'entries' => []]];
        foreach ($entries as $entry) {
            $segments = explode('/', trim((string)$entry->route, '/'));
            $key = count($segments) > 1 ? $segments[0] : '';
            if (!isset($sections[$key])) {
                $sections[$key] = ['title' => $titles[$key] ?? ucfirst(str_replace(['-', '_'], ' ', $key)), 'entries' => []];
            }
            $sections[$key]['entries'][] = $entry;
        }

        // The home page reads first, whatever its route sorts as.
        usort($sections['']['entries'], static fn(SitemapEntry $a, SitemapEntry $b) => (int)$b->home <=> (int)$a->home);

        return array_filter($sections, static fn(array $section) => $section['entries'] !== []);
    }

    /**
     * Every page of the active language as one Markdown document, for
     * `llms-full.txt`. Each page's own conversion is cached by Grav, and the
     * joined result is cached against the sitemap it was built from.
     *
     * @return string
     */
    protected function llmsFull(): string
    {
        if (!isset($this->grav['markdown_output'])) {
            return '';
        }

        // A page rendered for a logged-in visitor may carry that visitor's
        // state, so only the anonymous build goes in (and comes out of) the cache.
        $user = $this->grav['user'] ?? null;
        $anonymous = !($user && $user->authenticated && $user->authorized);

        /** @var Cache $cache */
        $cache = $this->grav['cache'];
        $cache_id = md5('llms-full-' . $this->sitemap_cache_id . $this->grav['config']->checksum());
        $cached = $anonymous ? $cache->fetch($cache_id) : false;
        if (is_string($cached)) {
            return $cached;
        }

        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $output = $this->grav['markdown_output'];

        $documents = [];
        foreach ($this->llmsSections() as $section) {
            foreach ($section['entries'] as $entry) {
                $page = $pages->find($entry->route);
                if ($page instanceof PageInterface && $page->routable()) {
                    $documents[] = rtrim($output->render($page));
                }
            }
        }

        $full = implode("\n\n", $documents) . "\n";
        if ($anonymous) {
            $cache->save($cache_id, $full);
        }

        return $full;
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

    public function timestampWithinDays(int $timestamp, int $days): bool
    {
        $now = time();
        $days_ago = $now - ($days * 24 * 60 * 60);
        return $timestamp >= $days_ago;
    }

    /**
     * A page's metadata description, if it has one, for `llms.txt`.
     *
     * @param PageInterface $page
     * @return string|null
     */
    protected function pageDescription(PageInterface $page): ?string
    {
        $description = $page->header()->metadata['description'] ?? null;
        $description = is_string($description) ? trim(preg_replace('/\s+/', ' ', $description)) : '';

        return $description !== '' ? $description : null;
    }

    protected function addRouteData($pages, $lang)
    {
        $routes = array_unique($pages->routes());
        ksort($routes);

        foreach ($routes as $route => $path) {
            /** @var PageInterface $page */
            $page = $pages->get($path);

            $rawroute = $page->rawRoute();
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
                $url = $page->url(false, $include_lang);
                $lastmod = !empty($header->sitemap['lastmod']) ? strtotime($header->sitemap['lastmod']) : $page->modified();

                $lang_route = [
                    'title' => $page->title(),
                    'url' => $url,
                    'route' => $route,
                    'lang' => $lang,
                    'translated' => in_array($lang, $page_languages),
                    'location' => $location,
                    'lastmod' => date($this->datetime_format, $lastmod),
                    'longdate' => date('Y-m-d\TH:i:sP', $page->date()),
                    'shortdate' => date('Y-m-d', $page->date()),
                    'timestamp' => intval($page->date()),
                    'rawroute' => $page->rawRoute(),
                    'description' => $this->pageDescription($page),
                    'home' => $page->home(),
                    'markdown' => isset($this->grav['markdown_output']) ? $this->grav['markdown_output']->url($page) : null,
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



                $this->route_data[$rawroute][$lang] = $lang_route;
            }
        }
    }
}
