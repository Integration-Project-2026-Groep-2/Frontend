#!/usr/bin/env bash
set -e

DRUSH="/opt/drupal/vendor/bin/drush"

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

# ── settings.php aanmaken met database configuratie ──────────────────────────
SETTINGS_FILE="/opt/drupal/web/sites/default/settings.php"

if [ ! -f "$SETTINGS_FILE" ]; then
  cp /opt/drupal/web/sites/default/default.settings.php "$SETTINGS_FILE"
  chmod 644 "$SETTINGS_FILE"
fi

if ! grep -q "drupal_db_configured" "$SETTINGS_FILE"; then
  cat >> "$SETTINGS_FILE" << SETTINGS
// drupal_db_configured
\$databases['default']['default'] = [
  'driver'    => 'mysql',
  'host'      => '${DRUPAL_DB_HOST}',
  'database'  => '${DRUPAL_DB_NAME}',
  'username'  => '${DRUPAL_DB_USER}',
  'password'  => '${DRUPAL_DB_PASS}',
  'port'      => '3306',
  'prefix'    => '',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload'  => 'core/modules/mysql/src/Driver/Database/mysql/',
];
SETTINGS
fi

# ── Drupal install (opt-in) + install.php verwijderen na succesvolle installatie ──
# Eerste run: install.php blijft beschikbaar zolang Drupal nog niet geïnstalleerd is.
# Na een succesvolle bootstrap wordt install.php verwijderd.
if [ -x "$DRUSH" ]; then
  BOOTSTRAP_STATUS="$("$DRUSH" status --field=bootstrap 2>/dev/null || true)"

  if [ "${ALLOW_AUTO_INSTALL:-0}" = "1" ] && [ "$BOOTSTRAP_STATUS" != "Successful" ]; then
    echo "ALLOW_AUTO_INSTALL=1 + Drupal not bootstrapped → installing fresh standard..."
    "$DRUSH" site:install standard \
      --site-name="Frontend" \
      --account-name="admin" \
      --account-pass="admin" \
      --account-mail="admin@example.com" \
      --locale=en \
      --yes
  fi

  BOOTSTRAP_STATUS="$("$DRUSH" status --field=bootstrap 2>/dev/null || true)"
  if [ "$BOOTSTRAP_STATUS" = "Successful" ] && [ -f "/opt/drupal/web/core/install.php" ]; then
    if rm -f /opt/drupal/web/core/install.php; then
      echo "install.php verwijderd na succesvolle installatie."
    else
      echo "Waarschuwing: install.php kon niet verwijderd worden; doorgaan met opstarten."
  fi

  "$DRUSH" en hello_world custom_roles ai_dashboard -y || true
  "$DRUSH" cr || true
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
  php /opt/drupal/ai_incident_consumer.php &
fi

# ── Wachten op Apache ─────────────────────────────────────────────────────────
wait "$apache_pid"