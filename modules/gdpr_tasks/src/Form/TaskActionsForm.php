<?php

namespace Drupal\gdpr_tasks\Form;

use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

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
   * Performs the SAR export.
   */
  private function doSarExport(FormStateInterface $form_state): void {
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
   * Performs the removal request.
   */
  private function doRemoval(FormStateInterface $form_state) {
    // @todo Should be injected
    $anonymizer = \Drupal::service('gdpr_tasks.anonymizer');
    $errors = $anonymizer->run($this->entity);
    return $errors;
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
      if ($this->entity->bundle() == 'gdpr_remove') {
        $actions['submit']['#value'] = 'Remove and Anonymise Data';
        $actions['submit']['#name'] = 'remove';
      }
      else {
        $actions['submit']['#value'] = 'Process';
        $actions['submit']['#name'] = 'export';
      }
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\gdpr_tasks\Entity\Task */
    $entity = $this->entity;

    if ($entity->bundle() == 'gdpr_remove') {
      $errors = $this->doRemoval($form_state);
      // Removals may have generated errors.
      // If this happens, combine the error messages and display them.
      if (count($errors) > 0) {
        $should_save = FALSE;
        \Drupal::messenger()->addError(implode(' ', $errors));
        $form_state->setRebuild();
      }
      else {
        $should_save = TRUE;
      }
    }
    else {
      $this->doSarExport($form_state);
      $should_save = TRUE;
    }

    if ($should_save) {
      $entity->status = 'closed';
      $entity->setProcessedById(\Drupal::currentUser()->id());
      \Drupal::messenger()->addStatus('Task has been processed.');
      parent::save($form, $form_state);
    }
  }

}
