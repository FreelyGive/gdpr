<?php

/**
 * Base class for export UI.
 */
class GDPRSanitizerName extends GDPRSanitizerDefault {

  /**
   * {@inheritdoc}
   */
  var $name = 'gdpr_sanitizer_name';

  /**
   * {@inheritdoc}
   */
  var $label = 'Name field sanitizer';

  /**
   * Constant for name length.
   */
  const MIN_LENGTH = 4;

  /**
   * Constant for name length.
   */
  const MAX_LENGTH = 12;

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, $field = NULL, $wrapper = NULL) {
    if (empty($input)) {
      return $input;
    }

    $random = new GDPRUtilRandom();
    return array(
      'given' => 'anon_' . $random->word(rand(self::MIN_LENGTH, self::MAX_LENGTH)),
      'family' => $random->word(rand(self::MIN_LENGTH, self::MAX_LENGTH)),
    );
  }
}
