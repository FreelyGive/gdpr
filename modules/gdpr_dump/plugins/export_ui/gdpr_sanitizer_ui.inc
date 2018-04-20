<?php

/**
 * @file
 * Plugin definition for CTools Export UI integration.
 */

$plugin = array(
  'schema' => 'gdpr_dump_sanitizers',
  'access' => 'administer gdpr sanitizers',

  'menu' => array(
    'menu prefix' => 'admin/gdpr',
    'menu item' => 'sanitizer-list',
    'menu title' => 'GDPR Sanitizers',
    'menu description' => 'Configure GDPR Sanitizers.',
  ),

  'title singular' => t('sanitizer'),
  'title singular proper' => t('Sanitizer'),
  'title plural' => t('sanitizers'),
  'title plural proper' => t('Sanitizers'),

  'form' => array(
    'settings' => 'gdpr_dump_sanitizer_export_ui_form',
//    'submit' => 'gdpr_dump_sanitizer_export_ui_form_submit',
  ),
  'handler' => 'gdpr_sanitizer_ui',
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
function gdpr_dump_sanitizer_export_ui_form(&$form, &$form_state) {
  /* @var GDPRSanitizerDefault $sanitizer */
  $sanitizer = $form_state['item'];

   $form['settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Settings'),
    '#tree' => TRUE,
  );

  $form['settings']['notes'] = array(
    '#type' => 'textarea',
    '#title' => t('Notes'),
    '#default_value' => $sanitizer->getSetting('notes', ''),
  );
}

/**
 * Define the submit function for the add/edit form.
 */
//function gdpr_dump_sanitizer_export_ui_form_submit(&$form, &$form_state) {}
