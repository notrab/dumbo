---
description: What changed since Dumbo 1.x and how to update your app.
---

# Upgrading from 1.x

Here's what changed since Dumbo 1.x and how to update your app.

## Middleware

Middleware moved from `Dumbo\Helpers` to `Dumbo\Middleware`, and some classes were renamed:

| 1.x | 2.x |
| --- | --- |
| `Dumbo\Helpers\CacheMiddleware` | `Dumbo\Middleware\Cache` |
| `Dumbo\Helpers\CsrfMiddleware` | `Dumbo\Middleware\Csrf` |
| `BodyLimit::limit()` | `BodyLimit::bodyLimit()` |
| `CacheMiddleware::withHeaders(...)` | `Cache::cache([...])` |

`Cache::cache()` takes `type`, `maxAge` and `mustRevalidate` options.

## CORS options

CORS options are now camelCase: `allowMethods`, `allowHeaders`, `exposeHeaders` and `maxAge`.

## Removed helpers

`DateHelper` was removed. Use PHP's date functions instead.

## JWT

`firebase/php-jwt` is no longer installed automatically. Require it if you use `Dumbo\Helpers\JWT`:

```bash
composer require firebase/php-jwt
```

## PSR-7

Dumbo now uses [nyholm/psr7](https://github.com/Nyholm/psr7) instead of `guzzlehttp/psr7`, which makes each request cheaper. If your app creates Guzzle responses, requests or streams itself, switch to the Nyholm classes:

| guzzlehttp/psr7 | nyholm/psr7 |
| --- | --- |
| `GuzzleHttp\Psr7\Response` | `Nyholm\Psr7\Response` |
| `GuzzleHttp\Psr7\ServerRequest` | `Nyholm\Psr7\ServerRequest` |
| `GuzzleHttp\Psr7\Utils::streamFor($body)` | `Nyholm\Psr7\Stream::create($body)` |

Or keep using Guzzle with `composer require guzzlehttp/psr7`. Dumbo accepts any PSR-7 response from your handlers.

## Environments

Pass the environment to `new Dumbo('production')` or set `DUMBO_ENV`. These methods were removed:

- `setEnvironment()`
- `detectEnvironment()`
- `isProduction()`
- `isTesting()`

Dumbo no longer changes `error_reporting`, and the `environment` context variable is now the environment name.
