# Dumbo Docs

The Dumbo documentation site, built with Dumbo, Markdown and Tailwind.

## Running locally

```bash
composer install
composer start
```

Then open [http://localhost:8000](http://localhost:8000).

## Writing docs

- Pages are Markdown files in `content/`.
- Add a page to the sidebar in `nav.php`. The order there sets the previous and next links.
- The "On this page" menu is built from each page's `##` and `###` headings.
