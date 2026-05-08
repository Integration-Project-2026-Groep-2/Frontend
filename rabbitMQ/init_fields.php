<?php

define('DRUPAL_ROOT', '/opt/drupal/web');
chdir(DRUPAL_ROOT);

$autoloader = require DRUPAL_ROOT . '/autoload.php';

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$request = Request::createFromGlobals();
$kernel  = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$kernel->preHandle($request);

function ensure_field(string $entity_type, string $bundle, string $field_name, string $type, string $label): void {
  $storage = FieldStorageConfig::loadByName($entity_type, $field_name);
  if (!$storage) {
    FieldStorageConfig::create([
      'field_name'  => $field_name,
      'entity_type' => $entity_type,
      'type'        => $type,
      'cardinality' => 1,
    ])->save();
    echo "Storage voor '$field_name' aangemaakt.\n";
  }

  $field = FieldConfig::loadByName($entity_type, $bundle, $field_name);
  if (!$field) {
    FieldConfig::create([
      'field_name'  => $field_name,
      'entity_type' => $entity_type,
      'bundle'      => $bundle,
      'label'       => $label,
    ])->save();
    echo "Veld '$field_name' toegevoegd aan '$bundle'.\n";
  }
}

// Zorg ervoor dat alle CRM-gerelateerde velden bestaan op de User entity.
ensure_field('user', 'user', 'field_crm_id',       'string',  'CRM ID');
ensure_field('user', 'user', 'field_first_name',   'string',  'First Name');
ensure_field('user', 'user', 'field_surname',      'string',  'Last Name');
ensure_field('user', 'user', 'field_phone',        'string',  'Phone');
ensure_field('user', 'user', 'field_company_id',   'string',  'Company ID');
ensure_field('user', 'user', 'field_badge_code',   'string',  'Badge Code');
ensure_field('user', 'user', 'field_gdpr_consent', 'boolean', 'GDPR Consent');

echo "Alle benodigde user velden gecontroleerd en aangemaakt indien nodig.\n";
