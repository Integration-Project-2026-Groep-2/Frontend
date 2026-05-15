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

  /**
   * Rendert de privacyverklaring.
   */
  public function privacyPagina() {
    return [
      '#type' => 'markup',
      '#markup' => '
        <div class="terms-content">
        <h1>' . $this->t('Privacyverklaring') . '</h1>

        <h2>' . $this->t('1. Wie zijn wij?') . '</h2>
        <p>' . $this->t('Shift Festival is de verwerkingsverantwoordelijke voor de persoonsgegevens die via dit platform worden verzameld.') . '</p>
        <p><strong>' . $this->t('Contactgegevens:') . '</strong></p>
        <p>' . $this->t('E-mail:') . '</p>
        <p>' . $this->t('Locatie: Anderlecht, België') . '</p>

        <h2>' . $this->t('2. Welke gegevens verzamelen wij?') . '</h2>
        <p>' . $this->t('Wanneer u ons platform gebruikt of zich registreert, kunnen wij de volgende gegevens verwerken:') . '</p>
        <ul>
          <li>' . $this->t('Bedrijfsgegevens: Ondernemingsnummer, bedrijfsnaam en adres.') . '</li>
          <li>' . $this->t('Contactgegevens: Naam, e-mailadres en telefoonnummer van contactpersonen.') . '</li>
          <li>' . $this->t('Accountgegevens: Gebruikersnaam en versleutelde wachtwoorden.') . '</li>
          <li>' . $this->t('Technische gegevens: IP-adres, browsertype en bezoekstatistieken via cookies.') . '</li>
        </ul>

        <h2>' . $this->t('3. Doel van de verwerking') . '</h2>
        <p>' . $this->t('Wij gebruiken uw gegevens uitsluitend voor de volgende doeleinden:') . '</p>
        <ul>
          <li>' . $this->t('Het beheren van uw account en toegang tot het platform.') . '</li>
          <li>' . $this->t('De organisatie en uitvoering van het Shift Festival (bijv. matchmaking tussen bedrijven).') . '</li>
          <li>' . $this->t('Het verzenden van belangrijke updates over het evenement.') . '</li>
          <li>' . $this->t('Het verbeteren van de gebruikerservaring op onze website.') . '</li>
        </ul>

        <h2>' . $this->t('4. Rechtsgrondslag') . '</h2>
        <p>' . $this->t('Wij verwerken gegevens op basis van:') . '</p>
        <ul>
          <li>' . $this->t('Uitvoering van de overeenkomst: Om uw deelname aan het festival mogelijk te maken.') . '</li>
          <li>' . $this->t('Wettelijke verplichting: Voor onze fiscale administratie.') . '</li>
          <li>' . $this->t('Gerechtvaardigd belang: Voor de beveiliging van onze website.') . '</li>
          <li>' . $this->t('Toestemming: Voor het versturen van nieuwsbrieven (indien u zich hiervoor heeft aangemeld).') . '</li>
        </ul>

        <h2>' . $this->t('5. Bewaartermijn') . '</h2>
        <p>' . $this->t('Wij bewaren uw gegevens niet langer dan strikt noodzakelijk. Accountgegevens worden bewaard zolang uw profiel actief is. Financiële gegevens worden conform de Belgische wetgeving 7 jaar bewaard.') . '</p>

        <h2>' . $this->t('6. Delen van gegevens') . '</h2>
        <p>' . $this->t('Uw gegevens worden niet verkocht aan derden. Wij delen enkel gegevens met partners (zoals IT-leveranciers of ticketing-partners) die strikt noodzakelijk zijn voor onze dienstverlening en die voldoen aan de GDPR-wetgeving via verwerkersovereenkomsten.') . '</p>

        <h2>' . $this->t('7. Uw rechten') . '</h2>
        <p>' . $this->t('Onder de GDPR heeft u de volgende rechten:') . '</p>
        <ul>
          <li>' . $this->t('Recht op inzage: U kunt opvragen welke gegevens wij van u hebben.') . '</li>
          <li>' . $this->t('Recht op correctie: U kunt onjuiste gegevens laten aanpassen.') . '</li>
          <li>' . $this->t('Recht op verwijdering: U kunt verzoeken om uw gegevens te laten wissen.') . '</li>
          <li>' . $this->t('Recht op bezwaar: U kunt zich verzetten tegen bepaalde vormen van verwerking.') . '</li>
        </ul>

        <h2>' . $this->t('8. Beveiliging') . '</h2>
        <p>' . $this->t('Wij nemen de beveiliging van uw gegevens serieus en gebruiken moderne technieken zoals SSL-encryptie en beveiligde servers om ongeautoriseerde toegang te voorkomen.') . '</p>

        <h2>' . $this->t('9. Cookies') . '</h2>
        <p>' . $this->t('Onze website maakt gebruik van functionele cookies. U kunt uw cookievoorkeuren beheren via uw browserinstellingen.') . '</p>

        <h2>' . $this->t('10. Contact') . '</h2>
        <p>' . $this->t('Heeft u vragen over deze privacyverklaring? Neem dan contact op met de systeembeheerder via het platform of stuur een e-mail naar ons privacy-team.') . '</p>
        </div>
      ',
    ];
  }

}