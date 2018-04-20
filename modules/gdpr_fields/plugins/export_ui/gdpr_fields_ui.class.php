<?php

/**
 * @file
 * Contains the CTools Export UI integration code.
 */

/**
 * CTools Export UI class handler for GDPR Fields UI.
 */
class gdpr_fields_ui extends ctools_export_ui {

  protected $rows = array();

  /**
   * {@inheritdoc}
   */
  function hook_menu(&$items) {
    unset($this->plugin['menu']['items']['add']);
    // @todo Make sure import always overrides and never adds.
    $this->plugin['menu']['items']['import']['title'] = 'Override';
    parent::hook_menu($items);
  }

  /**
   * {@inheritdoc}
   *
   * @param GDPRFieldData $item
   */
  public function list_build_row($item, &$form_state, $operations) {
    parent::list_build_row($item, $form_state, $operations);

    $name = $item->{$this->plugin['export']['key']};
    $row = $this->rows[$name];
    unset($this->rows[$name]);

    $ops = array_pop($row['data']);

    $title = array('data' => check_plain($item->getSetting('label')), 'class' => array('ctools-export-ui-title'));
    array_unshift($row['data'], $title);

    unset($row['data'][1]);

//    $row['data'][] = array('data' => check_plain($item->entity_type), 'class' => array('ctools-export-ui-entity-type'));
//    $row['data'][] = array('data' => check_plain($item->entity_bundle), 'class' => array('ctools-export-ui-entity-bundle'));
//    $row['data'][] = array('data' => check_plain($item->field_name), 'class' => array('ctools-export-ui-field-name'));
    $row['data'][] = array('data' => check_plain($item->getSetting('gdpr_fields_rta', 'none')), 'class' => array('ctools-export-ui-rta'));
    $row['data'][] = array('data' => check_plain($item->getSetting('gdpr_fields_rtf', 'none')), 'class' => array('ctools-export-ui-rtf'));
    $row['data'][] = $ops;

//    $this->rows[$item->entity_type][$item->entity_bundle][$name] = $row;
  }

  /**
   * {@inheritdoc}
   */
  public function list_table_header() {
    $header = parent::list_table_header();
    $ops = array_pop($header);

    $title = array('data' => t('Label'), 'class' => array('ctools-export-ui-title'));
    array_unshift($header, $title);

    unset($header[1]);

//    $header[] = array('data' => t('Entity type'), 'class' => array('ctools-export-ui-entity-type'));
//    $header[] = array('data' => t('Entity bundle'), 'class' => array('ctools-export-ui-entity-bundle'));
//    $header[] = array('data' => t('Field name'), 'class' => array('ctools-export-ui-field-name'));
    $header[] = array('data' => t('Right to access'), 'class' => array('ctools-export-ui-rta'));
    $header[] = array('data' => t('Right to be forgotten'), 'class' => array('ctools-export-ui-rtf'));
    $header[] = $ops;
    return $header;

  }

  /**
   * {@inheritdoc}
   */
  public function list_render(&$form_state) {
    $tables = '';

    foreach ($this->rows as $entity_type => $entities) {
      dpm($entity_type);
      foreach ($entities as $bundle => $rows) {
        $table = array(
          'header' => $this->list_table_header(),
          'rows' => $rows,
          'attributes' => array('id' => 'ctools-export-ui-list-items'),
          'empty' => $this->plugin['strings']['message']['no items'],
        );
        $tables .= theme('table', $table);
      }
    }


    return '';
    return $tables;
  }

}