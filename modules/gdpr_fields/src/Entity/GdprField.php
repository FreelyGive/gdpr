<?php


namespace Drupal\gdpr_fields\Entity;


class GdprField {

  public $bundle;

  public $name;

  public $enabled = FALSE;

  public $rtf = 'no';

  public $rta = 'no';

  public $sanitizer = '';

  public $notes = '';

  public $configured = FALSE;

  /**
   * GdprField constructor.
   *
   * @param $bundle
   * @param $name
   */
  public function __construct($bundle, $name) {
    $this->bundle = $bundle;
    $this->name = $name;
  }

  public static function create(array $array) {
    $field = new GdprField($array['bundle'], $array['name']);
    $field->rtf = $array['rtf'];
    $field->rta = $array['rta'];
    $field->enabled = $array['enabled'];
    $field->sanitizer = $array['sanitizer'];
    $field->notes = $array['notes'];
    $field->configured = TRUE;
    return $field;
  }

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
}