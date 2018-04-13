<?php

/**
 * @file
 * Contains the GDPRFieldData class.
 */

/**
 * Class for storing GDPR metadata for fields.
 */
class GDPRFieldData {

  /**
   * The machine name for this field.
   * @var string
   */
  public $name;

  /**
   * Human readable name for the field.
   * @var string
   */
  public $label;

  /**
   * Short description for the field.
   * @var string
   */
  public $description;

  /**
   * Entity type of field.
   * @var string
   */
  public $entity_type;

  /**
   * Entity bundle of field.
   * @var string
   */
  public $entity_bundle;

  /**
   * Field name of field.
   * @var string
   */
  public $field_name;

  /**
   * Whether this finder is disabled.
   */
  public $disabled = FALSE;

  /**
   * Additional settings.
   * @var array
   */
  public $settings;

  /**
   * Create field data from plugin definition.
   *
   * @param array $plugin
   *   An array of duplicates. Each item should be an array with 2 party id.
   *
   * @return static
   *   New field data object.
   */
  public static function createFromPlugin(array $plugin) {
    $field = new static();

    list($type, $name) = explode(':', $plugin['name']);
    list($entity_type, $entity_bundle, $field_name) = explode('|', $name);
    $field->entity_type = $entity_type;
    $field->entity_bundle = $entity_bundle;
    $field->field_name = $field_name;
    $field->name = $name;

    // @todo Should computed properties be removed instead or disabled?
    if (!empty($plugin['computed'])) {
      $field->disabled = TRUE;
    }

//    $field->name = $plugin['name'];
    if (isset($plugin['label'])) {
      $field->label = $plugin['label'];
      $field->setSetting('label', $plugin['label']);
    }
    if (isset($plugin['description'])) {
      $field->description = $plugin['label'];
      $field->setSetting('description', $plugin['description']);
    }

    return $field;
  }

  /**
   * Get a stored setting.
   *
   * @param string $setting
   *   The key of the setting to be fetched.
   * @param mixed|null $default
   *   The default to be returned if not stored.
   *
   * @return mixed|null
   *   The field data setting.
   */
  public function getSetting($setting, $default = NULL) {
    if (isset($this->settings[$setting])) {
      return $this->settings[$setting];
    }

    return $default;
  }

  /**
   * Get a stored setting.
   *
   * @param string $setting
   *   The key of the setting to be stored.
   * @param mixed $value
   *   The value to be stored.
   *
   * @return $this
   */
  public function setSetting($setting, $value) {
    $this->settings[$setting] = $value;
    return $this;
  }


  public function getValue($user) {
    return $user->name;
  }

}
