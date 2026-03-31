FROM drupal:11-apache

ARG COMPOSER_FLAGS="--no-interaction --no-progress --prefer-dist --optimize-autoloader"

WORKDIR /opt/drupal

RUN composer require php-amqplib/php-amqplib $COMPOSER_FLAGS

COPY ./rabbitmq_integration ./modules/custom/rabbitmq_integration

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 --start-period=10s \
  CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]
