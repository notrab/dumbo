# Static Hosting Example

This example demonstrates how to prepare a Jumbo site for a static host: redirects for moved pages, pages built as `.html` files, and a build hook that writes the host's own redirect and header files.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Build it and serve the build the way a static host would:

   ```bash
   composer build
   composer preview
   ```

3. Open [http://localhost:4000](http://localhost:4000), and follow the link to the old getting started page.

Use `composer dev` to work on it with the PHP server, which sends the redirects as real 301s.

## What it shows

- `redirects` in `app.php` moves old URLs, and `jumbo routes` lists them
- `build.format` set to `"file"` builds `guides/peanuts.html` rather than `guides/peanuts/index.html`
- The `build:done` hook writes `_redirects` and `_headers`, which Netlify and Cloudflare Pages read, so those hosts send real redirects
- `pages/404.md` becomes `404.html`, and its `noindex` keeps it out of `sitemap.xml`
- Builds check every link, and list any that lead nowhere
