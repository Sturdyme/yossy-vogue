FROM php:8.2-cli

# 1. Install system dependencies (Added libpq-dev for PostgreSQL support)
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip \
    libonig-dev libxml2-dev libcurl4-openssl-dev \
    libpq-dev \
    && docker-php-ext-install \
    pdo pdo_mysql pdo_pgsql pgsql mbstring bcmath zip xml curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# 2. Install dependencies (Removed --ignore-platform-reqs to ensure drivers are validated)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Fix permissions
RUN chmod -R 775 storage bootstrap/cache
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 10000

# 3. Use a single command to migrate and then start the server
# The --force flag is required for migrations to run in production mode
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT