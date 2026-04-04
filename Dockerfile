# Use Drupal 11 as the base image
FROM drupal:11

# Set working directory
WORKDIR /opt/drupal

RUN apt-get update && apt-get install -y libgmp-dev \
    && docker-php-ext-install bcmath \
    && rm -rf /var/lib/apt/lists/*

# Copy composer files and install dependencies
RUN composer require "php-amqplib/php-amqplib"

# Copy custom module(s) into the Drupal modules directory
COPY ./modules/custom/module /opt/drupal/web/modules/custom/module

# Copy the heartbeat script
COPY ./rabbitMQ/heartbeat.php /opt/drupal/heartbeat.php

# Set correct ownership and permissions
RUN chown -R www-data:www-data /opt/drupal/web/modules/custom/module \
    && chown www-data:www-data /opt/drupal/heartbeat.php \
    && chmod 750 /opt/drupal/heartbeat.php

# Use an entrypoint script to start the heartbeat and Apache
COPY ./docker-entrypoint.sh /docker-entrypoint-custom.sh
RUN sed -i 's/\r//' /docker-entrypoint-custom.sh \
    && chmod +x /docker-entrypoint-custom.sh

ENTRYPOINT ["/docker-entrypoint-custom.sh"]