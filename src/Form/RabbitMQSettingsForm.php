<?php

namespace Drupal\rabbitmq_integration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rabbitmq_integration\Service\RabbitMQConnectionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Admin configuration form for RabbitMQ Integration.
 *
 * Accessible at: /admin/config/services/rabbitmq
 */
class RabbitMQSettingsForm extends ConfigFormBase {

  public function __construct(
    protected RabbitMQConnectionManager $connectionManager,
    ...$args
  ) {
    parent::__construct(...$args);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->connectionManager = $container->get('rabbitmq_integration.connection_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'rabbitmq_integration_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['rabbitmq_integration.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('rabbitmq_integration.settings');

    // ── Connection ───────────────────────────────────────────────────────────
    $form['connection'] = [
      '#type'  => 'details',
      '#title' => $this->t('Connection Settings'),
      '#open'  => true,
    ];

    $form['connection']['host'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Host'),
      '#default_value' => $config->get('host') ?? 'localhost',
      '#required'      => true,
    ];

    $form['connection']['port'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Port'),
      '#default_value' => $config->get('port') ?? 5672,
      '#min'           => 1,
      '#max'           => 65535,
      '#required'      => true,
    ];

    $form['connection']['username'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Username'),
      '#default_value' => $config->get('username') ?? 'guest',
      '#required'      => true,
    ];

    $form['connection']['password'] = [
      '#type'          => 'password',
      '#title'         => $this->t('Password'),
      '#description'   => $this->t('Leave blank to keep the existing password.'),
      '#default_value' => '',
    ];

    $form['connection']['vhost'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Virtual Host'),
      '#default_value' => $config->get('vhost') ?? '/',
      '#required'      => true,
    ];

    $form['connection']['ssl_enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable SSL/TLS'),
      '#default_value' => $config->get('ssl_enabled') ?? false,
    ];

    $form['connection']['connection_timeout'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Connection Timeout (seconds)'),
      '#default_value' => $config->get('connection_timeout') ?? 3.0,
      '#step'          => 0.5,
      '#min'           => 0.5,
    ];

    // ── Exchange ─────────────────────────────────────────────────────────────
    $form['exchange'] = [
      '#type'  => 'details',
      '#title' => $this->t('Exchange Settings'),
      '#open'  => false,
    ];

    $form['exchange']['exchange_name'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Exchange Name'),
      '#default_value' => $config->get('exchange_name') ?? 'drupal_exchange',
      '#required'      => true,
    ];

    $form['exchange']['exchange_type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Exchange Type'),
      '#options'       => [
        'topic'   => 'Topic (recommended — supports wildcards)',
        'direct'  => 'Direct',
        'fanout'  => 'Fanout',
        'headers' => 'Headers',
      ],
      '#default_value' => $config->get('exchange_type') ?? 'topic',
    ];

    $form['exchange']['exchange_durable'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Durable Exchange (survives broker restart)'),
      '#default_value' => $config->get('exchange_durable') ?? true,
    ];

    // ── Publish Queues ────────────────────────────────────────────────────────
    $form['publishing'] = [
      '#type'  => 'details',
      '#title' => $this->t('Publishing (Outgoing)'),
      '#open'  => true,
    ];

    $publishQueues = $config->get('publish_queues') ?? [];

    $form['publishing']['user_registration_enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Publish on user registration'),
      '#description'   => $this->t('Send a message to RabbitMQ whenever a new user registers on this site.'),
      '#default_value' => $publishQueues['user_registration']['enabled'] ?? true,
    ];

    $form['publishing']['user_update_enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Publish on user account update'),
      '#description'   => $this->t('Send a message to RabbitMQ whenever a user updates their account.'),
      '#default_value' => $publishQueues['user_update']['enabled'] ?? false,
    ];

    // ── Consume Queues ────────────────────────────────────────────────────────
    $form['consuming'] = [
      '#type'  => 'details',
      '#title' => $this->t('Consuming (Incoming from other apps)'),
      '#open'  => true,
    ];

    $consumeQueues = $config->get('consume_queues') ?? [];

    $form['consuming']['event_companies_enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable event companies queue'),
      '#description'   => $this->t('Receive company data for events from external applications.'),
      '#default_value' => $consumeQueues['event_companies']['enabled'] ?? true,
    ];

    $form['consuming']['event_companies_queue'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Event Companies Queue Name'),
      '#default_value' => $consumeQueues['event_companies']['queue_name'] ?? 'event.companies',
      '#states'        => [
        'visible' => [':input[name="event_companies_enabled"]' => ['checked' => true]],
      ],
    ];

    $form['consuming']['event_updates_enabled'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Enable event updates queue'),
      '#description'   => $this->t('Receive real-time updates when events are created or modified externally.'),
      '#default_value' => $consumeQueues['event_updates']['enabled'] ?? false,
    ];

    // ── Test Connection ────────────────────────────────────────────────────────
    $form['test'] = [
      '#type'  => 'details',
      '#title' => $this->t('Test Connection'),
      '#open'  => false,
    ];

    $form['test']['test_connection'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Test Connection Now'),
      '#submit' => ['::testConnection'],
      '#limit_validation_errors' => [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('rabbitmq_integration.settings');

    $config->set('host', $form_state->getValue('host'));
    $config->set('port', (int) $form_state->getValue('port'));
    $config->set('username', $form_state->getValue('username'));
    $config->set('vhost', $form_state->getValue('vhost'));
    $config->set('ssl_enabled', (bool) $form_state->getValue('ssl_enabled'));
    $config->set('connection_timeout', (float) $form_state->getValue('connection_timeout'));
    $config->set('exchange_name', $form_state->getValue('exchange_name'));
    $config->set('exchange_type', $form_state->getValue('exchange_type'));
    $config->set('exchange_durable', (bool) $form_state->getValue('exchange_durable'));

    // Only update password if a new one was provided.
    $newPassword = $form_state->getValue('password');
    if (!empty($newPassword)) {
      $config->set('password', $newPassword);
    }

    // Update publish queues.
    $publishQueues = $config->get('publish_queues') ?? [];
    $publishQueues['user_registration']['enabled'] = (bool) $form_state->getValue('user_registration_enabled');
    $publishQueues['user_update']['enabled']        = (bool) $form_state->getValue('user_update_enabled');
    $config->set('publish_queues', $publishQueues);

    // Update consume queues.
    $consumeQueues = $config->get('consume_queues') ?? [];
    $consumeQueues['event_companies']['enabled']    = (bool) $form_state->getValue('event_companies_enabled');
    $consumeQueues['event_companies']['queue_name'] = $form_state->getValue('event_companies_queue');
    $consumeQueues['event_updates']['enabled']      = (bool) $form_state->getValue('event_updates_enabled');
    $config->set('consume_queues', $consumeQueues);

    $config->save();

    // Reset connection so next request uses the new config.
    $this->connectionManager->closeConnection();

    parent::submitForm($form, $form_state);
  }

  /**
   * Tests the RabbitMQ connection and displays a status message.
   */
  public function testConnection(array &$form, FormStateInterface $form_state): void {
    try {
      $this->connectionManager->closeConnection(); // Force a fresh attempt.
      $connection = $this->connectionManager->getConnection();

      if ($connection->isConnected()) {
        $this->messenger()->addStatus($this->t('✅ Successfully connected to RabbitMQ!'));
      }
      else {
        $this->messenger()->addError($this->t('❌ Connection object created but reports as not connected.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('❌ Connection failed: @msg', ['@msg' => $e->getMessage()]));
    }
  }

}
