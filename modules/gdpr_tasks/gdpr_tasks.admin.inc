<?php

/**
 * @file
 * Administrative page callbacks for the GDPR Tasks module.
 */

/**
 * List all task bundles.
 */
function gdpr_task_type_list() {
  // Call your hook_entity_info implementation, 'cause it includes all your bundles.
  $task_info = gdpr_tasks_entity_info();
  $headers = array(t('Name'), t('Actions')); // Table Headers
  $rows = array();
  foreach ($task_info['gdpr_task']['bundles'] as $name => $bundle) {
    // Get the bundle edit link, add it to a table row.
    $link = l(t('edit'), $bundle['admin']['real path']);
    $rows[] = array($bundle['label'], $link);
  }
  // Format the table array. This is a pretty simple table, you could include
  // links to the field management pages for each bundle too.
  $output = array(
    '#theme' => 'table',
    '#header' => $headers,
    '#rows' => $rows,
  );
  return $output;
}

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


/**
 * Form builder for the tasks overview administration form.
 */
function gdpr_task_admin_overview($form, &$form_state) {
  // Load the tasks that need to be displayed.
  $header = array(
    'task' => array('data' => t('Task'), 'field' => 'id'),
    'user' => array('data' => t('User'), 'field' => 'user_id'),
    'type' => array('data' => t('Type'), 'field' => 'type'),
    'created' => array('data' => t('Updated'), 'field' => 'created'),
    'operations' => array('data' => t('Operations')),
  );

  $query = db_select('gdpr_task', 't')->extend('PagerDefault')->extend('TableSort');
  $result = $query
    ->fields('t', array('id', 'user_id', 'type', 'created'))
    ->limit(50)
    ->orderByHeader($header)
    ->execute();

  $ids = array();

  foreach ($result as $row) {
    $ids[] = $row->id;
  }
  /* @var GDPRTask[] $tasks */
  $tasks = gdpr_task_load_multiple($ids);

  // Build a table listing the appropriate tasks.
  $options = array();
  $destination = drupal_get_destination();

  foreach ($tasks as $task) {
    $options[$task->id] = array(
      'task' => array(
        'data' => array(
          '#type' => 'link',
          '#title' => $task->label(),
          '#href' => 'admin/content/task/' . $task->id,
        ),
      ),
      'user' => theme('username', array('account' => user_load($task->user_id))),
      'type' => array(
        'data' => $task->bundleLabel(),
      ),
      'created' => format_date($task->created, 'short'),
      'operations' => array(
        'data' => array(
          '#type' => 'link',
          '#title' => t('edit'),
          '#href' => 'admin/content/task/' . $task->id . '/edit',
          '#options' => array('query' => $destination),
        ),
      ),
    );
  }

  $form['tasks'] = array(
    '#type' => 'tableselect',
    '#header' => $header,
    '#options' => $options,
    '#empty' => t('No tasks available.'),
  );

  $form['pager'] = array('#theme' => 'pager');

  return $form;
}

/**
 * Example entity display page.
 */
function gdpr_task_page($task) {
  $build = array();

  field_attach_prepare_view('gdpr_task', array($task->id => $task), 'full');

  return $build;
}

/**
 * Example bundle edit page.
 */
function gdpr_task_edit($task) {
  $build = array();
  $form_state = array();
  field_attach_form('gdpr_task', $task, $build, $form_state);

  return $build;
}