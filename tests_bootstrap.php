<?php

// Minimale bootstrap voor Drupal unit- en kernel-tests in CI.
// Laadt alleen de autoloader en DRUPAL_ROOT — geen browser-pakketten nodig.
// Drupal's eigen bootstrap.php vereist behat/mink (browser-tests) wat wij
// hier niet installeren; PHPUnit 12 behandelt bootstrap-fouten als fataal.
if (!defined('DRUPAL_ROOT')) {
  define('DRUPAL_ROOT', __DIR__ . '/web');
}

require_once __DIR__ . '/vendor/autoload.php';
