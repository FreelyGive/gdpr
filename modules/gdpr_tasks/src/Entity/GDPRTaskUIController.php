<?php

/**
 * The Task type entity controller class.
 */
class GDPRTaskUIController extends EntityBundleableUIController {

  /**
   * {@inheritdoc}
   */
  public function hook_menu() {
    $items = parent::hook_menu();
    // Set this on the object so classes that extend hook_menu() can use it.
    $plural_label = isset($this->entityInfo['plural label']) ? $this->entityInfo['plural label'] : $this->entityInfo['label'] . 's';

    $items[$this->path . '/types'] = array(
      'title' => 'Task Types',
      'type' => MENU_DEFAULT_LOCAL_TASK,
      'weight' => -10,
    );

    $items[$this->path . '/list'] = array(
      'title' => $plural_label,
      'page callback' => 'drupal_get_form',
      'page arguments' => array($this->entityType . '_overview_form', $this->entityType),
      'description' => 'Manage ' . $plural_label . '.',
      'access callback' => 'entity_access',
      'access arguments' => array('view', $this->entityType),
      'file' => 'includes/entity.ui.inc',
      'type' => MENU_LOCAL_TASK,
      'weight' => 10,
    );

    return $items;
  }

  /**
   * {@inheritdoc}
   */
  protected function overviewTableHeaders($conditions, $rows, $additional_header = array()) {
    $additional_header = array(
      t('Type'),
      t('Status'),
      t('User'),
      t('Requested'),
    );
    return parent::overviewTableHeaders($conditions, $rows, $additional_header);
  }

  /**
   * {@inheritdoc}
   */
  protected function overviewTableRow($conditions, $id, $entity, $additional_cols = array()) {
    /* @var GDPRTask $entity */
    $additional_cols = array(
      $entity->bundleLabel(),
      $entity->status,
      theme('username', array('account' => user_load($entity->user_id))),
      format_date($entity->created, 'short'),
    );
    $row = parent::overviewTableRow($conditions, $id, $entity, $additional_cols);
    // @todo Fix hardcoded links.
    $row[0] = l($entity->label(), $this->path . '/' . $id . '/view', array('query' => drupal_get_destination()));
    $row[5] = l(t('edit'), $this->path . '/' . $id . '/edit', array('query' => drupal_get_destination()));
    $row[6] = l(t('delete'), $this->path . '/' . $id . '/delete', array('query' => drupal_get_destination()));

    return $row;
  }

}
