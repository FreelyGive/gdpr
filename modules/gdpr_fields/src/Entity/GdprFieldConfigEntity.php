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
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field.
   * @param array $values
   *   Additional values. Keys should be enabled, rtf, rta, anonymizer, notes.
   *
   * @return $this
   */
  public function setField($bundle, $field_name, array $values) {
    $values['bundle'] = $bundle;
    $values['name'] = $field_name;
    $values['entity_type_id'] = $this->id();

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
      return GdprField::create($result);
    }

    return new GdprField($bundle, $field_name, $this->id());
  }

  /**
   * Gets all GDPR field settings for this entity type.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField[]
   *   Array of GDPR field settings.
   */
  public function getAllFields() {
    $results = [];
    foreach ($this->bundles as $fields_in_bundle) {
      foreach ($fields_in_bundle as $field) {
        $results[] = GdprField::create($field);
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
   *   Array of fields within this bundle.
   */
  public function getFieldsForBundle($bundle) {
    return array_map(function ($field) {
      return GdprField::create($field);
    }, $this->bundles[$bundle]);
  }

}
