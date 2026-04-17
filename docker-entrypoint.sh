#!/bin/bash
set -e

# Start Apache immediately so the container serves HTTP
docker-php-entrypoint apache2-foreground &
apache_pid=$!

# Wait for database only if Drush exists
if [ -x vendor/bin/drush ]; then
  until vendor/bin/drush status --field=db-status 2>/dev/null | grep -q "Connected"; do
    echo "Wachten op database..."
    sleep 3
  done

  # Activate modules
  vendor/bin/drush en group ginvite -y || true
else
  echo "Drush not found, skipping Drupal init."
fi

# Start heartbeat in background
php /opt/drupal/heartbeat.php &

# Keep container alive with Apache
wait "$apache_pid"