<?php

/**
 * @file
 * Plugin definition for CTools Export UI integration.
 */

$plugin = array(
  'schema' => 'gdpr_fields_field_data',
  'access' => 'administer gdpr fields',

  'menu' => array(
    'menu prefix' => 'admin/gdpr',
    'menu item' => 'field-list',
    'menu title' => 'GDPR Field config',
    'menu description' => 'Find and configure GDPR field data.',
  ),

  'title singular' => t('field'),
  'title singular proper' => t('Field'),
  'title plural' => t('field'),
  'title plural proper' => t('Fields'),

  'form' => array(
    'settings' => 'gdpr_fields_field_data_export_ui_form',
    'submit' => 'gdpr_fields_field_data_export_ui_form_submit',
  ),
  'handler' => 'gdpr_fields_ui',
//  'handler' => array(
//    'class' => 'gdpr_fields_ui',
//    'parent' => 'ctools_export_ui',
//  ),
  'strings' => array(
    'confirmation' => array(
      'revert' => array(
        'information' => t('This action will permanently remove any customizations made to this view.'),
        'success' => t('The view has been reverted.'),
      ),
      'delete' => array(
        'information' => t('This action will permanently remove the view from your database.'),
        'success' => t('The view has been deleted.'),
      ),
    ),
  ),
);

/**
 * Define the preset add/edit form.
 */
function gdpr_fields_field_data_export_ui_form(&$form, &$form_state) {
  /* @var GDPRFieldData $field_data */
  $field_data = $form_state['item'];

  $form['field'] = array(
    '#type' => 'fieldset',
    '#title' => t('Field info'),
    '#tree' => TRUE,
  );

  $form['field']['entity_type'] = array(
    '#type' => 'textfield',
    '#title' => t('Entity type'),
    '#default_value' => $field_data->entity_type,
    '#disabled' => TRUE,
  );

  $form['field']['entity_bundle'] = array(
    '#type' => 'textfield',
    '#title' => t('Bundle'),
    '#default_value' => $field_data->entity_bundle,
    '#disabled' => TRUE,
  );

  $form['field']['field_name'] = array(
    '#type' => 'textfield',
    '#title' => t('Field name'),
    '#default_value' => $field_data->field_name,
    '#disabled' => TRUE,
  );

  $form['field']['label'] = array(
    '#type' => 'textfield',
    '#title' => t('Label'),
    '#default_value' => $field_data->getSetting('label', ''),
    '#disabled' => TRUE,
  );

  $form['field']['descriptions'] = array(
    '#type' => 'textfield',
    '#title' => t('Description'),
    '#default_value' => $field_data->getSetting('description', ''),
    '#disabled' => TRUE,
  );

  $form['settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Settings'),
    '#tree' => TRUE,
  );

  $form['settings']['gdpr_fields_rta'] = array(
    '#type' => 'select',
    '#title' => t('Right to access'),
    '#options' => array(
      'inc' => 'Included',
      'maybe' => 'Maybe',
      'no' => 'Not',
    ),
    '#default_value' => $field_data->getSetting('gdpr_fields_rta', 'no'),
  );

  $form['settings']['gdpr_fields_rtf'] = array(
    '#type' => 'select',
    '#title' => t('Right to be forgotten'),
    '#options' => array(
      'anonymise' => 'Anonymise',
      'remove' => 'Remove',
      'maybe' => 'Maybe',
      'no' => 'Not',
    ),
    '#default_value' => $field_data->getSetting('gdpr_fields_rtf', 'no'),
  );

  // @todo Filter by relevance.
  $sanitizer_options = array();
  foreach (ctools_export_load_object('gdpr_dump_sanitizers') as $sanitizer) {
    $sanitizer_options[$sanitizer->name] = $sanitizer->label;
  }

  $form['settings']['gdpr_fields_sanitizer'] = array(
    '#type' => 'select',
    '#title' => t('Sanitizer to use'),
    '#options' => $sanitizer_options,
    '#default_value' => $field_data->getSetting('gdpr_fields_sanitizer', ''),
    '#states' => array(
      'visible' => array(
        ':input[name="settings[gdpr_fields_rtf]"]' => array('value' => 'anonymise'),
      ),
    ),
  );

  $form['settings']['notes'] = array(
    '#type' => 'textarea',
    '#title' => t('Notes'),
    '#default_value' => $field_data->getSetting('gdpr_fields_notes', ''),
  );
}

/**
 * Define the submit function for the add/edit form.
 */
function gdpr_fields_field_data_export_ui_form_submit(&$form, &$form_state) {
  if (isset($form_state['values']['field']['label'])) {
    $form_state['values']['settings']['label'] = $form_state['values']['field']['label'];
  }
  if (isset($form_state['values']['field']['description'])) {
    $form_state['values']['settings']['description'] = $form_state['values']['field']['description'];
  }
}
