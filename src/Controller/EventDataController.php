<?php

namespace Drupal\rabbitmq_integration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\rabbitmq_integration\Service\EventDataService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides admin pages for browsing RabbitMQ event data.
 */
class EventDataController extends ControllerBase {

  public function __construct(
    protected EventDataService $eventDataService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('rabbitmq_integration.event_data'),
    );
  }

  /**
   * Lists companies at a given event, fetched via RabbitMQ.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   */
  public function listCompanies(Request $request): array {
    $eventId = (int) $request->query->get('event_id', 0);

    $build = [];

    // Event ID input form.
    $build['filter'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'p',
      '#value'  => $this->t('Use the query parameter <code>?event_id=123</code> to fetch companies for a specific event.'),
    ];

    if ($eventId <= 0) {
      $build['message'] = [
        '#markup' => '<p>' . $this->t('Please provide an event ID to load company data.') . '</p>',
      ];
      return $build;
    }

    $companies = $this->eventDataService->getCompaniesForEvent($eventId);

    if (empty($companies)) {
      $build['empty'] = [
        '#markup' => '<p>' . $this->t('No companies found for event @id, or the request timed out.', ['@id' => $eventId]) . '</p>',
      ];
      return $build;
    }

    $rows = [];
    foreach ($companies as $company) {
      $rows[] = [
        $company['id'] ?? '—',
        $company['name'],
        $company['category'],
        $company['booth'] ?? '—',
        [
          'data' => [
            '#markup' => !empty($company['website'])
              ? '<a href="' . htmlspecialchars($company['website']) . '" target="_blank">' . htmlspecialchars($company['website']) . '</a>'
              : '—',
          ],
        ],
        $company['contact']['email'] ?? '—',
      ];
    }

    $build['table'] = [
      '#type'    => 'table',
      '#caption' => $this->t('Companies at Event #@id (@count found)', [
        '@id'    => $eventId,
        '@count' => count($companies),
      ]),
      '#header'  => [
        $this->t('ID'),
        $this->t('Company Name'),
        $this->t('Category'),
        $this->t('Booth'),
        $this->t('Website'),
        $this->t('Contact Email'),
      ],
      '#rows'    => $rows,
      '#empty'   => $this->t('No data available.'),
    ];

    // Cache info note.
    $build['cache_note'] = [
      '#markup' => '<p><em>' . $this->t('Data is cached for 5 minutes. Append <code>&refresh=1</code> to bypass cache.') . '</em></p>',
    ];

    return $build;
  }

}
