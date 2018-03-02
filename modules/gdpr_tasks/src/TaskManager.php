<?php

namespace Drupal\gdpr_tasks;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Session\AccountProxy;

/**
 * Defines a helper class for stuff related to views data.
 */
class TaskManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $taskStorage;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Constructs a TaskManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $current_user
   *   The current user service.
   */
  public function __construct(EntityTypeManager $entity_type_manager, AccountProxy $current_user) {
    $this->entityTypeManager = $entity_type_manager;
    $this->taskStorage = $entity_type_manager->getStorage('gdpr_task');
    $this->currentUser = $current_user;
  }

  public function getUserTasks($account = NULL, $type = NULL) {
    $tasks = [];

    if (!$account) {
      $account = $this->currentUser->getAccount();
    }

    $query = $this->taskStorage->getQuery();
    $query->condition('user_id', $account->id(), '=');

    if ($type) {
      $query->condition('type', $type, '=');
    }

    if (!empty($ids = $query->execute())) {
      $tasks = $this->taskStorage->loadMultiple($ids);
    }

    return $tasks;
  }

}
