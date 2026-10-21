FROM php:8.3-apache

# System libraries and PHP extensions.
# No --ignore-platform-reqs anywhere: a missing extension should fail the BUILD, not production.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql bcmath zip opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Production PHP settings (errors are not shown to visitors) plus upload limits
# that match the 4 MB product images the admin form accepts.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN { \
        echo 'expose_php=0'; \
        echo 'upload_max_filesize=8M'; \
        echo 'post_max_size=32M'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=10000'; \
    } > "$PHP_INI_DIR/conf.d/app.ini"

# Apache serves Laravel's public/ folder, allows its .htaccess rewrites,
# hides its version and adds two basic security headers.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's/^ServerTokens .*/ServerTokens Prod/' -e 's/^ServerSignature .*/ServerSignature Off/' \
        /etc/apache2/conf-available/security.conf \
    && printf '%s\n' \
        'ServerName localhost' \
        'Header always set X-Content-Type-Options "nosniff"' \
        'Header always set Referrer-Policy "strict-origin-when-cross-origin"' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

WORKDIR /var/www/html

# Dependencies first, so this layer is cached until composer.json / composer.lock change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["docker-entrypoint.sh"]
