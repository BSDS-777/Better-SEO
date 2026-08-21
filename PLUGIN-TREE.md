# Better SEO — Plugin Directory Tree

> \*\*WordPress Plugin Structure Reference\*\*
> Last updated: 2026-08-20
> Standard: WordPress Plugin Handbook + PHP 8.3 + GPL-2.0-or-later
> Verified against actual filesystem — every ✅ entry confirmed to exist on disk.

\---

## 📁 Complete Directory Tree

```
better-seo/
│
├── better-seo.php                          ✅ — main plugin entry point
├── uninstall.php                           ✅ — cleanup on plugin deletion
├── index.php                               ✅ — directory access guard (Psalm 4, KJV)
├── README.md                               ✅ — GitHub readme
├── CHANGELOG.md                            ✅ — version history (Keep a Changelog 1.0.0)
├── PLUGIN-TREE.md                          ✅ — this file
├── composer.json                           ✅ — PSR-4 autoloader + dev dependencies
├── LICENSE                                 ✅ — GPL-2.0 license text
├── .gitignore                              ✅ — Git ignore rules
│
├── inc/                                    ✅ — non-autoloaded PHP files
│   ├── compat/                             ✅ — third-party compatibility
│   │   ├── plugin-bbpress.php              ✅
│   │   ├── plugin-buddypress.php           ✅
│   │   ├── plugin-edd.php                  ✅
│   │   ├── plugin-elementor.php            ✅
│   │   ├── plugin-jetpack.php              ✅
│   │   ├── plugin-polylang.php             ✅
│   │   ├── plugin-ultimatemember.php       ✅
│   │   ├── plugin-woocommerce.php          ✅
│   │   ├── plugin-wpforo.php               ✅
│   │   ├── plugin-wpml.php                 ✅
│   │   ├── theme-avada.php                 ✅
│   │   ├── theme-bricks.php                ✅
│   │   └── theme-genesis.php               ✅
│   │
│   ├── functions/                          ✅ — global function definitions
│   │   ├── api.php                         ✅ — Better\_SEO() / BetterSeo() global functions
│   │   ├── deprecated.php                  ✅ — deprecated function stubs
│   │   └── upgrade-suggestion.php          ✅ — post-upgrade notice logic
│   │
│   └── views/                              ✅ — PHP template/view files
│       ├── debug/                          ✅
│       │   ├── header.php                  ✅
│       │   └── output.php                  ✅
│       ├── list/                           ✅ — list-edit (quick/bulk edit) views
│       │   ├── bulk-post.php               ✅
│       │   ├── quick-post.php              ✅
│       │   └── quick-term.php              ✅
│       ├── notice/                         ✅
│       │   └── persistent.php              ✅
│       ├── post/                           ✅ — post edit screen views
│       │   ├── gutenberg-data.php          ✅
│       │   ├── homepage-warning.php        ✅
│       │   ├── settings.php                ✅
│       │   ├── wrap-content.php            ✅
│       │   └── wrap-nav.php                ✅
│       ├── profile/                        ✅ — user profile views
│       │   └── settings.php                ✅
│       ├── settings/                       ✅ — global settings page views
│       │   ├── columns.php                 ✅
│       │   ├── notice.php                  ✅
│       │   ├── wrap-content.php            ✅
│       │   ├── wrap-nav.php                ✅
│       │   └── wrap.php                    ✅
│       ├── sitemap/                        ✅
│       │   ├── xml-sitemap.php             ✅
│       │   └── xsl-stylesheet.php          ✅
│       ├── templates/                      ✅ — JS-consumed wp.template() templates
│       │   ├── inpost/                     ✅
│       │   │   └── primary-term-selector.php ✅
│       │   ├── list/                       ✅
│       │   │   └── primary-term-selector.php ✅
│       │   └── settings/                   ✅
│       │       └── warnings.php            ✅
│       └── term/                           ✅ — term edit screen views
│           └── settings.php                ✅
│
├── includes/                               ✅ — PSR-4 autoloaded classes (Better\_SEO\\)
│   ├── class-pool.php                      ✅ — Better\_SEO\\Pool (facade/proxy pool)
│   ├── class-load.php                      ✅ — Better\_SEO\\Load (main bootstrap)
│   ├── class-legacy-api.php                ✅ — Better\_SEO\\Legacy\_API (Pool base)
│   │
│   ├── admin/                              ✅ — Better\_SEO\\Admin namespace
│   │   ├── class-notice.php                ✅
│   │   ├── class-plugin-table.php          ✅
│   │   ├── class-utils.php                 ✅
│   │   ├── class-menu.php                  ✅ — settings page registration
│   │   ├── lists/                          ✅
│   │   │   ├── class-post-states.php       ✅
│   │   │   └── class-table.php             ✅
│   │   ├── notice/                         ✅
│   │   │   └── class-persistent.php        ✅
│   │   ├── script/                         ✅
│   │   │   ├── class-ajax.php              ✅
│   │   │   ├── class-loader.php            ✅
│   │   │   ├── class-registry.php          ✅
│   │   │   └── class-utils.php             ✅
│   │   ├── seobar/                         ✅
│   │   │   ├── class-builder.php           ✅
│   │   │   ├── class-listtable.php         ✅
│   │   │   └── builder/                    ✅
│   │   │       ├── class-main.php          ✅
│   │   │       ├── class-page.php          ✅
│   │   │       └── class-term.php          ✅
│   │   └── settings/                       ✅
│   │       ├── class-listedit.php          ✅
│   │       ├── class-post.php              ✅
│   │       ├── class-term.php              ✅
│   │       ├── class-user.php              ✅
│   │       └── layout/                     ✅
│   │           ├── class-form.php          ✅
│   │           ├── class-html.php          ✅
│   │           └── class-input.php         ✅
│   │
│   ├── data/                               ✅ — Better\_SEO\\Data namespace
│   │   ├── class-blog.php                  ✅
│   │   ├── class-plugin.php                ✅
│   │   ├── class-post.php                  ✅
│   │   ├── class-term.php                  ✅
│   │   ├── class-user.php                  ✅
│   │   ├── admin/                          ✅
│   │   │   ├── class-plugin.php            ✅
│   │   │   ├── class-post.php              ✅
│   │   │   ├── class-term.php              ✅
│   │   │   └── class-user.php              ✅
│   │   ├── filter/                         ✅
│   │   │   ├── class-escape.php            ✅
│   │   │   ├── class-plugin.php            ✅
│   │   │   ├── class-post.php              ✅
│   │   │   ├── class-sanitize.php          ✅
│   │   │   ├── class-term.php              ✅
│   │   │   └── class-user.php              ✅
│   │   └── plugin/                         ✅
│   │       ├── class-deprecated.php        ✅
│   │       ├── class-helper.php            ✅
│   │       ├── class-post.php              ✅
│   │       ├── class-pta.php               ✅
│   │       ├── class-setup.php             ✅
│   │       ├── class-term.php              ✅
│   │       └── class-user.php              ✅
│   │
│   ├── front/                              ✅ — Better\_SEO\\Front namespace
│   │   ├── class-feed.php                  ✅
│   │   ├── class-oembed.php                ✅
│   │   ├── class-query.php                 ✅
│   │   ├── class-redirect.php              ✅
│   │   ├── class-title.php                 ✅
│   │   └── meta/                           ✅
│   │       ├── class-head.php              ✅
│   │       ├── class-tags.php              ✅
│   │       └── generator/                  ✅
│   │           ├── class-advanced-query-protection.php ✅
│   │           ├── class-description.php   ✅
│   │           ├── class-facebook.php      ✅
│   │           ├── class-open-graph.php    ✅
│   │           ├── class-robots.php        ✅
│   │           ├── class-schema.php        ✅
│   │           ├── class-theme-color.php   ✅
│   │           ├── class-twitter.php       ✅
│   │           ├── class-uri.php           ✅
│   │           └── class-webmasters.php    ✅
│   │
│   ├── helper/                             ✅ — Better\_SEO\\Helper namespace
│   │   ├── class-compatibility.php         ✅
│   │   ├── class-guidelines.php            ✅
│   │   ├── class-headers.php               ✅
│   │   ├── class-migrate.php               ✅
│   │   ├── class-post-type.php             ✅
│   │   ├── class-query.php                 ✅
│   │   ├── class-redirect.php              ✅
│   │   ├── class-taxonomy.php              ✅
│   │   ├── class-template.php              ✅
│   │   ├── format/                         ✅
│   │   │   ├── class-arrays.php            ✅
│   │   │   ├── class-color.php             ✅
│   │   │   ├── class-html.php              ✅
│   │   │   ├── class-markdown.php          ✅
│   │   │   ├── class-minify.php            ✅
│   │   │   ├── class-strings.php           ✅
│   │   │   └── class-time.php              ✅
│   │   └── query/                          ✅
│   │       ├── class-cache.php             ✅
│   │       ├── class-exclusion.php         ✅
│   │       ├── class-filter.php            ✅
│   │       └── class-utils.php             ✅
│   │
│   ├── internal/                           ✅ — Better\_SEO\\Internal namespace
│   │   ├── class-debug.php                 ✅
│   │   ├── class-deprecated.php            ✅
│   │   └── class-silencer.php              ✅
│   │
│   ├── meta/                               ✅ — Better\_SEO\\Meta namespace
│   │   ├── class-breadcrumbs.php           ✅
│   │   ├── class-description.php           ✅
│   │   ├── class-facebook.php              ✅
│   │   ├── class-image.php                 ✅
│   │   ├── class-open-graph.php            ✅
│   │   ├── class-robots.php                ✅
│   │   ├── class-schema.php                ✅
│   │   ├── class-theme-color.php           ✅
│   │   ├── class-title.php                 ✅
│   │   ├── class-twitter.php               ✅
│   │   ├── class-uri.php                   ✅
│   │   ├── description/                    ✅
│   │   │   └── class-excerpt.php           ✅
│   │   ├── facebook/                       ✅
│   │   │   └── class-utils.php             ✅
│   │   ├── image/                          ✅
│   │   │   ├── class-generator.php         ✅
│   │   │   └── class-utils.php             ✅
│   │   ├── open-graph/                     ✅
│   │   │   └── class-utils.php             ✅
│   │   ├── robots/                         ✅
│   │   │   ├── class-args.php              ✅
│   │   │   ├── class-factory.php           ✅
│   │   │   ├── class-front.php             ✅
│   │   │   └── class-main.php              ✅
│   │   ├── schema/                         ✅
│   │   │   └── entities/                   ✅
│   │   │       ├── class-author.php        ✅
│   │   │       ├── class-breadcrumblist.php ✅
│   │   │       ├── class-organization.php  ✅
│   │   │       ├── class-person.php        ✅
│   │   │       ├── class-reference.php     ✅
│   │   │       ├── class-webpage.php       ✅
│   │   │       └── class-website.php       ✅
│   │   ├── title/                          ✅
│   │   │   ├── class-conditions.php        ✅
│   │   │   └── class-utils.php             ✅
│   │   ├── twitter/                        ✅
│   │   │   └── class-utils.php             ✅
│   │   └── uri/                            ✅
│   │       └── class-utils.php             ✅
│   │
│   ├── robotstxt/                          ✅ — Better\_SEO\\RobotsTxt namespace
│   │   ├── class-main.php                  ✅
│   │   └── class-utils.php                 ✅
│   │
│   ├── sitemap/                            ✅ — Better\_SEO\\Sitemap namespace
│   │   ├── class-cache.php                 ✅
│   │   ├── class-cron.php                  ✅
│   │   ├── class-lock.php                  ✅
│   │   ├── class-registry.php              ✅
│   │   ├── class-utils.php                 ✅
│   │   ├── optimized/                      ✅
│   │   │   ├── class-base.php              ✅
│   │   │   ├── class-main.php              ✅
│   │   │   └── class-xsl.php               ✅
│   │   └── wp/                             ✅
│   │       ├── class-filter.php            ✅
│   │       ├── class-posts.php             ✅
│   │       └── class-taxonomies.php        ✅
│   │
│   └── traits/                             ✅ — Better\_SEO\\Traits namespace
│       ├── trait-property-refresher.php    ✅
│       └── internal/                       ✅
│           └── trait-static-deprecator.php ✅
│
├── languages/                              ✅ 
│   └── better-seo.pot                      ✅ — translation template
│
├── lib/                                    ✅ — compiled/minified assets
│   ├── css/                                ✅
│   │   ├── tokens.css                      ✅ — CSS custom property design tokens
│   │   ├── better-seo.css                  ✅ — core admin styles
│   │   ├── better-seo-c.css                ✅ — counter styles
│   │   ├── le.css                          ✅ — list-edit styles
│   │   ├── media.css                       ✅ — media uploader styles
│   │   ├── post.css                        ✅ — post edit styles
│   │   ├── pt.css                          ✅ — primary term styles
│   │   ├── settings.css                    ✅ — settings page styles
│   │   ├── term.css                        ✅ — term edit styles
│   │   ├── tt.css                          ✅ — tooltip styles
│   │   ├── ui.css                          ✅ — shared UI styles
│   │   └── DESIGN-TOKENS.md               ✅ — token reference documentation
│   │
│   └── js/                                 ✅
│       ├── utils.js                        ✅ — load ORDER 1: debounce, delay
│       ├── better-seo.js                   ✅ — load ORDER 2: core namespace
│       ├── ui.js                           ✅ — load ORDER 3: UI helpers
│       ├── tt.js                           ✅ — tooltips
│       ├── ays.js                          ✅ — are-you-sure (unsaved changes)
│       ├── c.js                            ✅ — character/pixel counters
│       ├── tabs.js                         ✅ — tab navigation
│       ├── title.js                        ✅ — title input module
│       ├── description.js                  ✅ — description input module
│       ├── canonical.js                    ✅ — canonical URL module
│       ├── social.js                       ✅ — OG/Twitter social meta module
│       ├── media.js                        ✅ — media uploader module
│       ├── authorslugs.js                  ✅ — author slug fetcher
│       ├── postslugs.js                    ✅ — post slug fetcher
│       ├── termslugs.js                    ✅ — term slug fetcher
│       ├── gbc.js                          ✅ — Gutenberg block compat
│       ├── pt.js                           ✅ — primary term selector
│       ├── pt-gb.js                        ✅ — primary term (Gutenberg)
│       ├── pt-le.js                        ✅ — primary term (list-edit)
│       ├── le.js                           ✅ — list-edit module
│       ├── post.js                         ✅ — post edit screen module
│       ├── term.js                         ✅ — term edit screen module
│       └── settings.js                     ✅ — settings page module
│
└── tools/                                  ✅ — developer utilities
    └── migrate-css-tokens.php              ✅ — CSS token migration script
```

\---

## 🔄 Files Moved (All Applied)

|From|To|Status|
|-|-|-|
|`inc/traits/trait-property-refresher.php`|`includes/traits/trait-property-refresher.php`|✅ Done|
|`inc/traits/internal/trait-static-deprecator.php`|`includes/traits/internal/trait-static-deprecator.php`|✅ Done|
|`analysis/pool-analysis.md`|*(deleted — internal scratch file, not a deliverable)*|✅ Done|

\---

## ✅ Load Order (`better-seo.php`)

```
1. Define constants
2. require\_once inc/functions/api.php          — global functions
3. require\_once inc/functions/deprecated.php   — deprecated stubs
4. Composer autoloader (includes/ via PSR-4)
5. Load compat files conditionally             — inc/compat/
6. Register activation/deactivation hooks
7. Better\_SEO\\Load::get\_instance()             — bootstrap
```

\---

## 📊 Plugin Stats (Verified)

|Category|Count|
|-|-|
|PHP class files (`includes/`)|108|
|PHP trait files (`includes/traits/`)|2|
|PHP view/template files (`inc/views/`)|22|
|PHP compat files (`inc/compat/`)|13|
|PHP function files (`inc/functions/`)|3|
|JavaScript files (`lib/js/`)|22|
|CSS files (`lib/css/`)|11|
|Root/config files|7|
|**Total files on disk**|**188**|



