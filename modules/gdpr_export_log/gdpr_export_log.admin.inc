<?php

/**
 * @file
 * Administrative page and form callbacks for the GDPR Export Logs module.
 */

/**
 * Form callback for all task bundles.
 */
function gdpr_export_log_form($form, &$form_state) {
  /* @var GDPRExportLog $log */
  $log = $form_state['export_log'] = $form_state['build_info']['args'][0];

  if (!empty($log->is_new)) {
    $form['info'] = array('#markup' => t('Manually creating new log entries is not supported.'));
    return $form;
  }

  field_attach_form('gdpr_export_log', $log, $form, $form_state);

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    '#submit' => array('gdpr_export_log_form_submit_save'),
    '#weight' => 10,
  );

  $form['actions']['remove'] = array(
    '#type' => 'submit',
    '#value' => t('Remove export'),
    '#submit' => array(
      'gdpr_export_log_form_submit_remove',
      'gdpr_export_log_form_submit_save',
    ),
    '#weight' => 40,
  );

  if ($log->status == 'removed') {
    $form['actions']['#access'] = FALSE;
  }

  return $form;
}

/**
 * Validate handler for logs form.
 */
function gdpr_export_log_form_validate($form, &$form_state) {
  $log = $form_state['export_log'];
  field_attach_validate('gdpr_export_log', $log);
}

/**
 * Submit handler for logs form.
 */
function gdpr_export_log_form_submit_save($form, &$form_state) {
  /* @var GDPRExportLog $log */
  $log = $form_state['export_log'];

  // General form submission.
  field_attach_submit('gdpr_export_log', $log, $form, $form_state);

  // Process and close the task.
  $log->save();
  drupal_set_message(t('Log has been updated.'));
}

/**
 * Submit handler for logs form when removing .
 */
function gdpr_export_log_form_submit_remove($form, &$form_state) {
//  global $user;

  /* @var GDPRExportLog $log */
  $log = $form_state['export_log'];

  // General form submission.
  field_attach_submit('gdpr_export_log', $log, $form, $form_state);

  // Process and close the task.
  drupal_set_message(t('Export has been marked as removed.'));
  $log->status = 'removed';
  $log->removed = REQUEST_TIME;
}


function gdpr_export_log_export_approval($name, $display_id) {
    $args = func_get_args();
  // Remove $name and $display_id from the arguments.
  array_shift($args);
  array_shift($args);

  // Load the view and render it.
  if ($view = views_get_view($name)) {
    $view->set_display($display_id);
    return drupal_get_form('gdpr_export_log_export_approval_form', $view);

  }

  // Fallback; if we get here no view was found or handler was not valid.
  return MENU_NOT_FOUND;
}

function gdpr_export_log_export_approval_form($form, &$form_state, $view) {
  global $user;
  $values = array(
    'export' => $view->name,
    'exported_by' => $user->uid,
  );
  $log = $form_state['export_log'] = entity_create('gdpr_export_log', $values);

  field_attach_form('gdpr_export_log', $log, $form, $form_state);

  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Export'),
    '#weight' => 10,
  );

  return $form;
}

/**
 * Submit handler for logs form.
 */
function gdpr_export_log_export_approval_form_submit($form, &$form_state) {
  /* @var GDPRExportLog $log */
  $log = $form_state['export_log'];

  // General form submission.
  field_attach_submit('gdpr_export_log', $log, $form, $form_state);

  // Process and close the task.
  $log->save();

  $options = array();
  if (isset($_GET['destination'])) {
    $options['query'] = drupal_get_destination();
    unset($_GET['destination']);
  }
  $options['query']['gdpr_export_log'] = $log->identifier();

  drupal_goto(current_path() . '/gdpr_approved', $options);
}