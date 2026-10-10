---
description: Deploy Dumbo with Nginx, Apache, FrankenPHP or Docker.
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
```
:::
::::

## Production checklist

::: steps
1. Set `DUMBO_ENV=production` so errors don't leak stack traces.
2. Install with `composer install --no-dev --optimize-autoloader`.
3. Enable OPcache.
4. Preload Dumbo with OPcache, below.
:::

## Preloading

With PHP-FPM, every request loads Dumbo's classes again. Dumbo ships a preload script that loads them once, when PHP starts, which makes each request around 15% cheaper. Add it to your `php.ini`:

```ini
opcache.preload=/var/www/vendor/notrab/dumbo/preload.php
opcache.preload_user=www-data
```

Preloaded code only changes when PHP restarts, so restart PHP-FPM after each deploy.

## Docker examples

The repo includes ready-to-run Docker setups:

- [FrankenPHP](https://github.com/notrab/dumbo/tree/main/examples/docker-frankenphp)
- [Nginx](https://github.com/notrab/dumbo/tree/main/examples/docker-nginx)
- [PHP-FPM](https://github.com/notrab/dumbo/tree/main/examples/docker-php-fpm)
