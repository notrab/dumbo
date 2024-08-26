# Dumbo

A lightweight, friendly PHP framework for HTTP &mdash; inspired by Hono.

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

$app->get("/", function ($c) {
    return $c->json('Hello Dumbo!');
});

$app->run();
```

## Routing

```php
<?php

$app->get('/users', function($c) { /* ... */ });
$app->post('/users', function($c) { /* ... */ });
$app->put('/users/:id', function($c) { /* ... */ });
$app->delete('/users/:id', function($c) { /* ... */ });
```

### Params

```php
<?php

$app->get('/users/:id', function($c) {
    $id = $c->req->param('id');

    return $c->json(['id' => $id]);
});
```

### Nested

```php
<?php

$nestedApp = new Dumbo();

$nestedApp->get('/nested', function($c) {
    return $c->text('This is a nested route');
});

$app->route('/prefix', $nestedApp);

```

### Context

```php
<?php

$app->get('/', function($c) {
    $pathname = $c->req->pathname();
    $routePath = $c->req->routePath();
    $queryParam = $c->req->query('param');
    $tags = $c->req->queries('tags');
    $body = $c->req->body();
    $userAgent = $c->req->header('User-Agent');
});
```

## Response

```php
<?php

return $c->json(['key' => 'value']);
return $c->text('Hello, World!');
return $c->html('<h1>Hello, World!</h1>');
return $c->redirect('/new-url');
```

## Middleware

```php
<?php

$app->use(function($c, $next) {
    $response = $next($c);

    return $response;
});
```

## Helpers

### Bearer Auth

```php
<?php

$app = new Dumbo();
$protectedRoutes = new Dumbo();

$token = "mysupersecret";

$protectedRoutes->use(BearerAuth::bearer($token));

$protectedRoutes->get("/", function ($c) {
    return $c->json(["message" => "Welcome to the protected routes!"]);
});

$app->route("/api", $protectedRoutes);
```
