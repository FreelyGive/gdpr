<?php

namespace Drupal\gdpr_tasks\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Task edit forms.
 *
 * @ingroup gdpr_tasks
 */
class TaskActionsForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    $entity = $this->entity;

    if ($entity->status->value == 'closed') {
      $form['manual_data']['widget']['#disabled'] = TRUE;
      $form['actions']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if (isset($actions['delete'])) {
      unset($actions['delete']);
    }

    if (isset($actions['submit'])) {
      $actions['submit']['#value'] = 'Process';
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $entity = $this->entity;
    $manual = $form_state->getValue(['manual_data', 0, 'value']);

    $data = gdpr_tasks_generate_sar_report($entity->getOwner());

    $inc = [];
    foreach ($data as $key => $values) {
      $rta = $values['gdpr_rta'];
      unset($values['gdpr_rta']);
      if ($rta == 'inc') {
        $inc[$key] = $values;
      }
    }

    $file_name = $entity->sar_export->entity->getFilename();
    $file_uri = $entity->sar_export->entity->getFileUri();
    $dirname = str_replace($file_name, '', $file_uri);

    /* @var \Drupal\gdpr_tasks\TaskManager $task_manager */
    $task_manager = \Drupal::service('gdpr_tasks.manager');
    $destination = $task_manager->toCSV($inc, $dirname);
    $export = file_get_contents($destination);

    $export .= $manual;

    // @todo Add headers to csv export.
    file_save_data($export, $file_uri, FILE_EXISTS_REPLACE);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    $entity = $this->entity;
    $entity->status = 'closed';

    \Drupal::messenger()->addStatus('Task has been processed.');
    parent::save($form, $form_state);
  }

}
