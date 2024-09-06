# Dumbo

A lightweight, friendly PHP framework for HTTP &mdash; inspired by Hono. [Join us on Discord](https://discord.gg/CYkq2A6G)

![Dumbo](/dumbo.jpeg)

## Install

```bash
composer require notrab/dumbo
```

## Quickstart

Here's a basic example of how it works!

```php
<?php

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get("/", function ($context) {
    return $context->json(["message" => "Hello, Dumbo!"]);
});

$app->run();
```

See the [examples](/examples) directory for more quickstarts.

## Routing

```php
<?php

$app->get('/users', function($context) { /* ... */ });
$app->post('/users', function($context) { /* ... */ });
$app->put('/users/:id', function($context) { /* ... */ });
$app->delete('/users/:id', function($context) { /* ... */ });
```

### Params

```php
<?php

$app->get('/users/:id', function($context) {
    $id = $context->req->param('id');

    return $context->json(['id' => $id]);
});
```

### Nested

```php
<?php

$nestedApp = new Dumbo();

$nestedApp->get('/nested', function($context) {
    return $context->text('This is a nested route');
});

$app->route('/prefix', $nestedApp);

```

## Context

```php
<?php

$app->get('/', function($context) {
    $pathname = $context->req->pathname();
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

### Custom context

```php
<?php

$app = new Dumbo();

// Set configuration values
$app->set('DB_URL', 'mysql://user:pass@localhost/mydb');
$app->set('API_KEY', 'your-secret-key');
$app->set('DEBUG', true);

// Get configuration values
$dbUrl = $app->get('DB_URL');
$apiKey = $app->get('API_KEY');
$debug = $app->get('DEBUG');

// Use configuration in your routes
$app->get('/api/data', function(Context $context) {
    $apiKey = $context->get('API_KEY');

    // Use $apiKey in your logic...
    return $context->json(['message' => 'API key is set']);
});

$app->run();
```

### CORS

Usage:

```php
use Dumbo\Helpers\CORS;

$app->use(CORS::cors()); // allow from all origin
// or
$app->use(CORS::cors([
    'origin' => fn($origin, $c) => $origin,
    'allow_headers' => ['X-Custom-Header', 'Upgrade-Insecure-Requests'],
    'allow_methods' => ['POST', 'GET', 'OPTIONS'],
    'expose_headers' => ['Content-Length', 'X-Kuma-Revision'],
    'max_age' => 600,
    'credentials' => true,
]));

```

Options:

- **origin**: `string` | `string[]` | `callable(string, Context): string`

  The value of "_Access-Control-Allow-Origin_" CORS header. You can also pass the callback function like `'origin': fn($origin, $c) => (str_ends_with($origin, '.example.com') ? $origin : 'http://example.com')`. The default is `*`.

- **allow_headers**: `string[]`

  The value of "_Access-Control-Allow-Headers_" CORS header. The default is `[]`.

- **allow_methods**: `string[]`

  The value of "_Access-Control-Allow-Methods_" CORS header. The default is `['GET', 'HEAD', 'PUT', 'POST', 'DELETE', 'PATCH']`.

- **expose_headers**: `string[]`

  The value of "_Access-Control-Expose-Headers_" CORS header.

- **credentials**: `bool`

  The value of "_Access-Control-Allow-Credentials_" CORS header.

- **max_age**: `int`

  The value of "_Access-Control-Max-Age_" CORS header.
