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
WORKDIR /var/www/html

# Copy the application code to the working directory
COPY . /var/www/html

# Install Composer dependencies with optimized options for production
RUN composer install \
    --ignore-platform-reqs \
    --no-scripts \
    --no-progress \
    --no-ansi \
    --no-dev

# Set proper permissions for the web server
RUN chown -R www-data:www-data /var/www/html

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
