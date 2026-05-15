# Integratie Documentatie — Frontend ↔ Planning (RabbitMQ)

## Inhoudsopgave

1. [Hoe de integratie werkt](#1-hoe-de-integratie-werkt)
2. [Architectuuroverzicht](#2-architectuuroverzicht)
3. [Wat al bestaat in de codebase](#3-wat-al-bestaat-in-de-codebase)
4. [Kritieke problemen gevonden](#4-kritieke-problemen-gevonden)
5. [Wat volledig ontbreekt](#5-wat-volledig-ontbreekt)
6. [Concreet actieplan — stap voor stap](#6-concreet-actieplan--stap-voor-stap)
7. [XSD contract referentie](#7-xsd-contract-referentie)

---

## 1. Hoe de integratie werkt

### Het principe

Drupal (Frontend) en de Planning service praten **nooit rechtstreeks** met elkaar.
Ze communiceren via RabbitMQ als tussenlaag. Elk bericht is een XML-string die
voldoet aan een XSD-schema (het officiële contract).

```
Drupal Frontend          RabbitMQ (broker)         Planning Service
      │                        │                          │
      │  XML bericht sturen    │                          │
      │──── publish() ────────>│                          │
      │                        │──── levert in queue ────>│
      │                        │                          │  verwerkt
      │                        │<─── antwoord (XML) ──────│
      │<─── consumer ──────────│                          │
      │  verwerkt antwoord     │                          │
```

### De drie sleutelbegrippen

**Exchange** — het postkantoor waar berichten naartoe gestuurd worden
- `frontend.topic` — exchange waar Drupal op publiceert (Planning luistert)
- `planning.topic` — exchange waar Planning op publiceert (Drupal moet luisteren)

**Queue** — de eigen brievenbus van elke service
- Elke service declareert zijn eigen queues bij opstart
- Een queue is gekoppeld aan een exchange via een routing key

**Routing key** — het adres op de envelop
- `frontend.session.created` → bericht van Frontend, over een sessie, aangemaakt
- `planning.session.created` → bevestiging van Planning dat sessie verwerkt is

### XML contract

Elk bericht is XML. De structuur ligt vast in XSD-schemas:
- Root element: **PascalCase** (bijv. `<SessionCreated>`)
- Velden erin: **lowerCamelCase** (bijv. `<startTime>`, `<locationId>`)

De XSD-bestanden staan in de `xsd/` map van dit project. Vóór elke publish
valideert `RabbitMQClient` het bericht tegen het XSD — een fout in het XML
gooit een `RuntimeException` en het bericht wordt nooit verstuurd.

---

## 2. Architectuuroverzicht

### Volledige flow: Admin maakt een sessie aan

```
[Admin vult SessionCreateForm in]
          │
          ▼
[SessionCreateForm::submitForm()]
  → new PlanningSessionCreatedMessage(title, date, ...)
  → RabbitMQClient::publish()
      → toXml()  →  XSD validatie (session.xsd)
      → basic_publish() naar exchange: frontend.topic
                         routing key:  frontend.session.created
          │
          ▼  (RabbitMQ bezorgt aan Planning)
[Planning::frontend.session.created consumer]
  → valideert XML
  → slaat sessie op in PostgreSQL
  → stuurt bevestiging terug
          │
          ▼  (Planning publiceert op planning.topic)
  routing key: planning.session.created
  XML: <SessionCreated> met sessionId, title, date, ...
          │
          ▼  ← [ONTBREEKT: consumer in Drupal]
[Drupal consumer verwerkt SessionCreated]
  → slaat sessie op in Drupal state/database
          │
          ▼
[BezoekerController::sessiesPage()]
  → leest sessies uit Drupal
  → toont sessie-overzicht aan bezoeker
```

### Volledige flow: Bezoeker schrijft in op sessie

```
[Bezoeker klikt "Inschrijven" op sessie]
          │
          ▼
[BezoekerController::inschrijven($session_id)]  ← [ONTBREEKT: stuurt niets]
  → moet sturen: new RegistrationCreatedMessage(sessionId, crmMasterId)
  → RabbitMQClient::publish()
  → exchange: frontend.topic, routing key: frontend.registration.created
          │
          ▼  (Planning verwerkt inschrijving)
[Planning stuurt bevestiging terug]
  routing key: planning.registration.confirmed
  XML: <RegistrationConfirmed registrationId, sessionId, crmMasterId>
          │
          ▼  ← [ONTBREEKT: consumer in Drupal]
[Drupal toont "Inschrijving bevestigd" aan bezoeker]
```

---

## 3. Wat al bestaat in de codebase

### Producers (Drupal stuurt naar Planning) — gedeeltelijk correct

| Klasse | Routing key | XSD root element | Status |
|--------|-------------|-----------------|--------|
| `PlanningSessionCreatedMessage` | `frontend.session.created` | `<SessionCreated>` | ✓ Correct (session.xsd) |
| `PlanningSessionUpdatedMessage` | `frontend.session.updated` | `<SessionUpdated>` | ✓ Correct (session.xsd) |
| `PlanningSessionCancelledMessage` | `frontend.session.cancelled` | `<SessionCancelled>` | ✓ Correct (session.xsd) |
| `PlanningSessionRescheduledMessage` | `frontend.session.rescheduled` | `<SessionRescheduled>` | ✓ Correct (session.xsd) |
| `PlanningLocationCreatedMessage` | `frontend.location.created` | `<FrontendLocationCreated>` | ✓ Correct (frontend.xsd) |
| `PlanningLocationUpdatedMessage` | `frontend.location.updated` | `<FrontendLocationUpdated>` | ✓ Correct (frontend.xsd) |
| `PlanningLocationDeletedMessage` | `frontend.location.deleted` | `<FrontendLocationDeleted>` | ✓ Correct (frontend.xsd) |
| `PlanningLocationsRequestedMessage` | `frontend.locations.requested` | `<FrontendLocationsRequested>` | ✓ Correct (frontend.xsd) |
| `RegistrationCreatedMessage` | `frontend.registration.created` | `<RegistrationCreated>` | ✓ Correct (session.xsd) |

> **Conclusie:** De message klassen zelf zijn correct qua XML structuur.
> Het probleem zit elders — zie sectie 4.

### Consumers (Drupal ontvangt van andere services) — alleen CRM

| Klasse | Exchange | Routing key | Status |
|--------|----------|-------------|--------|
| `UserConfirmedConsumer` | `contact.topic` | `crm.user.confirmed` | ✓ Volledig |
| `UserDeactivatedConsumer` | `contact.topic` | `crm.user.deactivated` | ✓ Volledig |
| `UserUpdateConsumer` | `contact.topic` | `crm.user.updated` | ✓ Volledig |

### RabbitMQClient

`modules/custom/module/src/RabbitMQ/RabbitMQClient.php`

De centrale client die alle publish/consume logica afhandelt:
- Verbinding via `$_ENV['RABBITMQ_HOST/PORT/USER/PASS']`
- XSD-validatie vóór elke publish via `XsdValidator`
- Exchange declaratie bij verbinding: `frontend.topic`, `user.topic`, `heartbeat.direct`
- **Probleem:** `planning.topic` exchange wordt **niet** gedeclareerd (zie sectie 4)

### XsdRegistry

`modules/custom/module/src/RabbitMQ/Validation/XsdRegistry.php`

Koppelt message types aan XSD-bestanden en root elementen:
- Planning session berichten → `session.xsd`
- Location/Speaker berichten → `frontend.xsd`
- **Probleem:** Inkomende Planning berichten (consumers) zijn niet geregistreerd (zie sectie 4)

---

## 4. Kritieke problemen gevonden

### Probleem 1 — `planning.topic` exchange wordt niet gedeclareerd

**Bestand:** `RabbitMQClient.php` regel 191-201

```php
private function declareExchanges(): void {
    $this->channel->exchange_declare('user.topic',       'topic',  ...);
    $this->channel->exchange_declare('frontend.topic',   'topic',  ...);
    $this->channel->exchange_declare('heartbeat.direct', 'direct', ...);
    // planning.topic ontbreekt!
}
```

**Impact:** Als je een consumer probeert te starten die luistert op `planning.topic`,
zal de queue-binding mislukken omdat de exchange niet bestaat.

**Oplossing:**
```php
private function declareExchanges(): void {
    $this->channel->exchange_declare('user.topic',       'topic',  FALSE, TRUE, FALSE);
    $this->channel->exchange_declare('frontend.topic',   'topic',  FALSE, TRUE, FALSE);
    $this->channel->exchange_declare('planning.topic',   'topic',  FALSE, TRUE, FALSE);  // TOEVOEGEN
    $this->channel->exchange_declare('heartbeat.direct', 'direct', FALSE, TRUE, FALSE);
}
```

---

### Probleem 2 — Geen consumers voor Planning → Frontend berichten

Planning stuurt bevestigingen terug op `planning.topic`, maar Drupal heeft
**geen enkele consumer** die hierop luistert. Hierdoor:
- Worden sessies nooit opgeslagen in Drupal
- Weet Drupal niet of een inschrijving geslaagd is
- Toont `BezoekerController::sessiesPage()` altijd een lege lijst

**Ontbrekende consumers:**

| Routing key | XML element | Wat Drupal ermee moet doen |
|-------------|-------------|---------------------------|
| `planning.session.created` | `<SessionCreated>` | Sessie opslaan in Drupal state |
| `planning.session.updated` | `<SessionUpdated>` | Sessie updaten in Drupal state |
| `planning.session.cancelled` | `<SessionCancelled>` | Status zetten op 'cancelled' |
| `planning.session.rescheduled` | `<SessionRescheduled>` | Datum/tijd updaten |
| `planning.session.full` | `<SessionFull>` | Status zetten op 'full' |
| `planning.registration.confirmed` | `<RegistrationConfirmed>` | Bezoeker tonen: inschrijving ok |

**Oplossing:** Voor elke routing key een consumer klasse aanmaken,
analoog aan `UserConfirmedConsumer`.

---

### Probleem 3 — `BezoekerController::sessiesPage()` leest van verkeerde bron

**Bestand:** `modules/custom/shift_bezoeker/src/Controller/BezoekerController.php` regel 12-59

```php
public function sessionsPage() {
    $query = \Drupal::entityQuery('node')
        ->condition('type', 'session');  // zoekt naar Drupal nodes
    // ...
}
```

**Impact:** Sessies komen via RabbitMQ binnen (niet als Drupal nodes).
Er zijn geen `session` nodes in Drupal, dus de pagina toont altijd niets.

**Oplossing:** Twee opties:
- **Optie A (aanbevolen):** Consumer slaat sessies op in Drupal `state` of een
  custom database tabel. Controller leest van daar.
- **Optie B:** Consumer maakt Drupal nodes aan van type `session`.
  Dan werkt de huidige query, maar je hebt een content type `session` nodig.

---

### Probleem 4 — `BezoekerController::inschrijven()` stuurt niets

**Bestand:** `modules/custom/shift_bezoeker/src/Controller/BezoekerController.php` regel 65-70

```php
public function inschrijven($session_id) {
    return [
        '#theme' => 'inschrijving_bevestigd',
        '#session_id' => $session_id,
    ];
    // Stuurt GEEN RegistrationCreated bericht naar Planning!
}
```

**Impact:** Planning weet nooit dat een bezoeker zich inschrijft.
De bezoeker ziet een bevestigingspagina maar de inschrijving bestaat niet in Planning.

**Oplossing:**
```php
public function inschrijven($session_id) {
    $account = \Drupal::currentUser();
    // Haal crmMasterId op van de ingelogde gebruiker
    $user = \Drupal\user\Entity\User::load($account->id());
    $crmMasterId = $user->get('field_crm_id')->value;

    $message = new RegistrationCreatedMessage(
        sessionId:   $session_id,
        crmMasterId: $crmMasterId,
    );
    $client = RabbitMQClient::fromEnv();
    $client->publish($message);
    $client->disconnect();

    return [
        '#theme' => 'inschrijving_bevestigd',
        '#session_id' => $session_id,
    ];
}
```

---

### Probleem 5 — Geen `FrontendSessionsRequested` message klasse

Om bij opstart alle sessies op te halen stuurt Frontend een leeg
`<SessionsRequested>` bericht. Planning antwoordt dan met `<SessionsAll>`.

**Impact:** Bij een herstart van Drupal zijn er geen sessies zichtbaar
totdat Planning er zelf een stuurt via events.

**Oplossing:** Message klasse aanmaken en bij module-init sturen.

---

### Probleem 6 — Speaker producers bestaan niet

De `SessionCreateForm` heeft een speaker-dropdown, maar er zijn
geen message klassen voor `FrontendSpeakerCreated/Updated/Deactivated`.
Wanneer een gebruiker met de `speaker` rol zich registreert,
weet Planning daar niets van.

**Impact:** De speaker-dropdown in `SessionCreateForm` toont wel speakers,
maar Planning kent die speakers niet (geen UUID match mogelijk).

**Oplossing:** Drie message klassen aanmaken + publisher koppelen aan
het moment waarop een gebruiker de `speaker` rol krijgt.

---

## 5. Wat volledig ontbreekt

### Ontbrekende message klassen (producers)

| Te maken klasse | Routing key | XSD element | Wanneer sturen |
|-----------------|-------------|-------------|----------------|
| `FrontendSpeakerCreatedMessage` | `frontend.speaker.created` | `<FrontendSpeakerCreated>` | Bij registratie met rol `speaker` |
| `FrontendSpeakerUpdatedMessage` | `frontend.speaker.updated` | `<FrontendSpeakerUpdated>` | Bij `EditAccountForm` submit voor speaker |
| `FrontendSpeakerDeactivatedMessage` | `frontend.speaker.deactivated` | `<FrontendSpeakerDeactivated>` | Bij `CancelAccountConfirmForm` voor speaker |
| `FrontendSessionsRequestedMessage` | `frontend.sessions.requested` | `<SessionsRequested>` | Bij module-init / page refresh |

### Ontbrekende consumer klassen

| Te maken klasse | Exchange | Routing key | Wat opslaan |
|-----------------|----------|-------------|-------------|
| `SessionCreatedConsumer` | `planning.topic` | `planning.session.created` | Sessie data in Drupal state |
| `SessionUpdatedConsumer` | `planning.topic` | `planning.session.updated` | Sessie updaten in state |
| `SessionCancelledConsumer` | `planning.topic` | `planning.session.cancelled` | Status 'cancelled' |
| `SessionRescheduledConsumer` | `planning.topic` | `planning.session.rescheduled` | Nieuwe datum/tijd |
| `SessionFullConsumer` | `planning.topic` | `planning.session.full` | Status 'full' |
| `RegistrationConfirmedConsumer` | `planning.topic` | `planning.registration.confirmed` | Bevestiging opslaan |
| `SessionsAllConsumer` | `planning.topic` | `planning.sessions.all` | Alle sessies opslaan bij opstart |

### Ontbrekende opslaglaag voor sessies

Er is geen tabel of state entry waar sessiedata lokaal bewaard wordt.
De consumers hebben een opslagplaats nodig om data naartoe te schrijven
en de controller heeft een leesplaats nodig.

**Optie A — Drupal State API** (eenvoudig, niet queryable):
```php
\Drupal::state()->set('shift_sessions', $sessions);
\Drupal::state()->get('shift_sessions', []);
```

**Optie B — Custom database tabel** (aanbevolen voor productie):
Een `hook_schema()` in een `.install` bestand dat een tabel
`shift_sessions` aanmaakt met kolommen: `session_id`, `title`, `date`,
`start_time`, `end_time`, `location_id`, `status`, `capacity`.

---

## 6. Concreet actieplan — stap voor stap

### Stap 1 — Fix `RabbitMQClient`: `planning.topic` toevoegen

**Bestand:** `modules/custom/module/src/RabbitMQ/RabbitMQClient.php`

Voeg `planning.topic` toe aan `declareExchanges()`.
Dit is een vereiste voor alle volgende stappen.

**Geschatte tijd:** 5 minuten

---

### Stap 2 — Consumer aanmaken: `SessionCreatedConsumer`

**Nieuw bestand:** `modules/custom/module/src/RabbitMQ/Consumer/SessionCreatedConsumer.php`

Structuur (analoog aan `UserConfirmedConsumer`):
```php
class SessionCreatedConsumer {
    public function listen(): void {
        // exchange: planning.topic
        // queue: frontend.session.created
        // routing key: planning.session.created
    }

    private function handleMessage(AMQPMessage $msg): void {
        $xml = $msg->getBody();
        // parse <SessionCreated> XML
        // sla op in Drupal state of database
    }
}
```

**XSD referentie** (`session.xsd`):
```xml
<SessionCreated>
  <sessionId>UUID</sessionId>           <!-- optioneel bij aanmaken, verplicht in response -->
  <title>string</title>
  <date>string</date>
  <startTime>HH:MM:SS</startTime>
  <endTime>HH:MM:SS</endTime>           <!-- optioneel -->
  <capacity>int</capacity>              <!-- optioneel -->
  <locationId>UUID</locationId>         <!-- optioneel -->
  <speakerId>UUID</speakerId>           <!-- optioneel -->
  <location>string</location>           <!-- optioneel -->
  <status>string</status>               <!-- optioneel -->
  <icsData>string</icsData>             <!-- optioneel -->
  <timestamp>dateTime</timestamp>       <!-- optioneel -->
</SessionCreated>
```

**Geschatte tijd:** 1-2 uur

---

### Stap 3 — Sessie opslaglaag aanmaken

**Optie A (snelst):** Drupal State API in `SessionCreatedConsumer`:
```php
$sessions = \Drupal::state()->get('shift_sessions', []);
$sessions[$data['sessionId']] = $data;
\Drupal::state()->set('shift_sessions', $sessions);
```

**Optie B (robuust):** `hook_schema()` in `module.install`:
```php
function module_schema(): array {
    return [
        'shift_sessions' => [
            'fields' => [
                'session_id' => ['type' => 'varchar', 'length' => 36, 'not null' => TRUE],
                'title'      => ['type' => 'varchar', 'length' => 255],
                'date'       => ['type' => 'varchar', 'length' => 10],
                'start_time' => ['type' => 'varchar', 'length' => 8],
                'end_time'   => ['type' => 'varchar', 'length' => 8],
                'location'   => ['type' => 'varchar', 'length' => 255],
                'status'     => ['type' => 'varchar', 'length' => 50],
                'capacity'   => ['type' => 'int'],
            ],
            'primary key' => ['session_id'],
        ],
    ];
}
```

**Geschatte tijd:** 30 minuten - 1 uur

---

### Stap 4 — `BezoekerController::sessiesPage()` aanpassen

Na stap 2 en 3 leest de controller sessies uit de opslaglaag:

```php
public function sessionsPage(): array {
    // Lees sessies uit state (of database)
    $sessions = \Drupal::state()->get('shift_sessions', []);

    $grid_data = [];
    foreach ($sessions as $session) {
        $start = $session['start_time'] ?? '';
        $stage = $session['location']   ?? 'main';
        $grid_data[$stage][$start] = [
            'id'    => $session['session_id'],
            'title' => $session['title'],
            'time'  => $start . ' - ' . ($session['end_time'] ?? ''),
            'type'  => $session['status'] === 'full' ? 'vol' : 'open',
        ];
    }
    // ...
}
```

**Geschatte tijd:** 30 minuten

---

### Stap 5 — `BezoekerController::inschrijven()` koppelen

```php
public function inschrijven(string $session_id): array {
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $crmMasterId = $user->hasField('field_crm_id')
        ? $user->get('field_crm_id')->value
        : NULL;

    if ($crmMasterId) {
        try {
            $client = RabbitMQClient::fromEnv();
            $client->publish(new RegistrationCreatedMessage($session_id, $crmMasterId));
            $client->disconnect();
        } catch (\Throwable $e) {
            \Drupal::logger('shift_bezoeker')->error('Inschrijving RabbitMQ fout: @e', ['@e' => $e->getMessage()]);
        }
    }

    return ['#theme' => 'inschrijving_bevestigd', '#session_id' => $session_id];
}
```

**Geschatte tijd:** 30 minuten

---

### Stap 6 — Overige consumers aanmaken

Na stap 2 zijn de andere consumers snel te maken (zelfde patroon):
- `SessionUpdatedConsumer` — update bestaande sessie in state
- `SessionCancelledConsumer` — zet status op 'cancelled'
- `SessionFullConsumer` — zet status op 'full'
- `RegistrationConfirmedConsumer` — sla `registrationId` op per gebruiker

**Geschatte tijd:** 2-3 uur

---

### Stap 7 — Speaker producers aanmaken

Wanneer een gebruiker de rol `speaker` krijgt (bij registratie of rol-wijziging),
moet een `FrontendSpeakerCreated` bericht naar Planning gestuurd worden.

**Nieuwe message klassen:**
```php
// FrontendSpeakerCreatedMessage
$xml = new SimpleXMLElement('<FrontendSpeakerCreated/>');
$xml->addChild('firstName',   $this->firstName);
$xml->addChild('lastName',    $this->lastName);
$xml->addChild('email',       $this->email);
// optioneel: phoneNumber, company
```

Routing key: `frontend.speaker.created`
XSD: `frontend.xsd` element `FrontendSpeakerCreated`

**Geschatte tijd:** 1 uur

---

### Stap 8 — Consumer runner (worker)

Consumers zijn blocking processen — ze draaien continu en wachten op berichten.
Ze kunnen niet als Drupal request draaien.

**Optie A — Drush command (aanbevolen):**
```php
// src/Commands/RabbitMQWorkerCommands.php
class RabbitMQWorkerCommands extends DrushCommands {
    #[CLI\Command(name: 'shift:consume:sessions')]
    public function consumeSessions(): void {
        (new SessionCreatedConsumer())->listen();
    }
}
```
Starten: `drush shift:consume:sessions`

**Optie B — Docker service:**
Een aparte container in `docker-compose.yml` die het Drush command uitvoert.

**Geschatte tijd:** 1 uur

---

## 7. XSD contract referentie

### Frontend → Planning (jij stuurt, Planning ontvangt)

Alle berichten gaan naar exchange `frontend.topic`.

| Routing key | XSD element | XSD bestand | Verplichte velden |
|-------------|-------------|-------------|-------------------|
| `frontend.session.created` | `<SessionCreated>` | `session.xsd` | `title`, `date`, `startTime` |
| `frontend.session.updated` | `<SessionUpdated>` | `session.xsd` | `sessionId`, `sessionName`, `changeType` |
| `frontend.session.cancelled` | `<SessionCancelled>` | `session.xsd` | `sessionId`, `sessionName` |
| `frontend.session.rescheduled` | `<SessionRescheduled>` | `session.xsd` | `sessionId`, `sessionName`, `oldDate`, `oldStartTime`, `newDate`, `newStartTime` |
| `frontend.location.created` | `<FrontendLocationCreated>` | `frontend.xsd` | `roomName`, `capacity` |
| `frontend.location.updated` | `<FrontendLocationUpdated>` | `frontend.xsd` | `locationId`, `roomName`, `capacity`, `status` |
| `frontend.location.deleted` | `<FrontendLocationDeleted>` | `frontend.xsd` | `locationId` |
| `frontend.speaker.created` | `<FrontendSpeakerCreated>` | `frontend.xsd` | `firstName`, `lastName`, `email` |
| `frontend.speaker.updated` | `<FrontendSpeakerUpdated>` | `frontend.xsd` | `speakerId`, `firstName`, `lastName`, `email` |
| `frontend.speaker.deactivated` | `<FrontendSpeakerDeactivated>` | `frontend.xsd` | `speakerId` |
| `frontend.registration.created` | `<RegistrationCreated>` | `session.xsd` | `sessionId`, `crmMasterId` |
| `frontend.sessions.requested` | `<SessionsRequested>` | `session.xsd` | *(leeg bericht)* |
| `frontend.locations.requested` | `<FrontendLocationsRequested>` | `frontend.xsd` | *(leeg bericht)* |

### Planning → Frontend (Planning stuurt, jij ontvangt)

Alle berichten komen van exchange `planning.topic`.

| Routing key | XSD element | XSD bestand | Belangrijke velden |
|-------------|-------------|-------------|-------------------|
| `planning.session.created` | `<SessionCreated>` | `session.xsd` | `sessionId`, `title`, `date`, `startTime`, `status` |
| `planning.session.updated` | `<SessionUpdated>` | `session.xsd` | `sessionId`, `sessionName`, `changeType` |
| `planning.session.cancelled` | `<SessionCancelled>` | `session.xsd` | `sessionId`, `sessionName`, `reason` |
| `planning.session.rescheduled` | `<SessionRescheduled>` | `session.xsd` | `sessionId`, `newDate`, `newStartTime` |
| `planning.session.full` | `<SessionFull>` | `session.xsd` | `sessionId`, `capacity`, `currentRegistrations` |
| `planning.registration.confirmed` | `<RegistrationConfirmed>` | `session.xsd` | `registrationId`, `sessionId`, `crmMasterId` |
| `planning.sessions.all` | `<SessionsAll>` | `session.xsd` | array van `<session>` elementen |

### Docker poorten (Planning service)

| Service | Poort | Beschrijving |
|---------|-------|-------------|
| Planning Service API | 3000 | REST endpoints |
| PostgreSQL | 5432 | Database (intern) |
| RabbitMQ | 5672 | Messaging |
| RabbitMQ Management | 15672 | Dashboard (dev only) |

---

## Samenvatting prioriteiten

| Prioriteit | Taak | Impact |
|-----------|------|--------|
| 🔴 Kritiek | `planning.topic` exchange toevoegen aan `RabbitMQClient` | Zonder dit werken alle consumers niet |
| 🔴 Kritiek | `SessionCreatedConsumer` aanmaken | Sessies komen nooit in Drupal |
| 🔴 Kritiek | Sessie opslaglaag aanmaken | Consumer heeft nergens naartoe te schrijven |
| 🔴 Kritiek | `BezoekerController::inschrijven()` koppelen | Inschrijvingen bereiken Planning nooit |
| 🟡 Belangrijk | `BezoekerController::sessiesPage()` aanpassen | Sessie-overzicht is altijd leeg |
| 🟡 Belangrijk | `RegistrationConfirmedConsumer` aanmaken | Bevestiging bereikt bezoeker nooit |
| 🟡 Belangrijk | `SessionCancelledConsumer` + `SessionFullConsumer` | Statuswijzigingen worden genegeerd |
| 🟢 Later | Speaker producers aanmaken | Speaker-sessie koppeling werkt niet |
| 🟢 Later | `FrontendSessionsRequestedMessage` | Proactief sessies ophalen bij opstart |
| 🟢 Later | Consumer runner (Drush worker) | Consumers draaien anders niet automatisch |
