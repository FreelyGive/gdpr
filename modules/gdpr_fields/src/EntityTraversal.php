<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\gdpr_fields\Entity\GdprField;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;

/**
 * Base class for traversing entities.
 *
 * @package Drupal\gdpr_fields
 */
class EntityTraversal {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Reverse relationship information.
   *
   * @var \Drupal\gdpr_fields\Entity\GdprField[]
   */
  private $reverseRelationshipFields = NULL;

  /**
   * EntityTraversal constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * Traverses the entity relationship tree.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to traverse.
   *
   * @return array
   *   Results collected by the traversal.
   *   By default this will be a nested array. The first dimension is
   *   keyed by entity type and contains an array keyed by  entity ID.
   *   The values will be the entity instances (although this can be changed by
   *   overriding the handleEntity method).
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function traverse(EntityInterface $entity) {
    $progress = [];
    $results = [];
    $this->doTraversalRecursive($entity, $progress, NULL, $results, NULL);
    return $results;
  }

  /**
   * Traverses the entity relationship tree.
   *
   * Calls the handleEntity method for every entity found.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The root entity to traverse.
   * @param array $progress
   *   Tracks which entities have been handled.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function doTraversalRecursive(EntityInterface $entity, array &$progress, $row_id = NULL, array& $results, $parent_config) {
    $entity_type = $entity->getEntityTypeId();

    // If the entity is not fieldable, don't continue.
    if (!$entity instanceof FieldableEntityInterface) {
      return;
    }

    /* @var \Drupal\Core\Entity\FieldableEntityInterface $entity */

    if ($entity_type == 'gdpr_task') {
      // Explicitly make sure we don't traverse any links to gdpr_task
      // even if the user has explicitly included the reference for traversal.
      return;
    }

    // Check for infinite loop.
    if (isset($progress[$entity_type][$entity->id()])) {
      return;
    }

    if (!isset($row_id)) {
      $row_id = $entity->id();
    }

    // Store the entity in progress to make sure we don't get stuck
    // in an infinite loop by processing the same entity again.
    $progress[$entity_type][$entity->id()] = $entity;

    // GDPR config for this entity.
    $config = GdprFieldConfigEntity::load($entity_type);

    // Let subclasses do with the entity. They will add to the $results array.
    $this->processEntity($entity, $config, $row_id, $results, $parent_config);

    // Find relationships from this entity.
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity->bundle());
    foreach ($fields as $field_name => $field_definition) {
      if ($field_definition->getType() == 'entity_reference') {
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
          if ($field_definition->getFieldStorageDefinition()->getCardinality() != 1) {
            $this->doTraversalRecursive($child_entity, $progress, NULL, $results, $field_config);
          }
          else {
            $this->doTraversalRecursive($child_entity, $progress, $row_id, $results, $field_config);
          }
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
          ->condition($relationship['field'] . '.target_id', $entity->id())
          ->execute();

        foreach ($storage->loadMultiple($ids) as $related_entity) {
          $this->doTraversalRecursive($related_entity, $progress, NULL, $results, $relationship['config']);
        }
      }
    }
  }

  /**
   * Handles the entity.
   *
   * By default this just returns the entity instance, but derived classes
   * should override this method if they need to collect additional data on the
   * instance.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to handle.
   * @param \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity $config
   *   GDPR config for this entity.
   * @param array $results
   *   Subclasses should add any data they need to collect to the results array.
   */
  protected function processEntity(FieldableEntityInterface $entity, GdprFieldConfigEntity $config, $row_id, array &$results, GdprField $parent_config = NULL) {

  }

  /**
   * Gets all reverse relationships configured in the system.
   *
   * @return array
   *   Information about reversible relationships.
   */
  protected function getAllReverseRelationships() {
    if ($this->reverseRelationshipFields !== NULL) {
      // Make sure reverse relationships are cached.
      // as this is called many times in the recursion loop.
      return $this->reverseRelationshipFields;
    }

    $this->reverseRelationshipFields = [];
    /* @var \Drupal\gdpr_fields\Entity\GdprFieldConfigEntity $config  */
    foreach (GdprFieldConfigEntity::loadMultiple() as $config) {
      foreach ($config->getAllFields() as $field) {
        if ($field->enabled && $field->isOwner()) {
          foreach ($this->entityFieldManager->getFieldDefinitions($config->id(), $field->bundle) as $field_definition) {
            if ($field_definition->getName() == $field->name && $field_definition->getType() == 'entity_reference') {
              $this->reverseRelationshipFields[] = [
                'entity_type' => $config->id(),
                'bundle' => $field->bundle,
                'field' => $field->name,
                'config' => $field,
                'target_type' => $field_definition->getSetting('target_type'),
              ];
            }
          }
        }
      }
    }

    return $this->reverseRelationshipFields;
  }

  /**
   * Gets the entity bundle label. Useful for display traversal.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the bundle label for.
   *
   * @return string
   *   Bundle label
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getBundleLabel(EntityInterface $entity) {
    $entity_definition = $entity->getEntityType();
    $bundle_type = $entity_definition->getBundleEntityType();

    if ($bundle_type) {
      $bundle_storage = $this->entityTypeManager->getStorage($bundle_type);
      $bundle_entity = $bundle_storage->load($entity->bundle());
      $bundle_label = $bundle_entity == NULL ? '' : $bundle_entity->label();
    }
    else {
      $bundle_label = $entity_definition->getLabel();
    }
    return $bundle_label;
  }

}
