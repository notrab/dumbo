# Sessions Example

This example demonstrates how to keep data between requests with sessions: a visit counter, signing in and out, flash messages after a redirect, and CSRF tokens stored in the session.

## Running the Example

1. Install dependencies:

   ```bash
   composer install
   ```

2. Start the server:

   ```bash
   composer start
   ```

3. Open [http://localhost:8000](http://localhost:8000), reload a few times, then sign in as `dumbo` with the password `peanuts`.

Sessions are kept as files in `storage/`. To keep them in an encrypted cookie instead, so the server stores nothing, start the server with a secret:

```bash
SESSION_SECRET="$(php -r 'echo bin2hex(random_bytes(32));')" composer start
```

## What it shows

- `Session::session()` loads the session and saves it after each request
- `$context->session()->set()` and `get()` keep values between requests
- `flash()` shows a message once, after a redirect
- `regenerate()` on sign in, and `destroy()` on sign out
- The CSRF middleware storing its token in the session
