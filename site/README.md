# Dumbo Site

Content sites on [Dumbo](../README.md). Pages are files, templates are plain PHP with layouts, Markdown has components, and the same app serves pages on demand or builds them to static files for any host.

Dumbo's own docs in [`docs/`](../docs) are built with it.

## A site

```
app.php          Returns the site
index.php        Runs it as a server
pages/           One file per route
views/           Layouts and templates
components/      Markdown components
content/         Markdown to load in pages
public/          Files served as-is
```

```php
<?php // app.php

require __DIR__ . "/vendor/autoload.php";

return new Dumbo\Site\Site(__DIR__, [
    "name" => "Acme",
    "url" => "https://acme.dev",
    "seo" => ["titleTemplate" => "%s - Acme"],
]);
```

```php
<?php // index.php

$site = require __DIR__ . "/app.php";

$site->run();
```

The site is a Dumbo app underneath, as `$site->app`, so middleware and API routes work as usual:

```php
$site->app->use(Dumbo\Middleware\Compress::compress());
$site->app->post("/api/subscribe", fn($context) => $context->json(["ok" => true]));
```

## Pages

Every `.php` and `.md` file in `pages/` is a route:

| File | Route |
| --- | --- |
| `pages/index.php` | `/` |
| `pages/about.md` | `/about` |
| `pages/blog/index.php` | `/blog` |
| `pages/blog/[slug].php` | `/blog/:slug` |
| `pages/docs/[...path].php` | `/docs/*`, at any depth |
| `pages/sitemap.xml.php` | `/sitemap.xml`, sent as XML |
| `pages/404.php` | Any route that isn't found, and `404.html` in builds |

Files and folders starting with `_` aren't routes, so helpers can sit next to the pages that use them.

### Templates

A page that's just markup is a template. It gets `$site`, `$context` and the route's `$params`:

```php
<?php $this->layout("base", ["title" => "About"]) ?>

<h1>About <?= e($site->config["name"]) ?></h1>
```

### Markdown

A Markdown page is rendered in the view named by its `layout` front matter, which gets the page as `$page` (with `meta`, `title`, `html` and `toc`) and its meta tags as `$seo`:

```markdown
---
layout: article
title: About us
description: Who we are
---

# About us
```

### Pages with data

A page that loads data, has params, or shouldn't be built ahead of time returns a `Page`:

```php
<?php // pages/blog/[slug].php

use Dumbo\Context;
use Dumbo\Site\Page;
use Dumbo\Site\Site;

return new Page(
    // The params to build, one page each
    paths: fn(Site $site) => array_map(fn($slug) => ["slug" => $slug], $site->slugs()),
    render: function (Context $context, Site $site) {
        $post = $site->find($context->req->param("slug"));

        return $post ? $site->view("post", ["post" => $post]) : $context->notFound();
    },
);
```

| Option | Does |
| --- | --- |
| `render` | Called with the context and site. Returns HTML, a response, or null for the context's response |
| `paths` | The params to build for a page with `[params]` in its name |
| `prerender` | `false`, or a closure returning it, to render the page on each request and leave it out of builds |
| `middleware` | Dumbo middleware for the page |

## Views and layouts

Views are PHP templates in `views/`, rendered with `$site->view("name", [...])`. Inside any template, `$this` picks a layout and fills its slots:

```php
<?php $this->layout("base", ["title" => "Pricing"]) ?>

<?php $this->start("head") ?>
    <link rel="stylesheet" href="/pricing.css">
<?php $this->end() ?>

<h1>Pricing</h1>
```

The layout gets the template's output as `$slot`, named slots from `$this->slot("head")`, and the template's variables. Layouts can have layouts, so a docs layout can sit inside the site's base layout:

```php
<?php // views/base.php ?>
<!DOCTYPE html>
<html>
<head>
    <title><?= e($title) ?></title>
    <?= $this->slot("head") ?>
</head>
<body><?= $slot ?></body>
</html>
```

Escape values with `e()`.

## Content and components

`$site->find("hello")` loads `content/hello.md` with its front matter, HTML, title and a table of contents from its `##` and `###` headings. `$site->slugs()` lists every file in `content/`.

Components drop richer blocks into Markdown. `::: name key="value"` renders `components/name.php` with `$attributes` and the Markdown inside as `$slot`:

```markdown
::: callout type="tip"
Any **Markdown**.
:::
```

Give the outer fence more colons to nest components.

## Commands

```bash
vendor/bin/dumbo-site dev                         # Serve on localhost:8000
vendor/bin/dumbo-site routes                      # List the pages and how each renders
vendor/bin/dumbo-site build                       # Build to dist/
vendor/bin/dumbo-site build --url=https://acme.dev/blog
```

A build sends each page through the app, so the files match what the server sends. Pages become `path/index.html`, redirects become meta refresh pages, the 404 page becomes `404.html`, and `public/` is copied over. Any sub-path in `--url`, or `--base`, is added to every link. `--out` sets the folder.

## Config

| Key | Does |
| --- | --- |
| `name` | The site's name |
| `url` | Where the site is hosted, for canonical URLs and the build's sub-path |
| `locale` | The site's language |
| `seo` | Defaults for `$site->seo()`: `title`, `titleTemplate`, `description`, `image`, `twitter` |
| `assets` | `"cdn"` or `"local"` for `$site->asset()`. CDN in development, local in production |
| `cdn` | The npm CDN for `$site->asset()` |
| `dirs` | Folder names for `pages`, `views`, `components`, `content` and `public` |

Extend `Dumbo\Site\Site` to add defaults for your own config and helpers for your templates, as the docs do in [`docs/src/Site.php`](../docs/src/Site.php).
