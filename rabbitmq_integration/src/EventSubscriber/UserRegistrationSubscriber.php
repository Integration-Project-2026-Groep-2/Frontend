<?php

namespace Drupal\rabbitmq_integration\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\rabbitmq_integration\Service\RabbitMQPublisher;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeEvents;
use Drupal\hook_event_dispatcher\Event\Entity\EntityInsertEvent;
use Drupal\hook_event_dispatcher\Event\Entity\EntityUpdateEvent;

/**
 * Listens to Drupal user events and forwards them to RabbitMQ.
 *
 * Triggers on:
 *   - New user registration → publishes 'user.registered'
 *   - User account update  → publishes 'user.updated' (if enabled)
 */
class UserRegistrationSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected RabbitMQPublisher $publisher,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Hook into Drupal's kernel request cycle for user inserts/updates.
    // We use hook_entity_insert / hook_entity_update equivalents.
    return [
      // These event names work with core dispatched events.
      'drupal_core.entity.insert' => ['onEntityInsert', 0],
      'drupal_core.entity.update' => ['onEntityUpdate', 0],
    ];
  }

  /**
   * Called when any entity is inserted.
   *
   * We filter for User entities only.
   */
  public function onEntityInsert($event): void {
    $entity = method_exists($event, 'getEntity') ? $event->getEntity() : null;

    if (!$entity instanceof UserInterface) {
      return;
    }

    $config = $this->configFactory->get('rabbitmq_integration.settings');
    $queues = $config->get('publish_queues') ?? [];

    if (!($queues['user_registration']['enabled'] ?? true)) {
      return;
    }

    $userData = $this->extractUserData($entity);
    $success  = $this->publisher->publishUserRegistration($userData);

    if ($success) {
      $this->loggerFactory->get('rabbitmq_integration')->info(
        'User registration event published for UID @uid (@email).',
        ['@uid' => $entity->id(), '@email' => $entity->getEmail()]
      );
    }
  }

  /**
   * Called when any entity is updated.
   */
  public function onEntityUpdate($event): void {
    $entity = method_exists($event, 'getEntity') ? $event->getEntity() : null;

    if (!$entity instanceof UserInterface) {
      return;
    }

    $config = $this->configFactory->get('rabbitmq_integration.settings');
    $queues = $config->get('publish_queues') ?? [];

    if (!($queues['user_update']['enabled'] ?? false)) {
      return;
    }

    $userData = $this->extractUserData($entity);
    $userData['changed'] = date('c');

    $this->publisher->publish('user.updated', [
      'event'     => 'user.updated',
      'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
      'source'    => 'drupal',
      'user'      => $userData,
    ]);
  }

  /**
   * Extracts a safe, serializable array of user data.
   */
  protected function extractUserData(UserInterface $user): array {
    $roles = array_values(array_filter(
      $user->getRoles(),
      fn($r) => $r !== 'authenticated'
    ));

    // Collect all profile field values.
    $extraFields = [];
    foreach ($user->getFields() as $fieldName => $field) {
      // Skip base fields and empty values.
      if (in_array($fieldName, ['uid', 'uuid', 'name', 'mail', 'pass', 'created', 'changed', 'roles'])) {
        continue;
      }
      if ($field->isEmpty()) {
        continue;
      }
      try {
        $extraFields[$fieldName] = $field->getString();
      }
      catch (\Exception $e) {
        // Some fields may not be safely serializable; skip them.
      }
    }

    return [
      'uid'          => (int) $user->id(),
      'uuid'         => $user->uuid(),
      'name'         => $user->getAccountName(),
      'email'        => $user->getEmail(),
      'created'      => date('c', $user->getCreatedTime()),
      'roles'        => $roles,
      'language'     => $user->getPreferredLangcode(),
      'status'       => (int) $user->isActive(),
      'extra_fields' => $extraFields,
    ];
  }

}
