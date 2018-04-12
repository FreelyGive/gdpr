<?php

/**
 * Form for managing a consent agreement entity.
 */
function gdpr_consent_agreement_form($form, &$form_state, $entity = NULL) {
  $form['title'] = array(
    '#title' => t('Title'),
    '#type' => 'textfield',
    '#default_value' => isset($entity->title) ? $entity->title : '',
    '#description' => t('Agreement title.'),
    '#required' => TRUE,
    '#weight' => -50,
  );
  $form['description'] = array(
    '#title' => t('Description'),
    '#type' => 'textfield',
    '#default_value' => isset($entity->description) ? $entity->description : '',
    '#description' => t('Agreement description.'),
  );

  field_attach_form('gdpr_consent_agreement', $entity, $form, $form_state);

  $form['actions'] = array(
    '#type' => 'actions',
    'submit' => array(
      '#type' => 'submit',
      '#value' => isset($entity->id) ? t('Update consent agreement') : t('Save consent agreement'),
    ),
  );

  return $form;
}

/**
 * Submit handler for consent agreement entity form.
 */
function gdpr_consent_agreement_form_submit($form, &$form_state) {
  $entity = entity_ui_form_submit_build_entity($form, $form_state);
  $entity->save();
  drupal_set_message(t('@title agreement has been saved.', array('@title' => $entity->title)));
  $form_state['redirect'] = 'admin/consent';
}
