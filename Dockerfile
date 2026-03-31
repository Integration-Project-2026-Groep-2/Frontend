FROM php:8.5-apache
RUN a2enmod rewrite && \
    docker-php-ext-install pdo pdo_mysql sockets && \
    sed -i 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf