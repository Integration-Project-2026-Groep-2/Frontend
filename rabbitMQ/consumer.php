<?php

/**
 * /opt/drupal/consumer.php
 *
 * Gebruik:
 *   php consumer.php confirmed   → start UserConfirmedConsumer
 *   php consumer.php updated     → start UserUpdateConsumer
 */

$type = $argv[1] ?? null;

if (!in_array($type, ['confirmed', 'updated'])) {
  echo "Gebruik: php consumer.php [confirmed|updated]\n";
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

echo "Drupal gebootstrapt.\n";

// ─── Consumer starten ─────────────────────────────────────────────────────────

use Drupal\hello_world\RabbitMQ\Consumer\UserConfirmedConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\UserUpdateConsumer;

if ($type === 'confirmed') {
  echo "UserConfirmedConsumer starten...\n";
  $consumer = new UserConfirmedConsumer();
  $consumer->listen('crm.user.confirmed');
}
elseif ($type === 'updated') {
  echo "UserUpdateConsumer starten...\n";
  $consumer = new UserUpdateConsumer();
  $consumer->listen('crm.user.updated');
}