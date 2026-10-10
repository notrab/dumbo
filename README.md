<p align="center">
  <img src=".github/dumbo.jpeg" alt="Dumbo" width="600"/>
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
- 🧩 Middleware support, including PSR-15
- 🛣️ Flexible routing with parameters
- 🔒 Built-in security features (CSRF, JWT)
- 🍪 Cookie management
- 🔍 Request ID for tracing
- 📁 Static file serving
- 🔐 Basic and Bearer authentication
- 📝 Logging support
- 🗃️ HTTP caching
- 🔄 CORS support
- 📡 Streaming and server-sent events
- 🗜️ Response compression
- 📦 Request body size limits
- 🧬 Environment-based configuration
- ✅ Request validation

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

## FrankenPHP worker mode

Keep your app in memory between requests by running it in a
[FrankenPHP worker](https://frankenphp.dev/docs/worker/):

```php
<?php

$app = new Dumbo();
// ...routes

if (function_exists('frankenphp_handle_request')) {
    while (frankenphp_handle_request(fn() => $app->run())) {
        gc_collect_cycles();
    }
} else {
    $app->run();
}
```

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

// Several methods, or every method
$app->on(['GET', 'POST'], '/form', function($context) { /* ... */ });
$app->all('/anything', function($context) { /* ... */ });
```

#### Params

```php
<?php

$app->get('/users/:id', function($context) {
    $id = $context->req->param('id');

    return $context->json(['id' => $id]);
});
```

Constrain a param with a regex, make the last ones optional, or match
anything with `*`. A trailing `/*` also matches the path without it.

```php
<?php

$app->get('/posts/:id{[0-9]+}', fn($c) => $c->json(['id' => $c->req->param('id')]));
$app->get('/animals/:type?', fn($c) => $c->text($c->req->param('type') ?? 'all'));
$app->get('/files/*', fn($c) => $c->text('any file'));
$app->get('/wild/*/card', fn($c) => $c->text('any path in between'));
```

FastRoute's `{id:\d+}` syntax works too.

#### Chaining

Route and middleware methods return the app, so you can chain them:

```php
<?php

$app
    ->use(Logger::logger($psrLogger))
    ->get('/', fn($c) => $c->text('Home'))
    ->post('/users', fn($c) => $c->json($c->req->body(), 201));
```

#### Trailing slashes

Requests with a trailing slash are redirected to the path without it. `GET`
and `HEAD` get a `301`; other methods get a `308` so the method and body are
kept.

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
    $rawBody = $context->req->text();
    $url = $context->req->url();
    $theme = $context->req->cookie('theme');
    $userAgent = $context->req->header('User-Agent');
    $psrRequest = $context->req->raw();
});
```

### Response

```php
<?php

return $context->json(['key' => 'value']);
return $context->text('Hello, World!');
return $context->html('<h1>Hello, World!</h1>');
return $context->redirect('/new-url');
return $context->send($csv, 'text/csv');

// Set the status for responses that don't pass one
return $context->status(201)->json($user);
```

#### Streaming

`stream()` sends the body as you write it, and `streamSSE()` sends
[server-sent events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events).
The callback runs when the response is sent, so middleware that reads the
whole body, like `Compress` and `Cache`, leaves streams alone.

```php
<?php

use Dumbo\Stream\StreamWriter;

$app->get('/events', function ($context) {
    return $context->streamSSE(function (StreamWriter $stream) {
        for ($i = 1; !$stream->aborted() && $i <= 10; $i++) {
            $stream->writeSSE(['tick' => $i], event: 'tick', id: (string) $i);
            $stream->sleep(1);
        }
    });
});

$app->get('/export', function ($context) {
    return $context->stream(function (StreamWriter $stream) {
        foreach (fetchRows() as $row) {
            $stream->writeln(implode(',', $row));
        }
    }, 'text/csv');
});
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
use Dumbo\Middleware\Validator;

$app->use(Logger::logger($psrLogger));
$app->use(RequestId::requestId());
$app->use(CORS::cors(['origin' => ['https://example.com']]));
$app->use(Compress::compress());
$app->use(BodyLimit::bodyLimit(1024 * 1024));
$app->use('/admin/*', BasicAuth::basicAuth('user:password'));
$app->use('/api', BearerAuth::bearerAuth('secret-token'));
$app->use('/blog', Cache::cache(['type' => 'public', 'maxAge' => 3600]));
```

#### Validation

`Validator::validator()` checks the `json`, `form`, `query`, `param`,
`header` or `cookie` part of a request before your handler runs. Return a
response to reject the request, or the validated data, which the handler
reads with `$context->req->valid()`. Bring whichever validation library you
like.

```php
<?php

use Dumbo\Middleware\Validator;

$app->post('/users', function ($context) {
    $user = $context->req->valid('json');

    return $context->json($user, 201);
}, [
    Validator::validator('json', function (array $value, $context) {
        if (!filter_var($value['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            return $context->json(['error' => 'A valid email is required'], 400);
        }

        return ['email' => strtolower($value['email'])];
    }),
]);
```

#### PSR-15 middleware

Any [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware works anywhere
Dumbo middleware does. Request attributes it adds are available from
`$context->req->raw()`.

```php
<?php

$app->use(new Middlewares\ClientIp());

$app->get('/', function ($context) {
    return $context->text($context->req->raw()->getAttribute('client-ip'));
});
```

Dumbo is also a PSR-15 request handler, so `$app->handle($request)` works
with any PSR-7 server request.

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

### Testing

`$app->request()` sends a request straight to your app, without a server.
Arrays are sent as JSON.

```php
<?php

$response = $app->request('/users?page=2');
$response = $app->request('/users', 'POST', ['Authorization' => 'Bearer token'], ['name' => 'Jamie']);

$this->assertSame(201, $response->getStatusCode());
```

### Errors and 404s

Throw an `HTTPException` to respond with a JSON error, or handle every
uncaught exception yourself with `onError()`. Requests that match no route go
to `notFound()`, which runs after middleware like any route handler. Call
`$context->notFound()` from a handler to send the same response.

```php
<?php

use Dumbo\HTTPException;

$app->onError(function ($error, $context) {
    return $context->json(['error' => $error->getMessage()], 500);
});

$app->notFound(function ($context) {
    return $context->json(['error' => 'Not found'], 404);
});

$app->get('/users/:id', function ($context) {
    $user = findUser($context->req->param('id'));

    if (!$user) {
        return $context->notFound();
    }

    if (!$user->active) {
        throw new HTTPException(403, 'User is disabled', 'USER_DISABLED');
    }

    return $context->json($user);
});
```
