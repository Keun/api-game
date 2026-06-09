FROM php:8.2-apache

RUN apt-get update && apt-get install -y libonig-dev --no-install-recommends \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo pdo_mysql mbstring \
    && a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html
