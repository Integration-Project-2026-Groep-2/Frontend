FROM composer:latest AS composer

WORKDIR /app
COPY src/composer.json src/composer.lock ./
RUN composer install --no-dev --optimize-autoloader

FROM php:8.5-apache
RUN a2enmod rewrite && \
    docker-php-ext-install pdo pdo_mysql sockets && \
    sed -i 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf

COPY ./ /var/www/html

COPY --from=composer /app/vendor /var/www/hml/vendor