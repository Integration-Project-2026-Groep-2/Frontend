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

  "$DRUSH" en group ginvite hello_world -y || true
else
  echo "Drush not found, skipping Drupal init."
fi

php /opt/drupal/heartbeat.php &
php /opt/drupal/setup.php
php /opt/drupal/consumer.php confirmed &
php /opt/drupal/consumer.php updated &
php /opt/drupal/consumer.php deactivated &
wait "$apache_pid"
