# Use Drupal 11 as the base image
FROM drupal:11

WORKDIR /opt/drupal

RUN apt-get update && apt-get install -y libgmp-dev \
    && docker-php-ext-install bcmath \
    && rm -rf /var/lib/apt/lists/*

RUN composer require \
    "php-amqplib/php-amqplib" \
    "drupal/group:^3.0" \
    "drupal/ginvite"

COPY ./modules/custom/module       /opt/drupal/web/modules/custom/module
COPY ./modules/custom/custom_roles /opt/drupal/web/modules/custom/custom_roles

# Heartbeat en consumer scripts
COPY ./rabbitMQ/heartbeat.php /opt/drupal/heartbeat.php
COPY ./rabbitMQ/consumer.php  /opt/drupal/consumer.php

RUN chown -R www-data:www-data /opt/drupal/web/modules/custom/module \
    && chown www-data:www-data /opt/drupal/heartbeat.php \
    && chown www-data:www-data /opt/drupal/consumer.php \
    && chmod 750 /opt/drupal/heartbeat.php \
    && chmod 750 /opt/drupal/consumer.php

COPY ./docker-entrypoint.sh /docker-entrypoint-custom.sh
RUN sed -i 's/\r//' /docker-entrypoint-custom.sh \
    && chmod +x /docker-entrypoint-custom.sh

ENTRYPOINT ["/docker-entrypoint-custom.sh"]
