FROM php:8.4-cli-alpine

# Install system utilities, postgres headers, and compiling tools for PECL
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    zip \
    postgresql-dev \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# PHPStan/Larastan can exceed the default 128M CLI memory limit during analysis.
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/99-memory-limit.ini

# Bring in Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

# COPY STEP: Copy your entire codebase into the container's working directory
COPY . /var/www/app

# Install production composer dependencies inside the image
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Set correct write permissions for Laravel storage and cache directories
RUN chmod -R 775 /var/www/app/storage /var/www/app/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]