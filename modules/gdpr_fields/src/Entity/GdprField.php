<?php

namespace Drupal\gdpr_fields\Entity;

/**
 * Metadata for a GDPR field.
 */
class GdprField {

  /**
   * Indicates a relationship is not enabled for GDPR processing.
   */
  const RELATIONSHIP_DISABLED = 0;

  /**
   * Indicates a relationship should be followed.
   */
  const RELATIONSHIP_FOLLOW = 1;

  /**
   * Indicates this is a reverse relationship.
   *
   * Indicates that the current entity is the owner.
   */
  const RELATIONSHIP_OWNER = 2;

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
   * SARS filename when handling multiple cardinality fields.
   *
   * @var string
   */
  public $sarsFilename = '';


  /**
   * Relationship status.
   *
   * 0 = Disabled
   * 1 = Follow
   * 2 = Owner/Reverse.
   *
   * @var int
   */
  public $relationship = 0;

  /**
   * Entity type.
   *
   * @var string
   */
  public $entityTypeId;

  /**
   * GdprField constructor.
   *
   * @param array $values
   *   Underlying data values for the field.
   */
  public function __construct(array $values = []) {
    $this->bundle = $values['bundle'];
    $this->name = $values['name'];
    $this->entityTypeId = $values['entity_type_id'];

    $this->rtf = array_key_exists('rtf', $values) ? $values['rtf'] : 'no';
    $this->rta = array_key_exists('rta', $values) ? $values['rta'] : 'no';
    $this->enabled = array_key_exists('enabled', $values) ? $values['enabled'] : FALSE;
    $this->anonymizer = array_key_exists('anonymizer', $values) ? $values['anonymizer'] : NULL;
    $this->notes = array_key_exists('notes', $values) ? $values['notes'] : '';
    $this->relationship = array_key_exists('relationship', $values) ? $values['relationship'] : self::RELATIONSHIP_DISABLED;
    $this->sarsFilename = array_key_exists('sars_filename', $values) ? $values['sars_filename'] : '';
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
   * Indicates that this is a reverse relationship.
   *
   * Indicates that the current entity is the owner of the relationship, and
   * traversal should take place from this side, rather than from the root
   * entity.
   *
   * @return bool
   *   True if owner, otherwise false.
   */
  public function isOwner() {
    return $this->relationship === self::RELATIONSHIP_OWNER;
  }

  /**
   * Indicates if the relationship should be followed.
   *
   * @return bool
   *   True if it should be followed, otherwise false.
   */
  public function followRelationship() {
    return $this->relationship === self::RELATIONSHIP_FOLLOW;
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
    if ($this->isOwner()) {
      return FALSE;
    }

    // Only follow the relationship if it's been explicitly enabled.
    if ($this->followRelationship()) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get the options array for right to access field.
   *
   * @return array
   *   Right to access field options array.
   */
  public static function rtaOptions() {
    return [
      'inc' => 'Included',
      'maybe' => 'Maybe included',
      'no' => 'Not included',
    ];
  }

  /**
   * Get the options array for right to be forgotten field.
   *
   * @return array
   *   Right to be forgotten field options array.
   */
  public static function rtfOptions() {
    return [
      'anonymise' => 'Anonymise',
      'remove' => 'Remove',
      'maybe' => 'Maybe included',
      'no' => 'Not included',
    ];
  }

}
