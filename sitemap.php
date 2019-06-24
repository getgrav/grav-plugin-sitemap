<?php
namespace Grav\Plugin;

use Grav\Common\Data;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use RocketTheme\Toolbox\Event\Event;

require_once __DIR__ . '/classes/sitemapentry.php';

class SitemapPlugin extends Plugin
{
    protected $sitemap = array();

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
        $this->sitemap = array_merge(
            $this->buildSitemapFromPages(),
            $this->buildAdditionalSitemap()
        );
    }

    public function onPageInitialized()
    {
        if ($this->debugViewEnabled()) {
            $filename = 'debug_sitemap.md';
        } else {
            $filename = 'sitemap.md';
        }

        // Set a dummy page.
        $page = new Page;
        $page->init(new \SplFileInfo(__DIR__ . '/pages/' . $filename));

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
        $twig->twig_vars['sitemap'] = $this->sitemap;

        if ($this->debugViewEnabled()) {
            $twig->template = 'debug_sitemap.html.twig';
        } else {
            $twig->template = 'sitemap.xml.twig';
        }
    }

    /**
     * Extend page blueprints with feed configuration options.
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

    private function buildSitemapFromPages() {
        $sitemap = [];

        $pages = $this->grav['pages'];
        $routes = array_unique($pages->routes());
        ksort($routes);

        foreach ($routes as $route => $path) {
            $page = $pages->get($path);

            if ($page->published() && $page->routable() && !$this->ignorePage($page)) {
                $entry = SitemapEntry::fromPage($page);

                if ($this->websiteIsMultiLang()) {
                    foreach ($this->getTranslatedPages($page) as $translatedPage) {
                        if (!$page->published() || !$page->routable()) {
                            continue;
                        }
                        if ($translatedPage->home()) {
                            $translatedPage->route('/');
                        }

                        $entry->translations[] = SitemapEntry::fromPage($translatedPage);
                    }
                }

                $sitemap[] = $entry;
            }
        }

        return $sitemap;
    }

    private function buildAdditionalSitemap() {
        $sitemap = [];

        $pages = $this->grav['pages'];
        $rootUrl = $this->grav['uri']->rootUrl(true) . $pages->base();
        $additions = (array) $this->config->get('plugins.sitemap.additions');

        foreach ($additions as $addition) {
            $this->sitemap[] = new SitemapEntry(
                $rootUrl . $addition['location'],
                $addition['lastmod']
            );
        }

        return $sitemap;
    }

    private function getTranslatedPages($page) {
        $translatedPages = [];

        foreach (array_keys($page->translatedLanguages(true)) as $language) {
            if ($language == $page->language()) {
                continue;
            }

            $translatedPages[] = new TranslatedPage($page, $language);
        }

        return $translatedPages;
    }

    private function websiteIsMultiLang() {
        return count($this->config->get('system.languages.supported', [])) > 0;
    }

    private function ignorePage($page) {
        $header = $page->header();
        if (isset($header->sitemap['ignore']) && $header->sitemap['ignore']) {
            return true;
        }

        $ignores = (array) $this->config->get('plugins.sitemap.ignores');
        if (array_search($page->route(), $ignores, true) !== false) {
            return true;
        }

        return false;
    }

    private function debugViewEnabled() {
        return array_key_exists('debug', $this->grav['request']->getQueryParams());
    }
}

class TranslatedPage extends Page
{
    private $grav;
    private $basePage;
    private $targetLanguage;

    function __construct($basePage, $targetLanguage)
    {
        $this->grav = Grav::instance();

        $this->basePage = $basePage;
        $this->targetLanguage = $targetLanguage;

        $extension = $targetLanguage . ".md";
        $filepath = $this->replacePageFilePathLanguage(
            $basePage->filePath(),
            $targetLanguage
        );

        parent::__construct();
        $this->init(new \SplFileInfo($filepath), $extension);
    }

    public function canonical($include_lang = true)
    {
        $root = $this->grav['uri']->rootUrl(true);
        $base = $this->grav['pages']->base();
        $language = $include_lang ? '/' . $this->language() : '';
        return $root . $base . $language . $this->url(false, true, false);
    }

    public function parent($var = null)
    {
        if ($var) {
            throw new Exception('not implemented');
        }

        $baseParent = $this->basePage->parent();
        if (is_null($baseParent)) {
            return null;
        }

        return new static($baseParent, $this->targetLanguage);
    }

    private function replacePageFilePathLanguage($filepath, $targetLanguage)
    {
        return preg_replace(
            '/\.[a-z]{2}\.md$/',
            '.' . $targetLanguage . '.md',
            $filepath
        );
    }
}
