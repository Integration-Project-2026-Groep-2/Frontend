<?php

namespace Drupal\rabbitmq_integration\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * High-level service for fetching business data from external apps via RabbitMQ.
 *
 * Currently supports:
 *  - getCompaniesForEvent(): get all companies present at a given event.
 *  - processIncomingEventData(): handle incoming event data pushed by other apps.
 */
class EventDataService {

  /**
   * Cache lifetime in seconds (5 minutes).
   */
  const CACHE_TTL = 300;

  /**
   * Reply queue name for RPC responses.
   */
  const REPLY_QUEUE = 'drupal.event.reply';

  public function __construct(
    protected RabbitMQPublisher $publisher,
    protected RabbitMQConsumer $consumer,
    protected CacheBackendInterface $cache,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Returns a list of companies attending a given event.
   *
   * Uses RPC pattern over RabbitMQ:
   *   1. Publish a request to 'event.companies.request'.
   *   2. Wait for reply on our REPLY_QUEUE.
   *   3. Cache the result to avoid hammering the queue.
   *
   * @param int   $eventId  The event ID to look up.
   * @param bool  $useCache Whether to use cached results.
   * @param float $timeout  How long to wait for the reply (seconds).
   *
   * @return array  Array of company data arrays, or empty array on failure.
   */
  public function getCompaniesForEvent(int $eventId, bool $useCache = true, float $timeout = 5.0): array {
    $cacheKey = 'rabbitmq_integration:event_companies:' . $eventId;

    // Try cache first.
    if ($useCache) {
      $cached = $this->cache->get($cacheKey);
      if ($cached !== false) {
        return $cached->data;
      }
    }

    // Publish a request and wait for the reply.
    $correlationId = $this->publisher->requestEventCompanies($eventId, self::REPLY_QUEUE);

    $reply = $this->consumer->waitForRpcReply(self::REPLY_QUEUE, $correlationId, $timeout);

    if ($reply === null) {
      $this->loggerFactory->get('rabbitmq_integration')->warning(
        'Timeout waiting for companies for event @id (correlation: @cid).',
        ['@id' => $eventId, '@cid' => $correlationId]
      );
      return [];
    }

    $companies = $reply['companies'] ?? [];

    // Normalize each company to a consistent structure.
    $companies = array_map([$this, 'normalizeCompany'], $companies);

    // Cache the result.
    $this->cache->set($cacheKey, $companies, time() + self::CACHE_TTL);

    return $companies;
  }

  /**
   * Processes an incoming event update message pushed by an external app.
   *
   * This is called by the consumer handler when a message arrives
   * on the 'event.updates' queue.
   *
   * @param AMQPMessage $message
   */
  public function handleIncomingEventUpdate(AMQPMessage $message): void {
    try {
      $payload = json_decode($message->getBody(), true);

      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \InvalidArgumentException('Invalid JSON payload.');
      }

      $action  = $payload['action'] ?? 'unknown';
      $eventId = $payload['event_id'] ?? null;

      $this->loggerFactory->get('rabbitmq_integration')->info(
        'Received event update: action "@action", event ID @id.',
        ['@action' => $action, '@id' => $eventId ?? 'N/A']
      );

      // Invalidate cache for this event so fresh data is fetched next time.
      if ($eventId !== null) {
        $cacheKey = 'rabbitmq_integration:event_companies:' . $eventId;
        $this->cache->delete($cacheKey);
      }

      // Dispatch to specific action handlers.
      match ($action) {
        'companies_updated' => $this->handleCompaniesUpdated($payload),
        'event_cancelled'   => $this->handleEventCancelled($payload),
        'event_created'     => $this->handleEventCreated($payload),
        default             => $this->loggerFactory->get('rabbitmq_integration')->info(
          'No handler for event action "@action".', ['@action' => $action]
        ),
      };

      $message->ack();
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('rabbitmq_integration')->error(
        'Error processing event update message: @msg', ['@msg' => $e->getMessage()]
      );
      // nack without requeue if it's a bad message; requeue for transient errors.
      $isBadMessage = $e instanceof \InvalidArgumentException;
      $message->nack(!$isBadMessage);
    }
  }

  /**
   * Handles a 'companies_updated' action from an external app.
   */
  protected function handleCompaniesUpdated(array $payload): void {
    $eventId   = $payload['event_id'] ?? null;
    $companies = $payload['companies'] ?? [];

    // Update cache immediately with the pushed data (no need for RPC).
    if ($eventId !== null && !empty($companies)) {
      $cacheKey  = 'rabbitmq_integration:event_companies:' . $eventId;
      $companies = array_map([$this, 'normalizeCompany'], $companies);
      $this->cache->set($cacheKey, $companies, time() + self::CACHE_TTL);

      $this->loggerFactory->get('rabbitmq_integration')->info(
        'Cached @count companies for event @id.',
        ['@count' => count($companies), '@id' => $eventId]
      );
    }
  }

  /**
   * Handles a 'event_cancelled' action.
   */
  protected function handleEventCancelled(array $payload): void {
    $eventId = $payload['event_id'] ?? null;
    if ($eventId !== null) {
      $this->cache->delete('rabbitmq_integration:event_companies:' . $eventId);
      // TODO: notify Drupal editors, update nodes, etc.
    }
  }

  /**
   * Handles a 'event_created' action.
   */
  protected function handleEventCreated(array $payload): void {
    // TODO: optionally auto-create a Drupal node for the new event.
    $this->loggerFactory->get('rabbitmq_integration')->info(
      'New event created externally: @title (ID: @id).',
      [
        '@title' => $payload['title'] ?? 'Unknown',
        '@id'    => $payload['event_id'] ?? 'N/A',
      ]
    );
  }

  /**
   * Normalizes a raw company array from external apps to a consistent shape.
   *
   * @param array $raw  Raw company data from the external app.
   * @return array      Normalized company data.
   */
  protected function normalizeCompany(array $raw): array {
    return [
      'id'          => $raw['id'] ?? $raw['company_id'] ?? null,
      'name'        => $raw['name'] ?? $raw['company_name'] ?? 'Unknown',
      'description' => $raw['description'] ?? $raw['desc'] ?? '',
      'website'     => $raw['website'] ?? $raw['url'] ?? '',
      'logo_url'    => $raw['logo_url'] ?? $raw['logo'] ?? '',
      'booth'       => $raw['booth'] ?? $raw['booth_number'] ?? null,
      'category'    => $raw['category'] ?? $raw['type'] ?? '',
      'contact'     => [
        'email' => $raw['contact_email'] ?? $raw['email'] ?? '',
        'phone' => $raw['contact_phone'] ?? $raw['phone'] ?? '',
      ],
      '_raw'        => $raw,  // Keep original for debugging.
    ];
  }

}
