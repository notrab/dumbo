#!/bin/bash
set -e

# Start PHP-FPM service in the background
php-fpm -D

# Start Nginx service in the foreground
nginx -g "daemon off;"
