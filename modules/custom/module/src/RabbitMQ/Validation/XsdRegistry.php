<?php

namespace Drupal\hello_world\RabbitMQ\Validation;

/**
 * Maps message type identifiers to XSD-file + root element.
 *
 * Twee bronbestanden:
 *  - xsd/frontend-contract.xsd  — Frontend → CRM outbound contracts
 *    (Registration, RegistrationChange, CompanyCreated). Heeft een
 *    targetNamespace urn:frontend:crm:contract en gebruikt lowercase
 *    rolwaarden (visitor, company_contact, spreker, ...).
 *  - xsd/crm-master.xsd         — Master schema voor alle 35 contracts
 *    (zonder namespace, UPPERCASE rolwaarden). Inbound bus-validatie.
 *
 * Een nieuw contract toevoegen:
 *   1. Voeg root-element toe in $elementMap.
 *   2. Optioneel: override XSD in $schemaMap (default = crm-master.xsd).
 *   3. Implementeer MessageInterface met getType() = je sleutel.
 */
class XsdRegistry {

  private string $xsdRoot;

  private const DEFAULT_SCHEMA = 'crm-master.xsd';

  /**
   * Type-sleutel => XML root-elementnaam.
   *
   * @var array<string, string>
   */
  private array $elementMap = [
    // Outbound (Frontend → CRM) — frontend-contract.xsd.
    'registration'              => 'Registration',
    'registration_change'       => 'RegistrationChange',
    'company_created'           => 'CompanyCreated',

    // Heartbeat (apart bestand maar nog niet gebruikt voor validate-pad).
    'heartbeat'                 => 'Heartbeat',

    // Inbound: user-updates van andere services → Drupal.
    'user_updated'              => 'UserUpdated',
    'user_confirmed'            => 'UserConfirmed',
    'user_deactivated'          => 'UserDeactivated',
    'user_created'              => 'UserCreated',

    // Inbound: company-updates.
    'company_updated'           => 'CompanyUpdated',
    'company_confirmed'         => 'CompanyConfirmed',
    'company_deactivated'       => 'CompanyDeactivated',

    // Mailing.
    'mailing_user_created'      => 'MailingUserCreated',
    'mailing_user_updated'      => 'MailingUserUpdated',
    'mailing_user_deactivated'  => 'MailingUserDeactivated',

    // Planning.
    'planning_user_created'     => 'PlanningUserCreated',
    'planning_user_updated'     => 'PlanningUserUpdated',
    'planning_user_deactivated' => 'PlanningUserDeactivated',
    'session_update'            => 'SessionUpdate',

    // Facturatie company.
    'facturatie_company_created'     => 'FacturatieCompanyCreated',
    'facturatie_company_updated'     => 'FacturatieCompanyUpdated',
    'facturatie_company_deactivated' => 'FacturatieCompanyDeactivated',

    // Kassa.
    'person_lookup_request'  => 'PersonLookupRequest',
    'person_lookup_response' => 'PersonLookupResponse',
    'payment_confirmed'      => 'PaymentConfirmed',
    'unpaid_request'         => 'UnpaidRequest',
    'unpaid_response'        => 'UnpaidResponse',

    // Overige.
    'badge_link'             => 'BadgeLink',
    'user_conflict'          => 'UserConflict',
    'bounce_reported'        => 'BounceReported',
    'invoice_requested'      => 'InvoiceRequested',
    'warning'                => 'Warning',
    'mail_request'           => 'MailRequest',
    'company_request'        => 'CompanyRequest',
    'company_response'       => 'CompanyResponse',
  ];

  /**
   * Per-type XSD-bestand. Niet-vermelde types vallen terug op crm-master.xsd.
   *
   * @var array<string, string>
   */
  private array $schemaMap = [
    'registration'        => 'frontend-contract.xsd',
    'registration_change' => 'frontend-contract.xsd',
    'company_created'     => 'frontend-contract.xsd',
  ];

  public function __construct(?string $xsdRoot = NULL) {
    $this->xsdRoot = $xsdRoot ?? self::resolveXsdRoot();
  }

  /**
   * Vindt de xsd/ map. Volgorde:
   *   1. $_ENV['XSD_ROOT']           (test/override)
   *   2. /opt/drupal/xsd             (Dockerfile copy in productie/lokaal)
   *   3. <project-root>/xsd          (PHPUnit op host of bind-mount)
   *   4. <module>/xsd                (legacy fallback)
   */
  private static function resolveXsdRoot(): string {
    if (!empty($_ENV['XSD_ROOT']) && is_dir($_ENV['XSD_ROOT'])) {
      return $_ENV['XSD_ROOT'];
    }
    if (is_dir('/opt/drupal/xsd')) {
      return '/opt/drupal/xsd';
    }
    // From Validation/ → up 6: Validation, RabbitMQ, src, module, custom, modules → Frontend root.
    $projectRoot = dirname(__DIR__, 6) . '/xsd';
    if (is_dir($projectRoot)) {
      return $projectRoot;
    }
    // Last resort — original module-local layout.
    return dirname(__DIR__, 3) . '/xsd';
  }

  /**
   * Pad naar het XSD-bestand voor een gegeven type.
   *
   * @throws \RuntimeException Als het bestand niet bestaat.
   */
  public function getXsdPath(string $type): string {
    $file = $this->schemaMap[$type] ?? self::DEFAULT_SCHEMA;
    $path = $this->xsdRoot . '/' . $file;
    if (!file_exists($path)) {
      throw new \RuntimeException(sprintf('XSD niet gevonden voor type "%s": %s', $type, $path));
    }
    return $path;
  }

  /**
   * XML root-elementnaam voor een type.
   *
   * @throws \InvalidArgumentException Als het type niet geregistreerd is.
   */
  public function getRootElement(string $type): string {
    if (!isset($this->elementMap[$type])) {
      throw new \InvalidArgumentException(
        sprintf('Onbekend message type "%s". Geregistreerde types: %s',
          $type, implode(', ', array_keys($this->elementMap)))
      );
    }
    return $this->elementMap[$type];
  }

  /**
   * TRUE als het type geregistreerd is én het bijhorende XSD-bestand bestaat.
   */
  public function has(string $type): bool {
    if (!isset($this->elementMap[$type])) {
      return FALSE;
    }
    $file = $this->schemaMap[$type] ?? self::DEFAULT_SCHEMA;
    return file_exists($this->xsdRoot . '/' . $file);
  }

  /**
   * @return string[]
   */
  public function types(): array {
    return array_keys($this->elementMap);
  }

}
