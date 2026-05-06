<?php

namespace Drupal\hello_world\RabbitMQ\Validation;

/**
 * Maps message type identifiers to their root element name in the master XSD.
 *
 * Er is slechts één XSD-bestand:
 *   web/modules/custom/hello_world/xsd/crm-master.xsd
 *
 * Alle 35 contracts zijn daarin gedefinieerd als aparte root-elementen.
 * Deze registry mapt een intern type-sleutel → het XML root-elementnaam
 * dat in dat contract verwacht wordt.
 *
 * Een nieuw contract toevoegen:
 *   1. Het root-element staat al in crm-master.xsd.
 *   2. Voeg één lijn toe in $elementMap hieronder.
 *   3. Maak een MessageInterface-implementatie met getType() = jouw sleutel.
 */
class XsdRegistry {

  private string $xsdPath;

  /**
   * Interne type-sleutel => XML root-elementnaam (zoals in crm-master.xsd).
   *
   * @var array<string, string>
   */
  private array $elementMap = [
    // ── Outbound (Frontend → CRM) ────────────────────────────────────── //
    'registration'              => 'Registration',           // C1
    'registration_change'       => 'RegistrationChange',     // C2
    'company_created'           => 'CompanyCreated',         // C3

    // ── Heartbeat ────────────────────────────────────────────────────── //
    'heartbeat'                 => 'Heartbeat',              // C7

    // ── Inbound: user-updates van andere services → Drupal ───────────── //
    'user_updated'              => 'UserUpdated',            // C18/C25
    'user_confirmed'            => 'UserConfirmed',          // C13
    'user_deactivated'          => 'UserDeactivated',        // C22/C26
    'user_created'              => 'UserCreated',            // C24

    // ── Inbound: company-updates ──────────────────────────────────────  //
    'company_updated'           => 'CompanyUpdated',         // C19
    'company_confirmed'         => 'CompanyConfirmed',       // C14
    'company_deactivated'       => 'CompanyDeactivated',     // C23

    // ── Mailing ───────────────────────────────────────────────────────  //
    'mailing_user_created'      => 'MailingUserCreated',     // C27
    'mailing_user_updated'      => 'MailingUserUpdated',     // C28
    'mailing_user_deactivated'  => 'MailingUserDeactivated', // C29

    // ── Planning ──────────────────────────────────────────────────────  //
    'planning_user_created'     => 'PlanningUserCreated',    // C30
    'planning_user_updated'     => 'PlanningUserUpdated',    // C31
    'planning_user_deactivated' => 'PlanningUserDeactivated',// C32
    'session_update'            => 'SessionUpdate',          // C11

    // ── Facturatie company ────────────────────────────────────────────  //
    'facturatie_company_created'     => 'FacturatieCompanyCreated',     // C33
    'facturatie_company_updated'     => 'FacturatieCompanyUpdated',     // C34
    'facturatie_company_deactivated' => 'FacturatieCompanyDeactivated', // C35

    // ── Kassa ─────────────────────────────────────────────────────────  //
    'person_lookup_request'  => 'PersonLookupRequest',   // C10a
    'person_lookup_response' => 'PersonLookupResponse',  // C10b
    'payment_confirmed'      => 'PaymentConfirmed',      // C16
    'unpaid_request'         => 'UnpaidRequest',         // C17a
    'unpaid_response'        => 'UnpaidResponse',        // C17b

    // ── Overige ───────────────────────────────────────────────────────  //
    'badge_link'             => 'BadgeLink',             // C12
    'user_conflict'          => 'UserConflict',          // C15
    'bounce_reported'        => 'BounceReported',        // C20
    'invoice_requested'      => 'InvoiceRequested',      // C21
    'warning'                => 'Warning',               // C9
    'mail_request'           => 'MailRequest',           // C6
    'company_request'        => 'CompanyRequest',        // C5a
    'company_response'       => 'CompanyResponse',       // C5b
  ];

  public function __construct() {
    // Twee niveaus omhoog vanuit src/RabbitMQ/Validation/ => module root.
    $moduleRoot    = dirname(__DIR__, 3);
    $this->xsdPath = $moduleRoot . '/xsd/crm-master.xsd';
  }

  /**
   * Geeft het pad naar het master XSD-bestand.
   *
   * @throws \RuntimeException Wanneer het bestand niet gevonden wordt.
   */
  public function getXsdPath(): string {
    if (!file_exists($this->xsdPath)) {
      throw new \RuntimeException(
        'Master XSD niet gevonden op: ' . $this->xsdPath
      );
    }
    return $this->xsdPath;
  }

  /**
   * Geeft de XML root-elementnaam voor een intern type-sleutel.
   *
   * @throws \InvalidArgumentException Wanneer het type niet geregistreerd is.
   */
  public function getRootElement(string $type): string {
    if (!isset($this->elementMap[$type])) {
      throw new \InvalidArgumentException(
        sprintf(
          'Onbekend message type "%s". Geregistreerde types: %s',
          $type,
          implode(', ', array_keys($this->elementMap))
        )
      );
    }
    return $this->elementMap[$type];
  }

  /**
   * TRUE als het type geregistreerd is én het XSD-bestand bestaat.
   */
  public function has(string $type): bool {
    return isset($this->elementMap[$type]) && file_exists($this->xsdPath);
  }

  /**
   * Geeft alle geregistreerde type-sleutels terug (handig voor debugging).
   *
   * @return string[]
   */
  public function types(): array {
    return array_keys($this->elementMap);
  }

}
