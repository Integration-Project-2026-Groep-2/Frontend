# Documentatie: CI Testing & Unit Tests

**Branch:** `CI-en-unit-testen`  
**Datum:** 2026-05-13  
**Auteur:** Bilal Bouchta

---

## Inhoudsopgave

1. [Wat is CI Testing?](#1-wat-is-ci-testing)
2. [Wat is een Unit Test?](#2-wat-is-een-unit-test)
3. [Vergelijking met andere teams](#3-vergelijking-met-andere-teams)
4. [Overzicht van alle bestanden](#4-overzicht-van-alle-bestanden)
5. [ci.yml — de CI workflow](#5-ciyml--de-ci-workflow)
6. [cd.yml — de CD workflow (aangepast)](#6-cdyml--de-cd-workflow-aangepast)
7. [phpunit.xml — de testconfiguratie](#7-phpunitxml--de-testconfiguratie)
8. [Wat is Linting?](#8-wat-is-linting)
9. [JarvisJwtSignerTest.php](#9-jarvisjwtsignertestphp)
10. [SessionListRequestTest.php](#10-sessionlistrequesttestphp)
11. [SessionListResponseTest.php](#11-sessionlistresponsetestphp)
12. [LoginRedirectionSubscriberTest.php](#12-loginredirectionsubscribertestphp)
13. [Hoe werkt alles samen?](#13-hoe-werkt-alles-samen)
14. [Bestaande tests in het project](#14-bestaande-tests-in-het-project)

---

## 1. Wat is CI Testing?

**CI** staat voor **Continuous Integration** (continue integratie). Het is een manier van werken waarbij code automatisch getest wordt elke keer dat iemand iets pusht naar GitHub.

### Zonder CI:
```
Developer schrijft code → pusht naar GitHub → niemand weet of het werkt
```

### Met CI:
```
Developer schrijft code → pusht naar GitHub → GitHub voert automatisch
alle tests uit → groen ✅ = code werkt | rood ❌ = er is iets stuk
```

### Waarom is dit nuttig?
- Je ontdekt fouten **meteen**, niet weken later
- Je weet zeker dat nieuwe code de bestaande functionaliteit niet **breekt**
- Elke pull request wordt automatisch gecontroleerd vóór samenvoegen
- Je team heeft altijd vertrouwen dat de `main` branch werkt

---

## 2. Wat is een Unit Test?

Een **unit test** is een klein stukje code dat één specifieke functie of methode test.

### Het AAA-patroon:
```php
public function testVoorbeeldNaam(): void {
    // 1. ARRANGE — zet alles klaar
    $service = new MijnService();

    // 2. ACT — voer de te testen actie uit
    $resultaat = $service->berekenSom(3, 5);

    // 3. ASSERT — controleer of het resultaat klopt
    $this->assertSame(8, $resultaat);
}
```

### Soorten tests in dit project:

| Type | Beschrijving | Database nodig? | Snelheid |
|------|-------------|-----------------|---------|
| **Unit test** | Test één klasse geïsoleerd met nep-objecten | Nee | ⚡ Zeer snel |
| **Kernel test** | Test met echte Drupal-omgeving + SQLite | Ja (SQLite) | 🐢 Trager |

---

## 3. Vergelijking met andere teams

De Frontend CI volgt dezelfde structuur als de andere teams in het project:

| Team | CI aanwezig | Framework | Linting | Unit tests | Integratie tests |
|------|------------|-----------|---------|-----------|-----------------|
| **CRM** | ✅ | Python + pytest | ✅ ruff + pip-audit | ✅ | ✅ RabbitMQ |
| **Facturatie** | ✅ | PHP + Composer | ✅ phpcs | ✅ | ✅ RabbitMQ + MariaDB |
| **Mailing** | ✅ | Node.js + Jest | ❌ | ✅ | ✅ RabbitMQ + MariaDB |
| **Frontend** | ✅ | PHP + PHPUnit | ✅ phpcs Drupal | ✅ | ✅ Kernel + SQLite |

### Gedeelde patronen van alle teams:
- CI draait bij elke **push** en **pull request** naar `main`
- CD deployt **alleen** als CI geslaagd is (via `workflow_run`)
- Aparte jobs voor linting, unit tests en integratie tests
- **Concurrency**: nieuwe push annuleert vorige CI-run

---

## 4. Overzicht van alle bestanden

```
Frontend/
├── phpunit.xml                                              ← NIEUW
├── CI_TESTING_DOCUMENTATIE.md                              ← NIEUW
├── .github/
│   └── workflows/
│       ├── ci.yml                                          ← NIEUW (3 jobs + linting)
│       └── cd.yml                                          ← GEWIJZIGD (workflow_run trigger)
└── modules/custom/
    ├── jarvis_chat/
    │   └── tests/src/Unit/Service/
    │       └── JarvisJwtSignerTest.php                     ← NIEUW
    ├── Session_Management/
    │   └── tests/src/Unit/RabbitMQ/Message/
    │       ├── SessionListRequestTest.php                  ← NIEUW
    │       └── SessionListResponseTest.php                 ← NIEUW
    └── custom_roles/
        └── tests/src/Unit/EventSubscriber/
            └── LoginRedirectionSubscriberTest.php          ← NIEUW
```

---

## 5. ci.yml — de CI workflow

**Locatie:** `.github/workflows/ci.yml`

### Structuur: 3 jobs

```
push/PR naar main
       │
       ▼
┌─────────────┐
│    lint     │  ← Job 1: controleert codekwaliteit EERST
└──────┬──────┘
       │ needs: lint
  ┌────┴────┐
  ▼         ▼
┌────────┐ ┌──────────────┐
│ unit-  │ │   kernel-    │  ← Jobs 2 & 3: draaien PARALLEL
│ tests  │ │   tests      │    maar pas NA lint
└────────┘ └──────────────┘
```

> **Zelfde patroon als Facturatie:** lint eerst, daarna tests.  
> **Zelfde patroon als CRM:** aparte jobs, parallel na de eerste gate.

### Concurrency (zelfde als CRM):
```yaml
concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true
```
Als je snel twee commits pusht, wordt de eerste CI-run automatisch geannuleerd. Dit bespaart GitHub Actions minuten.

### Timeouts (zelfde als CRM en Facturatie):
```yaml
timeout-minutes: 10   # lint
timeout-minutes: 25   # unit-tests en integration
```
Elke job stopt als hij vastloopt: lint na 10 minuten, tests na 25 minuten. Zo raken de CI-minuten niet op door een stuk test dat eeuwig hangt.

### Permissions (zelfde als CRM):
```yaml
permissions:
  contents: read
```
Beperkt de rechten tot het minimum. De job mag alleen code lezen, niets schrijven of pushen.

---

### Job 1: Lint & Code Standards

**Wat het doet:**
1. PHP 8.3 installeren (zonder zware Drupal container — sneller)
2. `phpcs` en `drupal/coder` installeren via Composer
3. Coding standards controleren op alle custom modules
4. Beveiligingsaudit uitvoeren op PHP dependencies

```yaml
- name: Controleer coding standards (Drupal standaard)
  run: ./vendor/bin/phpcs --standard=phpcs.xml
```

**`--standard=phpcs.xml`** — gebruikt de projectconfiguratie in `phpcs.xml` (Drupal standaard + uitgesloten regels)

```yaml
- name: Beveiligingsaudit van PHP dependencies
  run: composer audit --no-dev
  continue-on-error: true
```

**`composer audit`** — controleert of er bekende beveiligingslekken zijn in de PHP packages  
**`continue-on-error: true`** — meldt beveiligingsproblemen maar blokkeert de build niet (zelfde aanpak als CRM's pip-audit)

---

### Job 2: Unit Tests

- Draait **na** lint (`needs: lint`)
- Gebruikt `ubuntu-latest` runner met `composer create-project drupal/recommended-project:^11`
- Installeert PHPUnit via `drupal/core-dev:^11`
- Kopieert custom modules en XSD schemas naar Drupal
- Voert alle unit tests uit (geen database nodig)

---

### Job 3: Kernel Tests

- Draait **parallel** met unit tests, ook na lint
- Gebruikt `ubuntu-latest` runner met `composer create-project drupal/recommended-project:^11`
- SQLite als tijdelijke database (`SIMPLETEST_DB`)
- RabbitMQ uitgeschakeld (`SHIFT_BEZOEKER_DISABLE_AMQP=1`)
- Voert alle kernel tests uit

---

## 6. cd.yml — de CD workflow (aangepast)

**Locatie:** `.github/workflows/cd.yml`

### Wat er veranderd is

**Voordien** (origineel):
```yaml
on:
  push:
    branches: [main]   # Deployde bij ELKE push, ook als tests faalden!
```

**Nu** (zelfde als Facturatie en CRM):
```yaml
on:
  workflow_run:
    workflows: ["CI"]
    branches: [main]
    types: [completed]
```

### Waarom is dit belangrijk?

Met de oude trigger kon dit gebeuren:
```
Developer pusht code met een bug
→ CI start (maar CD ook al!)
→ CI faalt ❌
→ CD deployt toch de kapotte code naar Dev 💥
```

Met de nieuwe trigger:
```
Developer pusht code met een bug
→ CI start
→ CI faalt ❌
→ CD start NIET — server blijft werkend
```

### Extra veiligheidscontrole:
```yaml
if: >
  github.event.workflow_run.conclusion == 'success' &&
  github.event.workflow_run.event == 'push' &&
  github.event.workflow_run.head_branch == 'main'
```
De CD job controleert expliciet:
- CI was **succesvol** (niet gefaald of geannuleerd)
- Het was een **push** (niet een pull request)
- De branch is **main**

### Timeouts toegevoegd (zoals Facturatie):
- Build & push: 15 minuten
- Deploy: 5 minuten

---

## 7. phpunit.xml — de testconfiguratie

**Locatie:** `phpunit.xml`

```xml
<testsuite name="unit">
  <!-- Alle unit tests van alle modules -->
</testsuite>

<testsuite name="kernel">
  <!-- Kernel tests van shift_bezoeker -->
</testsuite>
```

Dit bestand vertelt PHPUnit **waar** de tests staan en **hoe** ze uitgevoerd moeten worden. De CI kopieert dit bestand naar `$HOME/drupal/phpunit.xml` op de ubuntu-latest runner.

---

## 8. Wat is Linting?

**Linting** is automatische code-kwaliteitscontrole. Een linter leest je code en zoekt naar fouten en slechte gewoontes **zonder de code uit te voeren**.

### Wat phpcs controleert (Drupal standaard):

| Categorie | Voorbeeld fout |
|-----------|---------------|
| **Opmaak** | Ontbrekende spaties rond operators |
| **Documentatie** | Ontbrekende PHPDoc commentaar |
| **Naamgeving** | Functies die niet snake_case volgen |
| **Complexiteit** | Functies die te lang zijn |
| **PHP stijl** | `==` gebruiken in plaats van `===` |

### Voorbeeld van een phpcs fout:
```
FILE: modules/custom/hello_world/src/Controller/HelloWorldController.php
---------------------------------------------------------------------------
FOUND 2 ERRORS AFFECTING 2 LINES
---------------------------------------------------------------------------
 15 | ERROR | Missing function doc comment
 23 | ERROR | Spaces must be used to indent lines; tabs are not allowed
---------------------------------------------------------------------------
```

### Waarom `--warning-severity=0`?

Drupal's standaard heeft veel regels. Sommige zijn echte **fouten** (de code werkt verkeerd), andere zijn **stijlwaarschuwingen** (de code werkt wel maar ziet er niet netjes uit). Met `--warning-severity=0` faalt de build alleen bij echte fouten — stijlwaarschuwingen worden getoond maar blokkeren niets. Dit is de standaardaanpak bij een bestaand project dat net linting toevoegt.

---

## 9. JarvisJwtSignerTest.php

**Locatie:** `modules/custom/jarvis_chat/tests/src/Unit/Service/JarvisJwtSignerTest.php`

### Wat doet de JarvisJwtSigner?
Maakt JWT tokens aan voor communicatie tussen Jarvis chatbot en mcp-master. Een JWT is een versleuteld token dat bewijst wie je bent:
```
eyJhbGciOiJIUzI1NiJ9 . eyJzdWIiOiI0MiJ9 . abc123
        HEADER               PAYLOAD        HANDTEKENING
```

### Tests (9 stuks):

| Test | Wat wordt getest |
|------|-----------------|
| `testMintReturnsNullWhenSecretNotSet` | Geen `CHAT_JWT_SECRET` → geeft `null` terug |
| `testMintReturnsNullWhenSecretIsEmpty` | Lege secret → geeft `null` terug |
| `testMintReturnsNullForNonNumericUserId` | Gebruikers-ID is geen getal → geeft `null` terug |
| `testMintReturnsNullForEmptyUserId` | Leeg gebruikers-ID → geeft `null` terug |
| `testMintProducesThreePartToken` | Geldig token heeft 3 delen gescheiden door punten |
| `testMintedTokenHeaderIsHs256` | Header bevat `alg: HS256` en `typ: JWT` |
| `testMintedTokenPayloadContainsSubAndScope` | Payload bevat `sub`, `scope` en `exp` |
| `testMintedTokenExpiresInApproximatelyOneHour` | Token vervalt over 3600 seconden |
| `testMintedTokenSignatureIsValid` | HMAC-SHA256 handtekening is wiskundig correct |

---

## 10. SessionListRequestTest.php

**Locatie:** `modules/custom/Session_Management/tests/src/Unit/RabbitMQ/Message/SessionListRequestTest.php`

### Wat doet de SessionListRequest?
RabbitMQ bericht dat naar de planning-service vraagt: *"Geef mij alle sessies."*

### Tests (6 stuks):

| Test | Wat wordt getest |
|------|-----------------|
| `testRoutingKeyIsCorrect` | Routing key is `session.list.request` |
| `testGetTypeIsCorrect` | Type is `SessionListRequest` |
| `testToXmlProducesValidXml` | Geproduceerde XML is geldig en heeft correct root element |
| `testToXmlBevatRequestId` | XML bevat `<requestId>` dat begint met `req_` |
| `testToXmlBevatTimestamp` | XML bevat een `<timestamp>` element |
| `testRequestIdIsUniekPerAanroep` | Elke aanroep genereert een ander uniek ID |

---

## 11. SessionListResponseTest.php

**Locatie:** `modules/custom/Session_Management/tests/src/Unit/RabbitMQ/Message/SessionListResponseTest.php`

### Wat doet de SessionListResponse?
Zet het XML-antwoord van de planning-service om naar een PHP array van sessies.

### Tests (6 stuks):

| Test | Wat wordt getest |
|------|-----------------|
| `testLegeXmlGeeftLegeArray` | Geen sessies in XML → lege array `[]` |
| `testOngeldigeXmlGeeftLegeArray` | Ongeldige XML → lege array, geen crash |
| `testParseerEénSessie` | Alle velden (id, title, times, location, speaker, capacity) correct geparseerd |
| `testParseerMeerdereSessies` | 3 sessies in XML → array met 3 items |
| `testCapacityWordtGecastedNaarInteger` | XML-tekst `"42"` wordt `int` 42 |
| `testGetSessionsGeeftZelfdeArrayTerug` | Meerdere aanroepen geven altijd hetzelfde resultaat |

---

## 12. LoginRedirectionSubscriberTest.php

**Locatie:** `modules/custom/custom_roles/tests/src/Unit/EventSubscriber/LoginRedirectionSubscriberTest.php`

### Wat doet de LoginRedirectionSubscriber?
Stuurt gebruikers na inloggen naar de juiste pagina op basis van hun rol:
- Administrator → `/hello/admin`
- Speaker → `/bespreker`
- Andere rollen (visitor, kassa, ...) → `/`

### Technische implementatie
De subscriber gebruikt gewone padstrings (`/hello/admin`, `/bespreker`, `/`) in plaats van `Url::fromUri()->toString()`. Dit vermijdt een Drupal container-afhankelijkheid en maakt de klasse volledig testbaar als unit test — geen try/catch nodig.

### Tests (6 stuks):

| Test | Wat wordt getest |
|------|-----------------|
| `testGetSubscribedEventsRegistreertResponseEvent` | Subscriber luistert naar `KernelEvents::RESPONSE` |
| `testNietIngelogdeGebruikerWordtNietOmgeleid` | Anonieme gebruiker → geen redirect, response ongewijzigd |
| `testIngelogdeGebruikerOpAndereRouteWordtNietOmgeleid` | Ingelogd maar op andere route dan `user.login` → geen redirect |
| `testAdministratorWordtOmgeleidNaLogin` | Administrator op login route → `RedirectResponse` naar `/hello/admin` |
| `testSpeakerWordtOmgeleidNaLogin` | Speaker op login route → `RedirectResponse` naar `/bespreker` |
| `testVisitorWordtOmgeleidNaLogin` | Visitor op login route → `RedirectResponse` naar `/` |

---

## 13. Hoe werkt alles samen?

```
DEVELOPER
   │
   │  git push naar main
   ▼
GITHUB ACTIONS — leest .github/workflows/ci.yml
   │
   ▼
┌──────────────────────────────────────┐
│  Job 1: LINT (ubuntu-latest + PHP)   │
│  ✓ phpcs --standard=Drupal           │
│  ✓ composer audit                    │
└──────────────────┬───────────────────┘
                   │ needs: lint
         ┌─────────┴──────────┐
         ▼                    ▼
┌──────────────────┐  ┌──────────────────┐
│ Job 2: UNIT TEST │  │ Job 3: KERNEL    │
│ (ubuntu-latest)  │  │ TEST             │
│ ✓ PHPUnit unit   │  │ (ubuntu-latest)  │
│ ✓ 9+ test klassen│  │ ✓ SQLite DB      │
└──────────────────┘  └──────────────────┘
         │                    │
         └──────┬─────────────┘
                │ alles geslaagd?
                ▼
┌──────────────────────────────────────┐
│  .github/workflows/cd.yml            │
│  workflow_run: CI completed          │
│                                      │
│  ✓ Build Docker image                │
│  ✓ Push naar ghcr.io                 │
│  ✓ Deploy naar Dev server            │
└──────────────────────────────────────┘
```

### Wat gebeurt er bij een fout?

```
Job 1 LINT faalt ❌
→ Jobs 2 en 3 starten NIET (needs: lint)
→ CD deployt NIET (workflow_run wacht op success)
→ GitHub toont rode ❌ op de pull request
→ Merge is geblokkeerd
```

---

## 14. Bestaande tests in het project

Deze tests waren al aanwezig vóór deze branch en worden meegenomen in de CI.

### ai_dashboard module:

| Testbestand | Wat wordt getest |
|-------------|-----------------|
| `AiDashboardControllerTest.php` | API endpoints: 403 bij verkeerde rol, 200 bij administrator, paginering, detail ophalen |
| `IncidentIngesterTest.php` | RabbitMQ berichten opslaan: validatie, deduplicatie, ontbrekende velden |
| `IncidentRepositoryShapersTest.php` | Data omzetten van database naar API-formaat |

### jarvis_chat module:

| Testbestand | Wat wordt getest |
|-------------|-----------------|
| `JarvisControllerTest.php` | Chat proxy: foutafhandeling, rol-beveiliging, JWT doorsturen, streaming |

### hello_world module (map: `module`):

| Testbestand | Wat wordt getest |
|-------------|-----------------|
| `CompanyCreatedMessageTest.php` | XML-bericht voor bedrijfsaanmaak: structuur, XSD-validatie, speciale tekens |

### shift_bezoeker module:

| Testbestand | Type | Wat wordt getest |
|-------------|------|-----------------|
| `RegistratieFormValidationTest.php` | Unit | Formulier-validatie: e-mail, wachtwoord, BTW-nummer |
| `EditAccountFormValidationTest.php` | Unit | Validatie bij account bewerken |
| `RegistratieFormBuildMessagesTest.php` | Unit | Foutmeldingen bouwen |
| `RegistratieFormTest.php` | Kernel | Echte gebruiker aanmaken, rollen toewijzen, data opslaan |
| `EditAccountFormTest.php` | Kernel | Account bewerken in echte Drupal omgeving |
| `CancelAccountConfirmFormTest.php` | Kernel | Account verwijderen bevestigen |
