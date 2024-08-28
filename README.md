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

```bash
curl -H 'Authorization: Bearer mysupersecret' http://localhost:8000/api
```

```php
<?php

$app = new Dumbo();
$protectedRoutes = new Dumbo();

$token = "mysupersecret";

$protectedRoutes->use(BearerAuth::bearer($token));

// You can add custom failure message as a second argument.😍 It's optional.
$protectedRoutes->use(BearerAuth::bearer($token, 'Unauthorized request.'));

$protectedRoutes->get("/", function ($c) {
    return $c->json(["message" => "Welcome to the protected routes!"]);
});

$app->route("/api", $protectedRoutes);
```

### Basic Auth

Implementing Basic authentication on Cloudflare Workers or other platforms can be complex. This helper middleware offers a straightforward solution for securing specific routes.

##### Usage

```php
<?php

use Dumbo\Dumbo;
use Dumbo\Context;

// Import the BasicAuth Helper
use Dumbo\Helpers\BasicAuth;

$app = new Dumbo();

// Use case 1: Static username and password
$app->use(BasicAuth::basicAuth([
    'username' => 'dumbo',
    'password' => 'youarecool',
    'realm' => 'Secure Area',
]));


// Use case 2: Dynamic verification function
$app->use(BasicAuth::basicAuth([
    'verifyUser' => function ($username, $password, Context $ctx) {
        return $username === 'dynamic-user' && $password === 'dumbo-password';
    },
    'realm' => 'Secure Area',
]));


// Use case 3: Multiple static users
$app->use(BasicAuth::basicAuth([
    'users' => [
        ['username' => 'user1', 'password' => 'pass1'],
        ['username' => 'user2', 'password' => 'pass2'],
    ],
    'realm' => 'Secure Area',
]));


// Define the route
$app->get("/", function (Context $ctx) {
    return $ctx->json([
        "status" => 'success',
        "message" => "Your authentication is successful! 😎"
    ], 200);
});

$app->run();
```
