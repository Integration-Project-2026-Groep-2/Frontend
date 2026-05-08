#!/bin/bash
set -e

# Start the heartbeat script detached in the background
php /opt/drupal/heartbeat.php &

# Hand off to the official Drupal entrypoint
exec docker-php-entrypoint apache2-foreground