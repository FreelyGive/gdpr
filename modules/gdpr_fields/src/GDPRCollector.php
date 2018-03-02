<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Url;
use Drupal\ctools\Plugin\RelationshipManager;

/**
 * Defines a helper class for stuff related to views data.
 */
class GDPRCollector {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The ctools relationship manager.
   *
   * @var \Drupal\ctools\Plugin\RelationshipManager
   */
  protected $relationshipManager;

  /**
   * A prepared list of all fields, keyed by base_table and handler type.
   *
   * @var array
   */
  protected $fields;

  /**
   * Constructs a GDPRCollector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ctools\Plugin\RelationshipManager $relationship_manager
   *   The ctools relationship manager.
   */
  public function __construct(EntityTypeManager $entity_type_manager, RelationshipManager $relationship_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->relationshipManager = $relationship_manager;
  }

  /**
   * Get entity tree for GDPR.
   *
   * @param $entity_list
   *   List of all gotten entities keyed by entity type and bundle id.
   * @param string $entity_type
   *   The entity type id.
   * @param string|null $bundle_id
   *   The entity bundle id, NULL if bundles should be loaded.
   */
  public function getEntities(&$entity_list, $entity_type = 'user', $bundle_id = NULL) {
    $definition = $this->entityTypeManager->getDefinition($entity_type);

    if ($definition instanceof ConfigEntityTypeInterface) {
      return;
    }

    if (!$bundle_id) {
      if ($definition->getBundleEntityType()) {
        $bundle_storage = $this->entityTypeManager->getStorage($definition->getBundleEntityType());
        foreach (array_keys($bundle_storage->loadMultiple()) as $bundle_id) {
          $this->getEntities($entity_list, $entity_type, $bundle_id);
        }
      }
      else {
        $this->getEntities($entity_list, $entity_type, $entity_type);
      }

      return;
    }

    // Check for recursion.
    if (isset($entity_list[$entity_type][$bundle_id])) {
      return;
    }

    // Set entity.
    $entity_list[$entity_type][$bundle_id] = $bundle_id;

    // Find relationships.
    $context = new Context(new ContextDefinition("entity:{$entity_type}"));
    $definitions = $this->relationshipManager->getDefinitionsForContexts([$context]);

    foreach ($definitions as $definition_id => $definition) {
      list($type, , , $field) = explode(':', $definition_id);

      if ($type == 'typed_data_entity_relationship') {
        if (isset($definition['target_entity_type'])) {
          $this->getEntities($entity_list, $definition['target_entity_type']);
        }
      }
      elseif ($type == 'typed_data_entity_relationship_reverse') {
        if (isset($definition['source_entity_type'])) {
          $this->getEntities($entity_list, $definition['source_entity_type']);
        }
      }
      else {
        continue;
      }
    }
  }

  /**
   * Get entity tree for GDPR.
   */
  public function getValueEntities(&$entity_list, $entity_type = 'user', $entity) {
    $definition = $this->entityTypeManager->getDefinition($entity_type);

    if ($definition instanceof ConfigEntityTypeInterface) {
      return;
    }

    // Check for recursion.
    if (isset($entity_list[$entity_type][$entity->id()])) {
      return;
    }

    // Set entity.
    $entity_list[$entity_type][$entity->id()] = $entity;

    // Find relationships.
    $context = new Context(new ContextDefinition("entity:{$entity_type}"));
    $definitions = $this->relationshipManager->getDefinitionsForContexts([$context]);


    foreach ($definitions as $definition_id => $definition) {
      list($type, , , $field) = explode(':', $definition_id);

      if ($type == 'typed_data_entity_relationship') {
        $plugin = $this->relationshipManager->createInstance($definition_id);
        $plugin->setContextValue('base', $entity);

        $test = $plugin->getRelationship();
//        $test = $this->relationshipManager->get;
        if ($test->hasContextValue()) {
         $relationship_entity = $test->getContextValue();
          $this->getValueEntities($entity_list, $relationship_entity->getEntityTypeId(), $relationship_entity);
        }
//        if (isset($definition['target_entity_type'])) {
//          $this->getEntities($entity_list, $definition['target_entity_type']);
//        }
      }
//      elseif ($type == 'typed_data_entity_relationship_reverse') {
//        if (isset($definition['source_entity_type'])) {
//          $this->getEntities($entity_list, $definition['source_entity_type']);
//        }
//      }
      else {
        continue;
      }
    }
  }

  /**
   * List fields on entity including their GDPR values.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $bundle_id
   *   The entity bundle id.
   *
   * @return array
   *   GDPR entity field list.
   */
  public function listFields($entity_type = 'user', $bundle_id) {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type);
    $bundle_type = $entity_definition->getBundleEntityType();

    // Create a blank entity.
    $values = [];
    if ($entity_definition->hasKey('bundle')) {
      $bundle_key = $entity_definition->getKey('bundle');
      $values[$bundle_key] = $bundle_id;
    }
    $entity = $storage->create($values);

    // Get fields for entity.
    $fields = [];
    foreach ($entity as $field_id => $field) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field */
      $field_definition = $field->getFieldDefinition();
      $key = "$entity_type.$bundle_id.$field_id";
      $route_name = "entity.field_config.{$entity_type}_field_edit_form";
      $route_params = [
        'field_config' => $key,
      ];

      if (isset($bundle_key)) {
        $route_params[$bundle_type] = $bundle_id;
      }

      $fields[$key] = [
        'title' => $field_definition->getLabel(),
        'type' => $field_definition->getType(),
        'gdpr_rta' => 'None',
        'gdpr_rtf' => 'None',
        'edit' => '',
      ];

      if ($entity_definition->get('field_ui_base_route')) {
        $url = Url::fromRoute($route_name, $route_params);

        if ($url->access()) {
          $fields[$key]['edit'] = Link::fromTextAndUrl('edit', $url);
        }
      }
      $config = $field_definition->getConfig($bundle_id);

      if ($config->getThirdPartySetting('gdpr_fields', 'gdpr_fields_enabled', FALSE)) {
        $fields[$key]['gdpr_rta'] = $config->getThirdPartySetting('gdpr_fields', 'gdpr_fields_rta', 'no');
        $fields[$key]['gdpr_rtf'] = $config->getThirdPartySetting('gdpr_fields', 'gdpr_fields_rtf', 'no');
      }
    }

    return $fields;
  }

  /**
   * Get a list of fields.
   *
   * @return array
   *   GDPR field list.
   */
  public function fieldValues($entity_type = 'user', $entity) {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type);
    $bundle_type = $entity_definition->getBundleEntityType();

    // Get fields for entity.
    $fields = [];
    foreach ($entity as $field_id => $field) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field */
      $field_definition = $field->getFieldDefinition();
      $key = "$entity_type.{$entity->id()}.$field_id";


      $fieldValue = $field->getString();


      $fields[$key] = [
        'title' => $field_definition->getLabel(),
        'value' => $fieldValue,
        'entity' => $entity->getEntityType()->getLabel(),
        'gdpr_rta' => 'None',
        'gdpr_rtf' => 'None',
        'edit' => '',
      ];

//      if ($entity_definition->get('field_ui_base_route')) {
//        $url = Url::fromRoute($route_name, $route_params);
//
//        if ($url->access()) {
//          $fields[$key]['edit'] = Link::fromTextAndUrl('edit', $url);
//        }
//      }
//
//
//      $config = $field_definition->getConfig($bundle_id);
//
//      if ($config->getThirdPartySetting('gdpr_fields', 'gdpr_fields_enabled', FALSE)) {
//        $fields[$key]['gdpr_rta'] = $config->getThirdPartySetting('gdpr_fields', 'gdpr_fields_rta', 'no');
//        $fields[$key]['gdpr_rtf'] = $config->getThirdPartySetting('gdpr_fields', 'gdpr_fields_rtf', 'no');
//      }
    }

    return $fields;
  }

}
