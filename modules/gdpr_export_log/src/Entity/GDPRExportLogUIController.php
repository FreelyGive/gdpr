<?php

/**
 * The Task type entity controller class.
 */
class GDPRExportLogUIController extends EntityDefaultUIController {

  /**
   * {@inheritdoc}
   */
  public function hook_menu() {
    $items = parent::hook_menu();

    // Remove 'add' local action.
    unset($items[$this->path . '/add']);

    return $items;
  }

  /**
   * Generates the render array for a overview tables for different statuses.
   *
   * @param $conditions
   *   An array of conditions as needed by entity_load().

   * @return array
   *   A renderable array.
   */
//  public function overviewTable($conditions = array()) {
//    $query = new EntityFieldQuery();
//    $query->entityCondition('entity_type', $this->entityType);
//    $query->propertyOrderBy('created');
//
//    // Add all conditions to query.
//    foreach ($conditions as $key => $value) {
//      $query->propertyCondition($key, $value);
//    }
//
//    if ($this->overviewPagerLimit) {
//      $query->pager($this->overviewPagerLimit);
//    }
//
//    $results = $query->execute();
//
//    $ids = isset($results[$this->entityType]) ? array_keys($results[$this->entityType]) : array();
//    $entities = $ids ? entity_load($this->entityType, $ids) : array();
//    ksort($entities);
//
//    // Always show at least requested and complete tables.
//    $rows = array(
//      'requested' => array(),
//      'complete' => array(),
//    );
//    foreach ($entities as $entity) {
//      $rows[$entity->status][] = $this->overviewTableRow($conditions, entity_id($this->entityType, $entity), $entity);
//    }
//
//    $render = array();
//    foreach ($rows as $status => $status_rows) {
//      $render[$status] = array(
//        '#theme' => 'table',
//        '#header' => $this->overviewTableHeaders($conditions, $status_rows),
//        '#rows' => $status_rows,
//        '#caption' => t('Tasks with status - @status', array('@status' => ucfirst($status))),
//        '#empty' => t('No tasks.'),
//        '#weight' => 3,
//      );
//
//      // @todo Find a better way to order statuses.
//      if ($status == 'requested') {
//        $render[$status]['#weight'] = 0;
//      }
//    }
//
//    return $render;
//  }

  /**
   * {@inheritdoc}
   */
  protected function overviewTableHeaders($conditions, $rows, $additional_header = array()) {
    $additional_header = array(
      t('User count'),
      t('Status'),
      t('Exported by'),
      t('Exported'),
      t('Lifetime'),
    );
    return parent::overviewTableHeaders($conditions, $rows, $additional_header);
  }

  /**
   * {@inheritdoc}
   */
  protected function overviewTableRow($conditions, $id, $entity, $additional_cols = array()) {
    /* @var GDPRExportLog $entity */

    //    $time_left = $entity->created + ($entity->getLifetime('U'))
    $time_left = $entity->created + ($entity->wrapper()->gdpr_export_log_lifetime->value() * 60 * 60 * 24) - REQUEST_TIME;
    $left_to_go = t('%time to go', array('%time' => format_interval($time_left, 2)));


    $additional_cols = array(
      count($entity->getUsers()),
      $entity->status,
      theme('username', array('account' => user_load($entity->exported_by))),
      format_date($entity->created, 'short'),
      $left_to_go,
    );
    $row = parent::overviewTableRow($conditions, $id, $entity, $additional_cols);
    // @todo Fix hardcoded links.
//    $row[0] = l($entity->label(), $this->path . '/' . $id . '/view', array('query' => drupal_get_destination()));
    $row[0] = $entity->label();

    return $row;
  }

}
