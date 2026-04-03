<?php
// /rabbitMQ/heartbeat.php

require_once __DIR__ . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$maxRetries = 10;
$retry = 0;
while ($retry < $maxRetries) {
    try {
        $connection = new AMQPStreamConnection(
            $_ENV['RABBITMQ_HOST'], 5672,
            $_ENV['RABBITMQ_USER'], $_ENV['RABBITMQ_PASS']
        );
        break;
    } catch (\Exception $e) {
        echo "RabbitMQ not ready, retrying in 5s...\n";
        $retry++;
        sleep(5);
    }
}

$channel = $connection->channel();

$channel->exchange_declare(
    'heartbeat.direct',
    'direct',
    false,  // passive
    true,   // durable
    false,  // auto-delete
    false   // internal
);

echo "Heartbeat started. Press Ctrl+C to stop.\n";

while (true) {
    $xml = new SimpleXMLElement('<Heartbeat/>');
    $xml->addChild('serviceId', 'frontend');
    $xml->addChild('timestamp', date('c'));

    $msg = new AMQPMessage(
        $xml->asXML(),
        ['content_type' => 'text/xml', 'delivery_mode' => 2]
    );

    $channel->basic_publish(
        $msg,
        'heartbeat.direct',
        'routing.heartbeat'
    );

    echo "Heartbeat sent at " . date('H:i:s') . "\n";

    sleep(1);
}

$channel->close();
$connection->close();