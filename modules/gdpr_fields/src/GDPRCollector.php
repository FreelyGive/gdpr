<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Link;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
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
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private $entityFieldManager;

  /**
   * Bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private $bundleInfo;

  /**
   * Reverse relationship information.
   *
   * @var \Drupal\gdpr_fields\Entity\GdprField[]
   */
  private $reverseRelationshipFields = NULL;

  /**
   * Constructs a GDPRCollector object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   Bundle info.
   */
  public function __construct(EntityTypeManager $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->bundleInfo = $bundle_info;
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
  public function getEntities(array &$entity_list, $entity_type, EntityInterface $entity) {
    $definition = $this->entityTypeManager->getDefinition($entity_type);

    if ($definition instanceof ConfigEntityTypeInterface) {
      return;
    }

    if ($entity_type == 'gdpr_task') {
      // Explicitly make sure we don't traverse any links to gdpr_task
      // even if the user has explicitly included the reference for traversal.
      return;
    }

    // Check for recursion.
    if (isset($entity_list[$entity_type][$entity->id()])) {
      return;
    }

    // Set entity.
    $entity_list[$entity_type][$entity->id()] = $entity;

    // GDPR config for this entity.
    $config = GdprFieldConfigEntity::load($entity_type);

    // Find relationships from this entity.
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity->bundle());
    foreach ($fields as $field_name => $field_definition) {
      if ($field_definition->getType() == 'entity_reference') {
        $target_type = $field_definition->getSetting('target_type');

        // If there is no value, we don't need to proceed.
        $referenced_entities = $entity->get($field_name)->referencedEntities();
        if (empty($referenced_entities)) {
          continue;
        }

        // If this field has not been configured for GDPR, skip it.
        /* @var \Drupal\gdpr_fields\Entity\GdprField $field_config */
        $field_config = $config->getField($entity->bundle(), $field_name);
        if (!$field_config->enabled) {
          continue;
        }

        // Skip if relationship traversal for this property has been disabled.
        if (!$field_config->includeRelatedEntities()) {
          continue;
        }

        // Loop through each child entity and traverse their relationships too.
        foreach ($referenced_entities as $child_entity) {
          $this->getEntities($entity_list, $target_type, $child_entity);
        }
      }
    }

    // Now we want to look up any reverse relationships that have been marked
    // as owner.
    foreach ($this->getAllReverseRelationships() as $relationship) {
      if ($relationship['target_type'] == $entity_type) {
        // Load all instances of this entity where the field value is the same
        // as our entity's ID.
        $storage = $this->entityTypeManager->getStorage($relationship['entity_type']);

        $ids = $storage->getQuery()
          ->condition($relationship['field'] . 'target_id')
          ->execute();

        foreach ($storage->loadMultiple($ids) as $related_entity) {
          $this->getEntities($entity_list, $relationship['entity_type'], $related_entity);
        }
      }
    }
  }

  /**
   * List fields on entity including their GDPR values.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type id.
   * @param string $bundle_id
   *   The entity bundle id.
   * @param array $filters
   *   Array of filters with following keys:
   *   'empty' => filter out entities where all fields are not configured.
   *   'rtf' => only include fields where RTF is configured.
   *   'rta' => only include fields where RTA is configured.
   *   'search' => only include fields whose name match.
   *
   * @return array
   *   GDPR entity field list.
   */
  public function listFields($entity_type, $bundle_id, array $filters) {
    $bundle_type = $entity_type->getBundleEntityType();
    $gdpr_settings = GdprFieldConfigEntity::load($entity_type->id());

    // Create a blank entity.
    $values = [];
    if ($entity_type->hasKey('bundle')) {
      $bundle_key = $entity_type->getKey('bundle');
      $values[$bundle_key] = $bundle_id;
    }

    // @todo explicitly skip commerce_order_item for now as they break bundles
    if ($entity_type->id() == 'commerce_order_item') {
      return [];
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type->id(), $bundle_id);

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
      $key = "{$entity_type->id()}.$bundle_id.$field_id";
      $route_name = 'gdpr_fields.edit_field';
      $route_params = [
        'entity_type' => $entity_type->id(),
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

      $is_id = $entity_type->getKey('id') == $field_id;

      $fields[$key] = [
        'title' => $label,
        'type' => $is_id ? 'primary_key' : $field_definition->getType(),
        'gdpr_rta' => 'Not Configured',
        'gdpr_rtf' => 'Not Configured',
        'notes' => '',
        'edit' => '',
        'is_id' => $is_id,
      ];

      if ($entity_type->get('field_ui_base_route')) {
        $url = Url::fromRoute($route_name, $route_params);

        if ($url->access()) {
          $fields[$key]['edit'] = Link::fromTextAndUrl('edit', $url);
        }
      }

      if ($gdpr_settings != NULL) {
        /* @var \Drupal\gdpr_fields\Entity\GdprField $field_settings */
        $field_settings = $gdpr_settings->getField($bundle_id, $field_id);
        if ($field_settings->configured && $field_settings->enabled) {
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

  /**
   * Check whether a property can be removed.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The property info.
   * @param null $error_message
   *   A variable to fill with an error message.
   *
   * @return bool
   *   TRUE if the property can be removed, FALSE if not.
   */
  public static function propertyCanBeRemoved(EntityTypeInterface $entity_type, FieldDefinitionInterface $field_definition, &$error_message = NULL) {
    if ($field_definition->isComputed()) {
      $error_message = new TranslatableMarkup('Unable to remove computed field %field.', ['%field' => $field_definition->getName()]);
      return FALSE;
    }

    if ($field_definition->isRequired()) {
      $error_message = new TranslatableMarkup('Unable to remove required field %field.', ['%field' => $field_definition->getName()]);
      return FALSE;
    }

    if ($field_definition->isReadOnly()) {
      $error_message = new TranslatableMarkup('Unable to remove readonly field %field.', ['%field' => $field_definition->getName()]);
      return FALSE;
    }

    if (in_array($field_definition->getName(), $entity_type->getKeys())) {
      $error_message = new TranslatableMarkup('Unable to remove entity key %field.', ['%field' => $field_definition->getName()]);
      return FALSE;
    }

    return TRUE;
  }

  private function getAllReverseRelationships() {
    if ($this->reverseRelationshipFields !== NULL) {
      // Make sure reverse relationships are cached.
      // as this is called many times in the recursion loop.
      return $this->reverseRelationshipFields;
    }

    $this->reverseRelationshipFields = [];
    /* @var \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity $config  */
    foreach (GdprFieldConfigEntity::loadMultiple() as $config) {
      foreach ($config->getAllFields() as $field) {
        if ($field->enabled && $field->owner) {
          foreach ($this->entityFieldManager->getFieldDefinitions($config->id(), $field->bundle) as $field_definition) {
            if ($field_definition->getName() == $field->name && $field_definition->getType() == 'entity_reference') {
              $this->reverseRelationshipFields[] = [
                'entity_type' => $config->id(),
                'bundle' => $field->bundle,
                'field' => $field->name,
                'target_type' => $field_definition->getSetting('target_type'),
              ];
            }
          }
        }
      }
    }

    return $this->reverseRelationshipFields;
  }

}
