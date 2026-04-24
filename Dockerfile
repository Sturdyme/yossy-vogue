FROM php:8.2-cli

# Install system dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
    git unzip curl libzip-dev zip \
    libonig-dev libxml2-dev libcurl4-openssl-dev \
    && docker-php-ext-install \
    pdo pdo_mysql mbstring bcmath zip xml curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

# Install dependencies (ignore platform issues just in case)
RUN composer install --no-dev --optimize-autoloader --no-scripts --ignore-platform-reqs

# Fix permissions
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 10000

CMD php artisan serve --host=0.0.0.0 --port=10000