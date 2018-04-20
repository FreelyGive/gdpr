<?php

/**
 * @file
 * Provide views data and handlers for GDPR Export Log module.
 */

/**
 * Implements hook_views_post_render().
 */
function gdpr_export_log_views_post_render(&$view, &$output, &$cache) {
  if (empty($_GET['gdpr_export_log']) || !is_numeric($_GET['gdpr_export_log'])) {
    return;
  }

  $log = entity_load_single('gdpr_export_log', $_GET['gdpr_export_log']);

  $uids = array();
  foreach ($view->result as $data) {
    // uid will always be set if user table is base table.
    if (isset($data->uid)) {
      $uids[$data->uid] = $data->uid;
    }
  }

  if (!empty($uids)) {
    // Set log uids.
    $log->setUsers($uids);
    $log->save();
  }
}

/**
 * Implements hook_views_plugins().
 */
function gdpr_export_log_views_plugins() {
  return array(
    'display_extender' => array(
      'gdpr_views_data_export' => array(
        'title' => t(''),
        'help' => t(''),
        'path' => drupal_get_path('module', 'gdpr_export_log') . '/src/Plugin/views/display_extender',
        'handler' => 'gdpr_export_log_views_plugin_display_extender_data_export',
      ),
    ),
  );
}

/**
 * Implements hook_views_plugins_alter().
 */
function gdpr_export_log_views_plugins_alter(&$plugins) {
  if (isset($plugins['display']['views_data_export'])) {
    $plugins['display']['views_data_export']['uses views_data_export'] = TRUE;
  }
}