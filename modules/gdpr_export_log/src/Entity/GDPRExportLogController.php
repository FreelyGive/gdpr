<?php

/**
 * The Task entity controller class.
 */
class GDPRExportLogController extends EntityAPIController {

  /**
   * {@inheritdoc}
   */
  public function create(array $values = array()) {
    $values += array('status' => 'exported');
    $values += array('created' => REQUEST_TIME);

    $task = parent::create($values);
    return $task;
  }

//  public function load($ids = array(), $conditions = array()) {
//    /* @var GDPRExportLog[] $entities */
//    $entities = parent::load($ids, $conditions);
//
//    foreach ($entities as $entity) {
//      $entity->init();
//    }
//
//    return $entities;
//  }


  /**
   * {@inheritdoc}
   */
  public function save($entity, DatabaseTransaction $transaction = NULL) {
    /* @var GDPRExportLog $entity */
    $entity->changed = REQUEST_TIME;
    $return = parent::save($entity, $transaction);

    foreach ($entity->getUsers() as $uid_data) {
      db_merge('gdpr_export_log_uid')
        ->key(array(
          'export_id' => $entity->identifier(),
          'uid' => $uid_data['uid'],
        ))
        ->fields($uid_data)
        ->execute();
    }

    return $return;
  }


}