FROM drupal:11-apache

ARG COMPOSER_FLAGS="--no-interaction --no-progress --prefer-dist --optimize-autoloader --classmap-authoritative"

WORKDIR /opt/drupal

# Require the php-amqplib/php-amqplib package for RabbitMQ integration
RUN composer require php-amqplib/php-amqplib $COMPOSER_FLAGS

# Copy the custom module and configuration files
COPY ./module /opt/drupal/modules/custom/custom_module

EXPOSE 80

# Health check for orchestration systems
HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=10s \
    CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"