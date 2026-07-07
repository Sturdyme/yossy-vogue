FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip \
    libonig-dev libxml2-dev libcurl4-openssl-dev \
    libpq-dev \
    && docker-php-ext-install \
    pdo pdo_mysql pdo_pgsql pgsql mbstring bcmath zip xml curl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
COPY . .

# ADDED --ignore-platform-reqs BACK IN
RUN composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs

RUN chmod -R 775 storage bootstrap/cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# ==================== NEW ENTRYPOINT ====================
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["docker-entrypoint.sh"]

# Default command (will be executed by the entrypoint)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=$PORT"]