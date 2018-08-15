<?php

namespace Drupal\gdpr_tasks\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\gdpr_tasks\Entity\Task;

/**
 * Form for user task requests.
 */
class CreateGdprRequestOnBehalfOfUserForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_tasks_create_request_on_behalf_of_user';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return [
      '#title' => $this->t('Create request on behalf of user'),
      'notes' => [
        '#type' => 'textarea',
        '#title' => $this->t('Notes'),
        '#description' => $this->t('Enter the reason for creating this request.'),
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Create Request'),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user_id = $this->getRouteMatch()->getParameter('user')->id();
    $request_type = $this->getRouteMatch()->getParameter('gdpr_task_type');
    $notes = $form_state->getValue('notes');

    $task = Task::create([
      'type' => $request_type,
      'user_id' => $user_id,
      'notes' => $notes,
    ]);
    $task->save();

    $queue = \Drupal::queue('gdpr_tasks_process_gdpr_sar');
    $queue->createQueue();
    $queue->createItem($task->id());

    $this->messenger()->addStatus('The request has been logged');
    $form_state->setRedirect('view.gdpr_tasks_my_data_requests.page_1', ['user' => $user_id]);
  }

}
