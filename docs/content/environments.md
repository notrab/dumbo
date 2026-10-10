---
description: Run Dumbo in development, production or testing mode.
---

# Environments

Dumbo runs in one of three environments: `development`, `production` or `testing`.

## Setting the environment

Pass it to the constructor:

```php
$app = new Dumbo('production');
```

Or set the `DUMBO_ENV` environment variable:

```bash
DUMBO_ENV=production php -S localhost:8000 index.php
```

When neither is set, Dumbo defaults to `development`. Constants are available for each one:

```php
$app = new Dumbo(Dumbo::ENV_PRODUCTION);
```

## Reading the environment

```php
$app->getEnvironment();   // "production"
$app->isDevelopment();    // false

$app->get('/', fn($context) => $context->text($context->get('environment')));
```

## What changes

In `development`, uncaught errors render an HTML page with the stack trace. In every other environment they respond with a generic JSON `500`. See [error handling](/docs/error-handling).

::: callout title="PHP's own settings"
Dumbo doesn't change PHP's `error_reporting` or `display_errors`. Configure those in `php.ini` as usual.
:::
