# Use the official PHP image with FPM (FastCGI Process Manager)
FROM php:8.3-fpm

# Install necessary PHP extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install Git we'll need it for Composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git

# Install Composer
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Set the working directory in the container
WORKDIR /

# Copy the application code to the working directory
COPY . /

# Install Composer dependencies with optimized options for production
RUN composer install \
    --ignore-platform-reqs \
    --no-scripts \
    --no-progress \
    --no-ansi \
    --no-dev

# Use the default production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Configure PHP-FPM to listen on port 9000 instead of the Unix socket
RUN sed -i 's|listen = /var/run/php/php-fpm.sock|listen = 9000|' /usr/local/etc/php-fpm.d/www.conf

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
