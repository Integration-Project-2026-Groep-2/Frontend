<?php

namespace Drupal\bedrijf_registratie\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class RegistratieController extends ControllerBase {

  public function gegevensPagina() {
    // Deze variabelen gaan we later vullen met echte data uit de database.
    $bedrijfsnaam = "Bedrijf X"; 
    $ondernemingsnummer = "BE 0123.456.789";
    $email = "info@bedrijfx.be";
    $telefoon = "+32 470 00 00 00";
    $zetel = "straat 1, 1000 Brussel";

    $medewerker_link = Link::fromTextAndUrl(
      $this->t('+ Medewerker toevoegen'),
      Url::fromRoute('bedrijf_registratie.medewerker_toevoegen')
    )->toRenderable();
    $medewerker_link['#attributes']['class'] = ['button'];

    return [
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Uw Bedrijfsgegevens'),
      ],
      'gegevens' => [
        '#theme' => 'item_list',
        '#items' => [
          $this->t('Bedrijfsnaam: @value', ['@value' => $bedrijfsnaam]),
          $this->t('Ondernemingsnummer: @value', ['@value' => $ondernemingsnummer]),
          $this->t('E-mailadres: @value', ['@value' => $email]),
          $this->t('Telefoonnummer: @value', ['@value' => $telefoon]),
          $this->t('Maatschappelijke zetel: @value', ['@value' => $zetel]),
        ],
      ],
      'medewerker_link' => $medewerker_link,
    ];
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
    $last_updated = '2026-05-06';

    return [
      '#type' => 'markup',
      '#markup' => '
        <div class="terms-content">
        <h1>' . $this->t('Algemene Voorwaarden') . '</h1>
        <p><em>' . $this->t('Laatst bijgewerkt op: @date', ['@date' => $last_updated]) . '</em></p>

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
        </div>
      ',
    ];
  }

}