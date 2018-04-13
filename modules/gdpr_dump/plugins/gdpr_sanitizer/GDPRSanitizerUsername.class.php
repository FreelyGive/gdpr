<?php

/**
 * Base class for export UI.
 */
class GDPRSanitizerUsername extends GDPRSanitizerDefault {

  /**
   * {@inheritdoc}
   */
  var $name = 'gdpr_sanitizer_username';

  /**
   * {@inheritdoc}
   */
  var $label = 'Username sanitizer';

  /**
   * Constant for username length.
   */
  const NAME_LENGTH = 7;

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, $field = NULL) {
    if (empty($input)) {
      return $input;
    }

    $random = new GDPRUtilRandom();
    return $random->name(self::NAME_LENGTH);
  }
}
