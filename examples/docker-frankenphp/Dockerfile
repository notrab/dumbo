# Use a specific version of the PHP image for consistency and reliability
FROM dunglas/frankenphp:builder-php8.3-alpine

# Set the server name, replace "your-domain-name.example.com" with your actual domain
# For HTTP-only setups, use ":80"
ENV SERVER_NAME=:80

# Enable PHP production settings for optimized performance and security
# RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Set the working directory for the application
WORKDIR /app

# Copy the entire project directory to the container's working directory
COPY . /app

# Copy Composer binary from the official Composer image for dependency management
COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

# Install Composer dependencies with optimized options for production
RUN composer install \
    --ignore-platform-reqs \
    --no-scripts \
    --no-progress \
    --no-ansi \
    --no-dev

# Install required PHP extensions for the application
RUN install-php-extensions \
    pdo \
    pdo_mysql \
    opcache

# Optional: Set the FRANKENPHP_CONFIG environment variable to start FrankenPHP with a specific worker script
# ENV FRANKENPHP_CONFIG="worker ./public/index.php"
