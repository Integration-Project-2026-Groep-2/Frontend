# ============================================================
# Stage 1 — Composer dependencies
# ============================================================
FROM composer:2.7 AS composer

WORKDIR /app

# Copy composer files first for better layer caching
COPY composer.json composer.lock ./

# Install dependencies (no dev in prod; we'll add them back in dev stage)
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --prefer-dist

# Copy the rest of the project
COPY . .

# ============================================================
# Stage 2 — Base PHP-FPM image (shared by dev and prod)
# ============================================================
FROM php:8.3-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libzip-dev \
    libxml2-dev \
    libpq-dev \
    mariadb-client \
    unzip \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions required by Drupal + php-amqplib
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql \
        mysqli \
        opcache \
        zip \
        xml \
        bcmath \
        exif \
        intl \
        mbstring \
        soap \
        sockets    # <-- required by php-amqplib

# Install APCu for Drupal's APCu cache backend (optional but recommended)
RUN pecl install apcu && docker-php-ext-enable apcu

# Copy PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/drupal.ini
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Set working directory
WORKDIR /var/www/html

# ============================================================
# Stage 3 — Development image
# ============================================================
FROM base AS dev

# Install Xdebug for local debugging
RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY docker/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Install Composer into the dev image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Copy all files including vendor (with dev dependencies)
COPY --from=composer /app /var/www/html

# Copy the custom module into place
COPY modules/custom/rabbitmq_integration /var/www/html/web/modules/custom/rabbitmq_integration

# Fix file permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/web/sites

# Copy Supervisor config (runs PHP-FPM + RabbitMQ consumer)
COPY docker/supervisor/supervisord.dev.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# ============================================================
# Stage 4 — Production image
# ============================================================
FROM base AS prod

# Production PHP settings
COPY docker/php/php.prod.ini /usr/local/etc/php/conf.d/drupal-prod.ini

# Copy application files from composer stage (no dev deps, optimized autoloader)
COPY --from=composer /app /var/www/html

# Copy the custom module into place
COPY modules/custom/rabbitmq_integration /var/www/html/web/modules/custom/rabbitmq_integration

# Fix file permissions — www-data owns everything
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod -R 775 /var/www/html/web/sites/default/files

# Copy Supervisor config (runs PHP-FPM + RabbitMQ consumer)
COPY docker/supervisor/supervisord.prod.conf /etc/supervisor/conf.d/supervisord.conf

# Drop to non-root user
USER www-data

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
