# Use the official PHP image with PHP 8.3 and FPM (FastCGI Process Manager)
FROM php:8.3-fpm

# Set environment variables for the Docker container
ENV DEBIAN_FRONTEND=noninteractive

# Update package list and install necessary packages for Nginx and PHP extensions
# - curl: used for HTTP requests by PHP applications
# - zip, unzip: used by Composer to handle archives
# - git: required for some Composer packages
# - libfreetype6-dev, libjpeg-dev, libpng-dev: required for GD PHP extension (for image manipulation)
# - libonig-dev: required for mbstring PHP extension
RUN apt-get update && apt-get install -y \
    nginx \
    curl \
    zip \
    unzip \
    git \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libonig-dev \
    libicu-dev \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mbstring intl zip pdo pdo_mysql pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer globally
# Composer is a dependency manager for PHP, often used in modern PHP development
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy custom Nginx configuration
# We'll define a simple Nginx configuration to serve PHP applications
COPY ./Docker/nginx.conf /etc/nginx/nginx.conf

# Set permissions for Nginx and PHP directories
RUN chown -R www-data:www-data /var/www

# Expose port 80 for HTTP traffic
EXPOSE 80

# Copy application code from your local directory to /var/www/html
COPY . /var/www/html

# Define the entrypoint script to start Nginx and PHP-FPM when the container runs
COPY ./Docker/docker-entrypoint.sh /usr/local/bin/

# Make the entrypoint script executable
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Entry point to start services
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Make sure to run PHP-FPM as a daemon
CMD ["php-fpm", "-F"]


