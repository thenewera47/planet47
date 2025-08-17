# Lightweight PHP + Apache
FROM php:8.2-apache

# Enable Apache rewrite + set proper timezone (optional)
RUN a2enmod rewrite && \
    echo "date.timezone=Asia/Kolkata" > /usr/local/etc/php/conf.d/timezone.ini

# Copy app
WORKDIR /var/www/html
COPY . /var/www/html

# Ensure writable data files for the bot
# If files don't exist, create them. Then set permissions for www-data.
RUN set -eux; \
    touch /var/www/html/users.json || true; \
    touch /var/www/html/error.log  || true; \
    chown -R www-data:www-data /var/www/html; \
    chmod 664 /var/www/html/users.json /var/www/html/error.log

# Apache site config: allow .htaccess overrides
RUN sed -ri 's!/var/www/html!/var/www/html!g' /etc/apache2/sites-available/000-default.conf && \
    sed -ri 's/AllowOverride None/AllowOverride All/i' /etc/apache2/apache2.conf

# Let Render choose the port (via $PORT). We’ll rewrite Apache to listen on it at runtime.
# Create a tiny start script that patches Apache’s port from $PORT (default 8080).
RUN printf '%s\n' \
    '#!/bin/sh' \
    'set -eu' \
    ': "${PORT:=8080}"' \
    'echo "Using PORT=$PORT"' \
    'sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf' \
    'sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf' \
    'exec apache2-foreground' \
    > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Environment you will set on Render dashboard:
#   BOT_TOKEN=<your_telegram_bot_token>
ENV BOT_TOKEN=""

# Expose for local use (Render ignores EXPOSE but it’s handy locally)
EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
