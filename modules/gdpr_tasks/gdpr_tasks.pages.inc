<?php

/**
 * @file
 * Page callbacks for the GDPR Tasks module.
 */

/**
 * Request page for user.
 */
function gdpr_task_user_request($user) {
  return array(
    'message' => array(
      '#markup' => 'Make data access requests.'
    ),
  );
}

/**
 * Request page for user.
 */
function gdpr_tasks_request($user, $gdpr_task_type) {
  $tasks = gdpr_tasks_get_user_tasks($user, $gdpr_task_type);

  if (!empty($tasks)) {
    drupal_set_message('You already have a pending task.', 'warning');
  }
  else {
    $values = [
      'type' => $gdpr_task_type,
      'user_id' => $user->uid,
    ];
    $task = entity_create('gdpr_task', $values);
    $task->save();
    drupal_set_message('Your request has been logged.');
  }

  drupal_goto("user/{$user->uid}/gdpr/requests");
}