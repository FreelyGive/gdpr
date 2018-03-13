<?php

namespace Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerBase;

/**
 * Class TextSanitizer.
 *
 * @GdprSanitizer(
 *   id = "gdpr_random_text_sanitizer",
 *   label = @Translation("Random Text sanitizer"),
 *   description=@Translation("Provides sanitation functionality intended to be
 *   used for text fields.")
 * )
 *
 * @package Drupal\gdpr_dump\Plugin\Gdpr\Sanitizer
 */
class RandomTextSanitizer extends GdprSanitizerBase {

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, FieldItemListInterface $field) {
    $max_length = $field->getDataDefinition()->getSetting("max_length");

    $value = '';

    if (!empty($input)) {
      // Generate a prefixed random string.
      $value = "anon_" . $this->generateRandomString(4);
      // If the value is too long, tirm it.
      if (isset($max_length) && strlen($input) > $max_length) {
        $value = substr(0, $max_length);
      }
    }
    return $value;
  }

  /**
   * Generates a random string of the specified length.
   */
  private function generateRandomString($length) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

}
