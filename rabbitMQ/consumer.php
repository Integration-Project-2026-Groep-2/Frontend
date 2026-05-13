<?php

// Onderdruk E_DEPRECATED van php-amqplib (PHP-8.4 parameter-volgorde warnings).
// Dit zijn library-bugs, geen fouten in onze code.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/logger.php';

/**
 * /opt/drupal/consumer.php
 *
 * Gebruik:
 *   php consumer.php confirmed    → start UserConfirmedConsumer  (frontend.user.confirmed)
 *   php consumer.php updated      → start UserUpdateConsumer     (frontend.user.updated)
 *   php consumer.php deactivated  → start UserDeactivatedConsumer (frontend.user.deactivated)
 */

$type = $argv[1] ?? null;

if (!in_array($type, ['confirmed', 'updated', 'deactivated', 'session_created', 'session_updated', 'session_rescheduled', 'session_cancelled', 'session_full', 'location_created', 'location_updated', 'location_deleted'])) {
  echo "Gebruik: php consumer.php [confirmed|updated|deactivated|session_created|session_updated|session_rescheduled|session_cancelled|session_full]\n";
  ControlRoomLogger::error('frontend-consumer', 'Gebruik: php consumer.php [confirmed|updated|deactivated|session_created|session_updated|session_rescheduled|session_cancelled|session_full|location_created|location_updated|location_deleted]');
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
    ControlRoomLogger::info('frontend-consumer', 'Database bereikbaar.');
    break;
  }
  catch (\PDOException $e) {
    echo "Database nog niet klaar, wacht 5s... ({$waited}s/{$maxWait}s)\n";
    ControlRoomLogger::warn('frontend-consumer', "Database nog niet klaar, wacht 5s... ({$waited}s/{$maxWait}s)");
    sleep(5);
    $waited += 5;
  }
}

if ($waited >= $maxWait) {
  echo "Database niet bereikbaar na {$maxWait}s. Consumer stopt.\n";
  ControlRoomLogger::fatal('frontend-consumer', "Database niet bereikbaar na {$maxWait}s. Consumer stopt.");
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
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0'); // Ook display_errors uitzetten om zeker te zijn dat ze niet in de stdout verschijnen.

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
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\Session\\SessionCreatedConsumer' => 'RabbitMQ/Consumer/Session/SessionCreatedConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\Session\\SessionUpdatedConsumer' => 'RabbitMQ/Consumer/Session/SessionUpdatedConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\Session\\SessionReschedueledConsumer' => 'RabbitMQ/Consumer/Session/SessionReschedueledConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\Session\\SessionCancelledConsumer' => 'RabbitMQ/Consumer/Session/SessionCancelledConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\Session\\SessionFullConsumer' => 'RabbitMQ/Consumer/Session/SessionFullConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\Location\\LocationCreatedConsumer' => 'RabbitMQ/Consumer/Location/LocationCreatedConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\Location\\LocationDeletedConsumer' => 'RabbitMQ/Consumer/Location/LocationDeletedConsumer.php',
  'Drupal\\hello_world\\RabbitMQ\\Consumer\\Location\\LocationUpdatedConsumer' => 'RabbitMQ/Consumer/Location/LocationUpdatedConsumer.php',

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
    ControlRoomLogger::error('frontend-consumer', "[FOUT] hello_world module-directory niet gevonden in {$_consumerClasses[$_fqcn]}. Consumer stopt.");
    exit(1);
  }
  $_path = $_moduleBase . $_rel;
  if (!file_exists($_path)) {
    echo "[FOUT] Klasse-bestand niet gevonden: {$_path}\n";
    ControlRoomLogger::error('frontend-consumer', "[FOUT] Klasse-bestand niet gevonden: {$_path}");
    exit(1);
  }
  require_once $_path;
}
unset($_consumerClasses, $_infoFiles, $_moduleBase, $_fqcn, $_rel, $_path);

echo "Drupal gebootstrapt.\n";
ControlRoomLogger::info('frontend-consumer', 'Drupal gebootstrapt.');

// ─── Consumer starten ─────────────────────────────────────────────────────────

use Drupal\hello_world\RabbitMQ\Consumer\UserConfirmedConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\UserUpdateConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\UserDeactivatedConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\Session\SessionCreatedConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\Session\SessionUpdatedConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\Session\SessionRescheduledConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\Session\SessionCancelledConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\Session\SessionFullConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\Location\LocationCreatedConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\Location\LocationUpdatedConsumer;
use Drupal\hello_world\RabbitMQ\Consumer\Location\LocationDeletedConsumer;

// ─── Consumer starten ─────────────────────────────────────────────────────────

switch ($type) {
    case 'confirmed':
        echo "UserConfirmedConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'UserConfirmedConsumer starten...');
        $consumer = new UserConfirmedConsumer();
        $consumer->listen('frontend.user.confirmed');
        break;

    case 'updated':
        echo "UserUpdateConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'UserUpdateConsumer starten...');
        $consumer = new UserUpdateConsumer();
        $consumer->listen('frontend.user.updated');
        break;

    case 'deactivated':
        echo "UserDeactivatedConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'UserDeactivatedConsumer starten...');
        $consumer = new UserDeactivatedConsumer();
        $consumer->listen('frontend.user.deactivated');
        break;

    case 'session_created':
        echo "SessionCreatedConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'SessionCreatedConsumer starten...');
        $consumer = new SessionCreatedConsumer();
        $consumer->listen('planning.session.created');
        break;

    case 'session_updated':
        echo "SessionUpdatedConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'SessionUpdatedConsumer starten...');
        $consumer = new SessionUpdatedConsumer();
        $consumer->listen('planning.session.updated');
        break;

    case 'session_rescheduled':
        echo "SessionReschedueledConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'SessionReschedueledConsumer starten...');
        $consumer = new SessionRescheduledConsumer();
        $consumer->listen('planning.session.rescheduled');
        break;

    case 'session_cancelled':
        echo "SessionCancelledConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'SessionCancelledConsumer starten...');
        $consumer = new SessionCancelledConsumer();
        $consumer->listen('planning.session.cancelled');
        break;

    case 'session_full':
        echo "SessionFullConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'SessionFullConsumer starten...');
        $consumer = new SessionFullConsumer();
        $consumer->listen('planning.session.full');
        break;
    case 'location_created':
        echo "LocationCreatedConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'LocationCreatedConsumer starten...');
        $consumer = new LocationCreatedConsumer();
        $consumer->listen('planning.location.created');
        break;

    case 'location_updated':
        echo "LocationUpdatedConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'LocationUpdatedConsumer starten...');
        $consumer = new LocationUpdatedConsumer();
        $consumer->listen('planning.location.updated');
        break;

    case 'location_deleted':
        echo "LocationDeletedConsumer starten...\n";
        ControlRoomLogger::info('frontend-consumer', 'LocationDeletedConsumer starten...');
        $consumer = new LocationDeletedConsumer();
        $consumer->listen('planning.location.deleted');
        break;

    default:
        // Dit zou technisch niet bereikbaar moeten zijn door de in_array check bovenaan,
        // maar het is goede gewoonte om een default te hebben.
        echo "Onbekend type: {$type}\n";
        exit(1);
}