<?php

/**
 * @file
 * Script to apply the new schema to the Drupal database.
 * Run this with: php apply_schema.php
 * Or if using drush: drush scr apply_schema.php
 */

use Drupal\Core\Database\Database;

// Bootstrapping Drupal if run via CLI directly.
if (PHP_SAPI === 'cli' && !defined('DRUPAL_CORE_NAMESPACE')) {
  // Try to find the Drupal root.
  $dir = __DIR__;
  while ($dir !== DIRECTORY_SEPARATOR && !file_exists($dir . '/core/lib/Drupal.php')) {
    $dir = dirname($dir);
  }
  
  if (file_exists($dir . '/core/lib/Drupal.php')) {
    require_once $dir . '/core/includes/bootstrap.inc';
    // This is a bit complex for a simple script, better to use \Drupal::database() 
    // if we assume this is run in an environment where Drupal is already available.
  }
}

try {
  $sql_file = __DIR__ . '/mariadb_schema.sql';
  if (!file_exists($sql_file)) {
    die("SQL file not found: $sql_file\n");
  }

  $sql = file_get_contents($sql_file);
  $database = \Drupal::database();
  
  // Split the SQL into individual statements.
  // Note: This is a simple split, won't handle complex triggers/procedures but fine for this schema.
  $statements = array_filter(array_map('trim', explode(';', $sql)));

  foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    echo "Executing: " . substr($statement, 0, 50) . "...\n";
    $database->query($statement);
  }

  echo "Schema applied successfully!\n";
}
catch (\Exception $e) {
  echo "Error applying schema: " . $e->getMessage() . "\n";
  exit(1);
}
