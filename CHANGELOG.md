# v1.9.5
## 04/27/2020

1. [](#improved)
    * Add admin toggle for `ignore` [#68](https://github.com/getgrav/grav-plugin-sitemap/pull/68)
    * Omit empty `<lastmod>` [#70](https://github.com/getgrav/grav-plugin-sitemap/pull/70)
    * Added Chinese language [#73](https://github.com/getgrav/grav-plugin-sitemap/pull/73)
    * Added German language [#66](https://github.com/getgrav/grav-plugin-sitemap/pull/66)

# v1.9.4
## 10/19/2019

1. [](#bugfix)
    * Fixed a regression issue that caused sitemap not to work 'unless' you had an existing `sitemap` page [#65](https://github.com/getgrav/grav-plugin-sitemap/issues/65)

# v1.9.3
## 10/18/2019

1. [](#improved)
    * Support existing `sitemap` HTML page to be used in place of XSL version
    * Remove `/` from end of home URLS [#58](https://github.com/getgrav/grav-plugin-sitemap/pull/58)
    * Include translated pages only [#57](https://github.com/getgrav/grav-plugin-sitemap/pull/57)
    * Make sure `modular` pages are not included in configuration [#56](https://github.com/getgrav/grav-plugin-sitemap/pull/56)

# v1.9.2
## 05/09/2019

1. [](#improved)
    * Enhanced HTML layout with XSL transformation [#24](https://github.com/getgrav/grav-plugin-sitemap/pull/24)
    * Global toggles for change frequency and priority [#52](https://github.com/getgrav/grav-plugin-sitemap/pull/52)
    * Added a meta name="robots" content="noindex" into the header for seo [#50](https://github.com/getgrav/grav-plugin-sitemap/pull/50)
    * Added `ru` and `uk` translations [#61](https://github.com/getgrav/grav-plugin-sitemap/pull/61)
1. [](#bugfix)
    * Only add published translations to the sitemap [#43](https://github.com/getgrav/grav-plugin-sitemap/issues/43)

# v1.9.1
## 04/21/2017

1. [](#bugfix)
    * Add a namespace xhtml for a international sitemap [#40](https://github.com/getgrav/grav-plugin-sitemap/pull/40)

# v1.9.0
## 04/19/2017

1. [](#new)
    * Added wildcard ignores [#34](https://github.com/getgrav/grav-plugin-sitemap/pull/34)
    * Added ability to add external URLs to sitemap [#35](https://github.com/getgrav/grav-plugin-sitemap/pull/35)
    * Added page-level ignores [#37](https://github.com/getgrav/grav-plugin-sitemap/pull/37)
    * Added multilanguage support [#36](https://github.com/getgrav/grav-plugin-sitemap/pull/36)

# v1.8.0
## 03/14/2017

1. [](#new)
    * Added `changefreq` and `priority` [#28](https://github.com/getgrav/grav-plugin-sitemap/pull/28)
1. [](#improved)
    * Use `$page->canonical()` rather than `$page->permalink()` [#28](https://github.com/getgrav/grav-plugin-sitemap/pull/28)

# v1.7.0
## 10/19/2016

1. [](#new)
    * Use new Grav feature to force output to be XML even when not passed `.xml` in URL

# v1.6.2
## 07/14/2016

1. [](#bugfix)
    * Fix sitemap XLS in multilanguage

# v1.6.1
## 05/30/2016

1. [](#bugfix)
    * Priority should be `float` in blueprints

# v1.6.0
## 04/29/2016

1. [](#new)
    * Added compatibility with Grav Admin 1.1
1. [](#improved)
    * Use some common translated strings in the blueprint

# v1.5.0
## 01/06/2016

1. [](#new)
    * Added a default XSL file for the sitemap
1. [](#improved)
    * Added a note to the README on how to only allow the link to the .xml sitemap
1. [](#bugfix)
    * Fixed saving the `priority` option when adding it to a page through the Admin Plugin

# v1.4.2
## 11/11/2015

1. [](#bugfix)
    * Escape the `loc` so it's properly parsed

# v1.4.1
## 10/07/2015

1. [](#bugfix)
    * Avoid duplication of sitemap items

# v1.4.0
## 08/25/2015

1. [](#improved)
    * Added blueprints for Grav Admin plugin
1. [](#bugfix)
    * Don't show unpublished pages in sitemap

# v1.3.0
## 02/25/2015

1. [](#new)
    * Added `ignores` list to allow certain routes to be left out of sitemap

# v1.2.0
## 11/30/2014

1. [](#new)
    * ChangeLog started...
