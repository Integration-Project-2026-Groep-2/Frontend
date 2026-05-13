<?php

// Minimale bootstrap voor Drupal unit- en kernel-tests in CI.
// Drupal's eigen bootstrap.php vereist behat/mink (browser-tests); PHPUnit 12
// behandelt bootstrap-fouten als fataal, dus gebruiken wij eigen bootstrap.
if (!defined('DRUPAL_ROOT')) {
  define('DRUPAL_ROOT', __DIR__ . '/web');
}

$loader = require_once __DIR__ . '/vendor/autoload.php';

// Drupal's test-klassen (UnitTestCase, KernelTestBase, ...) zitten in
// web/core/tests/Drupal/ en worden normaal via autoload-dev geregistreerd.
// Als vendor/autoload.php zonder dev-deps werd gegenereerd, ontbreken ze.
// We voegen het pad handmatig toe zodat PHPUnit de klassen kan vinden.
$loader->addPsr4('Drupal\\', [DRUPAL_ROOT . '/core/tests/Drupal']);
