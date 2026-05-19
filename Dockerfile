FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev \
    && docker-php-ext-install pdo pdo_mysql zip intl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer install --no-dev --optimize-autoloader --no-scripts
RUN rm -rf var/cache/*

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/router.php"]