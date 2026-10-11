# Dumbo Docs

The Dumbo documentation site, built with [Jumbo](https://notrab.github.io/jumbo). Copy this folder to start docs for your own project.

## Run it

```bash
composer install
npm install
composer start
```

Then open [http://localhost:8000](http://localhost:8000). Run `npm run dev` alongside it to rebuild the CSS as you edit.

## Make it yours

1. In `composer.json`, remove the `repositories` block and require `notrab/dumbo` and `notrab/jumbo` from Packagist.
2. Set the name, logo, colours, links and sidebar in `config.php`.
3. Replace the Markdown in `content/`, and the home page in `pages/index.md`.
4. Delete `components/examples.php` and `content/examples.md`, which list Dumbo's examples.

| Path | What's in it |
| --- | --- |
| `config.php` | Site settings |
| `content/` | Docs pages, each served at `/docs/{file name}` |
| `pages/` | The home page, docs routes, robots.txt and 404 page. Jumbo writes the sitemap. |
| `components/` | Markdown components |
| `theme/` | Layout, templates and Tailwind styles |
| `public/` | Files served as-is |

## Pages

A page's title comes from its `title` front matter or its first `#` heading. List it under `sidebar` in `config.php` to show it in the menu, in reading order.

| Front matter | Does |
| --- | --- |
| `title` | Title for the sidebar, links and `<title>` |
| `seo_title` | Full `<title>`, skipping `titleTemplate` |
| `description` | Meta and social description |
| `image` | Social card image |
| `canonical` | Canonical URL |
| `noindex` | Hide from search engines and the sitemap |
| `layout` | Template in `theme/`, `page` by default |

## Components

```markdown
::: callout type="tip" title="Heads up"
Any **Markdown**.
:::
```

Built in: `callout`, `tabs` and `tab`, `steps`, `cards` and `card`, and `prompt`. Nest them by giving the outer fence more colons. Add your own as PHP files in `components/`, which get `$attributes` and the rendered Markdown as `$slot`.

## Deploy

```bash
composer build -- --url=https://example.com
```

This renders every page to `dist/` for any static host. Any sub-path in the URL, like `/dumbo`, is added to every link. Preview with `php -S localhost:8080 -t dist`.

To run it as a server instead, point your web server at `index.php` and set `DUMBO_ENV=production`.

Dumbo's docs deploy to GitHub Pages from [`.github/workflows/docs.yml`](../.github/workflows/docs.yml) on every push to `main`.
