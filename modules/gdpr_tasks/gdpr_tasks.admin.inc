<?php

/**
 * @file
 * Administrative page callbacks for the GDPR Tasks module.
 */

/**
 * Edit form for task bundles.
 */
function gdpr_task_type_form($form, &$form_state, $bundle = array(), $op = 'edit') {

  // @todo Do we need to edit bundles?
  $form['message'] = array(
    '#type' => 'markup',
    '#markup' => 'Editing of GDPR Task types is not currently supported.'
  );

  return $form;
}

function gdpr_task_form($form, &$form_state) {
  field_attach_form('gdpr_task', $form_state['build_info']['args'][0], $form, $form_state);

  return $form;
}

function gdpr_task_edit_gdpr_remove_form($form, &$form_state) {
  $form = gdpr_task_form($form, $form_state);

  return $form;
}

function gdpr_task_edit_gdpr_sars_form($form, &$form_state) {
  $form = gdpr_task_form($form, $form_state);

  return $form;
}