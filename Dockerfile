FROM composer:2 AS dependencies

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader

FROM wordpress:php8.3-apache

COPY --chown=www-data:www-data . /var/www/html/
COPY --from=dependencies --chown=www-data:www-data /app/vendor/ /var/www/html/vendor/
