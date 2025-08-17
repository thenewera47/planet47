# Dockerfile — PHP 8.2 + Apache, production friendly for Render
FROM php:8.2-apache

# Install needed extensions & tools
RUN apt-get update && apt-get install -y \
    libzip-dev unzip git curl gnupg2 libonig-dev \
  && docker-php-ext-install zip mbstring

# Enable Apache modules
RUN a2enmod rewrite headers

# Working dir
WORKDIR /var/www/html

# Copy files
COPY . /var/www/html

# Ensure storage files exist and set permissions for www-data
RUN touch /var/www/html/users.json || true; \
    touch /var/www/html/error.log || true; \
    chown -R www-data:www-data /var/www/html; \
    chmod 664 /var/www/html/users.json /var/www/html/error.log

# Create small start script to use Render $PORT
RUN printf '%s\n' \
  '#!/bin/sh' \
  'set -eu' \
  ': "${PORT:=8080}"' \
  'sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf' \
  'sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf' \
  'exec apache2-foreground' \
  > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

ENV BOT_TOKEN=""

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
