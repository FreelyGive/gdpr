<?php

/**
 * Base class for export UI.
 *
 * @todo Move to a party specific module.
 */
class GDPRSanitizerPartyArchived extends GDPRSanitizerDefault {

  /**
   * {@inheritdoc}
   */
  var $name = 'gdpr_sanitizer_party_archived';

  /**
   * {@inheritdoc}
   */
  var $label = 'Archive party sanitizer';

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, $field = NULL, $wrapper = NULL) {
    return 1;
  }

}
