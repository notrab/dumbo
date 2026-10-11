---
description: Deploy Dumbo with Nginx, Apache, FrankenPHP, Docker or bunny.net Magic Containers.
---

# Deployment

A Dumbo app is a regular PHP app. Point your web server at a front controller that calls `$app->run()`, and send every request to it.

## Built-in server

PHP's built-in server is great for local development:

```bash
php -S localhost:8000 index.php
```

## Web servers

Send every request that isn't a real file to `index.php`:

:::: tabs
::: tab title="Nginx"
```nginx
server {
    listen 80;
    root /var/www/public;
    index index.php;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```
:::
::: tab title="Apache"
Add an `.htaccess` file next to `index.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```
:::
::: tab title="FrankenPHP"
[FrankenPHP](https://frankenphp.dev) bundles a web server and PHP in a single binary or Docker image:

```dockerfile
FROM dunglas/frankenphp:php8.3-alpine

ENV SERVER_NAME=:8080

WORKDIR /app
COPY . /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader

# Optional: keep the app in memory with worker mode, below
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
```
:::
::::

## Production checklist

::: steps
1. Set `DUMBO_ENV=production` so errors don't leak stack traces.
2. Install with `composer install --no-dev --optimize-autoloader`.
3. Enable OPcache, and set `opcache.validate_timestamps=0` so PHP stops checking files for changes. Restart PHP after each deploy.
4. Preload Dumbo with OPcache, below.
5. Leave the JIT off. Requests are too short to benefit, and it made Dumbo slower in our benchmarks.
6. Use worker mode if your server supports it, below.
:::

## Preloading

With PHP-FPM, every request loads Dumbo's classes again. Dumbo ships a preload script that loads them once, when PHP starts, which makes each request around 15% cheaper. Add it to your `php.ini`:

```ini
opcache.preload=/var/www/vendor/notrab/dumbo/preload.php
opcache.preload_user=www-data
```

Preloaded code only changes when PHP restarts, so restart PHP-FPM after each deploy.

## Worker mode

Worker mode boots your app once and keeps it in memory, instead of loading Dumbo and adding your routes on every request. It handled about 50% more requests per second than FrankenPHP's default mode in our benchmarks, and the number of routes stops mattering.

:::: tabs
::: tab title="FrankenPHP"
Start your front controller as a [FrankenPHP worker](https://frankenphp.dev/docs/worker/). `$app->run()` notices it's running in a worker and handles every request the worker receives, so `index.php` doesn't change:

```dockerfile
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
```

Or in a `Caddyfile`:

```caddyfile
{
    frankenphp {
        worker ./public/index.php
    }
}

:8080 {
    root public/
    php_server
}
```
:::
::: tab title="RoadRunner"
Your app is a PSR-15 request handler, so [RoadRunner](https://roadrunner.dev) can pass requests to it directly:

```bash
composer require spiral/roadrunner-http
```

```php
<?php

use Dumbo\Dumbo;
use Nyholm\Psr7\Factory\Psr17Factory;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

require __DIR__ . '/vendor/autoload.php';

$app = new Dumbo();

$app->get('/', fn($context) => $context->text('Hello Dumbo!'));

$factory = new Psr17Factory();
$worker = new PSR7Worker(Worker::create(), $factory, $factory, $factory);

while ($request = $worker->waitRequest()) {
    $worker->respond($app->handle($request));
}
```
:::
::::

Anything you store outside a request, like a static property or a variable your handlers capture, now lives across requests. Keep per-request data in the context.

## Hosting on bunny.net

[Magic Containers](/docs/magic-containers) runs your Docker image in regions around the world, behind bunny.net's CDN. The guide takes you from a `Dockerfile` to an app that deploys on every push.

## Docker examples

The repo includes ready-to-run Docker setups:

- [FrankenPHP](https://github.com/notrab/dumbo/tree/main/examples/docker-frankenphp)
- [Nginx](https://github.com/notrab/dumbo/tree/main/examples/docker-nginx)
- [PHP-FPM](https://github.com/notrab/dumbo/tree/main/examples/docker-php-fpm)
- [bunny.net Magic Containers](https://github.com/notrab/dumbo/tree/main/examples/bunny-magic-containers)
