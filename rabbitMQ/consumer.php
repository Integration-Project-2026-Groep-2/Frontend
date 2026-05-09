<?php

// Onderdruk E_DEPRECATED van php-amqplib (PHP-8.4 parameter-volgorde warnings).
// Dit zijn library-bugs, geen fouten in onze code.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

/**
 * /opt/drupal/consumer.php
 *
 * Gebruik:
 *   php consumer.php confirmed    → start UserConfirmedConsumer  (frontend.user.confirmed)
 *   php consumer.php updated      → start UserUpdateConsumer     (frontend.user.updated)
 *   php consumer.php deactivated  → start UserDeactivatedConsumer (frontend.user.deactivated)
 */

$type = $argv[1] ?? null;

if (!in_array($type, ['confirmed', 'updated', 'deactivated'])) {
  echo "Gebruik: php consumer.php [confirmed|updated|deactivated]\n";
  exit(1);
}

// ─── Database wachten ─────────────────────────────────────────────────────────
$maxWait = 60;
$waited  = 0;
while ($waited < $maxWait) {
  try {
    $dsn = sprintf(
      'mysql:host=%s;dbname=%s',
      $_ENV['DRUPAL_DB_HOST'] ?? 'frontend_db',
      $_ENV['DRUPAL_DB_NAME'] ?? 'drupal'
    );
    $pdo = new PDO($dsn,
      $_ENV['DRUPAL_DB_USER'] ?? 'drupal',
      $_ENV['DRUPAL_DB_PASS'] ?? 'drupal'
    );
    unset($pdo);
    echo "Database bereikbaar.\n";
    break;
  }
  catch (\PDOException $e) {
    echo "Database nog niet klaar, wacht 5s... ({$waited}s/{$maxWait}s)\n";
    sleep(5);
    $waited += 5;
  }
}

if ($waited >= $maxWait) {
  echo "Database niet bereikbaar na {$maxWait}s. Consumer stopt.\n";
  exit(1);
}

// ─── Drupal bootstrap ─────────────────────────────────────────────────────────
define('DRUPAL_ROOT', '/opt/drupal/web');
chdir(DRUPAL_ROOT);

$autoloader = require DRUPAL_ROOT . '/autoload.php';

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();
$kernel  = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->preHandle($request);

// Fallback class loader.
// Als het hello_world-module geïnstalleerd is in Drupal (drush en hello_world)
// zijn de klassen al beschikbaar via Drupal's eigen PSR-4 autoloader en
// wordt dit blok overgeslagen. Zo niet, laden we de bestanden direct op
// via glob() — robuust en onafhankelijk van de exacte directory-naam.
$_consumerClasses = [
  'Drupal\\hello_world\\RabbitMQ\\Validation\\XsdRegistry'           => 'RabbitMQ/Validation/XsdRegistry.php',
  'Drupal\\hello_world\\RabbitMQ\\Validation\\XsdValidator'          => 'RabbitMQ/Validation/XsdValidator.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\UserConfirmedConsumer'   => 'RabbitMQ/Consumer/UserConfirmedConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\UserUpdateConsumer'      => 'RabbitMQ/Consumer/UserUpdateConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\UserDeactivatedConsumer' => 'RabbitMQ/Consumer/UserDeactivatedConsumer.php',
];

// Zoek de module-directory via glob (werkt ongeacht de naam van de map).
$_infoFiles  = glob(DRUPAL_ROOT . '/modules/custom/*/hello_world.info.yml') ?: [];
$_moduleBase = $_infoFiles ? dirname($_infoFiles[0]) . '/src/' : null;

foreach ($_consumerClasses as $_fqcn => $_rel) {
  if (class_exists($_fqcn, false)) {
    continue; // Al geladen door Drupal's autoloader — overslaan.
  }
  if (!$_moduleBase) {
    echo "[FOUT] hello_world module-directory niet gevonden in {$_consumerClasses[$_fqcn]}. Consumer stopt.\n";
    exit(1);
  }
  $_path = $_moduleBase . $_rel;
  if (!file_exists($_path)) {
    echo "[FOUT] Klasse-bestand niet gevonden: {$_path}\n";
    exit(1);
  }
  require_once $_path;
}
unset($_consumerClasses, $_infoFiles, $_moduleBase, $_fqcn, $_rel, $_path);

echo "Drupal gebootstrapt.\n";

// ─── Consumer starten ─────────────────────────────────────────────────────────

use Drupal\hello_world\RabbitMQ\Consumer\UserConfirmedConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\UserUpdateConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\UserDeactivatedConsumer;

if ($type === 'confirmed') {
  echo "UserConfirmedConsumer starten...\n";
  $consumer = new UserConfirmedConsumer();
  $consumer->listen('frontend.user.confirmed');
}
elseif ($type === 'updated') {
  echo "UserUpdateConsumer starten...\n";
  $consumer = new UserUpdateConsumer();
  $consumer->listen('frontend.user.updated');
}
elseif ($type === 'deactivated') {
  echo "UserDeactivatedConsumer starten...\n";
  $consumer = new UserDeactivatedConsumer();
  $consumer->listen('frontend.user.deactivated');
}
