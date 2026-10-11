---
description: Throw HTTP exceptions, customise error responses and handle uncaught errors.
---

# Error handling

## HTTPException

Throw an `HTTPException` from a handler or middleware to send an error response:

```php
use Dumbo\HTTPException;

$app->get('/posts/:id', function ($context) {
    $post = findPost($context->req->param('id'));

    if (!$post) {
        throw new HTTPException(404, 'Post not found', 'POST_NOT_FOUND', [
            'id' => $context->req->param('id'),
        ]);
    }

    return $context->json($post);
});
```

By default it's sent as JSON with the exception's status code:

```json
{
    "status": 404,
    "error": {
        "code": "POST_NOT_FOUND",
        "message": "Post not found",
        "details": { "id": "42" }
    }
}
```

### Custom responses

Pass a response as the fifth argument to send it instead:

```php
use Nyholm\Psr7\Response;

throw new HTTPException(401, 'Unauthorized', customResponse: new Response(
    401,
    ['WWW-Authenticate' => 'Bearer'],
    'Please log in'
));
```

## onError

Register a handler with `onError()` to take over error handling completely. It receives the error and a fresh context:

```php
use Dumbo\HTTPException;

$app->onError(function (Throwable $error, $context) {
    $status = $error instanceof HTTPException ? $error->getStatusCode() : 500;

    return $context->json(['error' => $error->getMessage()], $status);
});
```

## Uncaught errors

Without an `onError` handler, any other exception responds with a `500`. In development that's an HTML page with the message and stack trace. In production it's a generic JSON body:

```json
{"error":"Internal Server Error"}
```

::: callout type="danger" title="Don't leak stack traces"
Set `DUMBO_ENV=production` in production, or anyone can see your stack traces. See [environments](/docs/environments).
:::
