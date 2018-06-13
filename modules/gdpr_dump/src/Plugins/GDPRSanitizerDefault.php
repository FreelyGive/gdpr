<?php

/**
 * @file
 * Contains the GDPRSanitizerDefault class.
 */

/**
 * Class for storing GDPR default sanitizer definition.
 */
class GDPRSanitizerDefault {

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
   * Whether this finder is disabled.
   */
  public $disabled = FALSE;

  /**
   * Additional settings.
   * @var array
   */
  public $settings;


  /**
   * Initialize plugin from definition.
   *
   * @param array $plugin
   *   Values provided by definition.
   *
   * @return static
   */
  public static function create(array $plugin) {
    $sanitzer = new static();
    if (!empty($plugin['name'])) {
      $sanitzer->name = $plugin['name'];
    }

    if (!empty($plugin['label'])) {
      $sanitzer->label = $plugin['label'];
    }

    return $sanitzer;
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

  /**
   * Return the sanitized input.
   *
   * @var int|string $input
   *   The input.
   * @var string|null $field
   *   The field we are acting on.
   * @var \EntityDrupalWrapper $wrapper
   *   The entity wrapper.
   *
   * @return int|string
   *   The sanitized input.
   */
  public function sanitize($input, $field = NULL, $wrapper = NULL) {

    return $input;
  }

}
