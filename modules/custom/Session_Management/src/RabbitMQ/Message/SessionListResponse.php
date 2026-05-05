<?php

namespace Drupal\Session_Management\RabbitMQ\Message;

class SessionListResponse {

  private array $sessions = [];

  public function __construct(private readonly string $xmlData) {
    $this->parseSessions();
  }

  private function parseSessions(): void {
    $xml = simplexml_load_string($this->xmlData);
    
    if ($xml && isset($xml->sessions->session)) {
      foreach ($xml->sessions->session as $sessionNode) {
        $this->sessions[] = [
          'id' => (string) $sessionNode->id,
          'title' => (string) $sessionNode->title,
          'start_time' => (string) $sessionNode->startTime,
          'end_time' => (string) $sessionNode->endTime,
          'location' => (string) $sessionNode->location,
          'speaker' => (string) $sessionNode->speaker,
          'capacity' => (int) $sessionNode->capacity,
        ];
      }
    }
  }

  public function getSessions(): array {
    return $this->sessions;
  }

}