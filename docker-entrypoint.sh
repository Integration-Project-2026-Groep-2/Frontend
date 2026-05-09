#!/usr/bin/env bash
set -e

cp -r /tmp/themes/custom /opt/drupal/web/themes/custom

chown -R www-data:www-data /opt/drupal/web/themes/custom

DRUSH="/opt/drupal/vendor/bin/drush"

docker-php-entrypoint apache2-foreground &
apache_pid=$!

if [ -x "$DRUSH" ]; then
  until "$DRUSH" status --field=db-status 2>/dev/null | grep -q "Connected"; do
    echo "Wachten op database..."
    sleep 3
  done

  "$DRUSH" en group ginvite hello_world custom_roles -y || true
else
  echo "Drush not found, skipping Drupal init."
fi

if [ "${SKIP_AMQP_CONSUMERS:-0}" = "1" ]; then
  echo "SKIP_AMQP_CONSUMERS=1 — geen heartbeat/consumer-processen starten (prod-broker test mode)"
else
  php /opt/drupal/heartbeat.php &
  php /opt/drupal/init_fields.php
  php /opt/drupal/setup.php
  php /opt/drupal/consumer.php confirmed &
  php /opt/drupal/consumer.php updated &
  php /opt/drupal/consumer.php deactivated &
fi
wait "$apache_pid"
