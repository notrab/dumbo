<p align="center">
  <img src="dumbo.jpeg" alt="Dumbo" width="600"/>
  <h1 align="center">Dumbo</h1>
</p>

<p align="center">
  A lightweight, friendly PHP framework for HTTP &mdash; Inspired by Hono.
</p>

<p align="center">
  <a href="https://discord.gg/nAq2h9BfsU">
    <img src="https://img.shields.io/discord/1278637768918171709?color=8A2BE2" alt="Discord" />
  </a>
  <img src="https://img.shields.io/github/contributors/notrab/dumbo?color=8A2BE2" alt="Contributors" />
  <a href="">
    <img src="https://img.shields.io/packagist/dt/notrab/dumbo?color=8A2BE2" alt="Total downloads" />
  </a>
  <a href="/examples">
    <img src="https://img.shields.io/badge/browse-examples-8A2BE2" alt="Examples" />
  </a>
</p>

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
