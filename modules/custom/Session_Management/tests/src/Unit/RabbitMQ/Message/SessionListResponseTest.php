<?php

declare(strict_types=1);

namespace Drupal\Tests\Session_Management\Unit\RabbitMQ\Message;

use Drupal\Session_Management\RabbitMQ\Message\SessionListResponse;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\Session_Management\RabbitMQ\Message\SessionListResponse
 * @group session_management
 */
class SessionListResponseTest extends UnitTestCase {

  private function xmlMetSessies(array $sessies): string {
    $sessieXml = '';
    foreach ($sessies as $s) {
      $sessieXml .= '<session>'
        . '<id>' . ($s['id'] ?? '') . '</id>'
        . '<title>' . ($s['title'] ?? '') . '</title>'
        . '<startTime>' . ($s['start_time'] ?? '') . '</startTime>'
        . '<endTime>' . ($s['end_time'] ?? '') . '</endTime>'
        . '<location>' . ($s['location'] ?? '') . '</location>'
        . '<speaker>' . ($s['speaker'] ?? '') . '</speaker>'
        . '<capacity>' . ($s['capacity'] ?? 0) . '</capacity>'
        . '</session>';
    }
    return '<?xml version="1.0" encoding="UTF-8"?><SessionListResponse><sessions>' . $sessieXml . '</sessions></SessionListResponse>';
  }

  public function testLegeXmlGeeftLegeArray(): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?><SessionListResponse><sessions></sessions></SessionListResponse>';
    $response = new SessionListResponse($xml);
    $this->assertSame([], $response->getSessions());
  }

  public function testOngeldigeXmlGeeftLegeArray(): void {
    $response = new SessionListResponse('dit is geen xml!!!');
    $this->assertSame([], $response->getSessions());
  }

  public function testParseerEenSessie(): void {
    $xml = $this->xmlMetSessies([[
      'id' => 'sess-1',
      'title' => 'PHP workshop',
      'start_time' => '2026-05-15T09:00:00',
      'end_time' => '2026-05-15T11:00:00',
      'location' => 'Zaal A',
      'speaker' => 'Jan Janssen',
      'capacity' => 50,
    ]]);

    $response = new SessionListResponse($xml);
    $sessies = $response->getSessions();

    $this->assertCount(1, $sessies);
    $this->assertSame('sess-1', $sessies[0]['id']);
    $this->assertSame('PHP workshop', $sessies[0]['title']);
    $this->assertSame('2026-05-15T09:00:00', $sessies[0]['start_time']);
    $this->assertSame('2026-05-15T11:00:00', $sessies[0]['end_time']);
    $this->assertSame('Zaal A', $sessies[0]['location']);
    $this->assertSame('Jan Janssen', $sessies[0]['speaker']);
    $this->assertSame(50, $sessies[0]['capacity']);
  }

  public function testParseerMeerdereSessies(): void {
    $xml = $this->xmlMetSessies([
      ['id' => 'sess-1', 'title' => 'Workshop A', 'capacity' => 30],
      ['id' => 'sess-2', 'title' => 'Workshop B', 'capacity' => 20],
      ['id' => 'sess-3', 'title' => 'Workshop C', 'capacity' => 15],
    ]);

    $response = new SessionListResponse($xml);
    $this->assertCount(3, $response->getSessions());
  }

  public function testCapacityWordtGecastedNaarInteger(): void {
    $xml = $this->xmlMetSessies([['id' => '1', 'capacity' => 42]]);
    $response = new SessionListResponse($xml);
    $sessies = $response->getSessions();
    $this->assertIsInt($sessies[0]['capacity']);
    $this->assertSame(42, $sessies[0]['capacity']);
  }

  public function testGetSessionsGeeftZelfdeArrayTerug(): void {
    $xml = $this->xmlMetSessies([['id' => 'sess-1', 'title' => 'Test']]);
    $response = new SessionListResponse($xml);
    $this->assertSame($response->getSessions(), $response->getSessions());
  }

}
