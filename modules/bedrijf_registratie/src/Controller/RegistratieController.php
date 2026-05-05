<?php

namespace Drupal\bedrijf_registratie\Controller;

use Drupal\Core\Controller\ControllerBase;

class RegistratieController extends ControllerBase {

  public function gegevensPagina() {
    // Deze variabelen gaan we later vullen met echte data uit de database.
    $bedrijfsnaam = "Bedrijf X"; 
    $ondernemingsnummer = "BE 0123.456.789";
    $email = "info@bedrijfx.be";
    $telefoon = "+32 470 00 00 00";
    $zetel = "straat 1, 1000 Brussel";

    $output = [
      '#type' => 'markup',
      '#markup' => '<h2>' . $this->t('Uw Bedrijfsgegevens') . '</h2>' .
                   '<ul>' .
                   '<li><strong>' . $this->t('Bedrijfsnaam') . ':</strong> ' . $bedrijfsnaam . '</li>' .
                   '<li><strong>' . $this->t('Ondernemingsnummer') . ':</strong> ' . $ondernemingsnummer . '</li>' .
                   '<li><strong>' . $this->t('E-mailadres') . ':</strong> ' . $email . '</li>' .
                   '<li><strong>' . $this->t('Telefoonnummer') . ':</strong> ' . $telefoon . '</li>' .
                   '<li><strong>' . $this->t('Maatschappelijke zetel') . ':</strong> ' . $zetel . '</li>' .
                   '</ul>' . 
                   '<p><a href="/bedrijf/medewerker/toevoegen" class="button">' . $this->t('+ Medewerker toevoegen') . '</a></p>' ,
                   

    ];
    return $output;
  }
  public function sessiePagina() {
    return [
      '#markup' => '<h2>' . $this->t('Sessie Overzicht') . '</h2><p>' . $this->t('Hier komen de geplande sessies te staan.') . '</p>',
    ];
  }

  public function betalingPagina() {
    return [
      '#markup' => '<h2>' . $this->t('Betalingsgeschiedenis') . '</h2><p>' . $this->t('Een overzicht van uw facturen en betalingen.') . '</p>',
    ];
  }

  public function accountPagina() {
    return [
      '#markup' => '<h2>' . $this->t('Accountinstellingen') . '</h2><p>' . $this->t('Beheer hier uw inloggegevens en voorkeuren.') . '</p>',
    ];
  }
  /**
   * Rendert de pagina met Algemene Voorwaarden.
   */
  public function termsPagina() {
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['terms-content']],
      '#markup' => '
        <h1>' . $this->t('Algemene Voorwaarden') . '</h1>
        <p><em>' . $this->t('Laatst bijgewerkt op: ') . date('d-m-Y') . '</em></p>

        <h2>' . $this->t('1. Toepasselijkheid') . '</h2>
        <p>' . $this->t('Deze algemene voorwaarden zijn van toepassing op elk gebruik van ons platform, zowel door incidentele bezoekers als door geregistreerde bedrijven en hun medewerkers.') . '</p>

        <h2>' . $this->t('2. Registratie en Accounts') . '</h2>
        <p>' . $this->t('Bedrijven dienen bij registratie correcte en volledige gegevens te verstrekken, zoals een geldig ondernemingsnummer en adresgegevens. Het account is strikt persoonlijk voor de organisatie.') . '</p>

        <h2>' . $this->t('3. Gebruik van het Platform') . '</h2>
        <p>' . $this->t('Het is niet toegestaan om het platform te gebruiken voor activiteiten die in strijd zijn met de Belgische wetgeving. Gebruikers zijn verantwoordelijk voor de vertrouwelijkheid van hun inloggegevens.') . '</p>

        <h2>' . $this->t('4. Privacy en Gegevens') . '</h2>
        <p>' . $this->t('Wij verwerken persoonsgegevens in overeenstemming met de GDPR (AVG). Door gebruik te maken van onze diensten, gaat u akkoord met de verwerking van deze gegevens zoals beschreven in onze privacyverklaring.') . '</p>

        <h2>' . $this->t('5. Aansprakelijkheid') . '</h2>
        <p>' . $this->t('Hoewel wij streven naar een foutloze werking van het systeem, kunnen wij niet aansprakelijk worden gesteld voor eventuele tijdelijke onbeschikbaarheid of verlies van gegevens door technische storingen.') . '</p>

        <h2>' . $this->t('6. Wijzigingen') . '</h2>
        <p>' . $this->t('Wij behouden ons het recht voor om deze voorwaarden op elk moment te wijzigen. Bij substantiële wijzigingen zullen geregistreerde gebruikers hiervan op de hoogte worden gesteld.') . '</p>

        <p><br><em>' . $this->t('Bij vragen over deze voorwaarden kunt u contact opnemen met de systeembeheerder.') . '</em></p>
      ',
    ];
  }

}