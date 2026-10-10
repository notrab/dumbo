# Contact Form Example

This example demonstrates how to handle forms with Jumbo's page actions: validation errors shown next to each field, a redirect after a successful post, two forms on one page, and a form that also works without reloading.

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

Try sending the contact form empty, then fill it in. Messages are saved to `storage/messages.jsonl`.

## What it shows

- `pages/index.php` defines a `default` action for the contact form and a `subscribe` action for the newsletter
- `Action::fail()` shows the page again with a 422, the errors and what was typed
- The contact action redirects to `/thanks` with a 303, so refreshing doesn't send it twice
- `$site->action($context, "subscribe")` reads one form's result on a page with two
- The newsletter form works without JavaScript, and a few lines of `fetch()` with `Accept: application/json` send it without a reload
- Forms posted from other sites are refused

Actions need the PHP server, as static hosts can't take a POST.
