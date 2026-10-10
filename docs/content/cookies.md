---
description: Read, set, sign and delete cookies with the Cookie helper.
---

# Cookies

The `Dumbo\Helpers\Cookie` helper reads request cookies and sets response cookies.

```php
use Dumbo\Helpers\Cookie;
```

## Reading cookies

```php
$app->get('/', function ($context) {
    $theme = Cookie::get($context, 'theme');   // A single cookie, or null
    $all = Cookie::get($context);              // Every cookie
    $hasTheme = Cookie::has($context, 'theme');
    $isDark = Cookie::has($context, 'theme', 'dark');

    return $context->json(compact('theme', 'all'));
});
```

## Setting cookies

```php
$app->get('/login', function ($context) {
    Cookie::set($context, 'session', 'abc123', [
        'httpOnly' => true,
        'secure' => true,
        'sameSite' => Cookie::SAME_SITE_LAX,
        'maxAge' => 3600,
        'path' => '/',
    ]);

    return $context->text('Logged in');
});
```

Cookies are added to the context's response, so return a response built from the context, such as `$context->text()` or `$context->json()`.

### Options

| Option | Description |
| --- | --- |
| `domain` | The cookie domain |
| `path` | The cookie path |
| `expires` | A timestamp or `DateTimeInterface` |
| `maxAge` | Lifetime in seconds |
| `secure` | Only send over HTTPS |
| `httpOnly` | Hide from JavaScript |
| `sameSite` | `Cookie::SAME_SITE_STRICT`, `SAME_SITE_LAX` or `SAME_SITE_NONE` |
| `prefix` | `Cookie::PREFIX_SECURE` or `Cookie::PREFIX_HOST` |

Prefixed cookies are named `__Secure-` or `__Host-` and are always `secure`. Host cookies also use path `/` and no domain. Pass the same prefix when reading them:

```php
Cookie::set($context, 'session', $id, ['prefix' => Cookie::PREFIX_HOST]);
Cookie::get($context, 'session', Cookie::PREFIX_HOST);
```

## Signed cookies

Sign a cookie with a secret so you can detect tampering:

```php
Cookie::setSigned($context, 'user_id', '42', 'my-secret', ['httpOnly' => true]);

$userId = Cookie::getSigned($context, 'my-secret', 'user_id');
// "42", false if the signature is invalid, or null if missing
```

## Deleting cookies

```php
Cookie::delete($context, 'session');     // Returns the old value
Cookie::refresh($context, 'session', ['maxAge' => 3600]); // Re-send with new options
Cookie::clearAll($context);              // Expire every cookie
```
