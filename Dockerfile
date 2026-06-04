FROM php:8.4-cli-alpine

# Extensiones PHP requeridas por Laravel + amqplib
RUN apk add --no-cache \
        unzip \
        curl \
        oniguruma-dev \
        libzip-dev \
        sqlite-dev \
    && docker-php-ext-install \
        pdo \
        pdo_sqlite \
        mbstring \
        bcmath \
        zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-req=ext-sockets

COPY . .

# Usar .env.docker (tiene RABBITMQ_HOST=rabbitmq, no localhost)
RUN cp .env.docker .env

RUN composer dump-autoload --optimize \
    && php artisan storage:link --quiet || true \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
