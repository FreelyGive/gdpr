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
  public $name = 'gdpr_sanitizer_party_archived';

  /**
   * {@inheritdoc}
   */
  public $label = 'Archive party sanitizer';

  /**
   * {@inheritdoc}
   */
  public function sanitize($input, $field = NULL) {
    return 1;
  }

}
