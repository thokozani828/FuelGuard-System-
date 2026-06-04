FROM php:8.2-apache

# Install PostgreSQL PDO extension
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite (if needed)
RUN a2enmod rewrite

# Copy application files
# Note: We copy from the root because the build context in CI/CD will be the root
COPY api/ /var/www/html/api/
COPY js/ /var/www/html/js/
COPY config.php /var/www/html/
COPY db.php /var/www/html/
COPY login.php /var/www/html/
COPY register.php /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# The base image already defines:
# EXPOSE 80
# CMD ["apache2-foreground"]
