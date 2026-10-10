---
description: Register routes for every HTTP method, capture params and mount nested apps.
---

# Routing

## Basic routes

Register a handler for each HTTP method:

```php
$app->get('/users', function ($context) { /* ... */ });
$app->post('/users', function ($context) { /* ... */ });
$app->put('/users/:id', function ($context) { /* ... */ });
$app->patch('/users/:id', function ($context) { /* ... */ });
$app->delete('/users/:id', function ($context) { /* ... */ });
$app->head('/users', function ($context) { /* ... */ });
$app->options('/users', function ($context) { /* ... */ });
```

Use `all()` to match every method:

```php
$app->all('/ping', fn($context) => $context->text('pong'));
```

## Route params

Prefix a path segment with `:` to capture it:

```php
$app->get('/posts/:postId/comments/:commentId', function ($context) {
    $postId = $context->req->param('postId');
    $commentId = $context->req->param('commentId');

    return $context->json(compact('postId', 'commentId'));
});
```

## Nested apps

Group related routes in their own app, then mount it under a prefix with `route()`:

```php
$api = new Dumbo();

$api->get('/users', fn($context) => $context->json([]));
$api->get('/users/:id', fn($context) => $context->json([
    'id' => $context->req->param('id'),
]));

$app->route('/api', $api);
// GET /api/users
// GET /api/users/:id
```

::: callout type="warning" title="Mount after registering"
Routes added to the nested app after it's mounted are not included, so register them first.
:::

## Route middleware

Pass an array of middleware as the third argument to run it for that route only:

```php
$auth = function ($context, $next) {
    if (!$context->req->header('Authorization')) {
        return $context->json(['error' => 'Unauthorized'], 401);
    }

    return $next($context);
};

$app->get('/dashboard', fn($context) => $context->text('Welcome'), [$auth]);
```

## Trailing slashes

Requests with a trailing slash are redirected with a `301` to the same path without it, so `/users/` becomes `/users`.

## 404 and 405

Unknown paths respond with `404 Not Found`. When a path exists but not for the request method, Dumbo responds with `405 Method Not Allowed` and an `Allow` header listing the methods it does accept.
