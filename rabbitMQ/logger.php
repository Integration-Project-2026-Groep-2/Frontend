<?php

class ControlRoomLogger
{
    private const VALID_LEVELS = ['DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL', 'PANIC'];
    private const EXCHANGE     = 'logs.direct';
    private const ROUTING_KEY  = 'routing.log';

    private static ?\PhpAmqpLib\Connection\AMQPStreamConnection $conn    = null;
    private static ?\PhpAmqpLib\Channel\AMQPChannel              $channel = null;

    public static function log(string $level, string $service, string $data): void
    {
        $level = strtoupper($level);
        if (!in_array($level, self::VALID_LEVELS, true)) {
            $level = 'INFO';
        }

        // Guard: if the autoloader has not been loaded yet (early startup phase),
        // AMQPStreamConnection is unavailable — stdout only in that case.
        if (!class_exists('\PhpAmqpLib\Connection\AMQPStreamConnection', false)) {
            return;
        }

        try {
            $ch        = self::channel();
            $timestamp = date('Y-m-d\TH:i:s');
            $xml = sprintf(
                "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
                "<LogEvent>\n  <level>%s</level>\n  <timestamp>%s</timestamp>\n" .
                "  <service>%s</service>\n  <data>%s</data>\n</LogEvent>",
                htmlspecialchars($level,   ENT_XML1, 'UTF-8'),
                $timestamp,
                htmlspecialchars($service, ENT_XML1, 'UTF-8'),
                htmlspecialchars($data,    ENT_XML1, 'UTF-8')
            );
            $msg = new \PhpAmqpLib\Message\AMQPMessage($xml, [
                'content_type'  => 'application/xml',
                'delivery_mode' => \PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ]);
            $ch->basic_publish($msg, self::EXCHANGE, self::ROUTING_KEY);
        }
        catch (\Throwable $e) {
            echo sprintf("[%s] [WARN] [%s] ControlRoom publish failed: %s\n",
                date('H:i:s'), $service, $e->getMessage());
            self::$conn    = null;
            self::$channel = null;
        }
    }

    private static function channel(): \PhpAmqpLib\Channel\AMQPChannel
    {
        if (self::$channel !== null && self::$conn !== null && self::$conn->isConnected()) {
            return self::$channel;
        }
        self::$conn = new \PhpAmqpLib\Connection\AMQPStreamConnection(
            $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
            (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
            $_ENV['RABBITMQ_USER'] ?? 'guest',
            $_ENV['RABBITMQ_PASS'] ?? 'guest',
            '/', false, 'AMQPLAIN', null, 'en_US',
            1.0,  // connection timeout — kort zodat een falende logger de consumer niet vertraagt
            1.0   // read/write timeout
        );
        self::$channel = self::$conn->channel();
        self::$channel->exchange_declare(self::EXCHANGE, 'direct', false, true, false);
        return self::$channel;
    }

    public static function debug(string $service, string $data): void { self::log('DEBUG', $service, $data); }
    public static function info (string $service, string $data): void { self::log('INFO',  $service, $data); }
    public static function warn (string $service, string $data): void { self::log('WARN',  $service, $data); }
    public static function error(string $service, string $data): void { self::log('ERROR', $service, $data); }
    public static function fatal(string $service, string $data): void { self::log('FATAL', $service, $data); }
    public static function panic(string $service, string $data): void { self::log('PANIC', $service, $data); }
}
