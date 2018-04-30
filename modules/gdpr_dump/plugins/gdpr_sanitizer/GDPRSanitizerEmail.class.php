<?php

/**
 * Base class for export UI.
 */
class GDPRSanitizerEmail extends GDPRSanitizerDefault {

  /**
   * {@inheritdoc}
   */
  public $name = 'gdpr_sanitizer_email';

  /**
   * {@inheritdoc}
   */
  public $label = 'Email sanitizer';

  /**
   * Constant for email length.
   */
  const EMAIL_LENGTH = 12;

  /**
   * {@inheritdoc}
   *
   * @todo See if we can pass in field settings.
   */
  public function sanitize($input, $field = NULL) {
    if (empty($input)) {
      return $input;
    }

    $random = new GDPRUtilRandom();
    return $random->word(self::EMAIL_LENGTH) . '@example.com';
  }

}
