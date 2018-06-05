<?php

namespace Drupal\gdpr_fields;

use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Url;
use Drupal\ctools\Plugin\RelationshipManager;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;

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
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * Constructs a GDPRCollector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\ctools\Plugin\RelationshipManager $relationship_manager
   *   The ctools relationship manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManager $entity_type_manager, RelationshipManager $relationship_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->relationshipManager = $relationship_manager;
    $this->entityFieldManager = $entity_field_manager;
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

    // @todo Add way of excluding irrelevant entity types.

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
      list($type, , ,) = explode(':', $definition_id);

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
   * Get entity value tree for GDPR entities.
   *
   * @param array $entity_list
   *   List of all gotten entities keyed by entity type and bundle id.
   * @param string $entity_type
   *   The entity type id.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The fully loaded entity for which values are gotten.
   */
  public function getValueEntities(array &$entity_list, $entity_type, EntityInterface $entity) {
    $definition = $this->entityTypeManager->getDefinition($entity_type);

    if ($definition instanceof ConfigEntityTypeInterface) {
      return;
    }

    // @todo Add way of excluding irrelevant entity types.

    // Check for recursion.
    if (isset($entity_list[$entity_type][$entity->id()])) {
      return;
    }

    // Set entity.
    $entity_list[$entity_type][$entity->id()] = $entity;

    // Find relationships.
    $context_definition = new ContextDefinition("entity:{$entity_type}");

    // @todo Error handling for broken bundles. (Eg. file module).
    if ($entity->bundle() != 'undefined') {
      $context_definition->addConstraint('Bundle', [$entity->bundle()]);
    }
    $context = new Context($context_definition);
    $definitions = $this->relationshipManager->getDefinitionsForContexts([$context]);

    foreach ($definitions as $definition_id => $definition) {
      list($type, $definition_entity, $related_entity_type,) = explode(':', $definition_id);

      // Ignore entity revisions for now.
      if ($definition_entity == 'entity_revision') {
        continue;
      }

      // Ignore links back to gdpr_task.
      // @todo Remove this once we have solved how to deal with ignored/excluded relationships
      if ($related_entity_type == 'gdpr_task' || $related_entity_type == 'message') {
        continue;
      }

      if ($type == 'typed_data_entity_relationship') {
        /* @var \Drupal\ctools\Plugin\Relationship\TypedDataEntityRelationship $plugin */
        $plugin = $this->relationshipManager->createInstance($definition_id);
        $plugin->setContextValue('base', $entity);

        $relationship = $plugin->getRelationship();
        if ($relationship->hasContextValue()) {
          $relationship_entity = $relationship->getContextValue();
          $this->getValueEntities($entity_list, $relationship_entity->getEntityTypeId(), $relationship_entity);
        }
      }
      elseif ($type == 'typed_data_entity_relationship_reverse') {
        /* @var \Drupal\gdpr_fields\Plugin\Relationship\TypedDataEntityRelationshipReverse $plugin */
        $plugin = $this->relationshipManager->createInstance($definition_id);
        $plugin->setContextValue('base', $entity);

        $relationship = $plugin->getRelationship();
        if ($relationship->hasContextValue()) {
          $relationship_entity = $relationship->getContextValue();
          $this->getValueEntities($entity_list, $relationship_entity->getEntityTypeId(), $relationship_entity);
        }
      }
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
  public function listFields($entity_type = 'user', $bundle_id, $filters) {
    $storage = $this->entityTypeManager->getStorage($entity_type);
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type);
    $bundle_type = $entity_definition->getBundleEntityType();
    $gdpr_settings = GdprFieldConfigEntity::load($entity_type);

    // Create a blank entity.
    $values = [];
    if ($entity_definition->hasKey('bundle')) {
      $bundle_key = $entity_definition->getKey('bundle');
      $values[$bundle_key] = $bundle_id;
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle_id);

    // Get fields for entity.
    $fields = [];

    // If the 'Filter out entities where all fields are not configured' option
    // is set, return an empty array if GDPR is not configured for the entity.
    if ($filters['empty'] && $gdpr_settings == NULL) {
      return $fields;
    }

    $has_at_least_one_configured_field = FALSE;

    foreach ($field_definitions as $field_id => $field_definition) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field_definition */
      $key = "$entity_type.$bundle_id.$field_id";
      $route_name = 'gdpr_fields.edit_field';
      $route_params = [
        'entity_type' => $entity_type,
        'bundle_name' => $bundle_id,
        'field_name' => $field_id,
      ];

      if (isset($bundle_key)) {
        $route_params[$bundle_type] = $bundle_id;
      }

      $rta = '0';
      $rtf = '0';

      $label = $field_definition->getLabel();

      // If we're searching by name, check if the label matches search.
      if ($filters['search'] && !stripos($label, $filters['search'])) {
        continue;
      }

      $is_id = $entity_definition->getKey('id') == $field_id;

      $fields[$key] = [
        'title' => $label,
        'type' => $is_id ? 'primary_key' : $field_definition->getType(),
        'gdpr_rta' => 'Not Configured',
        'gdpr_rtf' => 'Not Configured',
        'notes' => '',
        'edit' => '',
        'is_id' => $is_id,
      ];

      if ($entity_definition->get('field_ui_base_route')) {
        $url = Url::fromRoute($route_name, $route_params);

        if ($url->access()) {
          $fields[$key]['edit'] = Link::fromTextAndUrl('edit', $url);
        }
      }

      if ($gdpr_settings != NULL) {
        /* @var \Drupal\gdpr_fields\Entity\GdprField $field_settings */
        $field_settings = $gdpr_settings->getField($bundle_id, $field_id);
        if ($field_settings->configured) {
          $has_at_least_one_configured_field = TRUE;
          $rta = $field_settings->rta;
          $rtf = $field_settings->rtf;

          $fields[$key]['gdpr_rta'] = $field_settings->rtaDescription();
          $fields[$key]['gdpr_rtf'] = $field_settings->rtfDescription();
          $fields[$key]['notes'] = $field_settings->notes;
        }
      }

      // Apply filters.
      if (!empty($filters['rtf']) && !in_array($rtf, $filters['rtf'])) {
        unset($fields[$key]);
      }

      if (!empty($filters['rta']) && !in_array($rta, $filters['rta'])) {
        unset($fields[$key]);
      }
    }

    // Handle the 'Filter out Entities where all fields are not configured'
    // checkbox.
    if ($filters['empty'] && !$has_at_least_one_configured_field) {
      return [];
    }

    return $fields;
  }

  /**
   * List field values on an entity including their GDPR values.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The fully loaded entity for which values are listed.
   * @param array $extra_fields
   *   Add extra fields if required
   *
   * @return array
   *   GDPR entity field value list.
   */
  public function fieldValues($entity_type = 'user', EntityInterface $entity, $extra_fields = []) {
    $entity_definition = $this->entityTypeManager->getDefinition($entity_type);
    $bundle_type = $entity_definition->getBundleEntityType();
    $bundle_id = $entity->bundle();
    if ($bundle_type) {
      $bundle_storage = $this->entityTypeManager->getStorage($bundle_type);
      $bundle_entity = $bundle_storage->load($bundle_id);
      $bundle_label = $bundle_entity == NULL ? '' : $bundle_entity->label();
    }
    else {
      $bundle_label = $entity->getEntityType()->getLabel();
    }

    // Get fields for entity.
    $fields = [];

    $gdpr_config = GdprFieldConfigEntity::load($entity_type);

    if ($gdpr_config == NULL) {
      // No fields have been configured on this entity for GDPR.
      return $fields;
    }

    foreach ($entity as $field_id => $field) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field */
      $field_definition = $field->getFieldDefinition();

      $field_config = $gdpr_config->getField($bundle_id, $field->getName());

      if (!$field_config->enabled) {
        continue;
      }

      $key = "$entity_type.{$entity->id()}.$field_id";

      $fieldValue = $field->getString();
      $fields[$key] = [
        'title' => $field_definition->getLabel(),
        'value' => $fieldValue,
        'entity' => $entity->getEntityType()->getLabel(),
        'bundle' => $bundle_label,
        'notes' => $field_config->notes,
      ];

      if (empty($extra_fields)) {
        continue;
      }

      // Fetch and validate based on field settings.
      if (isset($extra_fields['rta'])) {
        $rta_value = $field_config->rta;

        if ($rta_value && $rta_value !== 'no') {
          $fields[$key]['gdpr_rta'] = $rta_value;
          //$fields[$key]['gdpr_rta_desc'] = $field_config->rtaDescription();
        }
        else {
          unset($fields[$key]);
        }
      }
      if (isset($extra_fields['rtf'])) {
        $rtf_value = $field_config->rtf;

        if ($rtf_value && $rtf_value !== 'no') {
          $fields[$key]['gdpr_rtf'] = $rtf_value;
          //$fields[$key]['gdpr_rtf_desc'] = $field_config->rtfDescription();

          // For 'maybes', provide a link to edit the entity.
          if ($rtf_value == 'maybe') {
            $fields[$key]['link'] = $entity->toLink('Edit', 'edit-form');
          }
          else {
            $fields[$key]['link'] = '';
          }
        }
        else {
          unset($fields[$key]);
        }
      }
    }

    return $fields;
  }

}
