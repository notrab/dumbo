# Deployment

A Dumbo app is a regular PHP app. Point your web server at a front controller that calls `$app->run()`, and send every request to it.

## Built-in server

PHP's built-in server is great for local development:

```bash
php -S localhost:8000 index.php
```

## Nginx and PHP-FPM

Send every request that isn't a real file to `index.php`:

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

## Apache

Add an `.htaccess` file next to `index.php`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

## FrankenPHP

[FrankenPHP](https://frankenphp.dev) bundles a web server and PHP in a single binary or Docker image:

```dockerfile
FROM dunglas/frankenphp:php8.3-alpine

ENV SERVER_NAME=:8080

WORKDIR /app
COPY . /app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader
```

## Production checklist

- Set `DUMBO_ENV=production` so errors don't leak stack traces.
- Install with `composer install --no-dev --optimize-autoloader`.
- Enable OPcache.

## Docker examples

The repo includes ready-to-run Docker setups:

- [FrankenPHP](https://github.com/notrab/dumbo/tree/main/examples/docker-frankenphp)
- [Nginx](https://github.com/notrab/dumbo/tree/main/examples/docker-nginx)
- [PHP-FPM](https://github.com/notrab/dumbo/tree/main/examples/docker-php-fpm)
