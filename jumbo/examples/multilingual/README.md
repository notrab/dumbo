# Multilingual Example

This example demonstrates how to serve a site in several languages with Jumbo: English at `/`, French at `/fr` and Spanish at `/es`, with a language switcher and hreflang links.

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

Then try [/fr](http://localhost:8000/fr) and [/es/visit](http://localhost:8000/es/visit), which has no Spanish copy yet.

## What it shows

- The `i18n` config in `app.php` serves every page under each locale
- `$site->locale($context)` picks the strings and the `<html lang>`
- `$site->find("visit", $locale)` loads `content/fr/visit.md`, falling back to `content/visit.md`
- `$site->alternates($context)` builds the language switcher and hreflang links
- `$site->localize("/visit", $locale)` links within a language
- Builds write every page in every locale, and list them all in `sitemap.xml`
