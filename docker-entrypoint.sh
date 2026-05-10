#!/usr/bin/env bash
set -e

DRUSH="/opt/drupal/vendor/bin/drush"
DB_URL="mysql://${DRUPAL_DB_USER}:${DRUPAL_DB_PASS}@${DRUPAL_DB_HOST}/${DRUPAL_DB_NAME}"

# ── Apache starten op de achtergrond ─────────────────────────────────────────
docker-php-entrypoint apache2-foreground &
apache_pid=$!

# ── Wachten tot database beschikbaar is ──────────────────────────────────────
echo "Wachten op database..."
until php -r "
  try {
    new PDO('mysql:host=${DRUPAL_DB_HOST};dbname=${DRUPAL_DB_NAME}', '${DRUPAL_DB_USER}', '${DRUPAL_DB_PASS}');
    exit(0);
  } catch (Exception \$e) {
    exit(1);
  }
" 2>/dev/null; do
  echo "Database nog niet klaar, opnieuw proberen in 3s..."
  sleep 3
done
echo "Database bereikbaar."

# ── Drupal installeren of updaten ─────────────────────────────────────────────
if [ -x "$DRUSH" ]; then
  # Controleer of Drupal al geinstalleerd is door de users tabel te checken.
  INSTALLED=$(php -r "
    try {
      \$pdo = new PDO('mysql:host=${DRUPAL_DB_HOST};dbname=${DRUPAL_DB_NAME}', '${DRUPAL_DB_USER}', '${DRUPAL_DB_PASS}');
      \$result = \$pdo->query('SHOW TABLES LIKE \"users\"');
      echo \$result->rowCount() > 0 ? 'yes' : 'no';
    } catch (Exception \$e) {
      echo 'no';
    }
  ")

  if [ "$INSTALLED" = "no" ]; then
    echo "Drupal installeren..."
    "$DRUSH" site:install standard \
      --db-url="$DB_URL" \
      --site-name="Frontend" \
      --account-name="admin" \
      --account-pass="admin" \
      --account-mail="admin@example.com" \
      --locale=en \
      --yes \
      2>&1
    echo "Drupal installatie voltooid."
  else
    echo "Drupal is al geinstalleerd."
  fi

  # Modules inschakelen
  echo "Modules inschakelen..."
  "$DRUSH" en hello_world custom_roles -y || true

  # Cache legen
  "$DRUSH" cr || true

  echo "Drupal init voltooid."
else
  echo "Drush niet gevonden — sla Drupal init over."
fi

# ── RabbitMQ scripts ──────────────────────────────────────────────────────────
if [ "${SKIP_AMQP_CONSUMERS:-0}" = "1" ]; then
  echo "SKIP_AMQP_CONSUMERS=1 — geen heartbeat/consumer-processen starten."
else
  php /opt/drupal/init_fields.php || echo "init_fields mislukt, verder gaan..."
  php /opt/drupal/setup.php       || echo "setup mislukt, verder gaan..."

  php /opt/drupal/heartbeat.php &
  php /opt/drupal/consumer.php confirmed   &
  php /opt/drupal/consumer.php updated     &
  php /opt/drupal/consumer.php deactivated &
  php /opt/drupal/r3_consumer.php &
fi

# ── Wachten op Apache ─────────────────────────────────────────────────────────
wait "$apache_pid"