<?php

/**
 * @file
 * Pages file for the GDPR Consent module.
 */

/**
 * Page callback to list a user's consent agreements.
 * @todo Finish this.
 */
function gdpr_consent_collected_agreements($account) {
  // Or load it with EFQ.
  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'gdpr_consent_agreement');

  $result = $query->execute();

  if (isset($result['gdpr_consent_agreement'])) {
    $consent_ids = array_keys($result['gdpr_consent_agreement']);
    $consent_items = entity_load('gdpr_consent_agreement', $consent_ids);
  }

  return MENU_ACCESS_DENIED;
}
