<?php

/**
 * @file
 * Contains the CTools Export UI integration code.
 */

/**
 * CTools Export UI class handler for GDPR Fields UI.
 */
class gdpr_sanitizer_ui extends ctools_export_ui {

  protected $rows = array();

  /**
   * {@inheritdoc}
   */
  function hook_menu(&$items) {
//    unset($this->plugin['menu']['items']['add']);
    // @todo Make sure import always overrides and never adds.
//    $this->plugin['menu']['items']['import']['title'] = 'Override';
    parent::hook_menu($items);
  }

  /**
   * {@inheritdoc}
   *
   * @param GDPRFieldData $item
   */
  public function list_build_row($item, &$form_state, $operations) {
    parent::list_build_row($item, $form_state, $operations);
    return;

    $name = $item->{$this->plugin['export']['key']};
    $ops = array_pop($this->rows[$name]['data']);

    $title = array('data' => check_plain($item->getSetting('label')), 'class' => array('ctools-export-ui-title'));
    array_unshift($this->rows[$name]['data'], $title);

    $this->rows[$name]['data'][] = array('data' => check_plain($item->entity_type), 'class' => array('ctools-export-ui-entity-type'));
    $this->rows[$name]['data'][] = array('data' => check_plain($item->entity_bundle), 'class' => array('ctools-export-ui-entity-bundle'));
    $this->rows[$name]['data'][] = array('data' => check_plain($item->field_name), 'class' => array('ctools-export-ui-field-name'));
    $this->rows[$name]['data'][] = array('data' => check_plain($item->getSetting('gdpr_fields_rta', 'none')), 'class' => array('ctools-export-ui-rta'));
    $this->rows[$name]['data'][] = array('data' => check_plain($item->getSetting('gdpr_fields_rtf', 'none')), 'class' => array('ctools-export-ui-rtf'));
    $this->rows[$name]['data'][] = $ops;
  }

  /**
   * {@inheritdoc}
   */
  public function list_table_header() {
    $header = parent::list_table_header();
    return $header;


    $ops = array_pop($header);

    $title = array('data' => t('Label'), 'class' => array('ctools-export-ui-title'));
    array_unshift($header, $title);

    $header[] = array('data' => t('Entity type'), 'class' => array('ctools-export-ui-entity-type'));
    $header[] = array('data' => t('Entity bundle'), 'class' => array('ctools-export-ui-entity-bundle'));
    $header[] = array('data' => t('Field name'), 'class' => array('ctools-export-ui-field-name'));
    $header[] = array('data' => t('Right to access'), 'class' => array('ctools-export-ui-rta'));
    $header[] = array('data' => t('Right to be forgotten'), 'class' => array('ctools-export-ui-rtf'));
    $header[] = $ops;
    return $header;

  }

}