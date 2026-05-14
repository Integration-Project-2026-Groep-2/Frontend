# Control Room Logger Documentatie — Frontend

## Inhoudsopgave

1. [Overzicht](#1-overzicht)
2. [Hoe de logging werkt](#2-hoe-de-logging-werkt)
3. [RabbitMQ contract met Control Room](#3-rabbitmq-contract-met-control-room)
4. [Nieuw aangemaakt bestand: ControlRoomLoggerService](#4-nieuw-aangemaakt-bestand-controlroomloggerservice)
5. [Nieuw aangemaakt bestand: hello_world.services.yml](#5-nieuw-aangemaakt-bestand-hello_worldservicesyml)
6. [Gewijzigde bestanden en wat er gelogd wordt](#6-gewijzigde-bestanden-en-wat-er-gelogd-wordt)
7. [Hoe de service gebruiken in nieuwe code](#7-hoe-de-service-gebruiken-in-nieuwe-code)
8. [Verschil met de standalone logger](#8-verschil-met-de-standalone-logger)
9. [Overzicht van alle log events](#9-overzicht-van-alle-log-events)

---

## 1. Overzicht

De Frontend module logt acties naar de **Control Room** via RabbitMQ. De Control Room
is een aparte microservice (Go + Elasticsearch + Kibana) die log events van alle teams
ontvangt, opslaat en visualiseert.

**Wat er nieuw is:**
- `ControlRoomLoggerService` — Drupal service die XML log events stuurt via RabbitMQ
- `hello_world.services.yml` — registreert de service in Drupal's container
- Logging toegevoegd in 4 forms: `RegistratieForm`, `CancelAccountConfirmForm`,
  `SessionCreateForm`, `SessionEditForm`

**Principe: soft-fail**
Als RabbitMQ niet bereikbaar is, wordt de fout enkel in Drupal's eigen logger gezet.
De gebruikersactie (registratie, inschrijving, sessie aanmaken) wordt **nooit geblokkeerd**
door een logger-fout.

---

## 2. Hoe de logging werkt

```
Drupal Form/Controller
        │
        │  $this->crLogger()->info('frontend-sessie', 'Sessie aangemaakt: ...')
        ▼
ControlRoomLoggerService::log()
        │
        │  Bouwt XML: <LogEvent>...</LogEvent>
        │  Verbindt met RabbitMQ
        │  Publiceert naar exchange: logs.direct
        │  Routing key: routing.log
        ▼
RabbitMQ broker
        │
        │  Bezorgt in queue: controlroom.logs.queue
        ▼
Control Room (Go service)
        │
        │  Valideert XML, slaat op in Elasticsearch
        ▼
Kibana dashboard (visualisatie)
```

---

## 3. RabbitMQ contract met Control Room

### Configuratie (geverifieerd in Control Room repo)

| Onderdeel | Waarde |
|-----------|--------|
| Exchange | `logs.direct` |
| Exchange type | `direct` |
| Routing key | `routing.log` |
| Queue (Control Room) | `controlroom.logs.queue` |
| Dead Letter Queue | `controlroom.logs.queue.dlq` |
| Content type | `application/xml` |
| Delivery mode | persistent |

### XML formaat

```xml
<?xml version="1.0" encoding="UTF-8"?>
<LogEvent>
  <level>INFO</level>
  <timestamp>2026-05-13T14:32:01</timestamp>
  <service>frontend-registratie</service>
  <data>Nieuw account aangemaakt: jan@example.com (bezoeker, rol: visitor)</data>
</LogEvent>
```

### Geldige log levels

| Level | Wanneer gebruiken |
|-------|------------------|
| `DEBUG` | Ontwikkeling/troubleshooting |
| `INFO` | Succesvolle acties (registratie, sessie aanmaken) |
| `WARN` | Iets ging niet ideaal maar is niet kritiek |
| `ERROR` | Actie mislukt (RabbitMQ fout, database fout) |
| `FATAL` | Service kan niet starten |
| `PANIC` | Onherstelbare fout |

### Service namen (prefix `frontend-`)

| Service naam | Gebruikt in |
|-------------|-------------|
| `frontend-registratie` | `RegistratieForm` |
| `frontend-account` | `CancelAccountConfirmForm` |
| `frontend-session` | `SessionCreateForm`, `SessionEditForm` |
| `frontend-consumer` | `rabbitMQ/consumer.php` (bestaand) |
| `frontend-setup` | `rabbitMQ/setup.php` (bestaand) |
| `frontend-heartbeat` | `rabbitMQ/heartbeat.php` (bestaand) |

---

## 4. Nieuw aangemaakt bestand: ControlRoomLoggerService

**Pad:** `modules/custom/module/src/Service/ControlRoomLoggerService.php`

**Namespace:** `Drupal\hello_world\Service`

**Service naam in Drupal:** `hello_world.controlroom_logger`

### Methodes

```php
$logger->log(string $level, string $service, string $data): void
$logger->debug(string $service, string $data): void
$logger->info(string $service, string $data): void
$logger->warn(string $service, string $data): void
$logger->error(string $service, string $data): void
$logger->fatal(string $service, string $data): void
$logger->panic(string $service, string $data): void
```

### Parameters

| Parameter | Type | Beschrijving |
|-----------|------|-------------|
| `$level` | string | Log level: DEBUG, INFO, WARN, ERROR, FATAL, PANIC |
| `$service` | string | Naam van de service (bijv. `frontend-registratie`) |
| `$data` | string | De log boodschap (vrije tekst) |

### Werking intern

1. Valideert het log level (onbekend level → INFO)
2. Opent een AMQP verbinding met timeout van 1 seconde
3. Declareert de `logs.direct` exchange (idempotent)
4. Bouwt de XML string
5. Publiceert het bericht persistent
6. Sluit verbinding
7. Bij elke fout: logt in Drupal's eigen logger als `warning`, gooit nooit een exception

### Omgevingsvariabelen

De service leest de RabbitMQ verbinding uit `$_ENV`:

| Variabele | Default | Beschrijving |
|-----------|---------|-------------|
| `RABBITMQ_HOST` | `rabbitmq` | Hostname van de broker |
| `RABBITMQ_PORT` | `5672` | Poort |
| `RABBITMQ_USER` | `guest` | Gebruikersnaam |
| `RABBITMQ_PASS` | `guest` | Wachtwoord |

### Volledige code

```php
<?php

namespace Drupal\hello_world\Service;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class ControlRoomLoggerService {

  private const EXCHANGE    = 'logs.direct';
  private const ROUTING_KEY = 'routing.log';
  private const VALID_LEVELS = ['DEBUG', 'INFO', 'WARN', 'ERROR', 'FATAL', 'PANIC'];

  public function log(string $level, string $service, string $data): void {
    $level = strtoupper($level);
    if (!in_array($level, self::VALID_LEVELS, TRUE)) {
      $level = 'INFO';
    }

    try {
      $connection = new AMQPStreamConnection(
        $_ENV['RABBITMQ_HOST'] ?? 'rabbitmq',
        (int) ($_ENV['RABBITMQ_PORT'] ?? 5672),
        $_ENV['RABBITMQ_USER'] ?? 'guest',
        $_ENV['RABBITMQ_PASS'] ?? 'guest',
        '/', FALSE, 'AMQPLAIN', NULL, 'en_US',
        1.0,   // connection timeout
        1.0    // read/write timeout
      );
      $channel = $connection->channel();
      $channel->exchange_declare(self::EXCHANGE, 'direct', FALSE, TRUE, FALSE);

      $xml = sprintf(
        '<?xml version="1.0" encoding="UTF-8"?>' .
        '<LogEvent>' .
        '<level>%s</level>' .
        '<timestamp>%s</timestamp>' .
        '<service>%s</service>' .
        '<data>%s</data>' .
        '</LogEvent>',
        htmlspecialchars($level,   ENT_XML1, 'UTF-8'),
        date('Y-m-d\TH:i:s'),
        htmlspecialchars($service, ENT_XML1, 'UTF-8'),
        htmlspecialchars($data,    ENT_XML1, 'UTF-8')
      );

      $msg = new AMQPMessage($xml, [
        'content_type'  => 'application/xml',
        'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
      ]);

      $channel->basic_publish($msg, self::EXCHANGE, self::ROUTING_KEY);
      $channel->close();
      $connection->close();
    }
    catch (\Throwable $e) {
      \Drupal::logger('controlroom_logger')->warning(
        'ControlRoom log publish mislukt (@service): @msg',
        ['@service' => $service, '@msg' => $e->getMessage()]
      );
    }
  }

  public function debug(string $service, string $data): void { $this->log('DEBUG', $service, $data); }
  public function info(string $service, string $data): void  { $this->log('INFO',  $service, $data); }
  public function warn(string $service, string $data): void  { $this->log('WARN',  $service, $data); }
  public function error(string $service, string $data): void { $this->log('ERROR', $service, $data); }
  public function fatal(string $service, string $data): void { $this->log('FATAL', $service, $data); }
  public function panic(string $service, string $data): void { $this->log('PANIC', $service, $data); }
}
```

---

## 5. Nieuw aangemaakt bestand: hello_world.services.yml

**Pad:** `modules/custom/module/hello_world.services.yml`

```yaml
services:
  hello_world.controlroom_logger:
    class: Drupal\hello_world\Service\ControlRoomLoggerService
```

Dit registreert de service in Drupal's dependency injection container.
Na een `drush cr` (cache rebuild) is de service beschikbaar via:

```php
\Drupal::service('hello_world.controlroom_logger')
```

---

## 6. Gewijzigde bestanden en wat er gelogd wordt

### 6.1 RegistratieForm

**Pad:** `modules/custom/shift_bezoeker/src/Form/RegistratieForm.php`

| Moment | Level | Service | Bericht voorbeeld |
|--------|-------|---------|-------------------|
| Account save mislukt | `ERROR` | `frontend-registratie` | `Registratie mislukt voor jan@example.com: SQLSTATE[...]` |
| AMQP publish mislukt | `ERROR` | `frontend-registratie` | `AMQP publish mislukt voor bezoeker registratie (jan@example.com): Connection refused` |
| Registratie geslaagd | `INFO` | `frontend-registratie` | `Nieuw account aangemaakt: jan@example.com (bezoeker, rol: visitor)` |

**Toegevoegde code:**

```php
// Bij account save fout:
$this->crLogger()->error('frontend-registratie', sprintf(
    'Registratie mislukt voor %s: %s', $values['email'] ?? '?', $e->getMessage()
));

// Bij succesvolle registratie:
$this->crLogger()->info('frontend-registratie', sprintf(
    'Nieuw account aangemaakt: %s (%s, rol: %s)',
    $values['email'], $type, self::mapRole($values)
));

// Bij AMQP fout:
$this->crLogger()->error('frontend-registratie', sprintf(
    'AMQP publish mislukt voor %s registratie (%s): %s',
    $values['registratie_type'] ?? '?',
    $values['email'] ?? '?',
    $e->getMessage()
));
```

---

### 6.2 CancelAccountConfirmForm

**Pad:** `modules/custom/shift_bezoeker/src/Form/CancelAccountConfirmForm.php`

| Moment | Level | Service | Bericht voorbeeld |
|--------|-------|---------|-------------------|
| Account geannuleerd | `INFO` | `frontend-account` | `Account geannuleerd: jan@example.com (uid: 42)` |
| AMQP publish mislukt | `ERROR` | `frontend-account` | `AMQP publish mislukt voor account annulering (jan@example.com): ...` |

**Toegevoegde code:**

```php
// Na account blokkering:
$this->crLogger()->info('frontend-account', sprintf(
    'Account geannuleerd: %s (uid: %d)', $email, $uid
));

// Bij AMQP fout:
$this->crLogger()->error('frontend-account', sprintf(
    'AMQP publish mislukt voor account annulering (%s): %s', $email, $e->getMessage()
));
```

---

### 6.3 SessionCreateForm

**Pad:** `modules/custom/Session_Management/src/Form/SessionCreateForm.php`

| Moment | Level | Service | Bericht voorbeeld |
|--------|-------|---------|-------------------|
| Sessie aangemaakt | `INFO` | `frontend-session` | `Sessie aangemaakt: "PHP Workshop" op 2026-05-15 09:00:00–11:00:00 (capacity: 50)` |
| RabbitMQ fout | `ERROR` | `frontend-session` | `Sessie aanmaken mislukt ("PHP Workshop"): Connection refused` |

**Toegevoegde code:**

```php
// Bij succesvolle publish:
$this->crLogger()->info('frontend-session', sprintf(
    'Sessie aangemaakt: "%s" op %s %s–%s (capacity: %d)',
    $title, $date, $startTime, $endTime,
    (int) $form_state->getValue('capacity')
));

// Bij RabbitMQ fout:
$this->crLogger()->error('frontend-session', sprintf(
    'Sessie aanmaken mislukt ("%s"): %s', $title, $e->getMessage()
));
```

---

### 6.4 SessionEditForm

**Pad:** `modules/custom/Session_Management/src/Form/SessionEditForm.php`

| Moment | Level | Service | Bericht voorbeeld |
|--------|-------|---------|-------------------|
| Sessie gewijzigd | `INFO` | `frontend-session` | `Sessie gewijzigd: "PHP Workshop" (id: abc-123) → 2026-05-15 10:00:00–12:00:00` |
| RabbitMQ fout | `ERROR` | `frontend-session` | `Sessie wijzigen mislukt ("PHP Workshop", id: abc-123): ...` |

**Toegevoegde code:**

```php
// Bij succesvolle publish:
$this->crLogger()->info('frontend-session', sprintf(
    'Sessie gewijzigd: "%s" (id: %s) → %s %s–%s',
    $title, $sessionId, $date, $startTime, $endTime
));

// Bij RabbitMQ fout:
$this->crLogger()->error('frontend-session', sprintf(
    'Sessie wijzigen mislukt ("%s", id: %s): %s', $title, $sessionId, $e->getMessage()
));
```

---

## 7. Hoe de service gebruiken in nieuwe code

### Stap 1 — Import toevoegen

```php
use Drupal\hello_world\Service\ControlRoomLoggerService;
```

### Stap 2 — Helper methode toevoegen in de klasse

```php
private function crLogger(): ControlRoomLoggerService {
    return \Drupal::service('hello_world.controlroom_logger');
}
```

### Stap 3 — Aanroepen

```php
// Succesvolle actie:
$this->crLogger()->info('frontend-mijn-module', 'Beschrijving van wat er gebeurd is');

// Fout:
$this->crLogger()->error('frontend-mijn-module', 'Fout: ' . $e->getMessage());

// Waarschuwing:
$this->crLogger()->warn('frontend-mijn-module', 'Iets is niet ideaal gegaan');
```

### Naamgeving service parameter

Gebruik altijd het prefix `frontend-` gevolgd door een beschrijvende naam:

```
frontend-registratie   → registratie flow
frontend-account       → account beheer
frontend-session       → sessie beheer
frontend-inschrijving  → inschrijvingen
```

---

## 8. Verschil met de standalone logger

Er bestond al een `ControlRoomLogger` klasse in `rabbitMQ/logger.php`.
Die klasse is enkel bruikbaar in de standalone PHP scripts (`consumer.php`,
`setup.php`, `heartbeat.php`) die buiten Drupal draaien.

| Aspect | `rabbitMQ/logger.php` | `ControlRoomLoggerService` |
|--------|----------------------|--------------------------|
| Type | Standalone PHP klasse | Drupal service |
| Gebruikt in | `consumer.php`, `setup.php`, `heartbeat.php` | Forms, Controllers, Services |
| Aanroepen | `ControlRoomLogger::info(...)` (static) | `\Drupal::service(...)->info(...)` |
| Drupal beschikbaar | Nee | Ja |
| RabbitMQ contract | Identiek | Identiek |
| Exchange | `logs.direct` | `logs.direct` |
| Routing key | `routing.log` | `routing.log` |
| XML formaat | Identiek | Identiek |

Beide klassen sturen **hetzelfde XML formaat** naar **dezelfde exchange** —
de Control Room ontvangt ze dus op dezelfde manier.

---

## 9. Overzicht van alle log events

Volledig overzicht van alle log events die de Frontend naar de Control Room stuurt:

### Bestaand (standalone scripts)

| Script | Level | Service | Wanneer |
|--------|-------|---------|---------|
| `setup.php` | INFO | `frontend-setup` | RabbitMQ bereikbaar |
| `setup.php` | WARN | `frontend-setup` | RabbitMQ nog niet klaar |
| `setup.php` | FATAL | `frontend-setup` | RabbitMQ niet bereikbaar na timeout |
| `setup.php` | INFO | `frontend-setup` | Exchange en queues gedeclareerd |
| `consumer.php` | INFO | `frontend-consumer` | Database bereikbaar |
| `consumer.php` | WARN | `frontend-consumer` | Database nog niet klaar |
| `consumer.php` | FATAL | `frontend-consumer` | Database niet bereikbaar na timeout |
| `consumer.php` | INFO | `frontend-consumer` | Drupal gebootstrapt |
| `consumer.php` | INFO | `frontend-consumer` | Consumer gestart |
| `heartbeat.php` | INFO | `frontend-heartbeat` | Heartbeat gestart |
| `heartbeat.php` | WARN | `frontend-heartbeat` | RabbitMQ niet klaar |
| Alle consumers | INFO | `frontend-user-confirmed` e.a. | User verwerkt |
| Alle consumers | ERROR | `frontend-user-confirmed` e.a. | Verwerkingsfout |

### Nieuw (Drupal forms)

| Form | Level | Service | Wanneer |
|------|-------|---------|---------|
| `RegistratieForm` | INFO | `frontend-registratie` | Account succesvol aangemaakt |
| `RegistratieForm` | ERROR | `frontend-registratie` | Account save mislukt |
| `RegistratieForm` | ERROR | `frontend-registratie` | AMQP publish mislukt na registratie |
| `CancelAccountConfirmForm` | INFO | `frontend-account` | Account geannuleerd |
| `CancelAccountConfirmForm` | ERROR | `frontend-account` | AMQP publish mislukt bij annulering |
| `SessionCreateForm` | INFO | `frontend-session` | Sessie aangemaakt en verstuurd |
| `SessionCreateForm` | ERROR | `frontend-session` | RabbitMQ fout bij sessie aanmaken |
| `SessionEditForm` | INFO | `frontend-session` | Sessie gewijzigd en verstuurd |
| `SessionEditForm` | ERROR | `frontend-session` | RabbitMQ fout bij sessie wijzigen |
