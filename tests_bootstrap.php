<?php

// Bootstrap voor Drupal unit- en kernel-tests in CI.
// Laadt Drupal's standaard bootstrap en voegt ontbrekende namespace-mappings toe.
if (!defined('DRUPAL_ROOT')) {
  define('DRUPAL_ROOT', __DIR__ . '/web');
}

// Drupal's standaard test bootstrap (autoloader, namespaces, error handlers).
require __DIR__ . '/web/core/tests/bootstrap.php';

// Session_Management gebruikt gemengde hoofdletters in de PHP-namespace
// maar de info.yml machine-name is lowercase (session_management).
// Op Linux is bestandspaden case-sensitive, dus de autoloader vindt de
// klassen niet automatisch. Expliciete PSR-4 mapping als workaround.
$loader = require __DIR__ . '/vendor/autoload.php';
$loader->addPsr4('Drupal\\Session_Management\\', __DIR__ . '/web/modules/custom/Session_Management/src/');
$loader->addPsr4('Drupal\\Tests\\Session_Management\\', __DIR__ . '/web/modules/custom/Session_Management/tests/src/');
