FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    sqlite3 libsqlite3-dev \
    unzip git curl zip \
    && docker-php-ext-install pdo pdo_sqlite

# Enable Apache rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy app files
COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port 80 for Apache
EXPOSE 80

CMD ["apache2-foreground"]
