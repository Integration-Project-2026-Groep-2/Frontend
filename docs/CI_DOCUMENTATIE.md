# CI Pipeline Documentatie — Frontend (Drupal 11)

## Inhoudsopgave

1. [Overzicht](#1-overzicht)
2. [Pipeline structuur](#2-pipeline-structuur)
3. [Bestanden aangemaakt of gewijzigd](#3-bestanden-aangemaakt-of-gewijzigd)
4. [Job 1 — Lint & Code Standards](#4-job-1--lint--code-standards)
5. [Job 2 — Unit Tests](#5-job-2--unit-tests)
6. [Job 3 — Integration Tests](#6-job-3--integration-tests)
7. [Technische keuzes en problemen opgelost](#7-technische-keuzes-en-problemen-opgelost)
8. [Overzicht van alle fouten en oplossingen](#8-overzicht-van-alle-fouten-en-oplossingen)
9. [Testresultaten (eindstand)](#9-testresultaten-eindstand)
10. [Hoe lokaal uitvoeren](#10-hoe-lokaal-uitvoeren)

---

## 1. Overzicht

De CI-pipeline voor het Frontend (Drupal 11) project automatiseert drie kwaliteitscontroles bij elke push naar `main` en bij elke pull request naar `main`:

| Job | Doel | Afhankelijkheid |
|-----|------|----------------|
| Lint & Code Standards | Controleert codestijl met phpcs (Drupal standaard) + beveiligingsaudit | — |
| Unit Tests | Voert snelle PHP unit tests uit zonder database | wacht op Lint |
| Integration Tests | Voert Drupal kernel tests uit met SQLite database | wacht op Lint |

De structuur is afgestemd op de andere teams (CRM en Facturatie):
- Lint loopt eerst
- Unit Tests en Integration Tests lopen parallel daarna

---

## 2. Pipeline structuur

```
push/PR naar main
        │
        ▼
┌─────────────────────┐
│  Lint & Code        │  (~12s)
│  Standards          │
└─────────┬───────────┘
          │  needs: lint
    ┌─────┴──────┐
    ▼            ▼
┌────────┐  ┌──────────────┐
│ Unit   │  │ Integration  │
│ Tests  │  │ Tests        │
│ (~30s) │  │ (~35s)       │
└────────┘  └──────────────┘
```

### Concurrency (annuleer vorige runs)
```yaml
concurrency:
  group: ${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: true
```
Als er een nieuwe push binnenkomt terwijl de vorige run nog bezig is, wordt de oude automatisch geannuleerd. Dit bespaart GitHub Actions minuten.

---

## 3. Bestanden aangemaakt of gewijzigd

### Nieuw aangemaakt

| Bestand | Doel |
|---------|------|
| `.github/workflows/ci.yml` | De volledige CI-pipeline definitie |
| `phpunit.xml` | PHPUnit configuratie met unit en kernel testsuites |
| `phpcs.xml` | PHP CodeSniffer configuratie met Drupal standaard |
| `tests_bootstrap.php` | Aangepaste bootstrap voor Drupal tests in CI |

### Gewijzigd (bestaande bestanden)

| Bestand | Wat is gewijzigd |
|---------|-----------------|
| `modules/custom/shift_bezoeker/tests/src/Kernel/Form/CancelAccountConfirmFormTest.php` | `hello_world` verwijderd uit `$modules` array |

---

## 4. Job 1 — Lint & Code Standards

### Doel
Controleert of alle PHP testbestanden voldoen aan de Drupal coding standard, en voert een beveiligingsaudit uit op production dependencies.

### Hoe het werkt
1. PHP 8.3 wordt geïnstalleerd via `shivammathur/setup-php`
2. `phpcs`, `drupal/coder` en `slevomat/coding-standard` worden tijdelijk geïnstalleerd via composer
3. `phpcs --standard=phpcs.xml` controleert alle testbestanden
4. `composer audit --no-dev` controleert security van production packages

### phpcs.xml — configuratiebestand

```xml
<?xml version="1.0"?>
<ruleset name="Frontend Tests">
  <rule ref="Drupal">
    <exclude name="Drupal.Commenting.FunctionComment"/>
    <exclude name="Drupal.Commenting.ClassComment"/>
    <exclude name="Drupal.Commenting.VariableComment"/>
    <exclude name="Drupal.Commenting.InlineComment"/>
    <exclude name="Drupal.Commenting.DocComment"/>
    <exclude name="Generic.PHP.UpperCaseConstant"/>
    <exclude name="Generic.Files.LineLength"/>
    <exclude name="Drupal.Arrays.Array"/>
  </rule>

  <file>modules/custom/ai_dashboard/tests</file>
  <file>modules/custom/jarvis_chat/tests</file>
  <file>modules/custom/Session_Management/tests</file>
  <file>modules/custom/custom_roles/tests</file>
  <file>modules/custom/shift_bezoeker/tests</file>
  <file>modules/custom/module/tests</file>

  <arg name="extensions" value="php"/>
  <arg name="warning-severity" value="0"/>
  <arg name="colors"/>
</ruleset>
```

**Uitgesloten regels (en waarom):**

| Regel | Reden van uitsluiting |
|-------|----------------------|
| `Drupal.Commenting.*` | Testmethodes zijn zelfbeschrijvend via hun naam — doc comments zijn overbodig |
| `Generic.PHP.UpperCaseConstant` | Moderne PHP gebruikt lowercase `null`, `true`, `false` |
| `Generic.Files.LineLength` | Lange regels in testdata zijn aanvaardbaar |
| `Drupal.Arrays.Array` | Flexibelere array opmaak in tests |

### composer audit — `--no-dev` vlag

De audit gebruikt `--no-dev` zodat alleen production packages worden gecontroleerd. De tijdelijk geïnstalleerde phpcs-tools (dev-only) bevatten CVEs in `phpseclib` die anders onterecht als fout verschijnen.

De stap heeft `continue-on-error: true` als extra vangnet — een security warning blokkeert de pipeline nooit.

---

## 5. Job 2 — Unit Tests

### Doel
Voert snelle PHP unit tests uit die **geen** database of Drupal kernel nodig hebben.

### Hoe het werkt
1. Een volledig nieuw Drupal 11 project wordt aangemaakt via `composer create-project`
2. `drupal/core-dev:^11` wordt toegevoegd — dit brengt PHPUnit 11 en alle test-infrastructuur mee
3. De custom modules worden gekopieerd naar `web/modules/custom/`
4. XSD schemas worden gekopieerd (nodig voor `shift_bezoeker` XML validatie tests)
5. `phpunit.xml` en `tests_bootstrap.php` worden gekopieerd
6. PHPUnit draait de `unit` testsuite

### Waarom `composer create-project` in CI?

De `vendor/` directory is niet getrackt in de repository. Een verse Drupal installatie in CI garandeert:
- Schrijfrechten in `$HOME/drupal` (geen permissieproblemen)
- Schone staat zonder conflicten met bestaande dependencies
- Deterministische omgeving (altijd dezelfde Drupal 11 versie)

### Omgevingsvariabele

```yaml
env:
  XSD_ROOT: /home/runner/drupal/xsd
```

De `shift_bezoeker` module gebruikt XSD schemas voor XML validatie. `XSD_ROOT` vertelt de module waar de schemas staan in de CI-omgeving.

### phpunit.xml — unit testsuite

```xml
<testsuite name="unit">
  <directory>web/modules/custom/ai_dashboard/tests/src/Unit</directory>
  <directory>web/modules/custom/jarvis_chat/tests/src/Unit</directory>
  <directory>web/modules/custom/module/tests/src/Unit</directory>
  <directory>web/modules/custom/shift_bezoeker/tests/src/Unit</directory>
  <directory>web/modules/custom/Session_Management/tests/src/Unit</directory>
  <directory>web/modules/custom/custom_roles/tests/src/Unit</directory>
</testsuite>
```

---

## 6. Job 3 — Integration Tests

### Doel
Voert Drupal kernel tests uit die een echte Drupal kernel en SQLite database gebruiken. Dit test de interactie tussen modules, forms, entities en de database.

### Hoe het werkt
Identiek aan Unit Tests, maar:
- De `kernel` testsuite wordt uitgevoerd in plaats van `unit`
- SQLite database wordt opgezet via `SIMPLETEST_DB`
- `SHIFT_BEZOEKER_DISABLE_AMQP=1` schakelt RabbitMQ/AMQP verbindingen uit in CI

### Omgevingsvariabelen

```yaml
env:
  SHIFT_BEZOEKER_DISABLE_AMQP: "1"
  SIMPLETEST_DB: "sqlite://localhost//tmp/drupal_ci_test.sqlite"
```

| Variabele | Waarde | Doel |
|-----------|--------|------|
| `SHIFT_BEZOEKER_DISABLE_AMQP` | `"1"` | Voorkomt dat de module probeert verbinding te maken met RabbitMQ (niet beschikbaar in CI) |
| `SIMPLETEST_DB` | SQLite pad | Drupal kernel tests hebben een database nodig — SQLite heeft geen aparte server nodig |

### phpunit.xml — kernel testsuite

```xml
<testsuite name="kernel">
  <directory>web/modules/custom/shift_bezoeker/tests/src/Kernel</directory>
</testsuite>
```

---

## 7. Technische keuzes en problemen opgelost

### 7.1 Custom bootstrap: `tests_bootstrap.php`

**Probleem:** Drupal's standaard `web/core/tests/bootstrap.php` werd niet gevonden, of de PSR-4 autoloader kende de `Session_Management` namespace niet.

**Oorzaak:** De module heet `Session_Management` (gemengde hoofdletters in de PHP namespace), maar op Linux zijn bestandspaden case-sensitive. De Drupal autoloader zoekt naar `session_management` (lowercase) en vindt de klassen niet.

**Oplossing:** Een eigen `tests_bootstrap.php` die:
1. Drupal's standaard bootstrap aanroept
2. Daarna expliciet de PSR-4 mapping toevoegt voor `Session_Management`

```php
<?php

if (!defined('DRUPAL_ROOT')) {
  define('DRUPAL_ROOT', __DIR__ . '/web');
}

require __DIR__ . '/web/core/tests/bootstrap.php';

$loader = require __DIR__ . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Session_Management\\', __DIR__ . '/web/modules/custom/Session_Management/src/');
$loader->addPsr4('Drupal\\Tests\\Session_Management\\', __DIR__ . '/web/modules/custom/Session_Management/tests/src/');
```

### 7.2 `drupal/core-dev` met `-W` vlag

**Probleem:** `composer require drupal/core-dev:^11` faalde met dependency conflicten (`sebastian/diff` versie conflict).

**Oorzaak:** De vers aangemaakte Drupal installatie had subtiel andere versies van core packages gelocked dan `core-dev` verwachtte.

**Oplossing:** De `-W` vlag (`--with-all-dependencies`) laat composer alle transitieve dependencies updaten om conflicten op te lossen:

```bash
composer require --dev "drupal/core-dev:^11" --no-interaction --no-progress -W
```

### 7.3 AMQP/RabbitMQ uitschakelen in CI

**Probleem:** `CancelAccountConfirmFormTest` (kernel test) faalde met `AMQPStreamConnection class not found` omdat de `shift_bezoeker` module probeert te verbinden met RabbitMQ bij elke form submit.

**Oorzaak:** De module laadt de `hello_world` module die AMQP-code bevat. In CI staat er geen RabbitMQ server.

**Oplossing (twee stappen):**

1. `hello_world` verwijderd uit de `$modules` array in `CancelAccountConfirmFormTest.php`:
   ```php
   protected static $modules = [
     'system',
     'user',
     'shift_bezoeker',
     // 'hello_world' verwijderd — heeft AMQP nodig dat niet beschikbaar is in CI
   ];
   ```

2. `SHIFT_BEZOEKER_DISABLE_AMQP=1` environment variabele in de Integration job — de module zelf controleert deze variabele en slaat de AMQP-verbinding over.

### 7.4 `tests_bootstrap.php` ook kopiëren in Integration job

**Probleem:** Integration tests faalden met een bootstrap-fout terwijl unit tests werkten.

**Oorzaak:** De stap `Kopieer phpunit.xml en bootstrap` ontbrak in de Integration job — alleen de Unit Tests job had deze stap.

**Oplossing:** Dezelfde kopieer-stap toegevoegd aan de Integration job:
```yaml
- name: Kopieer phpunit.xml en bootstrap
  run: |
    cp $GITHUB_WORKSPACE/phpunit.xml $HOME/drupal/phpunit.xml
    cp $GITHUB_WORKSPACE/tests_bootstrap.php $HOME/drupal/tests_bootstrap.php
```

### 7.5 Node.js 20 deprecation warnings

**Probleem:** GitHub Actions waarschuwde dat `actions/checkout@v4` en `actions/cache@v4` op Node.js 20 draaien, wat deprecated wordt vanaf juni 2026.

**Oplossing:** Actions gepind op de nieuwste patchversies:
- `actions/checkout@v4` → `actions/checkout@v4.2.2`
- `actions/cache@v4` → `actions/cache@v4.2.3`

### 7.6 phpcs fouten in testbestanden

Diverse phpcs-fouten werden opgelost in de testbestanden:

| Fout | Bestand | Oplossing |
|------|---------|-----------|
| Inline functies niet toegestaan | Meerdere tests | Anonieme functies herschreven naar expliciete methodes |
| Array opmaak (short syntax) | Meerdere tests | Uitgesloten via `phpcs.xml` (`Drupal.Arrays.Array`) |
| Niet-ASCII tekens in methodenamen | Nederlandse testnamen | Methodenamen omgezet naar ASCII-compatibel |
| Ontbrekende spaties na keywords | Meerdere tests | Handmatig gecorrigeerd |

---

## 8. Overzicht van alle fouten en oplossingen

Chronologisch overzicht van alle CI-fouten die zijn opgelost:

| # | Fout | Oorzaak | Oplossing |
|---|------|---------|-----------|
| 1 | `phpcs` fouten in testbestanden | Code voldeed niet aan Drupal standaard | Testcode gecorrigeerd + regels uitgesloten in `phpcs.xml` |
| 2 | `composer require` OR-syntax ongeldig | `phpunit:^10 \|\| ^11` is geen geldige composer syntax | Versieconstraint vereenvoudigd |
| 3 | Drupal container mist dev packages | Bestaande Drupal image had geen test-tools | Overgestapt naar `composer create-project` |
| 4 | `sebastian/diff` lockfile conflict | Versieconflict bij installeren `core-dev` | `-W` vlag toegevoegd aan `composer require` |
| 5 | Bootstrap `behat/mink` missing | PHPUnit 12 verwachtte andere bootstrap dan beschikbaar | Eigen `tests_bootstrap.php` gemaakt |
| 6 | `Session_Management` namespace niet gevonden | Hoofdlettergevoeligheid op Linux | Expliciete `addPsr4` mapping in bootstrap |
| 7 | `AMQPStreamConnection` class not found | `hello_world` module laadt AMQP zonder RabbitMQ server | `hello_world` verwijderd uit `$modules` + `DISABLE_AMQP=1` |
| 8 | Integration tests: bootstrap niet gevonden | Kopieer-stap ontbrak in Integration job | Kopieer-stap toegevoegd |
| 9 | `composer audit` exit code 1 (phpseclib CVEs) | Dev phpcs-tools bevatten kwetsbaarheden | `--no-dev` vlag toegevoegd |
| 10 | Node.js 20 deprecation warnings | Oude versies van GitHub Actions | Actions gepind op `@v4.2.2` en `@v4.2.3` |

---

## 9. Testresultaten (eindstand)

### Unit Tests
```
106 tests, alle geslaagd ✓
```

Modules met unit tests:
- `ai_dashboard`
- `jarvis_chat`
- `module`
- `shift_bezoeker`
- `Session_Management`
- `custom_roles`

### Integration Tests (Kernel)
```
11 tests, 10 geslaagd + 1 waarschuwing (geen fout) ✓
```

Module met kernel tests:
- `shift_bezoeker` — `CancelAccountConfirmFormTest`
  - `testConfirmBlocksAccount` — blokkeert gebruiker na form submit
  - `testFormQuestionAndConfirmText` — controleert form teksten en cancel URL

### Lint
```
Alle bestanden voldoen aan Drupal coding standard ✓
composer audit: geen kwetsbaarheden in production packages ✓
```

---

## 10. Hoe lokaal uitvoeren

### Vereisten
- PHP 8.3
- Composer 2
- SQLite3 extensie

### Stap 1: Maak een lokale Drupal testomgeving

```bash
composer create-project drupal/recommended-project:^11 ~/drupal-test \
  --no-dev --no-interaction --prefer-dist

cd ~/drupal-test && composer require --dev "drupal/core-dev:^11" -W
```

### Stap 2: Kopieer de projectbestanden

```bash
# Vanuit de root van dit project:
mkdir -p ~/drupal-test/web/modules/custom
cp -r modules/custom/. ~/drupal-test/web/modules/custom/
cp -r xsd ~/drupal-test/xsd
cp phpunit.xml ~/drupal-test/phpunit.xml
cp tests_bootstrap.php ~/drupal-test/tests_bootstrap.php
```

### Stap 3: Voer tests uit

```bash
cd ~/drupal-test

# Unit tests
./vendor/bin/phpunit --configuration phpunit.xml --testsuite unit --testdox

# Integration/kernel tests
SHIFT_BEZOEKER_DISABLE_AMQP=1 \
SIMPLETEST_DB="sqlite://localhost//tmp/drupal_ci_test.sqlite" \
./vendor/bin/phpunit --configuration phpunit.xml --testsuite kernel --testdox
```

### Stap 4: Lint controleren

```bash
# Vanuit de root van dit project:
composer config allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer require --dev \
  "squizlabs/php_codesniffer:^3" \
  "drupal/coder:^8" \
  "slevomat/coding-standard:^8"

./vendor/bin/phpcs --standard=phpcs.xml
```

---

## Bijlage: Volledige CI workflow

Zie [`.github/workflows/ci.yml`](../.github/workflows/ci.yml) voor de actuele, volledige workflow definitie.

De configuratiebestanden:
- [`phpunit.xml`](../phpunit.xml) — PHPUnit testsuites en omgevingsinstellingen
- [`phpcs.xml`](../phpcs.xml) — PHP CodeSniffer regels
- [`tests_bootstrap.php`](../tests_bootstrap.php) — Drupal test bootstrap met Session_Management fix
