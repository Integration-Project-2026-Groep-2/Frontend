# Use Drupal 11 as the base image
FROM drupal:11
WORKDIR /opt/drupal

RUN apt-get update && apt-get install -y libgmp-dev libxml2-utils \
    && docker-php-ext-install bcmath \
    && rm -rf /var/lib/apt/lists/*

RUN composer require \
    "php-amqplib/php-amqplib" \
    "drupal/group:^3.0" \
    "drupal/ginvite" \
    "drush/drush"

# Copy the custom modules and theme
COPY ./modules/custom /opt/drupal/web/modules/custom
COPY --chown=www-data:www-data ./themes/custom /opt/drupal/web/themes/custom
COPY ./xsd /opt/drupal/xsd

# RabbitMQ scripts
COPY ./rabbitMQ/heartbeat.php   /opt/drupal/heartbeat.php
COPY ./rabbitMQ/consumer.php    /opt/drupal/consumer.php
COPY ./rabbitMQ/setup.php       /opt/drupal/setup.php
COPY ./rabbitMQ/init_fields.php /opt/drupal/init_fields.php
COPY ./ai_incident_consumer.php         /opt/drupal/ai_incident_consumer.php

RUN chown -R www-data:www-data /opt/drupal/web/modules/custom \
    && chown www-data:www-data /opt/drupal/heartbeat.php \
    && chown www-data:www-data /opt/drupal/consumer.php \
    && chown www-data:www-data /opt/drupal/setup.php \
    && chown www-data:www-data /opt/drupal/init_fields.php \
    && chown www-data:www-data /opt/drupal/ai_incident_consumer.php \
    && chmod 750 /opt/drupal/heartbeat.php \
    && chmod 750 /opt/drupal/consumer.php \
    && chmod 750 /opt/drupal/setup.php \
    && chmod 750 /opt/drupal/init_fields.php \
    && chmod 750 /opt/drupal/ai_incident_consumer.php

COPY ./docker-entrypoint.sh /docker-entrypoint-custom.sh
RUN sed -i 's/\r//' /docker-entrypoint-custom.sh \
    && chmod +x /docker-entrypoint-custom.sh

ENTRYPOINT ["/docker-entrypoint-custom.sh"]