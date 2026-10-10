# Blog Example

This example demonstrates how to build a blog with Jumbo: posts in Markdown, pagination, tag pages, an RSS feed, a sitemap, highlighted code and responsive images.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the dev server, which reloads as you save:

   ```bash
   composer dev
   ```

3. Open [http://localhost:8000](http://localhost:8000).

To build it to static files and serve them the way a host would:

```bash
composer build
composer preview
```

## What it shows

- `app.php` extends the site with `posts()` and `tags()` helpers for every template
- `pages/posts/[slug].php` builds a page per post, passing each post as a prop
- `pages/blog/[page].php` splits the archive into pages with `$site->paginate()`
- `pages/tags/[tag].php` builds a page per tag
- `pages/rss.xml.php` is the feed, from `$site->rss()`, and builds write `sitemap.xml`
- Code in posts is highlighted on the server, styled with `$site->highlightStyles()`
- `$site->img()` and Markdown images are resized to WebP with a `srcset`
- `components/aside.php` brings its own CSS with `<style hoist>`
- `prefetch` and `viewTransitions` make moving between pages quick and smooth
