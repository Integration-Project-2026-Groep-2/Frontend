FROM php:8.5-cli

# Install AMQP extension + composer
RUN apt-get update && apt-get install -y \
    librabbitmq-dev \
    unzip \
    && docker-php-ext-install sockets \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /scripts

# Install composer deps first (better layer caching)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader

COPY . .