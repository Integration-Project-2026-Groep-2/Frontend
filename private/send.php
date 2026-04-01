<?php
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

// form data
$firstName	= $_POST['firstName'];
$lastName	= $_POST['lastName'];
$email		= $_POST['email'];
$phone		= $_POST['phone'] ?? '';
$gdpr		= isset($_POST['gdprConsent']) ? 'true' : 'false';

if (filter_var($email, FILTER_VALIDATE_EMAIL) and strlen($phone)>9) {
    // generate ids
    $registrationId = uniqid();
    $sessionId = uniqid();

    // build XML
    $xml = new SimpleXMLElement('<Registration/>');

    $xml->addChild('registrationId', $registrationId);
    $xml->addChild('firstName', $firstName);
    $xml->addChild('lastName', $lastName);
    $xml->addChild('email', $email);
    $xml->addChild('sessionId', $sessionId);
    $xml->addChild('role', 'VISITOR');
    $xml->addChild('gdprConsent', $gdpr);

    if (!empty($phone)) {
        $xml->addChild('phone', $phone);
    }

    // convert to string
    $xmlString = $xml->asXML();

    // connect
    $connection = new AMQPStreamConnection($_ENV['RABBITMQ_HOST'], 5672, $_ENV['RABBITMQ_USER'], $_ENV['RABBITMQ_PASS']);
    $channel = $connection->channel();

    // declare TOPIC exchange
    $channel->exchange_declare(
        'user.exchange',   // exchange name
        'topic',           // type
        false,
        true,              // durable
        false
    );

    // create message
    $msg = new AMQPMessage(
        $xmlString,
        ['content_type' => 'text/xml', 'delivery_mode' => 2]
    );

    // publish to topic
    $channel->basic_publish(
        $msg,
        'user.exchange',
        'user.topic'   // routing key
    );

    echo "User sent to topic!";

    // close
    $channel->close();
    $connection->close();
} else {
    $registrationError = "Some of your inputs are not valid";
}