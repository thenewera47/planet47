FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Set permissions
RUN chmod -R 777 /var/www/html/users.json \
    && chmod -R 777 /var/www/html/error.log

EXPOSE 80
