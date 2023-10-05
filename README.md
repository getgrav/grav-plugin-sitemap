# Grav Sitemap Plugin

`Sitemap` is a [Grav](https://github.com/getgrav/grav) Plugin that generates a [map of your pages](https://en.wikipedia.org/wiki/Site_map) in `XML` format that is easily understandable and indexable by Search engines.

# Installation

Installing the Sitemap plugin can be done in one of two ways. Our GPM (Grav Package Manager) installation method enables you to quickly and easily install the plugin with a simple terminal command, while the manual method enables you to do so via a zip file.

## GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](https://learn.getgrav.org/advanced/grav-gpm) through your system's Terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install sitemap

This will install the Sitemap plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/sitemap`.

## Manual Installation

To install this plugin, just download the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `sitemap`. You can find these files either on [GitHub](https://github.com/getgrav/grav-plugin-sitemap) or via [GetGrav.org](https://getgrav.org/downloads/plugins#extras).

You should now have all the plugin files under

    /your/site/grav/user/plugins/sitemap

>> NOTE: This plugin is a modular component for Grav which requires [Grav](https://github.com/getgrav/grav), the [Error](https://github.com/getgrav/grav-plugin-error) and [Problems](https://github.com/getgrav/grav-plugin-problems) plugins, and a theme to be installed in order to operate.


# Usage

The `sitemap` plugin works out of the box. You can just go directly to `http://yoursite.com/sitemap` and you will see the generated `XML`.

## Config Defaults

```yaml
enabled: true
route: '/sitemap'
ignore_external: true
ignore_protected: true
ignore_redirect: true
ignores:
  - /blog/blog-post-to-ignore
  - /ignore-this-route
  - /ignore-children-of-this-route/.*
include_news_tags: false
standalone_sitemap_news: false
sitemap_news_path: '/sitemap-news.xml'
news_max_age_days: 2
news_enabled_paths:
  - /blog
whitelist:
html_support: false
urlset: 'http://www.sitemaps.org/schemas/sitemap/0.9'
urlnewsset: 'http://www.google.com/schemas/sitemap-news/0.9'
short_date_format: true
include_changefreq: true
changefreq: daily
include_priority: true
priority: !!float 1
additions:
  -
    location: /something-special
    lastmod: '2020-04-16'
    changefreq: hourly
    priority: 0.3
  -
    location: /something-else
    lastmod: '2020-04-17'
    changefreq: weekly
    priority: 0.2
```

You can ignore your own pages by providing a list of routes to ignore. You can also use a page's Frontmatter to signal that the sitemap should ignore it:

```yaml
sitemap:
    ignore: true
```

## Multi-Language Support

The latest Sitemap `v3.0` includes all new multi-language support utilizing the latest [Google Search SEO Recomendations](https://developers.google.com/search/docs/advanced/crawling/localized-versions?hl=en&visit_id=637468720624267418-280936473&rd=2) which creates bi-directional `hreflang` entries for each language available.

This is handled automatically based on your Grav multi-language System configuration.

### News Support

New in version 4.0 of the plugin is support for Google's [**News Sitemap Extension**](https://developers.google.com/search/docs/crawling-indexing/sitemaps/news-sitemap) that uses a specific tags under a `<news:news></news:news>` tag to provide Google News specific data.  When enabled, the news extensions will be enabled when an item is in one of the configured news paths (`/` by default, so all), and if the published date is not older than the configured `max age` (default of 2 per Googles recommendations).

The output of the news tags is controlled by an overridable `sitemap-extensions/news.html.twig` template.

The default behavior when **Include News Tags** is enabled, is to  include the news tags directly in the primary `sitemap.xml` file.  However, if you enabled the **Standalone News URLs** option, news tags will not be added to the primary `sitemap.xml`, rather, they will be available in standalone paths that contain only the pages in the designated news paths.

For example, the default behavior is to enable `/blog` as a news path.  If this path exists, you have content in subfolders of this page, and that content is less than the defined "News Max Age" (2 days recommended by Google), then that sitemap-news-specific sitemap would be available via:

```
https://yoursite.com/blog/sitemap-news.xml
```

You can change the "News Path" to be something other than `sitemap-news.xml` if you wish.

## Images

You can add images to the sitemap by adding an entry in the page's Frontmatter.

```yaml
sitemap:
    images:
        your_image:
            loc: your-image.png
            caption: A caption for the image
            geoloc: Amsterdam, The Netherlands
            title: The title of your image
            license: A URL to the license of the image.
```

For more info on images in sitemaps see [Google image sitemaps](https://support.google.com/webmasters/answer/178636?hl=en).

## Only allow access to the .xml file

If you want your sitemap to only be accessible via `sitemap.xml` for example, set the route to `/sitemap` and add this to your `.htaccess` file:

`Redirect 301 /sitemap /sitemap.xml`

## HTML Support

As of Sitemap version `3.0.1` you can enable `html_support` in the configuration and then when you go to `/sitemap` or `/sitemap.html` you will view an HTML version of the sitemap per the `templates/sitemap.html.twig` template.  

You can copy and extend this Twig template in your theme to customize it for your needs.

## Manually add pages to the sitemap

You can manually add URLs to the sitemap using the Admin settings, or by adding entries to your `sitemap.yaml` with this format:

```yaml
additions:
  -
    location: /something-special
    lastmod: '2020-04-16'
    changefreq: hourly
    priority: 0.3
```
Note that Regex support is available: Just append `.*` to a path to ignore all of it's children.

## Dynamically adding pages to the sitemap

If you have some dynamic content being added to your site via another plugin, or perhaps a 3rd party API, you can now add them dynamically to the sitemap with a simple event:

Make sure you are subscribed to the `onSitemapProcessed` event then add simply add your entry to the sitemap like this:

```php
    public function onSitemapProcessed(\RocketTheme\Toolbox\Event\Event $e)
    {
        $sitemap = $e['sitemap'];
        $location = \Grav\Common\Utils::url('/foo-location', true);
        $sitemap['/foo'] = new \Grav\Plugin\Sitemap\SitemapEntry($location, '2020-07-02', 'weekly', '2.0');
        $e['sitemap'] = $sitemap;
    }
```

The use `Utils::url()` method allow us to easily create the correct full URL by passing it a route plus the optional `true` parameter.
