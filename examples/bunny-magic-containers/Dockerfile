# Magic Containers runs linux/amd64 images, so build for that platform:
#   docker build --platform linux/amd64 -t dumbo-app .
FROM dunglas/frankenphp:php8.3-alpine

# Serve plain HTTP on port 8080. The Magic Containers CDN endpoint takes
# HTTPS traffic and forwards it to this port.
ENV SERVER_NAME=:8080
ENV DUMBO_ENV=production

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && install-php-extensions opcache \
    && echo "opcache.validate_timestamps=0" > "$PHP_INI_DIR/conf.d/zz-dumbo.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first, so code changes don't reinstall them
COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-progress --no-interaction --classmap-authoritative

COPY . .

# Boot the app once and keep it in memory between requests
ENV FRANKENPHP_CONFIG="worker ./public/index.php"

EXPOSE 8080
