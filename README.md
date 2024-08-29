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


$protectedRoutes->use(BearerAuth::bearerAuth([
    'tokens' => ['token1', 'token2'],
    'realm' => 'API Access'
]));

$token = "mysupersecret";

// You can add custom failure message as a second argument.😍 It's optional.
$protectedRoutes->use(BearerAuth::bearerAuth($token, 'Unauthorized request.'));

// Custom token verification function
$app->use(BearerAuth::bearerAuth([
    'verifyToken' => function($token, $ctx) {
        // Perform custom token verification logic
        return verifyJWT($token);
    },
    'realm' => 'JWT API'
]));

$protectedRoutes->get("/", function ($c) {
    return $c->json(["message" => "Welcome to the protected routes!"]);
});

$app->route("/api", $protectedRoutes);
```

### Exception handlers

```php
<?php

$app = new Dumbo();

$app->post('/', function(Context $c) {
    if (!checkAuthStatus()) {
        throw new HTTPException(401, 'Unauthorized');
    }
});
```

Or with a custom response:

```php
<?php

$app = new Dumbo();

$app->onError(function (\Exception $error, Context $c) {
    // Custom error response
    if ($error instanceof HTTPException) {
        // We can now use the toArray() method to get a structured error response
        return $c->json($error->toArray(), $error->getStatusCode());
    }

    // Gotta catch 'em all
    return $c->json(['error' => 'Internal Server Error'], 500);
});

$app->post('/', function(Context $c) {
    if (!doSomething()) {
        $customResponse = $c->html('<h1>Something went wrong</h1>', 404);
        throw new HTTPException(
            404,
            'Something went wrong',
            'OPERATION_FAILED',
            ['operation' => 'doSomething'],
            $customResponse
        );
    }
});
```

### Basic Auth

Implementing Basic authentication on Cloudflare Workers or other platforms can be complex. This helper middleware offers a straightforward solution for securing specific routes.

```php
<?php

use Dumbo\Dumbo;
use Dumbo\Context;

use Dumbo\Helpers\BasicAuth;

$app = new Dumbo();

// Use case 1: Static username and password
$app->use(BasicAuth::basicAuth("user:password"));

// Use case 2: Dynamic verification
$app->use(BasicAuth::basicAuth([
        "verifyUser" => function ($username, $password, $ctx) {
            // You could call a database here...
            $validUsers = [
                "admin" => "strongpassword",
                "user" => "password",
            ];
            return isset($validUsers[$username]) &&
                $validUsers[$username] === $password;
        },
        "realm" => "Admin Area",
        ])
);

// Use case 3: For multiple users
$app->use(BasicAuth::basicAuth([
    // You could call a database here...
    "users" => [
        ["username" => "user1", "password" => "pass1"],
        ["username" => "user2", "password" => "pass2"],
    ],
    "realm" => "Admin Area"
    ])
);

// Define routes for the above three use cases
$app->get("/", function (Context $ctx) {
    return $ctx->json([
        "status" => 'success',
        "message" => "Your authentication is successful!!!"
    ], 200);
});

// Use case 4: For nested routes
$api = new Dumbo();

$api->use(BasicAuth::basicAuth("user:password"));

$api->get("/", function (Context $c) {
    return $c->html("<h1>Your authentication is successful! 😎</h1>");
});

$app->route("/api", $api);


$app->run();
```
