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

# ── BEVEILIGDE AUTO-INSTALL: install.php blokkeren ────────────────────────────
# Verwijder install.php na eerste run zodat het niet meer publiek bereikbaar is
if [ -f "/opt/drupal/web/core/install.php" ]; then
  rm -f /opt/drupal/web/core/install.php
  echo "install.php verwijderd voor beveiliging."
fi

# ── Drupal install (opt-in) + module enable + cache rebuild ──────────────────
# The install block runs only when ALLOW_AUTO_INSTALL=1 is set in the env.
# Production must never set it; local dev sets it in .env so a fresh git
# clone bootstraps without manual drush site:install. Detection uses
# `drush status --field=bootstrap`: Drush actually boots Drupal and reports
# "Successful" only when settings.php + DB schema are valid. The previous
# raw-SQL probe was fail-open and wiped production on edge-case failures.
if [ -x "$DRUSH" ]; then
  if [ "${ALLOW_AUTO_INSTALL:-0}" = "1" ]; then
    if ! "$DRUSH" status --field=bootstrap 2>/dev/null | grep -q "Successful"; then
      echo "ALLOW_AUTO_INSTALL=1 + Drupal not bootstrapped → installing fresh standard..."
      "$DRUSH" site:install standard \
        --site-name="Frontend" \
        --account-name="admin" \
        --account-pass="admin" \
        --account-mail="admin@example.com" \
        --locale=en \
        --yes
    fi
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
  php /opt/drupal/consumer.php session_created &
  php /opt/drupal/consumer.php session_updated &
  php /opt/drupal/consumer.php session_rescheduled &
  php /opt/drupal/consumer.php session_cancelled &
  php /opt/drupal/consumer.php session_full &
  php /opt/drupal/consumer.php location_created &
  php /opt/drupal/consumer.php location_updated &
  php /opt/drupal/consumer.php location_deleted &
  php /opt/drupal/ai_incident_consumer.php &
fi

# ── Wachten op Apache ─────────────────────────────────────────────────────────
wait "$apache_pid"