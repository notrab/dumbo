# Introduction

Dumbo is a lightweight, friendly PHP framework for HTTP, inspired by [Hono](https://hono.dev). It gives you fast routing, composable middleware and a handful of helpers, and then gets out of your way.

```php
<?php

use Dumbo\Dumbo;

$app = new Dumbo();

$app->get('/', fn($context) => $context->text('Hello Dumbo!'));

$app->run();
```

## Features

- **Lightweight and fast** — built on [FastRoute](https://github.com/nikic/FastRoute) and PSR-7.
- **Flexible routing** — route params, nested apps and every HTTP method.
- **Middleware** — global or scoped to a path prefix.
- **Security** — CSRF, JWT, Basic and Bearer auth, and body size limits.
- **Batteries included** — cookies, CORS, compression, HTTP caching, request IDs, logging and static files.
- **Templates** — bring Twig, Blade, Latte or Smarty.
- **Environments** — development, production and testing modes.

## Requirements

Dumbo requires **PHP 8.3** or newer and [Composer](https://getcomposer.org).

## Why Dumbo?

If you've used Hono or Express, Dumbo will feel familiar. Every handler receives a single `$context` holding the request, the response and any values your middleware shares. Handlers return a response and that's it.

Ready? Head to the [quickstart](/docs/quickstart), or browse the [examples](/docs/examples).
