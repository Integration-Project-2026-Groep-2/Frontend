<?php

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$maxWait = 60;
$waited = 0;
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
    echo "[r3_consumer] database reachable\n";
    break;
  }
  catch (\PDOException $e) {
    echo "[r3_consumer] db not ready, waiting 5s ({$waited}s/{$maxWait}s)\n";
    sleep(5);
    $waited += 5;
  }
}
if ($waited >= $maxWait) {
  echo "[r3_consumer] database unreachable after {$maxWait}s, exiting\n";
  exit(1);
}

define('DRUPAL_ROOT', '/opt/drupal/web');
chdir(DRUPAL_ROOT);

$autoloader = require DRUPAL_ROOT . '/autoload.php';

use Drupal\Core\DrupalKernel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\HttpFoundation\Request;

$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->preHandle($request);
ini_set('display_errors', '0');

echo "[r3_consumer] drupal kernel booted\n";

$host = $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq';
$port = (int) ($_ENV['RABBITMQ_PORT'] ?? 5672);
$user = $_ENV['RABBITMQ_USER'] ?? 'guest';
$pass = $_ENV['RABBITMQ_PASS'] ?? 'guest';

$maxRetries = 10;
$attempt = 0;
$conn = null;
while ($attempt < $maxRetries) {
  try {
    $conn = new AMQPStreamConnection($host, $port, $user, $pass);
    break;
  }
  catch (\Exception $e) {
    $attempt++;
    if ($attempt >= $maxRetries) {
      echo "[r3_consumer] rabbitmq unreachable after {$maxRetries} retries: {$e->getMessage()}\n";
      exit(1);
    }
    echo "[r3_consumer] rabbitmq not ready (attempt {$attempt}/{$maxRetries}), retrying in 5s\n";
    sleep(5);
  }
}

$ch = $conn->channel();
$ch->exchange_declare('ai.events', 'topic', false, true, false);
$ch->queue_declare('frontend.ai_incidents', false, true, false, false);
$ch->queue_bind('frontend.ai_incidents', 'ai.events', 'event.incident_diagnosed');
$ch->queue_bind('frontend.ai_incidents', 'ai.events', 'event.incident_skipped');
$ch->queue_bind('frontend.ai_incidents', 'ai.events', 'event.incident_circuit_open');
$ch->basic_qos(null, 1, null);

$ingester = \Drupal::service('ai_dashboard.incident_ingester');
$logger = \Drupal::logger('ai_dashboard');

$callback = function (AMQPMessage $msg) use ($ingester, $logger): void {
  try {
    $envelope = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($envelope)) {
      throw new \InvalidArgumentException('envelope decoded to non-array');
    }
    $ingester->save($envelope);
    $msg->ack();
  }
  catch (\Throwable $e) {
    $logger->warning('r3_consumer ingest failed: @msg (routing_key=@rk)', [
      '@msg' => $e->getMessage(),
      '@rk' => $msg->getRoutingKey(),
    ]);
    $msg->nack(false, false);
  }
};

$ch->basic_consume('frontend.ai_incidents', '', false, false, false, false, $callback);

echo "[r3_consumer] listening on frontend.ai_incidents (event.incident_*)\n";

$shutdown = false;
if (function_exists('pcntl_async_signals')) {
  pcntl_async_signals(true);
  $stop = function () use (&$shutdown) { $shutdown = true; };
  pcntl_signal(SIGTERM, $stop);
  pcntl_signal(SIGINT, $stop);
}

try {
  while (!$shutdown && $ch->is_consuming()) {
    try {
      $ch->wait(null, false, 30);
    }
    catch (AMQPTimeoutException $e) {
    }
  }
}
finally {
  echo "[r3_consumer] shutting down gracefully\n";
  try { $ch->close(); } catch (\Throwable $e) {}
  try { $conn->close(); } catch (\Throwable $e) {}
}
