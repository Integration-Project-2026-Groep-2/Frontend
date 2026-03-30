<?php

/**
 * @file
 * settings.docker.php
 *
 * Drop-in settings file that reads database and RabbitMQ credentials
 * from Docker environment variables.
 *
 * HOW TO USE:
 * Add this line at the bottom of your web/sites/default/settings.php:
 *
 *   if (file_exists($app_root . '/' . $site_path . '/settings.docker.php')) {
 *     include $app_root . '/' . $site_path . '/settings.docker.php';
 *   }
 *
 * Then place this file at:  web/sites/default/settings.docker.php
 */

// ── Database ─────────────────────────────────────────────────────────────────
$databases['default']['default'] = [
  'driver'    => 'mysql',
  'database'  => getenv('DRUPAL_DB_NAME')     ?: 'drupal',
  'username'  => getenv('DRUPAL_DB_USER')     ?: 'drupal',
  'password'  => getenv('DRUPAL_DB_PASSWORD') ?: 'drupal',
  'host'      => getenv('DRUPAL_DB_HOST')     ?: 'db',
  'port'      => getenv('DRUPAL_DB_PORT')     ?: '3306',
  'prefix'    => '',
  'collation' => 'utf8mb4_general_ci',
  // Drupal 11 database driver namespace.
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload'  => 'core/modules/mysql/src/Driver/Database/mysql/',
];

// ── RabbitMQ (read by RabbitMQConnectionManager via config override) ──────────
// This overwrites the stored config so credentials stay out of the database.
$config['rabbitmq_integration.settings']['host']     = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
$config['rabbitmq_integration.settings']['port']     = (int) (getenv('RABBITMQ_PORT') ?: 5672);
$config['rabbitmq_integration.settings']['username'] = getenv('RABBITMQ_USER') ?: 'drupal';
$config['rabbitmq_integration.settings']['password'] = getenv('RABBITMQ_PASS') ?: 'drupal';
$config['rabbitmq_integration.settings']['vhost']    = getenv('RABBITMQ_VHOST') ?: '/';

// ── Trusted host patterns ─────────────────────────────────────────────────────
// Add your domain(s) here. Adjust for production.
$settings['trusted_host_patterns'] = [
  '^localhost$',
  '^127\.0\.0\.1$',
  // '^yourdomain\.com$',
];

// ── Hash salt ─────────────────────────────────────────────────────────────────
// Override with a strong random value in production (store in an env var).
if ($hash_salt = getenv('DRUPAL_HASH_SALT')) {
  $settings['hash_salt'] = $hash_salt;
}

// ── File paths ────────────────────────────────────────────────────────────────
$settings['file_public_path']  = 'sites/default/files';
$settings['file_private_path'] = '/var/www/html/private';

// ── Config sync directory ─────────────────────────────────────────────────────
$settings['config_sync_directory'] = '../config/sync';

// ── Performance: disable caches in dev ───────────────────────────────────────
if (getenv('APP_ENV') === 'dev') {
  $settings['cache']['bins']['render']      = 'cache.backend.null';
  $settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
  $settings['cache']['bins']['page']        = 'cache.backend.null';
}
