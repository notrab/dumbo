---
description: Auth, CORS, CSRF, body limits, caching, compression, logging and request IDs.
---

# Built-in middleware

Built-in middleware lives in the `Dumbo\Middleware` namespace. Each one is a static factory that returns a middleware callable.

```php
use Dumbo\Middleware\BasicAuth;
use Dumbo\Middleware\BearerAuth;
use Dumbo\Middleware\BodyLimit;
use Dumbo\Middleware\Cache;
use Dumbo\Middleware\Compress;
use Dumbo\Middleware\CORS;
use Dumbo\Middleware\Csrf;
use Dumbo\Middleware\Logger;
use Dumbo\Middleware\RequestId;
```

## Basic auth

Protect routes with HTTP Basic authentication:

```php
$app->use('/admin', BasicAuth::basicAuth('user:password'));
```

Allow multiple users, or verify credentials yourself:

```php
$app->use('/admin', BasicAuth::basicAuth([
    'users' => [
        ['username' => 'jamie', 'password' => 'secret'],
        ['username' => 'sam', 'password' => 'hunter2'],
    ],
    'realm' => 'Admin',
]));

$app->use('/admin', BasicAuth::basicAuth([
    'verifyUser' => fn($username, $password, $context) => checkLogin($username, $password),
]));
```

## Bearer auth

Require a bearer token in the `Authorization` header:

```php
$app->use('/api', BearerAuth::bearerAuth('secret-token'));

$app->use('/api', BearerAuth::bearerAuth([
    'tokens' => ['token-one', 'token-two'],
]));

$app->use('/api', BearerAuth::bearerAuth([
    'verifyToken' => fn($token, $context) => isValidToken($token),
]));
```

## CORS

Add CORS headers and answer preflight requests:

```php
$app->use(CORS::cors());

$app->use('/api', CORS::cors([
    'origin' => ['https://example.com', 'https://app.example.com'],
    'allowMethods' => ['GET', 'POST'],
    'allowHeaders' => ['Content-Type', 'Authorization'],
    'exposeHeaders' => ['X-Request-Id'],
    'credentials' => true,
    'maxAge' => 600,
]));
```

`origin` may be `"*"`, a single origin, an array of origins, or a callable receiving `($origin, $context)` that returns the origin to allow, or `null` to deny.

## CSRF

Issue a token on safe requests and check it on every other request. You decide where the token is stored:

```php
use Dumbo\Helpers\Cookie;

$app->use(Csrf::csrf([
    'getToken' => fn($context) => Cookie::get($context, 'csrf_token'),
    'setToken' => fn($context, $token) => Cookie::set($context, 'csrf_token', $token, [
        'httpOnly' => true,
        'sameSite' => Cookie::SAME_SITE_STRICT,
    ]),
]));

$app->get('/form', function ($context) {
    $token = $context->get('csrf_token');

    return $context->html("<form method='post'><input type='hidden' name='csrf_token' value='$token'>...</form>");
});
```

| Option | Default | Description |
| --- | --- | --- |
| `getToken` | required | Reads the stored token |
| `setToken` | required | Stores a new token |
| `tokenName` | `csrf_token` | Body field and context variable name |
| `headerName` | `X-CSRF-TOKEN` | Header to send and read the token in |
| `useHeader` | `false` | Read the token from the header instead of the body |
| `tokenLength` | `32` | Length of generated tokens |
| `errorHandler` | `null` | Returns a response for invalid tokens |

With [sessions](/docs/sessions), keep the token in the session instead of its own cookie.

## Body limit

Reject requests whose `Content-Length` is larger than a number of bytes with a `413`:

```php
$app->use(BodyLimit::bodyLimit(1024 * 1024));

$app->use('/upload', BodyLimit::bodyLimit(10 * 1024 * 1024, function ($context, $maxSize) {
    return $context->json(['error' => "Max upload size is $maxSize bytes"], 413);
}));
```

## Cache

Add `Cache-Control` and `ETag` headers to successful `GET` and `HEAD` responses, and respond `304 Not Modified` when the client already has them:

```php
$app->use('/blog', Cache::cache([
    'type' => 'public',     // default "private"
    'maxAge' => 3600,       // default 86400
    'mustRevalidate' => true,
]));
```

## Compress

Compress responses with gzip or deflate when the client accepts it:

```php
$app->use(Compress::compress());

$app->use(Compress::compress([
    'threshold' => 2048,   // Minimum body size in bytes, default 1024
    'encoding' => 'gzip',  // Force an encoding
]));
```

## Logger

Log each request, its status and how long it took to any PSR-3 logger:

```php
$app->use(Logger::logger($psrLogger));
// --> GET /users
// <-- GET /users 200 3.42ms
```

## Request ID

Reuse the incoming `X-Request-Id` header or generate one, store it as the `requestId` context variable and echo it on the response:

```php
$app->use(RequestId::requestId());

$app->get('/', fn($context) => $context->text($context->get('requestId')));

$app->use(RequestId::requestId([
    'headerName' => 'X-Trace-Id',
    'limitLength' => 64,
    'generator' => fn($context) => uniqid('req_'),
]));
```
