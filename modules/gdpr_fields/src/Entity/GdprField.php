<?php

namespace Drupal\gdpr_fields\Entity;

/**
 * Metadata for a GDPR field.
 */
class GdprField {

  /**
   * Bundle name.
   *
   * @var string
   */
  public $bundle;

  /**
   * Field name.
   *
   * @var string
   */
  public $name;

  /**
   * Wether GDPR is enabled for this field.
   *
   * @var bool
   */
  public $enabled = FALSE;

  /**
   * Right to Forget setting for this field.
   *
   * @var string
   */
  public $rtf = 'no';

  /**
   * Right to Access setting for this field.
   *
   * @var string
   */
  public $rta = 'no';

  /**
   * Sanitizer to use on this field.
   *
   * @var string
   */
  public $sanitizer = '';

  /**
   * Notes.
   *
   * @var string
   */
  public $notes = '';

  /**
   * Whether this field has been configured for GDPR.
   *
   * This is different for enabled -
   * something can be configured but not enabled.
   *
   * @var bool
   */
  public $configured = FALSE;

  /**
   * SARS filename when handling multiple cardinality fields.
   *
   * @var string
   */
  public $sarsFilename = '';

  /**
   * Whether this relationship should be followed or not.
   *
   * @var bool
   */
  public $noFollow = FALSE;

  /**
   * Whether this is a reverse relationship where this side is the owner.
   *
   * @var bool
   */
  public $owner = FALSE;

  /**
   * GdprField constructor.
   *
   * @param string $bundle
   *   Bundle.
   * @param string $name
   *   Field name.
   */
  public function __construct(string $bundle, string $name) {
    $this->bundle = $bundle;
    $this->name = $name;
  }

  /**
   * Creates a GdprField instance based on array data from the config entity.
   *
   * @param array $values
   *   The undlerying data.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField
   *   The field metadata instance.
   */
  public static function create(array $values) {
    $field = new static($values['bundle'], $values['name']);
    $field->rtf = $values['rtf'];
    $field->rta = $values['rta'];
    $field->enabled = $values['enabled'];
    $field->sanitizer = $values['sanitizer'];
    $field->notes = $values['notes'];
    $field->owner = array_key_exists('owner', $values) ? $values['owner'] : FALSE;
    $field->noFollow = array_key_exists('no_follow', $values) ? $values['no_follow'] : FALSE;
    $field->sarsFilename = array_key_exists('sars_filename', $values) ?  $values['sars_filename'] : '';
    $field->configured = TRUE;
    return $field;
  }

  /**
   * Gets the RTF description.
   *
   * @return string
   *   The description.
   */
  public function rtfDescription() {
    switch ($this->rtf) {
      case 'anonymise':
        return 'Anonymise';

      case 'remove':
        return 'Remove';

      case 'maybe':
        return 'Maybe';

      case 'no':
        return 'Not Included';

      default:
        return 'Not Configured';

    }
  }

  /**
   * Gets the RTA description.
   *
   * @return string
   *   The description.
   */
  public function rtaDescription() {
    switch ($this->rta) {
      case 'inc':
        return 'Included';

      case 'maybe':
        return 'Maybe';

      case 'no':
        return 'Not Included';

      default:
        return 'Not Configured';

    }
  }

  /**
   * Whether to recurse to entities included in this property.
   */
  public function includeRelatedEntities() {
    // If not explicitly a GDPR field, don't recurse.
    if (!$this->enabled) {
      return FALSE;
    }

    // If the field is an owner, don't recurse.
    if ($this->owner) {
      return FALSE;
    }

    // Don't follow if we've been explicitly set not to.
    if ($this->noFollow) {
      return FALSE;
    }

    return TRUE;
  }

}
