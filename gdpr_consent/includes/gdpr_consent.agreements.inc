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

  //kpr($consent_items);

  return $account->name;
}

/**
 * Callback for /admin/gdpr/agreements/{gdpr_consent_agreement ID} page.
 */
function gdpr_consent_agreement_view_entity($agreement) {
  drupal_set_title($agreement->title);
  $entity = entity_view('gdpr_consent_agreement', array($agreement->id => $agreement), 'default');
  //kpr($entity);

  return $entity;
}

/**
 * Callback for /admin/gdpr/agreements/{gdpr_consent_agreement ID}/{revision ID} page.
 */
function gdpr_consent_agreement_view_revision($agreement) {
  $output = gdpr_consent_agreement_view_entity($agreement);
  drupal_set_title(t('@title revision @id', array('@title' => $agreement->title, '@id' => $agreement->revision_id)));

  return $output;
}
