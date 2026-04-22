#!/usr/bin/env bash
set -e

DRUSH="/opt/drupal/vendor/bin/drush"

docker-php-entrypoint apache2-foreground &
apache_pid=$!

if [ -x "$DRUSH" ]; then
  until "$DRUSH" status --field=db-status 2>/dev/null | grep -q "Connected"; do
    echo "Wachten op database..."
    sleep 3
  done

  "$DRUSH" en group ginvite -y || true
else
  echo "Drush not found, skipping Drupal init."
fi

php /opt/drupal/heartbeat.php &
wait "$apache_pid"