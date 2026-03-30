# RabbitMQ Integration — Drupal Module

A production-ready Drupal module for two-way RabbitMQ communication using **pure PHP** (`php-amqplib`).

---

## Features

- ✅ **Publish user registrations** to other apps automatically on `hook_user_insert`
- ✅ **Publish user updates** (optional) on `hook_user_update`
- ✅ **Consume event data** from external apps (companies at events, event updates)
- ✅ **RPC request/reply pattern** to query live data from other services
- ✅ **Admin UI** at `/admin/config/services/rabbitmq`
- ✅ **Drush commands** for running a consumer daemon
- ✅ **Caching** of consumed data (5 min TTL, invalidated on push updates)
- ✅ **Topic exchange** with wildcard routing keys

---

## Installation

### 1. Install the PHP library

```bash
composer require php-amqplib/php-amqplib:^3.6
```

### 2. Enable the module

```bash
drush en rabbitmq_integration
drush cr
```

### 3. Configure

Go to **Admin → Configuration → Services → RabbitMQ Integration**
or edit `config/install/rabbitmq_integration.settings.yml` before enabling.

---

## Architecture

```
Drupal (this module)
  │
  ├── PUBLISHES TO RabbitMQ
  │     • user.registered   ← when a new user registers
  │     • user.updated      ← when a user updates their account (optional)
  │     • event.companies.request  ← RPC request for event companies
  │
  └── CONSUMES FROM RabbitMQ
        • event.companies   ← companies attending an event (RPC reply or push)
        • event.updates     ← real-time event created/updated/cancelled
```

### Exchange

All messages flow through a single **topic exchange** (`drupal_exchange` by default).  
Topic exchanges support wildcard routing keys:
- `*` matches exactly one word
- `#` matches zero or more words

Example: `event.companies.#` matches `event.companies.42`, `event.companies.reply`, etc.

---

## Services

### `rabbitmq_integration.publisher`

```php
// Publish any message
\Drupal::service('rabbitmq_integration.publisher')
  ->publish('my.routing.key', ['key' => 'value']);

// Publish a user registration specifically
\Drupal::service('rabbitmq_integration.publisher')
  ->publishUserRegistration($userData);

// Request event companies (RPC)
$correlationId = \Drupal::service('rabbitmq_integration.publisher')
  ->requestEventCompanies(42, 'drupal.reply.queue');
```

### `rabbitmq_integration.consumer`

```php
$consumer = \Drupal::service('rabbitmq_integration.consumer');

// Register a custom handler for a queue
$consumer->registerHandler('my.queue', function(\PhpAmqpLib\Message\AMQPMessage $msg) {
  $data = json_decode($msg->getBody(), true);
  // process...
  $msg->ack();
});

// Start consuming (blocking — use in Drush command or daemon)
$consumer->startConsuming();

// Fetch a single message (non-blocking)
$msg = $consumer->getOneMessage('my.queue');
```

### `rabbitmq_integration.event_data`

```php
// Get companies for an event (fetches via RabbitMQ RPC, caches result)
$companies = \Drupal::service('rabbitmq_integration.event_data')
  ->getCompaniesForEvent(42);

foreach ($companies as $company) {
  echo $company['name'] . ' — Booth ' . $company['booth'];
}
```

---

## Drush Commands

```bash
# Start the consumer daemon (blocking, run as a background service)
drush rabbitmq:consume

# Run consumer for 60 seconds then exit (good for cron)
drush rabbitmq:consume --time-limit=60

# Publish a test message to verify the connection
drush rabbitmq:test-publish user.registered

# Fetch companies for event #42 via RabbitMQ
drush rabbitmq:event-companies 42

# Same but bypass cache
drush rabbitmq:event-companies 42 --no-cache
```

### Running as a daemon with Supervisor

```ini
[program:drupal_rabbitmq_consumer]
command=drush --root=/var/www/html rabbitmq:consume
autostart=true
autorestart=true
stderr_logfile=/var/log/supervisor/rabbitmq_consumer.err.log
stdout_logfile=/var/log/supervisor/rabbitmq_consumer.out.log
user=www-data
```

---

## Message Formats

### User Registration (outgoing)

```json
{
  "event": "user.registered",
  "timestamp": "2025-03-30T10:00:00+00:00",
  "source": "drupal",
  "user": {
    "uid": 42,
    "uuid": "abc-def-...",
    "name": "johndoe",
    "email": "john@example.com",
    "created": "2025-03-30T10:00:00+00:00",
    "roles": ["editor"],
    "language": "en",
    "status": 1,
    "extra_fields": {}
  }
}
```

### Event Companies Request (outgoing RPC)

```json
{
  "action": "get_companies",
  "event_id": 42,
  "timestamp": "2025-03-30T10:00:00+00:00"
}
```

### Event Companies Reply (expected incoming)

```json
{
  "companies": [
    {
      "id": 1,
      "name": "Acme Corp",
      "category": "Technology",
      "booth": "A-12",
      "website": "https://acme.example.com",
      "contact_email": "info@acme.example.com",
      "logo_url": "https://..."
    }
  ]
}
```

### Event Update Push (incoming)

```json
{
  "action": "companies_updated",
  "event_id": 42,
  "companies": [ ... ]
}
```

Supported `action` values: `companies_updated`, `event_cancelled`, `event_created`.

---

## File Structure

```
rabbitmq_integration/
├── composer.json
├── rabbitmq_integration.info.yml
├── rabbitmq_integration.module          ← hook_user_insert / hook_user_update
├── rabbitmq_integration.services.yml
├── rabbitmq_integration.routing.yml
├── rabbitmq_integration.permissions.yml
├── rabbitmq_integration.links.menu.yml
├── drush.services.yml
├── config/
│   └── install/
│       └── rabbitmq_integration.settings.yml
└── src/
    ├── Service/
    │   ├── RabbitMQConnectionManager.php  ← AMQP connection + channels
    │   ├── RabbitMQPublisher.php          ← publish messages
    │   ├── RabbitMQConsumer.php           ← consume messages
    │   └── EventDataService.php          ← high-level event/company logic
    ├── Form/
    │   └── RabbitMQSettingsForm.php       ← Admin UI
    ├── Controller/
    │   └── EventDataController.php        ← /admin/rabbitmq/event-companies
    ├── EventSubscriber/
    │   └── UserRegistrationSubscriber.php ← (alternative to hooks)
    └── Drush/
        └── Commands/
            └── RabbitMQCommands.php       ← drush rabbitmq:*
```
