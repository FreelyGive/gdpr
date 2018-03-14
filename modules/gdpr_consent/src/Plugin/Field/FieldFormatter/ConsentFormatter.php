<?php

namespace Drupal\gdpr_consent\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the gdpr_consent_formatter formatter.
 *
 * @FieldFormatter(
 *   id = "gdpr_consent_formatter",
 *   label = @Translation("GDPR Consent Formatter"),
 *   field_types = {
 *    "gdpr_user_consent"
 *   }
 * )
 */
class ConsentFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];



    return $elements;
  }

}
