# Jumbo

Content sites on [Dumbo](../README.md), named after his mum. Pages are files, templates are plain PHP, and the same app serves pages on demand or builds them to static files.

Dumbo's [docs](../docs) are built with it.

## Quickstart

```
app.php       Returns the site
pages/        One file per route
views/        Layouts and templates
components/   Markdown components
content/      Markdown to load in pages
public/       Files served as-is
```

```php
<?php // app.php

require __DIR__ . "/vendor/autoload.php";

return new Dumbo\Jumbo\Site(__DIR__, ["name" => "Acme", "url" => "https://acme.dev"]);
```

```bash
vendor/bin/jumbo dev      # Serve on localhost:8000
vendor/bin/jumbo build    # Build to dist/
vendor/bin/jumbo routes   # List the pages
```

The Dumbo app is `$site->app`, so middleware and API routes work as usual.

## Pages

| File | Route |
| --- | --- |
| `pages/index.php` | `/` |
| `pages/about.md` | `/about` |
| `pages/blog/[slug].php` | `/blog/:slug` |
| `pages/docs/[...path].php` | `/docs/*` |
| `pages/sitemap.xml.php` | `/sitemap.xml`, sent as XML |
| `pages/404.php` | Not found, and `404.html` in builds |

Files and folders starting with `_` are skipped.

- **Templates** are plain PHP and get `$site`, `$context` and `$params`.
- **Markdown** is rendered in the view named by its `layout` front matter, as `$page` and `$seo`.
- **Pages with data** return a `Page`:

```php
<?php // pages/blog/[slug].php

use Dumbo\Jumbo\Page;

return new Page(
    paths: fn($site) => array_map(fn($slug) => ["slug" => $slug], $site->slugs()),
    render: fn($context, $site) => $site->view("post", [
        "post" => $site->find($context->req->param("slug")),
    ]),
);
```

`paths` lists the params to build. Set `prerender: false` to render a page on each request instead, and `middleware` to add Dumbo middleware.

## Layouts

```php
<?php $this->layout("base", ["title" => "Pricing"]) ?>

<?php $this->start("head") ?><link rel="stylesheet" href="/pricing.css"><?php $this->end() ?>

<h1>Pricing</h1>
```

The layout gets the output as `$slot` and named slots from `$this->slot("head")`. Layouts can have layouts. Escape values with `e()`.

## Content

`$site->find("hello")` loads `content/hello.md` with its front matter, HTML, title and table of contents, and `$site->slugs()` lists them all. In Markdown, `::: name key="value"` renders `components/name.php` with `$attributes` and `$slot`.

## Builds

Each page is requested through the app, so builds match what the server sends. Pages become `path/index.html`, redirects become meta refreshes, and `public/` is copied over. Pass `--url` for canonical URLs and any sub-path, or `--base` and `--out` on their own.

## Config

`name`, `url`, `locale`, `seo` defaults, `assets` (`"cdn"` or `"local"`), and `dirs` to rename folders. Extend `Dumbo\Jumbo\Site` for your own defaults and template helpers, as [`docs/src/Site.php`](../docs/src/Site.php) does.
