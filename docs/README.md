# Dumbo Docs

The Dumbo documentation site, built with Dumbo, Markdown and Tailwind. It runs as a regular Dumbo app, or builds to static files you can upload to any CDN.

## Setup

```bash
composer install
npm install
```

## Dynamic mode

```bash
composer start
```

This builds the CSS and serves the site at [http://localhost:8000](http://localhost:8000). While editing templates, run `npm run dev` in another terminal to rebuild the CSS on every change.

To host it dynamically, point your web server at `index.php`, the same as any Dumbo app.

## Static mode

```bash
composer build
```

This builds the CSS, then renders every page to `dist/` by running each one through the app, so both modes produce the same HTML. Upload `dist/` to Netlify, Cloudflare Pages, GitHub Pages, S3 or any static host.

- Each page is written as `path/index.html`, so links like `/docs/routing` work without a server.
- `/docs` becomes a page that redirects to the introduction.
- `404.html` is the not found page, which most static hosts pick up automatically.

If the site lives under a sub-path, such as `https://notrab.github.io/dumbo/`, pass the base path:

```bash
composer build -- --base=/dumbo
```

Preview the build locally with:

```bash
php -S localhost:8080 -t dist
```

## How it fits together

- `app.php` defines the Dumbo app and returns it.
- `index.php` runs the app as a server.
- `build.php` renders the app to static files.

## Writing docs

- Pages are Markdown files in `content/`.
- Add a page to the sidebar in `nav.php`. The order there sets the previous and next links.
- The "On this page" menu is built from each page's `##` and `###` headings.

## Examples

The examples pages are generated from the repo's `examples/` directory. Each folder becomes a page showing its README and source, with the description on the listing taken from the first paragraph of the README. Add a folder with a `README.md` and it appears automatically.
