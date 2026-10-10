# Dumbo Docs

The Dumbo documentation site, built with Dumbo, Markdown and Tailwind.

## Running locally

```bash
composer install
npm install
composer start
```

`composer start` builds the CSS and serves the site at [http://localhost:8000](http://localhost:8000). While editing templates, run `npm run dev` in another terminal to rebuild the CSS on every change.

## Writing docs

- Pages are Markdown files in `content/`.
- Add a page to the sidebar in `nav.php`. The order there sets the previous and next links.
- The "On this page" menu is built from each page's `##` and `###` headings.

## Examples

The examples pages are generated from the repo's `examples/` directory. Each folder becomes a page showing its README and source, with the description on the listing taken from the first paragraph of the README. Add a folder with a `README.md` and it appears automatically.
