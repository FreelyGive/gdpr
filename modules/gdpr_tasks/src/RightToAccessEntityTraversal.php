<?php

namespace Drupal\gdpr_tasks;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Drupal\gdpr_fields\EntityTraversal;

class RightToAccessEntityTraversal extends EntityTraversal {


  private $assets;
  /**
   * {@inheritdoc}
   */
  public function traverse(EntityInterface $entity) {
    $this->assets = [];
    $results = parent::traverse($entity);
    $results['_assets'] = $this->assets;
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  protected function processEntity(FieldableEntityInterface $entity, GdprFieldConfigEntity $config, $row_id, array &$results) {
    $entity_type = $entity->getEntityTypeId();

    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity->bundle());
    $field_configs = $config->getFieldsForBundle($entity->bundle());

    foreach ($fields as $field_id => $field) {
      $field_config = isset($field_configs[$field_id]) ? $field_configs[$field_id] : NULL;

      // If the field is not configured, not enabled,
      // or not enabled for RTF, then skip it.
      if ($field_config === NULL || !$field_config->enabled || !in_array($field_config->rta, ['inc', 'maybe'])) {
        continue;
      }

      $plugin_name = "{$entity_type}|{$entity->bundle()}|{$field_id}";
      $filename = empty($field_config->sarsFilename) ? 'main' : $field_config->sarsFilename;

//      $values = $this->getFieldValues($entity, $field_id);

      $data = [
        'plugin_name' => $plugin_name,
        'entity_type' => $entity_type,
        'entity_id' => $entity->id(),
        'file' => $filename,
        'row_id' => $row_id,
        'label' => $field->getLabel(),
        'value' => $entity->get($field_id)->getString(),
        'notes' => $field_config->notes,
        'rta' => $field_config->rta,
      ];

      $results["{$plugin_name}|{$entity->id()}"] = $data;
    }
  }

//  private function getFieldValues(FieldableEntityInterface $entity, $field_id) {
//
//  }

}
