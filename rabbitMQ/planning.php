<?php

require_once __DIR__ . "/vendor/autoload.php";

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

$planning_exchanges = [
    "planning.session.created",
    "planning.session.updated",
    "planning.session.cancelled",
    "planning.session.rescheduled",
    "planning.session.full",
];

// nasr: bind queues to planning exchanges
for ($i = 0; i < sizeof($planning_exchanges); $i++) {
}

class PlanningSessions
{
    public function listen_created(
        string $queueName = "planning.session.created",
    ): void {}
    public function listen_cancelled(
        string $queueName = "planning.session.cancelled",
    ): void {}
    public function listen_updated(
        string $queueName = "planning.session.created",
    ): void {}

    public function listen_reshedueled(
        string $queueName = "planning.session.reshedueled",
    ): void {}
}

while (true) {
    $maxRetries = 10;
    $retry = 0;
    while ($retry < $maxRetries) {
        try {
            $connection = new AMQPStreamConnection(
                $_ENV["RABBITMQ_HOST"],
                5672,
                $_ENV["RABBITMQ_USER"],
                $_ENV["RABBITMQ_PASS"],
            );
            break;
        } catch (\Exception $e) {
            echo "RabbitMQ not ready, retrying in 5s...\n";
            $retry++;
            sleep(5);
        }
    }
}

$channel = $connection->channel();


