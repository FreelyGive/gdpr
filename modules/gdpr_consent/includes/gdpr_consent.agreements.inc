<?php

/**
 * Page callback to list a user's consent agreements.
 */
function gdpr_consent_collected_agreements($account) {
  // Or load it with EFQ.
  $query = new EntityFieldQuery();
  $query->entityCondition('entity_type', 'gdpr_consent_agreement');
    //->propertyCondition('uid', $account->uid);

  $result = $query->execute();

  if (isset($result['gdpr_consent_agreement'])) {
    $consent_ids = array_keys($result['gdpr_consent_agreement']);
    $consent_items = entity_load('gdpr_consent_agreement', $consent_ids);

  }

  kpr($consent_items);

  return $account->name;
}
