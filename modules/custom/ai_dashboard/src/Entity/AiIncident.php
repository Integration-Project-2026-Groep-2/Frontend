<?php

declare(strict_types=1);

namespace Drupal\ai_dashboard\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[ContentEntityType(
  id: 'ai_incident',
  label: new TranslatableMarkup('AI Incident'),
  base_table: 'ai_incident',
  entity_keys: [
    'id' => 'id',
    'uuid' => 'uuid',
  ],
  handlers: [
    'storage' => 'Drupal\Core\Entity\Sql\SqlContentEntityStorage',
  ],
)]
class AiIncident extends ContentEntityBase {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['event_type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Event type'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 64);

    $fields['correlation_id'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Correlation ID'))
      ->setSetting('max_length', 36);

    $fields['service'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Service'))
      ->setSetting('max_length', 64);

    $fields['severity'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Severity'))
      ->setSetting('max_length', 16);

    $fields['confidence'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Confidence'))
      ->setSetting('max_length', 32);

    $fields['received_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Received at'));

    $fields['processed_at'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(new TranslatableMarkup('Processed at'));

    $fields['payload_json'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Payload JSON'));

    return $fields;
  }

}
