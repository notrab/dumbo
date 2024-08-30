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
    return $context->json('Hello Dumbo!');
});

$app->run();
```

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
$app->get('/api/data', function(Context $ctx) {
    $apiKey = $ctx->get('API_KEY');

    // Use $apiKey in your logic...
    return $ctx->json(['message' => 'API key is set']);
});

$app->run();
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

$protectedRoutes->get("/", function ($context) {
    return $context->json(["message" => "Welcome to the protected routes!"]);
});

$app->route("/api", $protectedRoutes);
```

### Exception handlers

```php
<?php

$app = new Dumbo();

$app->post('/', function(Context $context) {
    if (!checkAuthStatus()) {
        throw new HTTPException(401, 'Unauthorized');
    }
});
```

Or with a custom response:

```php
<?php

$app = new Dumbo();

$app->onError(function (\Exception $error, Context $context) {
    // Custom error response
    if ($error instanceof HTTPException) {
        // We can now use the toArray() method to get a structured error response
        return $context->json($error->toArray(), $error->getStatusCode());
    }

    // Gotta catch 'em all
    return $context->json(['error' => 'Internal Server Error'], 500);
});

$app->post('/', function(Context $context) {
    if (!doSomething()) {
        $customResponse = $context->html('<h1>Something went wrong</h1>', 404);
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
    return $context->json([
        "status" => 'success',
        "message" => "Your authentication is successful!!!"
    ], 200);
});

// Use case 4: For nested routes
$api = new Dumbo();

$api->use(BasicAuth::basicAuth("user:password"));

$api->get("/", function (Context $context) {
    return $context->html("<h1>Your authentication is successful! 😎</h1>");
});

$app->route("/api", $api);


$app->run();
```

### Request-Id

```php
<?php

require "vendor/autoload.php";

use Dumbo\Dumbo;
use Dumbo\Helpers\RequestId;

$app = new Dumbo();

$app->use(RequestId::requestId());

// Or apply it with custom options
// $app->use(
//     RequestId::requestId([
//         "headerName" => "X-Custom-Request-Id",
//         "limitLength" => 128,
//         "generator" => function ($ctx) {
//             return uniqid("custom-", true);
//         },
//     ])
// );

$app->get("/", function ($context) {
    $requestId = $context->get("requestId");

    return $context->text("Your request ID is: " . $requestId);
});

$app->run();
```
