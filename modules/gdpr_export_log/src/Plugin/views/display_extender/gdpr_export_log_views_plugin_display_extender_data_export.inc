<?php

/**
 * @file
 * Definition of gdpr_export_log_views_plugin_display_extender_data_export.
 */

/**
 * Extend views data export views display for GDPR logging.
 */
class gdpr_export_log_views_plugin_display_extender_data_export extends views_plugin_display_extender {

  /**
   * Check if the extender should apply.
   *
   * @return bool
   *   Whether the extender should apply.
   */
  protected function applies() {
    // Check display class.
    if (!$this->display instanceof views_data_export_plugin_display_export) {
      return FALSE;
    }

    // Check that user table is a base table.
    if ($this->view->display_handler) {
      if (!in_array('users', array_keys($this->view->get_base_tables()))) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  function option_definition() {
    $options = array();

    if ($this->applies()) {
      $options['gdpr_export_log_audit'] = array('default' => FALSE);
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  function options_definition_alter(&$options) {
    if ($this->applies()) {
      $options['gdpr_export_log_audit'] = array('default' => FALSE);
    }
  }

  /**
   * {@inheritdoc}
   */
  function options_form(&$form, &$form_state) {
    if ($this->applies()) {
      if (substr($form['#section'], -21) == 'gdpr_export_log_audit') {
        $form['gdpr_export_log_audit'] = array(
          '#type' => 'checkbox',
          '#title' => t('Audit exports for GDPR'),
          '#description' => t('Include this export in a GDPR audit log.'),
          '#default_value' => $this->display->get_option('gdpr_export_log_audit'),
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  function options_submit(&$form, &$form_state) {
    if ($this->applies()) {
      if (isset($form['options']['gdpr_export_log_audit'])) {
        $this->display->set_option('gdpr_export_log_audit', $form_state['values']['gdpr_export_log_audit']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  function options_summary(&$categories, &$options) {
    if ($this->applies()) {
      $value = $this->display->get_option('gdpr_export_log_audit');
      $options['gdpr_export_log_audit'] = array(
        'category' => 'page',
        'title' => 'GDPR Audit',
        'value' => $value ? t('Include') : t("Don't include"),
      );
    }
  }

}
