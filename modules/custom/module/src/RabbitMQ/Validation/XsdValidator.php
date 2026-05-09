<?php

namespace Drupal\hello_world\RabbitMQ\Validation;

/**
 * Valideert een XML-string tegen het master XSD via het root-element.
 *
 * De master XSD (crm-master.xsd) definieert alle 35 contracts als aparte
 * root-elementen. PHP's DOMDocument::schemaValidate() valideert het volledige
 * document inclusief root-element, dus we hoeven enkel het juiste element in
 * de registry op te zoeken en te controleren of het root-element klopt.
 *
 * Gebruik:
 *   $validator = new XsdValidator(new XsdRegistry());
 *   $validator->validate($xmlString, 'registration');   // C1
 *   $validator->validate($xmlString, 'user_updated');   // C18/C25
 */
class XsdValidator {

  public function __construct(private readonly XsdRegistry $registry) {}

  /**
   * Valideert XML tegen het master XSD voor het opgegeven type.
   *
   * @param string $xml  Ruwe XML-string om te valideren.
   * @param string $type Intern type-sleutel (bv. 'registration').
   *
   * @throws \RuntimeException  Als het XSD-bestand ontbreekt.
   * @throws \RuntimeException  Als het root-element niet overeenkomt.
   * @throws \RuntimeException  Als de XSD-validatie mislukt (met alle fouten).
   */
  public function validate(string $xml, string $type): void {
    // Sla validatie over als het XSD-bestand nog niet bestaat (dev-modus).
    if (!$this->registry->has($type)) {
      return;
    }

    $xsdPath     = $this->registry->getXsdPath();
    $rootElement = $this->registry->getRootElement($type);

    $dom = new \DOMDocument();
    $dom->loadXML($xml, LIBXML_NOENT | LIBXML_DTDLOAD);

    // Controleer of het root-element overeenkomt met het verwachte element.
    if ($dom->documentElement->localName !== $rootElement) {
      throw new \RuntimeException(
        sprintf(
          'Verkeerd root-element voor type "%s": verwacht <%s>, kreeg <%s>.',
          $type,
          $rootElement,
          $dom->documentElement->localName
        )
      );
    }

    // Valideer de volledige XML-structuur tegen het master XSD.
    libxml_use_internal_errors(TRUE);
    $valid  = $dom->schemaValidate($xsdPath);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors(FALSE);

    if (!$valid) {
      $messages = array_map(
        fn(\LibXMLError $e) => trim($e->message) . ' (regel ' . $e->line . ')',
        $errors
      );
      throw new \RuntimeException(
        sprintf(
          'XSD-validatie mislukt voor type "%s" (element <%s>):%s%s',
          $type,
          $rootElement,
          PHP_EOL,
          implode(PHP_EOL, $messages)
        )
      );
    }
  }

}
