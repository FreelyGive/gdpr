<?php

namespace Drupal\gdpr_tasks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Views UI routes.
 */
class GDPRController extends ControllerBase {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * The task manager service.
   *
   * @var \Drupal\gdpr_tasks\TaskManager
   */
  protected $taskManager;

  /**
   * Constructs a new GDPRController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user service.
   */
  public function __construct(EntityTypeManager $entity_type_manager, AccountProxy $current_user, Messenger $messenger, $task_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->taskManager = $task_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('gdpr_tasks.manager')
    );
  }

  /**
   * Placeholder for a GDPR Dashboard.
   *
   * @return array
   *   Renderable Drupal markup.
   */
  public function summaryPage() {
    return ['#markup' => $this->t('Summary')];
  }

  /**
   * Request a GDPR Task.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user for whom the request is being made.
   * @param $gdpr_task_type
   *   Type of task to be created.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Return user to GDPR requests.
   */
  public function requestPage(AccountInterface $user, $gdpr_task_type) {
      $tasks = $this->taskManager->getUserTasks($user, $gdpr_task_type);

      if (!empty($tasks)) {
        $this->messenger->addWarning('You already have a pending task.');
      }
      else {
        $values = [
          'type' => $gdpr_task_type,
          'user_id' => $user->id(),
        ];
        $this->entityTypeManager->getStorage('gdpr_task')->create($values)->save();
        $this->messenger->addStatus('Your request has been logged');
      }

    $response = new RedirectResponse(Url::fromRoute('view.gdpr_tasks_my_data_requests.page_1', ['user' => $user->id()])->toString());
    return $response;
  }

}
