<?php

/**
 * Defines a utility class for creating random data.
 */
class GDPRUtilRandom {

  /**
   * Generates a random string of ASCII characters of codes 32 to 126.
   *
   * The generated string includes alpha-numeric characters and common
   * miscellaneous characters. Use this method when testing general input
   * where the content is not restricted.
   *
   * @param int $length
   *   Length of random string to generate.
   *
   * @return string
   *   Randomly generated string.
   *
   * @see GDPRUtilRandom::name()
   */
  public function string($length = 8) {

    $str = '';
    for ($i = 0; $i < $length; $i++) {
      $str .= chr(mt_rand(32, 126));
    }

    return $str;
  }

  /**
   * Generates a random string containing letters and numbers.
   *
   * The string will always start with a letter. The letters may be upper or
   * lower case. This method is better for restricted inputs that do not
   * accept certain characters. For example, when testing input fields that
   * require machine readable values (i.e. without spaces and non-standard
   * characters) this method is best.
   *
   * @param int $length
   *   Length of random string to generate.
   *
   * @return string
   *   Randomly generated string.
   *
   * @see GDPRUtilRandom::string()
   */
  public function name($length = 8) {
    $values = array_merge(range(65, 90), range(97, 122), range(48, 57));
    $max = count($values) - 1;

    $str = chr(mt_rand(97, 122));
    for ($i = 1; $i < $length; $i++) {
      $str .= chr($values[mt_rand(0, $max)]);
    }

    return $str;
  }

  /**
   * Generate a string that looks like a word (letters only, alternating consonants and vowels).
   *
   * @param int $length
   *   The desired word length.
   *
   * @return string
   */
  public function word($length) {
    mt_srand((double) microtime() * 1000000);

    $vowels = array("a", "e", "i", "o", "u");
    $cons = array("b", "c", "d", "g", "h", "j", "k", "l", "m", "n", "p", "r", "s", "t", "u", "v", "w", "tr",
      "cr", "br", "fr", "th", "dr", "ch", "ph", "wr", "st", "sp", "sw", "pr",
      "sl", "cl", "sh",
    );

    $num_vowels = count($vowels);
    $num_cons = count($cons);
    $word = '';

    while (strlen($word) < $length) {
      $word .= $cons[mt_rand(0, $num_cons - 1)] . $vowels[mt_rand(0, $num_vowels - 1)];
    }

    return substr($word, 0, $length);
  }

}
