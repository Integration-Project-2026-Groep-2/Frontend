<?php

namespace Drupal\rabbitmq_integration\Drush\Commands;

use Drupal\rabbitmq_integration\Service\RabbitMQConsumer;
use Drupal\rabbitmq_integration\Service\RabbitMQPublisher;
use Drupal\rabbitmq_integration\Service\EventDataService;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands for RabbitMQ Integration.
 */
final class RabbitMQCommands extends DrushCommands {

  public function __construct(
    private readonly RabbitMQConsumer $consumer,
    private readonly RabbitMQPublisher $publisher,
    private readonly EventDataService $eventDataService,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('rabbitmq_integration.consumer'),
      $container->get('rabbitmq_integration.publisher'),
      $container->get('rabbitmq_integration.event_data'),
    );
  }

  /**
   * Start the RabbitMQ consumer daemon.
   *
   * Runs a blocking loop consuming all enabled queues.
   * Run this as a background process or supervisor job.
   */
  #[CLI\Command(name: 'rabbitmq:consume', aliases: ['rmq-consume'])]
  #[CLI\Option(name: 'time-limit', description: 'Stop after N seconds. 0 = run forever.')]
  #[CLI\Usage(name: 'drush rabbitmq:consume', description: 'Start consuming (runs forever)')]
  #[CLI\Usage(name: 'drush rabbitmq:consume --time-limit=60', description: 'Consume for 60 seconds then exit')]
  public function consume(array $options = ['time-limit' => 0]): void {
    $timeLimit = (int) $options['time-limit'];

    $this->logger()->notice('Starting RabbitMQ consumer' . ($timeLimit > 0 ? " (time limit: {$timeLimit}s)" : ' (running forever)') . '...');
    $this->logger()->notice('Press Ctrl+C to stop.');

    // Register the event companies handler.
    $this->consumer->registerHandler(
      'event.companies',
      function ($msg) {
        $this->logger()->info('Received event companies message.');
        // The EventDataService handles this.
        $msg->ack();
      }
    );

    $this->consumer->registerHandler(
      'event.updates',
      [$this->eventDataService, 'handleIncomingEventUpdate']
    );

    $this->consumer->startConsuming($timeLimit);

    $this->logger()->success('Consumer stopped cleanly.');
  }

  /**
   * Publish a test message to verify the connection.
   */
  #[CLI\Command(name: 'rabbitmq:test-publish', aliases: ['rmq-test'])]
  #[CLI\Argument(name: 'routing_key', description: 'Routing key to publish to.')]
  #[CLI\Usage(name: 'drush rabbitmq:test-publish user.registered', description: 'Publish a test user registration event')]
  public function testPublish(string $routing_key = 'test.ping'): void {
    $payload = [
      'test'      => true,
      'message'   => 'Test from Drupal via Drush',
      'timestamp' => date('c'),
      'host'      => gethostname(),
    ];

    $this->logger()->notice("Publishing test message to routing key: {$routing_key}");

    $success = $this->publisher->publish($routing_key, $payload);

    if ($success) {
      $this->logger()->success('Test message published successfully!');
    }
    else {
      $this->logger()->error('Failed to publish test message.');
    }
  }

  /**
   * Fetch companies for an event via RabbitMQ RPC.
   */
  #[CLI\Command(name: 'rabbitmq:event-companies', aliases: ['rmq-companies'])]
  #[CLI\Argument(name: 'event_id', description: 'Event ID to fetch companies for.')]
  #[CLI\Option(name: 'no-cache', description: 'Bypass the cache.')]
  #[CLI\Usage(name: 'drush rabbitmq:event-companies 42', description: 'Fetch companies for event 42')]
  public function eventCompanies(int $event_id, array $options = ['no-cache' => false]): void {
    $this->logger()->notice("Fetching companies for event #{$event_id}...");

    $useCache  = !$options['no-cache'];
    $companies = $this->eventDataService->getCompaniesForEvent($event_id, $useCache);

    if (empty($companies)) {
      $this->logger()->warning('No companies found or request timed out.');
      return;
    }

    $this->logger()->success(count($companies) . ' companies found:');

    // Print a table of results.
    $rows = [];
    foreach ($companies as $company) {
      $rows[] = [
        'ID'       => $company['id'] ?? '—',
        'Name'     => $company['name'],
        'Category' => $company['category'],
        'Booth'    => $company['booth'] ?? '—',
        'Website'  => $company['website'],
      ];
    }

    $this->io()->table(array_keys($rows[0]), $rows);
  }

}
