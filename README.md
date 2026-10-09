<p align="center">
  <img src="dumbo.jpeg" alt="Dumbo" width="600"/>
  <h1 align="center">Dumbo</h1>
</p>

<p align="center">
  A lightweight, friendly PHP framework for HTTP &mdash; Inspired by Hono.
</p>

<p align="center">
  <a href="https://discord.gg/nAq2h9BfsU">
    <picture>
      <img src="https://img.shields.io/discord/1278637768918171709?color=8A2BE2" alt="Discord" />
    </picture>
  </a>
  <picture>
    <img src="https://img.shields.io/github/contributors/notrab/dumbo?color=8A2BE2" alt="Contributors" />
  </picture>
  <a href="https://packagist.org/packages/notrab/dumbo">
    <picture>
      <img src="https://img.shields.io/packagist/dt/notrab/dumbo?color=8A2BE2" alt="Total downloads" />
    </picture>
  </a>
  <a href="/examples">
    <img src="https://img.shields.io/badge/browse-examples-8A2BE2" alt="Examples" />
  </a>
</p>

## Features

- 🚀 Lightweight and fast
- 🧩 Middleware support
- 🛣️ Flexible routing with parameters
- 🔒 Built-in security features (CSRF, JWT)
- 🍪 Cookie management
- 🔍 Request ID for tracing
- 📁 Static file serving
- 🔐 Basic and Bearer authentication
- 📝 Logging support
- 🗃️ HTTP caching
- 🔄 CORS support
- 🗜️ Response compression
- 📦 Request body size limits
- 🧬 Environment-based configuration

## Install

```bash
composer require notrab/dumbo
```

## Quickstart

Here's a basic example of how it works!

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Dumbo\Dumbo;

$app = new Dumbo();

$app->use(function ($context, $next) {
    $context->set('message', 'Hello from middleware!');
    return $next($context);
});

$app->get('/', function ($context) {
    return $context->json([
        'message' => $context->get('message'),
        'timestamp' => time()
    ]);
});

$app->get('/users/:id', function ($context) {
    $id = $context->req->param('id');
    return $context->json(['userId' => $id]);
});

$app->post('/users', function ($context) {
    $body = $context->req->body();
    return $context->json($body, 201);
});

$app->run();
```

See the [examples](/examples) directory for more quickstarts.

## Upgrading from 1.x

- Middleware moved from `Dumbo\Helpers` to `Dumbo\Middleware`, and
  `CacheMiddleware` and `CsrfMiddleware` are now `Cache` and `Csrf`.
- `BodyLimit::limit()` is now `BodyLimit::bodyLimit()`, and
  `CacheMiddleware::withHeaders(...)` is now `Cache::cache([...])` with
  `type`, `maxAge` and `mustRevalidate` options.
- CORS options are camelCase: `allowMethods`, `allowHeaders`, `exposeHeaders`
  and `maxAge`.
- `DateHelper` was removed; use PHP's date functions.
- `firebase/php-jwt` is no longer installed automatically. Require it if you
  use `Dumbo\Helpers\JWT`.
- Pass the environment to `new Dumbo('production')` or set `DUMBO_ENV`.
  `setEnvironment()`, `detectEnvironment()`, `isProduction()` and `isTesting()`
  were removed, and Dumbo no longer changes `error_reporting`. The
  `environment` context variable is now the environment name.

## License

Dumbo is open-sourced software licensed under the [MIT license](LICENSE).

## Contributors

![Contributors](https://contrib.nn.ci/api?repo=notrab/dumbo)

## Documentation

### Routing

```php
<?php

$app->get('/users', function($context) { /* ... */ });
$app->post('/users', function($context) { /* ... */ });
$app->put('/users/:id', function($context) { /* ... */ });
$app->delete('/users/:id', function($context) { /* ... */ });
```

#### Params

```php
<?php

$app->get('/users/:id', function($context) {
    $id = $context->req->param('id');

    return $context->json(['id' => $id]);
});
```

#### Nested

```php
<?php

$nestedApp = new Dumbo();

$nestedApp->get('/nested', function($context) {
    return $context->text('This is a nested route');
});

$app->route('/prefix', $nestedApp);

```

### Context

```php
<?php

$app->get('/', function($context) {
    $path = $context->req->path();
    $routePath = $context->req->routePath();
    $queryParam = $context->req->query('param');
    $tags = $context->req->queries('tags');
    $body = $context->req->body();
    $userAgent = $context->req->header('User-Agent');
});
```

### Response

```php
<?php

return $context->json(['key' => 'value']);
return $context->text('Hello, World!');
return $context->html('<h1>Hello, World!</h1>');
return $context->redirect('/new-url');
```

### Middleware

```php
<?php

$app->use(function($context, $next) {
    $response = $next($context);

    return $response;
});
```

Built-in middleware lives in `Dumbo\Middleware`:

```php
<?php

use Dumbo\Middleware\BasicAuth;
use Dumbo\Middleware\BearerAuth;
use Dumbo\Middleware\BodyLimit;
use Dumbo\Middleware\Cache;
use Dumbo\Middleware\Compress;
use Dumbo\Middleware\CORS;
use Dumbo\Middleware\Csrf;
use Dumbo\Middleware\Logger;
use Dumbo\Middleware\RequestId;

$app->use(Logger::logger($psrLogger));
$app->use(RequestId::requestId());
$app->use(CORS::cors(['origin' => ['https://example.com']]));
$app->use(Compress::compress());
$app->use(BodyLimit::bodyLimit(1024 * 1024));
$app->use('/admin', BasicAuth::basicAuth('user:password'));
$app->use('/api', BearerAuth::bearerAuth('secret-token'));
$app->use('/blog', Cache::cache(['type' => 'public', 'maxAge' => 3600]));
```

Helpers for cookies, JWTs and static files live in `Dumbo\Helpers`. The JWT
helper needs `firebase/php-jwt`:

```bash
composer require firebase/php-jwt
```

### Custom context

Share values across middleware and handlers with `$context->set()` and
`$context->get()`.

```php
<?php

$app = new Dumbo();

// Available to every route
$app->use(function ($context, $next) {
    $context->set('DB_URL', 'mysql://user:pass@localhost/mydb');
    $context->set('API_KEY', 'your-secret-key');

    return $next($context);
});

// Or only to routes under a given prefix
$app->use('/api', function ($context, $next) {
    $context->set('DEBUG', true);

    return $next($context);
});

$app->get('/api/data', function ($context) {
    $apiKey = $context->get('API_KEY');

    // Use $apiKey in your logic...
    return $context->json(['message' => 'API key is set']);
});

$app->run();
```
