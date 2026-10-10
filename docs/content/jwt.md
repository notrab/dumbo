---
description: Sign, verify and decode JSON Web Tokens, and protect routes with them.
---

# JWT

The `Dumbo\Helpers\JWT` helper signs and verifies JSON Web Tokens.

::: callout type="warning" title="Install firebase/php-jwt first"
The helper uses `firebase/php-jwt`, which isn't installed with Dumbo:

```bash
composer require firebase/php-jwt
```
:::

## Sign

```php
use Dumbo\Helpers\JWT;

$token = JWT::sign([
    'sub' => 42,
    'iat' => time(),
    'exp' => time() + 3600,
], 'my-secret');
```

The algorithm defaults to `HS256`. Pass another as the third argument.

## Verify

`verify()` checks the signature and the `exp`, `nbf` and `iat` claims, and returns the payload as an object. It throws an exception if the token is invalid:

```php
try {
    $payload = JWT::verify($token, 'my-secret');
    $userId = $payload->sub;
} catch (Exception $e) {
    // "Invalid token: Token has expired"
}
```

## Decode

Read a token's header and payload without verifying it:

```php
['header' => $header, 'payload' => $payload] = JWT::decode($token);
```

## Protecting routes

Combine it with middleware to protect routes:

```php
use Dumbo\Helpers\JWT;

$app->use('/api', function ($context, $next) {
    $header = $context->req->header('Authorization') ?? '';

    try {
        $payload = JWT::verify(substr($header, 7), 'my-secret');
    } catch (Exception $e) {
        return $context->json(['error' => 'Unauthorized'], 401);
    }

    $context->set('user', $payload);

    return $next($context);
});
```
