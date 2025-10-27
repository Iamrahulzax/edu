FROM php:8.2-apache

# Install required packages and PHP extensions
RUN apt-get update && apt-get install -y default-mysql-client git unzip libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Copy project into container
COPY . /var/www/html/

# Fix permissions
RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]