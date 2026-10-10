# Jumbo

Content sites on [Dumbo](../README.md), named after his mum. Pages are files, templates are plain PHP, and the same app serves pages on demand or builds them to static files.

Dumbo's [docs](../docs) are built with it.

## Quickstart

```bash
vendor/bin/jumbo new my-site   # Copy the starter into my-site/
```

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
vendor/bin/jumbo dev      # Serve on localhost:8000, reloading as you save
vendor/bin/jumbo build    # Build to dist/
vendor/bin/jumbo preview  # Serve dist/ like a static host, on localhost:4000
vendor/bin/jumbo routes   # List the pages and redirects
```

The Dumbo app is `$site->app`, so middleware and API routes work as usual. There are runnable sites in [`examples/`](examples).

## Pages

| File | Route |
| --- | --- |
| `pages/index.php` | `/` |
| `pages/about.md` | `/about` |
| `pages/blog/[slug].php` | `/blog/:slug` |
| `pages/docs/[...path].php` | `/docs/*` |
| `pages/rss.xml.php` | `/rss.xml`, sent as XML |
| `pages/404.php` | Not found, and `404.html` in builds |
| `pages/500.php` | Errors in production, with `$error` |

Files and folders starting with `_` are skipped.

- **Templates** are plain PHP and get `$site`, `$context` and `$params`.
- **Markdown** is rendered in the view named by its `layout` front matter, as `$page` and `$seo`.
- **Pages with data** return a `Page`:

```php
<?php // pages/blog/[slug].php

use Dumbo\Jumbo\Page;

return new Page(
    paths: fn($site) => array_map(fn($slug) => [
        "params" => ["slug" => $slug],
        "props" => ["post" => $site->find($slug)],
    ], $site->slugs()),
    render: fn($context, $site, $props) => $site->view("post", ["post" => $props["post"]]),
);
```

`paths` lists the params to build, either on their own (`["slug" => "hello"]`) or with props for `render`. Set `prerender: false` to render a page on each request instead, and `middleware` to add Dumbo middleware.

`$site->paginate($items, 10)` makes the paths for `pages/blog/[page].php`, with a `Pagination` as the `page` prop: its `items`, `current` and `last`, and `url()`, `previous()` and `next()` for links.

## Actions

Pages handle their own forms with actions. A form posts back to the page, the action runs, and the page renders again with what happened:

```php
<?php // pages/contact.php

use Dumbo\Jumbo\Action;
use Dumbo\Jumbo\Page;

return new Page(
    render: fn($context, $site) => $site->view("contact", ["action" => $site->action($context)]),
    actions: [
        "default" => function (array $input, $context, $site) {
            if (!filter_var($input["email"] ?? "", FILTER_VALIDATE_EMAIL)) {
                return Action::fail(["email" => "Enter an email address"]);
            }

            // ...send the message
            return $context->redirect("/contact/thanks", 303);
        },
    ],
);
```

```php
<?php // views/contact.php ?>
<form method="post">
    <input name="email" value="<?= e($action?->old("email")) ?>">
    <?php if ($error = $action?->error("email")): ?><p><?= e($error) ?></p><?php endif ?>
    <button>Send</button>
</form>
```

- **Results:** return a response, like a redirect, to send it. Return data to show it on the page as `$action->data`, or `Action::fail($errors)` to show the page again with a 422, the errors and what was sent.
- **More than one form:** name the actions and add `<input type="hidden" name="_action" value="subscribe">`, or post to `?_action=subscribe`. `$site->action($context, "subscribe")` is only that action's result.
- **Without reloading:** a `fetch()` with `Accept: application/json` gets `{"ok", "data", "errors"}` back instead of the page.
- **Security:** forms posted from other sites are refused, using the `Sec-Fetch-Site` and `Origin` headers browsers send. Allow other origins with `actions.origins`, or turn it off with `actions.checkOrigin`.

Actions need the PHP server, as static hosts can't take a POST. The page itself still builds for GET. For API routes that aren't pages, add them to `$site->app` as usual.

## Layouts

```php
<?php $this->layout("base", ["title" => "Pricing"]) ?>

<?php $this->start("head") ?><link rel="stylesheet" href="/pricing.css"><?php $this->end() ?>

<h1>Pricing</h1>
```

The layout gets the output as `$slot` and named slots from `$this->slot("head")`. Layouts can have layouts. Escape values with `e()`.

`<style hoist>` and `<script hoist>` tags move into the `<head>`, once each, so components can bring their own CSS and JS.

## Content

`$site->find("hello")` loads `content/hello.md` with its front matter, HTML, title and table of contents, and `$site->slugs()` lists them all. In Markdown, `::: name key="value"` renders `components/name.php` with `$attributes` and `$slot`.

Fenced code is highlighted on the server with [tempest/highlight](https://github.com/tempestphp/highlight). By default it gets `hl-*` classes, which `<style><?= $site->highlightStyles("github-dark") ?></style>` styles. Set `markdown.highlight` to a theme name for inline colours instead, or `false` to leave code plain. Add CommonMark extensions and config with `markdown.extensions` and `markdown.config`.

## Images

```php
<?= $site->img("/photos/beach.jpg", "The beach", width: 800) ?>
```

That's an `<img>` with a WebP `srcset` up to twice the width, its `width` and `height`, and lazy loading. `$site->image("/photos/beach.jpg", 400)` is just the URL. Images in Markdown get the same. Resizing uses GD, and only the widths in `images.widths` (plus each image's own) are made, so visitors can't ask for any size. Builds write every size the pages use.

## Assets

`$site->file("/app.css")` is `/app.css?v=1a2b3c4d`, with a hash of the file, and those URLs are cached for a year. `$site->asset("package", "file.css")` loads a file from an npm package, from a CDN while developing and from `node_modules` in production.

## Redirects

```php
"redirects" => [
    "/old" => "/new",
    "/posts/[slug]" => ["to" => "/blog/[slug]", "status" => 302],
],
```

Redirects without params become meta refreshes in builds.

## Locales

```php
"i18n" => ["locales" => ["en", "fr"]],
```

Every page is also served under `/fr`. `$site->locale($context)` is the page's locale, `$site->localize("/about", "fr")` is `/fr/about`, and `$site->alternates($context)` is the page in each locale, for language switchers and hreflang links. `$site->find("hello", "fr")` loads `content/fr/hello.md` if there is one. Set `prefixDefault` to serve the default locale under its own prefix too.

## Feeds and sitemaps

`$site->rss($items)` returns an RSS feed with the channel from your config, for a page like `pages/rss.xml.php`. Builds write `sitemap.xml` with every HTML page that isn't `noindex`, when `url` is set and there's no sitemap page. Set `sitemap` to a callable to choose the URLs, or `false` to skip it.

## Builds

Each page is requested through the app, so builds match what the server sends. Pages become `path/index.html`, or `path.html` with `build.format` set to `"file"`. Redirects become meta refreshes, and `public/` is copied over. Pass `--url` for canonical URLs and any sub-path, or `--base` and `--out` on their own. Preview a build with a sub-path using the same `--base`.

Builds list links to pages that weren't built and that the app doesn't serve either. To run something around a build, like a CSS build or a deploy:

```php
$site->on("build:start", fn(string $out) => passthru("npm run build"));
$site->on("build:done", fn(string $out, array $files) => print(count($files) . " files\n"));
```

## Developing

`jumbo dev` reloads open pages when a file changes (`--no-reload` turns that off), and shows uncaught errors with the code around them. In production, errors render `pages/500.php` instead. To handle errors yourself, call `$site->app->onError()` after creating the site.

## Config

`name`, `url`, `locale`, `seo` defaults, `assets` (`"cdn"` or `"local"`), `markdown`, `images`, `redirects`, `i18n`, `build`, `sitemap`, `actions`, `prefetch` (prefetch links with speculation rules), `viewTransitions` (animate between pages), and `dirs` to rename folders. See `Site::DEFAULTS` for every option. Extend `Dumbo\Jumbo\Site` for your own defaults and template helpers, as [`docs/src/Site.php`](../docs/src/Site.php) does.
