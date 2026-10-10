---
description: Install Dumbo with Composer and serve your first route in about a minute.
---

# Quickstart

## Hello world

::: steps
1. Create a project and require Dumbo with Composer:

   ```bash
   mkdir my-app && cd my-app
   composer require notrab/dumbo
   ```

2. Create an `index.php` file:

   ```php
   <?php

   require __DIR__ . '/vendor/autoload.php';

   use Dumbo\Dumbo;

   $app = new Dumbo();

   $app->get('/', function ($context) {
       return $context->text('Hello Dumbo!');
   });

   $app->run();
   ```

3. Start PHP's built-in server, using `index.php` as the router:

   ```bash
   php -S localhost:8000 index.php
   ```

4. Open [http://localhost:8000](http://localhost:8000) and say hello. 🐘
:::

::: callout type="tip" title="Dumbo needs PHP 8.3"
Check your version with `php -v` before you start.
:::

## A bigger example

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Dumbo\Dumbo;

$app = new Dumbo();

$app->use(function ($context, $next) {
    $context->set('message', 'Hello from middleware!');

    return $next($context);
});

$app->get('/', function ($context) {
    return $context->json([
        'message' => $context->get('message'),
        'timestamp' => time(),
    ]);
});

$app->get('/users/:id', function ($context) {
    $id = $context->req->param('id');

    return $context->json(['userId' => $id]);
});

$app->post('/users', function ($context) {
    $body = $context->req->body();

    return $context->json($body, 201);
});

$app->run();
```

## Next steps

- Learn about [routing](/docs/routing)
- See what's on the [context](/docs/context)
- Browse the [examples](/docs/examples)
