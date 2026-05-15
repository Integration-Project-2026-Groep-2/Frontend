<?php

/**
 * @file
 * Update script to modify the speaker and session_speaker tables.
 * Run this with: drush scr update_speaker_schema.php
 * Or: docker exec -it frontend_drupal php /opt/drupal/web/modules/custom/Session_Management/update_speaker_schema.php
 */

use Drupal\Core\Database\Database;

if (!function_exists('drupal_get_messages')) {
  // If not running via drush, we might need some minimal bootstrap or just use raw PDO if we can find credentials.
  // But usually drush is available in these containers.
}

try {
  $database = \Drupal::database();
  $schema = $database->schema();

  echo "Starting schema update...\n";

  // 1. Update speaker table: rename company to companyId
  if ($schema->tableExists('speaker')) {
    if ($schema->fieldExists('speaker', 'company')) {
      echo "Renaming 'company' to 'companyId' in 'speaker' table...\n";
      $spec = [
        'description' => 'CRM Company ID',
        'type' => 'varchar',
        'length' => 36,
        'not null' => FALSE,
      ];
      $schema->changeField('speaker', 'company', 'companyId', $spec);
      echo "Successfully renamed 'company' to 'companyId'.\n";
    } else {
      echo "'company' field does not exist in 'speaker' table, skipping.\n";
    }
  } else {
    echo "'speaker' table does not exist, skipping.\n";
  }

  // 2. Drop foreign key in session_speaker
  // Drupal's Schema API doesn't have a direct 'dropForeignKey' that works reliably across all drivers without knowing the name.
  // We'll use a raw query to find and drop it.
  if ($schema->tableExists('session_speaker')) {
    echo "Checking for foreign key on 'session_speaker.speaker_id'...\n";
    
    // Get the actual table names (with prefixes if any)
    $connection_options = $database->getConnectionOptions();
    $db_name = $connection_options['database'];
    $session_speaker_table = $database->getFullQualifiedTableName('session_speaker');
    
    // We need the table name without the database prefix for INFORMATION_SCHEMA
    $session_speaker_pure = $session_speaker_table;
    if (strpos($session_speaker_pure, $db_name . '.') === 0) {
      $session_speaker_pure = substr($session_speaker_pure, strlen($db_name) + 1);
    }
    $session_speaker_pure = trim($session_speaker_pure, '`"');

    echo "Table name for metadata check: $session_speaker_pure\n";

    $constraint_query = "
      SELECT CONSTRAINT_NAME 
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
      WHERE TABLE_SCHEMA = :db
      AND TABLE_NAME = :table 
      AND COLUMN_NAME = 'speaker_id' 
      AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    
    $constraints = $database->query($constraint_query, [
      ':db' => $db_name,
      ':table' => $session_speaker_pure
    ])->fetchCol();

    if (!empty($constraints)) {
      foreach ($constraints as $constraint_name) {
        echo "Found foreign key constraint: $constraint_name. Dropping it...\n";
        try {
          // Use backticks for MariaDB/MySQL compatibility
          $database->query("ALTER TABLE `{$session_speaker_pure}` DROP FOREIGN KEY `{$constraint_name}`");
          echo "Successfully dropped foreign key: $constraint_name\n";
        } catch (\Exception $e) {
          echo "Failed to drop $constraint_name: " . $e->getMessage() . "\n";
        }
      }
    } else {
      echo "No foreign key found on 'session_speaker.speaker_id'.\n";
    }
  }

  echo "Schema update completed successfully!\n";
  
  // Clear caches
  if (function_exists('drupal_flush_all_caches')) {
    echo "Clearing caches...\n";
    drupal_flush_all_caches();
    echo "Caches cleared.\n";
  }
}
catch (\Exception $e) {
  echo "Error updating schema: " . $e->getMessage() . "\n";
  exit(1);
}
