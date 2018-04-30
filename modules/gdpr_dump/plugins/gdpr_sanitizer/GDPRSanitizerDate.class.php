<?php

/**
 * Base class for export UI.
 */
class GDPRSanitizerDate extends GDPRSanitizerDefault {

  /**
   * {@inheritdoc}
   */
  var $name = 'gdpr_sanitizer_date';

  /**
   * {@inheritdoc}
   */
  var $label = 'Date sanitizer';

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, $field = NULL) {
    $date = new DateTime('1000-01-01');
    return $date->format('U');
  }

}
