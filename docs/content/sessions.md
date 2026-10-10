---
description: Keep data for each visitor between requests, with flash messages and pluggable stores.
---

# Sessions

The session middleware gives each visitor a session that lasts between requests. Add it once, then use `$context->session()` in any handler:

```php
use Dumbo\Middleware\Session;

$app->use(Session::session());

$app->get('/', function ($context) {
    $session = $context->session();
    $session->set('visits', $session->get('visits', 0) + 1);

    return $context->text("Visit number {$session->get('visits')}");
});
```

A session starts the first time something is set, so visitors who never need one aren't sent a cookie.

## Values

```php
$session = $context->session();

$session->set('user_id', 42);
$session->get('user_id');          // 42
$session->get('theme', 'light');   // A default for missing values
$session->has('user_id');          // true
$session->pull('user_id');         // Get it and remove it
$session->forget('theme');
$session->all();
$session->clear();
```

Values can be anything that encodes as JSON.

## Flash messages

Flashed values last for the next request only, which suits a message to show after a redirect:

```php
$app->post('/settings', function ($context) {
    // ...save the settings
    $context->session()->flash('notice', 'Settings saved');

    return $context->redirect('/settings');
});

$app->get('/settings', function ($context) {
    $notice = $context->session()->get('notice');   // Gone on the request after this

    // ...render the page with the notice
});
```

## Signing in and out

Move the session to a new ID when someone signs in, so an ID planted before then is useless. Destroy it when they sign out:

```php
$app->post('/login', function ($context) {
    // ...check the credentials
    $context->session()->regenerate();
    $context->session()->set('user_id', $user['id']);

    return $context->redirect('/dashboard');
});

$app->post('/logout', function ($context) {
    $context->session()->destroy();

    return $context->redirect('/');
});
```

## Stores

Sessions are kept in files in the temp directory by default, and the cookie holds a random ID. Pick a store for anything else:

```php
use Dumbo\Session\CookieStore;
use Dumbo\Session\FileStore;

// Files somewhere of your choosing
$app->use(Session::session(['store' => new FileStore(__DIR__ . '/storage/sessions')]));

// The whole session in an encrypted cookie, so the server stores nothing
$app->use(Session::session(['store' => new CookieStore(getenv('SESSION_SECRET'))]));
```

| Store | Keeps sessions in | Good for |
| --- | --- | --- |
| `FileStore` | JSON files, readable only by the server | One server |
| `CookieStore` | The cookie, encrypted with libsodium | Serverless and many servers, with small sessions |
| `MemoryStore` | Memory, until the process ends | Tests |

::: callout type="warning" title="Cookie sessions can't be revoked"
A cookie session lives in the browser, so until it expires a copy of an old cookie still works, and browsers cap cookies at about 4KB. Use a server-side store if you need to sign people out everywhere.
:::

For Redis, a database or anything else, implement `Dumbo\Session\Store`. It has three methods: `read()` returns a session's data from its cookie value, `write()` saves the data and returns the cookie value, and `destroy()` removes a session.

## Options

```php
$app->use(Session::session([
    'name' => 'sid',
    'lifetime' => 60 * 60 * 24,
    'cookie' => ['sameSite' => 'Strict', 'domain' => 'example.com'],
]));
```

| Option | Default | Description |
| --- | --- | --- |
| `store` | `FileStore` | Where sessions are kept |
| `name` | `dumbo_session` | The cookie name |
| `lifetime` | `7200` | Seconds a session lasts without a request |
| `cookie` | | Cookie options, as for the [Cookie helper](/docs/cookies). Sessions are `HttpOnly`, `SameSite=Lax` and, on HTTPS, `Secure` |

## CSRF tokens

The [CSRF middleware](/docs/built-in-middleware#csrf) can keep its token in the session:

```php
use Dumbo\Middleware\Csrf;

$app->use(Session::session());
$app->use(Csrf::csrf([
    'getToken' => fn($context) => $context->session()->get('csrf_token'),
    'setToken' => fn($context, $token) => $context->session()->set('csrf_token', $token),
]));
```
