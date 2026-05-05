<?php

namespace Drupal\hello_world\RabbitMQ\Message;

abstract class Planning
{
    protected string $sessionId;
    protected \DateTimeImmutable $timestamp;

    public function __construct(
        string $sessionId,
        \DateTimeImmutable $timestamp,
    ) {
        $this->sessionId = $sessionId;
        $this->timestamp = $timestamp;
    }
}


