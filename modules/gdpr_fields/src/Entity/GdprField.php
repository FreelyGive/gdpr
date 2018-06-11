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
   * Whether GDPR is enabled for this field.
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
   * Anonymizer to use on this field.
   *
   * @var string
   */
  public $anonymizer = '';

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
  public $follow = FALSE;

  /**
   * Whether this is a reverse relationship where this side is the owner.
   *
   * @var bool
   */
  public $owner = FALSE;

  /**
   * Entity type.
   *
   * @var string
   */
  public $entityTypeId;

  /**
   * GdprField constructor.
   *
   * @param string $bundle
   *   Bundle.
   * @param string $name
   *   Field name.
   * @param string $entity_type_id
   *   The entity type that this GDPR field is tied to.
   */
  public function __construct($bundle, $name, $entity_type_id) {
    $this->bundle = $bundle;
    $this->name = $name;
    $this->entityTypeId = $entity_type_id;
  }

  /**
   * Creates a GdprField instance based on array data from the config entity.
   *
   * @param array $values
   *   The underlying data.
   *
   * @return \Drupal\gdpr_fields\Entity\GdprField
   *   The field metadata instance.
   */
  public static function create(array $values) {
    $field = new static($values['bundle'], $values['name'], $values['entity_type_id']);
    $field->rtf = $values['rtf'];
    $field->rta = $values['rta'];
    $field->enabled = $values['enabled'];
    $field->anonymizer = $values['anonymizer'];
    $field->notes = $values['notes'];
    $field->owner = array_key_exists('owner', $values) ? $values['owner'] : FALSE;
    $field->follow = array_key_exists('follow', $values) ? $values['follow'] : FALSE;
    $field->sarsFilename = array_key_exists('sars_filename', $values) ? $values['sars_filename'] : '';
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
      case 'anonymize':
        return 'Anonymize';

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
    if ($this->follow) {
      return TRUE;
    }

    return FALSE;
  }

}
