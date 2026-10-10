---
description: Read the request, build the response and share values between middleware.
---

# Context

Every handler and middleware receives a `$context`. It holds the request, the response being built, and any variables shared between middleware.

## Request

The incoming request is available as `$context->req`:

```php
$app->get('/search', function ($context) {
    $context->req->method();            // "GET"
    $context->req->path();              // "/search"
    $context->req->routePath();         // The registered path, e.g. "/posts/:id"
    $context->req->param('id');         // A route param
    $context->req->query();             // Every query param
    $context->req->query('q');          // A single query param
    $context->req->queries('tags');     // Every value of ?tags[]=a&tags[]=b
    $context->req->header('User-Agent'); // The first value of a header
    $context->req->headers();           // Every header
    $context->req->body();              // Parsed JSON or form body
    $context->req->getUploadedFiles();  // Uploaded files
});
```

`body()` parses `application/json` and `application/x-www-form-urlencoded` requests, and falls back to PHP's parsed body for multipart forms.

## Response

Return a response from your handler using one of the helpers:

```php
return $context->text('Hello, World!');
return $context->json(['key' => 'value']);
return $context->html('<h1>Hello, World!</h1>');
return $context->redirect('/new-url');
```

Each one accepts a status code and extra headers:

```php
return $context->json(['error' => 'Not found'], 404);
return $context->text('Created', 201, ['X-Custom' => 'value']);
return $context->redirect('/login', 301);
```

### send

`send()` responds with any body and content type. Arrays and objects are JSON encoded, and PSR-7 streams are used as-is:

```php
return $context->send('<rss>...</rss>', 'application/rss+xml');
```

### Headers

Set response headers with `header()`. Pass `true` as the third argument to add to existing values instead of replacing them:

```php
$context->header('X-Powered-By', 'Dumbo');
$context->header('Link', '</style.css>; rel=preload', true);

return $context->text('Hello');
```

### Returning other values

If a handler returns an array or object, Dumbo sends it as JSON. If it returns nothing, the context's current response is sent.

```php
$app->get('/', fn() => ['hello' => 'world']);
```

## Variables

Share values across middleware and handlers with `set()` and `get()`:

```php
$app->use(function ($context, $next) {
    $context->set('user', ['name' => 'Jamie']);

    return $next($context);
});

$app->get('/me', function ($context) {
    return $context->json($context->get('user'));
});
```

`get()` returns `null` for missing keys. Dumbo sets an `environment` variable on every request containing the current [environment](/docs/environments) name.
