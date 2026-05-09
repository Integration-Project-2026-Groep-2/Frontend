<?php

// Onderdruk E_DEPRECATED van php-amqplib (PHP-8.4 parameter-volgorde warnings).
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

/**
 * /opt/drupal/setup.php
 *
 * Declareert en bindt de drie Frontend user-queues op de contact.topic exchange.
 * Dit script wordt eenmalig uitgevoerd bij het opstarten van de container,
 * vóór de consumers starten.
 *
 * Queues en bindings:
 *   frontend.user.confirmed   ← crm.user.confirmed
 *   frontend.user.updated     ← crm.user.updated
 *   frontend.user.deactivated ← crm.user.deactivated
 */

$host    = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
$port    = (int) ($_ENV['RABBITMQ_PORT'] ?? 5672);
$user    = $_ENV['RABBITMQ_USER'] ?? 'guest';
$pass    = $_ENV['RABBITMQ_PASS'] ?? 'guest';

// ─── RabbitMQ bereikbaarheidcheck ─────────────────────────────────────────────
$maxWait = 60;
$waited  = 0;

echo "Wachten op RabbitMQ ({$host}:{$port})...\n";

while ($waited < $maxWait) {
  $sock = @fsockopen($host, $port, $errno, $errstr, 3);
  if ($sock) {
    fclose($sock);
    echo "RabbitMQ bereikbaar.\n";
    break;
  }
  echo "RabbitMQ nog niet klaar ({$waited}s/{$maxWait}s): {$errstr}\n";
  sleep(3);
  $waited += 3;
}

if ($waited >= $maxWait) {
  echo "RabbitMQ niet bereikbaar na {$maxWait}s. Setup mislukt.\n";
  exit(1);
}

// ─── Autoloader laden (PhpAmqpLib zit in Composer van Drupal) ─────────────────
$autoloadPaths = [
  '/opt/drupal/vendor/autoload.php',          // productie
  __DIR__ . '/../vendor/autoload.php',        // lokale fallback
];

$autoloaded = false;
foreach ($autoloadPaths as $path) {
  if (file_exists($path)) {
    require_once $path;
    $autoloaded = true;
    break;
  }
}

if (!$autoloaded) {
  echo "Composer autoloader niet gevonden. Setup mislukt.\n";
  exit(1);
}

use PhpAmqpLib\Connection\AMQPStreamConnection;

// ─── Verbinden ────────────────────────────────────────────────────────────────
try {
  $connection = new AMQPStreamConnection(
    $host, $port, $user, $pass,
    '/',        // vhost
    false,      // insist
    'AMQPLAIN', // login method
    null,       // login response
    'en_US',    // locale
    5.0,        // connection timeout
    10.0,       // read/write timeout
    null,       // context
    false,      // keepalive
    0           // heartbeat (0 = disabled voor éénmalig script)
  );
  $channel = $connection->channel();
} catch (\Exception $e) {
  echo "Verbinding met RabbitMQ mislukt: " . $e->getMessage() . "\n";
  exit(1);
}

// ─── Exchange declareren (idempotent — bestaat al) ────────────────────────────
//
// passive = false: sla het exchange opnieuw op als het er nog niet is.
// durable = true:  overleeft broker-herstart.
$channel->exchange_declare('contact.topic', 'topic', false, true, false);
echo "Exchange 'contact.topic' gedeclareerd.\n";

// ─── Queues declareren en binden ──────────────────────────────────────────────

$bindings = [
  [
    'queue'      => 'frontend.user.confirmed',
    'routingKey' => 'crm.user.confirmed',
  ],
  [
    'queue'      => 'frontend.user.updated',
    'routingKey' => 'crm.user.updated',
  ],
  [
    'queue'      => 'frontend.user.deactivated',
    'routingKey' => 'crm.user.deactivated',
  ],
];

foreach ($bindings as $binding) {
  $queue      = $binding['queue'];
  $routingKey = $binding['routingKey'];

  // queue_declare is idempotent: bestaande queue wordt niet opnieuw aangemaakt.
  // passive=false, durable=true, exclusive=false, auto_delete=false
  $channel->queue_declare($queue, false, true, false, false);

  $channel->queue_bind($queue, 'contact.topic', $routingKey);

  echo "Queue '{$queue}' gedeclareerd en gebonden aan 'contact.topic' met routing key '{$routingKey}'.\n";
}

// ─── Afsluiten ────────────────────────────────────────────────────────────────
$channel->close();
$connection->close();

echo "RabbitMQ setup voltooid.\n";
