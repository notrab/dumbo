# Middleware

Middleware runs before and after your handlers. It receives the `$context` and a `$next` callable that runs the rest of the chain.

## Writing middleware

```php
$app->use(function ($context, $next) {
    // Before the handler
    $start = microtime(true);

    $response = $next($context);

    // After the handler
    return $response->withHeader('X-Response-Time', round((microtime(true) - $start) * 1000) . 'ms');
});
```

Return a response early to stop the chain:

```php
$app->use(function ($context, $next) {
    if ($context->req->header('X-Api-Key') !== 'secret') {
        return $context->json(['error' => 'Forbidden'], 403);
    }

    return $next($context);
});
```

## Global middleware

Middleware added with `use()` runs for every request, in the order it was added.

```php
$app->use($logger);
$app->use($cors);
```

## Path middleware

Pass a path prefix as the first argument to run middleware only for routes under it:

```php
$app->use('/admin', function ($context, $next) {
    // Runs for /admin and /admin/*
    return $next($context);
});
```

## Route middleware

Middleware can also be attached to a single route:

```php
$app->get('/reports', $handler, [$auth, $audit]);
```

## Sharing values

Middleware can pass values to handlers through the [context](/docs/context#variables):

```php
$app->use('/api', function ($context, $next) {
    $context->set('DEBUG', true);

    return $next($context);
});

$app->get('/api/data', fn($context) => $context->json([
    'debug' => $context->get('DEBUG'),
]));
```

## Built-in middleware

Dumbo ships with middleware for auth, CORS, CSRF, caching, compression and more. See [built-in middleware](/docs/built-in-middleware).
