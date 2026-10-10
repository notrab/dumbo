# Dumbo Docs

The Dumbo documentation site, and a small docs starter you can copy for your own project. It's built on [`notrab/jumbo`](../jumbo), so it renders Markdown, runs as a server or builds to static files for any CDN, and ships with a playful theme.

## Quickstart

```bash
composer install
npm install
composer start
```

Then open [http://localhost:8000](http://localhost:8000).

## Using it for your own project

1. Copy this `docs/` folder into your project.
2. In `composer.json`, remove the `repositories` block and require `notrab/dumbo` and `notrab/jumbo` from Packagist.
3. Edit `config.php`: name, logo, colours, SEO, links and sidebar.
4. Replace the pages in `content/`. `pages/index.md` is the home page.
5. Delete `components/examples.php` and `content/examples.md`, which are specific to Dumbo.

## Folders

| Path | What's in it |
| --- | --- |
| `config.php` | Everything specific to your docs |
| `pages/` | One file per route: the home page, `/docs/{slug}`, the sitemap, robots.txt and the 404 page |
| `content/` | The docs pages, in Markdown |
| `components/` | Markdown components, built in and your own |
| `theme/` | The layout, page templates and Tailwind styles |
| `public/` | Files served as-is, like images |
| `src/` | The sidebar and previous and next links |
| `app.php` | Creates the site |
| `index.php` | Runs the site as a server |

## Pages

Each file in `content/` is a page at `/docs/{file name}`. Add it to `sidebar` in `config.php` to list it, and the order there sets the previous and next links. A page's title is its `title` front matter, or else its first `#` heading. The "On this page" menu is built from `##` and `###` headings.

```markdown
---
title: Routing
description: Register routes for every HTTP method.
---

# Routing
```

### Front matter

| Key | Does |
| --- | --- |
| `title` | The page title, used in the sidebar, links and the `<title>` |
| `seo_title` | The full `<title>`, skipping `titleTemplate` |
| `description` | The meta and social description |
| `image` | The social card image |
| `canonical` | The canonical URL |
| `noindex` | `true` hides the page from search engines and the sitemap |
| `layout` | A template in `theme/`, `page` by default |

### The home page

`pages/index.md` describes the home page in front matter, and `theme/home.php` renders it. Every section is optional. See Dumbo's for a full example.

```yaml
---
layout: home
hero:
  name: Acme
  text: The tagline under the name
  tagline: [Rotating, little, lines]
  badges: [v1.0, MIT]
  actions:
    - text: Get started
      href: /docs/introduction
  install: composer require acme/acme
  mascot: 🦊
  code:
    file: index.php
    language: php
    source: |
      <?php echo "hi";
features_title: Pick something to *build*
features:
  - title: A JSON API
    details: Routes and JSON responses in a few lines.
    href: /docs/routing
    link: Routing
    code: |
      $app->get('/', fn($c) => $c->json(['ok' => true]));
cta:
  title: Try it
  action:
    text: Quickstart
    href: /docs/quickstart
---
```

Words in `*asterisks*` in `features_title` get the marker-pen highlight. Each feature card takes a `title` and `details`, plus an optional `code` snippet (with `language`, PHP by default), a link (`href` and `link` text) and an `icon`. Dumbo's home page uses them to show use cases with real code.

## Components

Components drop richer blocks into Markdown. Open with three or more colons and the component's name, close with the same number of colons, and write Markdown in between:

```markdown
::: callout type="tip" title="Heads up"
Any **Markdown** goes here.
:::
```

To nest components, give the outer one more colons:

```markdown
:::: tabs
::: tab title="Composer"
...
:::
::: tab title="Manual"
...
:::
::::
```

### Built in

| Component | Attributes | Does |
| --- | --- | --- |
| `callout` | `type` (note, tip, warning, danger), `title` | A highlighted note |
| `tabs` | | A group of `tab` components |
| `tab` | `title` | One tab. Without JavaScript, tabs show one after another |
| `steps` | | Turns the ordered list inside into numbered steps |
| `cards` | | A grid of `card` components |
| `card` | `title`, `href`, `icon` | A card, linked when it has an `href` |
| `prompt` | `file`, `title` | Shows a text file from `public/` with a copy button, for prompts to paste into a coding agent |

### Your own

A component is a PHP file in `components/`. It gets `$attributes`, the rendered Markdown inside as `$slot`, and `$site` for config and helpers. For example, `components/badge.php`:

```php
<span class="rounded-full bg-accent-300 px-2 py-0.5 text-sm font-bold">
    <?= e($attributes["text"] ?? "") ?>
</span>
```

Then use it with `::: badge text="New"` and a closing `:::`. Dumbo's `components/examples.php` lists the repo's examples this way. Run `npm run build` after adding Tailwind classes that the site doesn't use yet.

## SEO

Defaults live in `seo` in `config.php`, and front matter overrides them per page. Every page gets a title, description, Open Graph and Twitter tags, and a theme colour.

Set `url` to where the site is hosted, or the `DOCS_URL` environment variable, to also get:

- Canonical URLs
- Absolute social card images
- `/sitemap.xml`, listing every page that isn't `noindex`
- A `Sitemap` line in `/robots.txt`

## Theme

Set `primary` and `accent` in `config.php` to any CSS colours. Every shade is mixed from them at runtime, so changing colours doesn't need a CSS rebuild. Keep the accent light, as text on it is dark.

For anything more, edit `theme/`. Run `npm run dev` in another terminal while you work so the CSS rebuilds on every change.

## Fonts and scripts

Fonts and highlight.js are npm packages, loaded in templates with `$site->asset()`:

```php
<script src="<?= e($site->asset("@highlightjs/cdn-assets", "highlight.min.js")) ?>"></script>
```

- In development, that's a [hop.js](https://hopjs.bunny.net) CDN URL, at the exact version in `package-lock.json`.
- In production (`DUMBO_ENV=production`), it's served from `node_modules`.
- Static builds bundle the files, and any fonts their CSS uses, into `dist/vendor/`, so the built site makes no requests to other hosts.

Set `assets` in `config.php` to `"cdn"` or `"local"` to choose yourself. To add a package, `npm install -D` it and call `$site->asset()`.

## Deploying

### Dynamically

Point your web server at `index.php`, as with any Dumbo app, and set `DUMBO_ENV=production`. Run `npm install && npm run build` first.

### Statically

```bash
composer build
```

This builds the CSS, then runs `jumbo build`, which renders every page to `dist/` by running each one through the app, so both modes produce the same HTML. Upload `dist/` to Netlify, Cloudflare Pages, GitHub Pages, S3 or any static host.

- Pages are written as `path/index.html`, so links like `/docs/routing` work without a server.
- `/docs` becomes a page that redirects to the first page in the sidebar.
- `404.html` is the not found page, which most static hosts pick up.

Pass the site URL to build the SEO tags and sitemap. Any sub-path in it, like `/dumbo`, is added to every link:

```bash
composer build -- --url=https://notrab.github.io/dumbo
```

Use `--base=/dumbo` to set the sub-path on its own. Preview a build with `php -S localhost:8080 -t dist`.

Dumbo's own docs deploy to GitHub Pages this way from [`.github/workflows/docs.yml`](../.github/workflows/docs.yml) on every push to `main`, and pull requests check that the build still works.
