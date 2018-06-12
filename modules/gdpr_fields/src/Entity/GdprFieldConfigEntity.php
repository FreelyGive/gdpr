<?php

namespace Drupal\gdpr_fields\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines a GDPR Field configuration entity.
 *
 * @ConfigEntityType(
 *   id = "gdpr_fields_config",
 *   label = @Translation("GDPR Fields"),
 *   config_prefix = "gdpr_fields_config",
 *   admin_permission = "view gdpr fields",
 *   entity_keys = {
 *     "id" = "id"
 *   }
 * )
 */
class GdprFieldConfigEntity extends ConfigEntityBase {

  /**
   * Associative array.
   *
   * Each element is keyed by bundle name and contains an array representing
   * a list of fields.
   *
   * Each field is in turn represented as a nested array.
   *
   * @var array
   */
  public $bundles = [];

  /**
   * Sets a GDPR field's settings.
   *
   * @param \Drupal\gdpr_fields\Entity\GdprField $field
   *   Field settings.
   *
   * @return $this
   */
  public function setField(GdprField $field) {
    $values = $field->toArray();

    $bundle = $values['bundle'];
    $field_name = $values['name'];

    foreach ($values as $key => $value) {
      $this->bundles[$bundle][$field_name][$key] = $value;
    }

    return $this;
  }

  /**
   * Gets field metadata.
   *
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field name.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField
   *   Field metadata.
   */
  public function getField($bundle, $field_name) {
    if (isset($this->bundles[$bundle][$field_name])) {
      $result = $this->bundles[$bundle][$field_name];
      return new GdprField($result);
    }

    return new GdprField([
      'bundle' => $bundle,
      'name' => $field_name,
      'entity_type_id' => $this->id(),
    ]);
  }

  /**
   * Gets all GDPR field settings for this entity type.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField[]
   *   Array of GDPR field settings.
   *   Keys are in the format of "bundle.fieldname".
   */
  public function getAllFields() {
    $results = [];
    foreach ($this->bundles as $bundle_id => $fields_in_bundle) {
      foreach ($fields_in_bundle as $field_name => $field) {
        $results["$bundle_id.$field_name"] = new GdprField($field);
      }
    }
    return $results;
  }

  /**
   * Gets all field configuration for a bundle.
   *
   * @param string $bundle
   *   The bundle.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField[]
   *   Array of fields within this bundle keyed by field name.
   */
  public function getFieldsForBundle($bundle) {
    return array_map(function ($field) {
      return new GdprField($field);
    }, $this->bundles[$bundle]);
  }

}
