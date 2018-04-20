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
    // Set up sorting
    $name = $item->{$this->plugin['export']['key']};
    $schema = ctools_export_get_schema($this->plugin['schema']);

    // Note: $item->{$schema['export']['export type string']} should have already been set up by export.inc so
    // we can use it safely.
    switch ($form_state['values']['order']) {
      case 'disabled':
        $this->sorts[$name] = empty($item->disabled) . $name;
        break;
      case 'title':
        $this->sorts[$name] = $item->{$this->plugin['export']['admin_title']};
        break;
      case 'name':
        $this->sorts[$name] = $name;
        break;
      case 'storage':
        $this->sorts[$name] = $item->{$schema['export']['export type string']} . $name;
        break;
    }

    $row['data'] = array();
    $row['class'] = !empty($item->disabled) ? array('ctools-export-ui-disabled') : array('ctools-export-ui-enabled');

    // If we have an admin title, make it the first row.
    $row['data'][] = array('data' => check_plain($item->getSetting('label')), 'class' => array('ctools-export-ui-title'));
//    $row['data'][] = array('data' => check_plain($name), 'class' => array('ctools-export-ui-name'));
    $row['data'][] = array('data' => check_plain($item->{$schema['export']['export type string']}), 'class' => array('ctools-export-ui-storage'));

//    $row['data'][] = array('data' => check_plain($item->entity_type), 'class' => array('ctools-export-ui-entity-type'));
//    $row['data'][] = array('data' => check_plain($item->entity_bundle), 'class' => array('ctools-export-ui-entity-bundle'));
//    $row['data'][] = array('data' => check_plain($item->field_name), 'class' => array('ctools-export-ui-field-name'));
    $row['data'][] = array('data' => check_plain($item->getSetting('gdpr_fields_rta', 'none')), 'class' => array('ctools-export-ui-rta'));
    $row['data'][] = array('data' => check_plain($item->getSetting('gdpr_fields_rtf', 'none')), 'class' => array('ctools-export-ui-rtf'));

    $ops = theme('links__ctools_dropbutton', array('links' => $operations, 'attributes' => array('class' => array('links', 'inline'))));

    $row['data'][] = array('data' => $ops, 'class' => array('ctools-export-ui-operations'));

    // Add an automatic mouseover of the description if one exists.
    if (!empty($this->plugin['export']['admin_description'])) {
      $row['title'] = $item->{$this->plugin['export']['admin_description']};
    }

    $this->rows[$name] = $row;
  }

  /**
   * {@inheritdoc}
   */
  public function list_table_header() {
    $header = array();
    $header[] = array('data' => t('Label'), 'class' => array('ctools-export-ui-title'));

//    $header[] = array('data' => t('Name'), 'class' => array('ctools-export-ui-name'));
    $header[] = array('data' => t('Storage'), 'class' => array('ctools-export-ui-storage'));

//    $header[] = array('data' => t('Entity type'), 'class' => array('ctools-export-ui-entity-type'));
//    $header[] = array('data' => t('Entity bundle'), 'class' => array('ctools-export-ui-entity-bundle'));
//    $header[] = array('data' => t('Field name'), 'class' => array('ctools-export-ui-field-name'));
    $header[] = array('data' => t('Right to access'), 'class' => array('ctools-export-ui-rta'));
    $header[] = array('data' => t('Right to be forgotten'), 'class' => array('ctools-export-ui-rtf'));

    $header[] = array('data' => t('Operations'), 'class' => array('ctools-export-ui-operations'));
    return $header;

  }

  /**
   * {@inheritdoc}
   */
  public function list_render(&$form_state) {
    $tables = array();
    $table_data = '';
    drupal_add_library('system', 'drupal.collapse');

    foreach ($this->rows as $name => $row) {
      list($entity_type, $entity_bundle, $field_name) = explode('|', $name);
      $tables[$entity_type][$entity_bundle][$name] = $row;
    }

    foreach ($tables as $entity_type => $entities) {
      foreach ($entities as $bundle => $rows) {
        $table = array(
          'header' => $this->list_table_header(),
          'rows' => $rows,
          'attributes' => array('id' => 'ctools-export-ui-list-items'),
          'empty' => $this->plugin['strings']['message']['no items'],
        );

        $fieldset_vars = array(
          'element' => array(
            '#title' => t('@entity: @bundle', array(
              '@entity' => $entity_type,
              '@bundle' => $bundle,
            )),
            '#value' => theme('table', $table),
            '#children' => '<div>',
            '#attributes' => array (
              'class' => array(
                'collapsible',
//                'collapsed',
              ),
            ),
          ),
        );

        $table_data .= theme('fieldset', $fieldset_vars);
      }
    }


    return $table_data;
  }

}