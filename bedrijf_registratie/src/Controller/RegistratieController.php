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

}