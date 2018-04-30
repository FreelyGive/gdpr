<?php

/**
 * Base class for export UI.
 */
class GDPRSanitizerText extends GDPRSanitizerDefault {

  /**
   * {@inheritdoc}
   */
  public $name = 'gdpr_sanitizer_text';

  /**
   * {@inheritdoc}
   */
  public $label = 'Text sanitizer';

  /**
   * {@inheritdoc}
   *
   * @todo See if we can pass in field settings.
   */
  public function sanitize($input, $field = NULL) {
    if ($field != NULL) {
      /* @var EntityValueWrapper $field */
      // @todo Get max length from field settings.
    }

    $value = '';

    if (!empty($input)) {
      // Generate a prefixed random string.
      $rand = new GDPRUtilRandom();
      $value = "anon_" . $rand->string(4);
      // If the value is too long, tirm it.
      if (isset($max_length) && strlen($value) > $max_length) {
        $value = substr($value, 0, $max_length);
      }
    }
    return $value;
  }

}
