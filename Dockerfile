# Use official PHP image with Apache
FROM php:8.2-apache

# Install required extensions
RUN docker-php-ext-install pcntl

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html

# Set permissions for storage files
RUN touch users.json error.log && \
    chown -R www-data:www-data /var/www/html && \
    chmod 664 users.json error.log

# Expose port
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
