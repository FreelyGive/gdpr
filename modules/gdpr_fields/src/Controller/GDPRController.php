<?php

namespace Drupal\gdpr_fields\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\gdpr_fields\Form\GdprFieldFilterForm;
use Drupal\gdpr_fields\GDPRCollector;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for GDPR Field routes.
 */
class GDPRController extends ControllerBase {

  /**
   * Stores the Views data cache object.
   *
   * @var \Drupal\gdpr_fields\GDPRCollector
   */
  protected $collector;

  /**
   * Constructs a new GDPRController.
   *
   * @param \Drupal\gdpr_fields\GDPRCollector $collector
   *   The GDPR collector service.
   */
  public function __construct(GDPRCollector $collector) {
    $this->collector = $collector;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gdpr_fields.collector')
    );
  }

  /**
   * Lists all fields with GDPR sensitivity.
   *
   * @return array
   *   The Views plugins report page.
   */
  public function fieldsList() {
    $filters = GdprFieldFilterForm::getFilters(\Drupal::request());

    $output = [];
    $entities = [];
    $this->collector->getEntities($entities);

    // If a filter was supplied for only certain entities,
    // remove any that don't match.
    if (!empty($filters['gdpr_entity'])) {
      $entities = array_intersect_key($entities, $filters['gdpr_entity']);
    }

    $output['filter'] = $this->formBuilder()->getForm('Drupal\gdpr_fields\Form\GdprFieldFilterForm');
    $output['#attached']['library'][] = 'gdpr_fields/field-list';


    foreach ($entities as $entity_type => $bundles) {
      $output[$entity_type] = [
        '#type' => 'details',
        '#title' => t($entity_type),
        '#open' => TRUE,
      ];

      if (count($bundles) > 1) {
        $at_least_one_bundle_has_fields = FALSE;
        foreach ($bundles as $bundle_id) {
          $field_table = $this->buildFieldTable($entity_type, $bundle_id, $filters);

          if ($field_table) {
            $at_least_one_bundle_has_fields = TRUE;
            $output[$entity_type][$bundle_id] = [
              '#type' => 'details',
              '#title' => t($bundle_id),
              '#open' => TRUE,
            ];
            $output[$entity_type][$bundle_id]['fields'] = $field_table;
          }
        }

        if (!$at_least_one_bundle_has_fields) {
          unset($output[$entity_type]);
        }
      }
      else {
        // Don't add another collapsible wrapper around single bundle entities.
        $bundle_id = reset($bundles);
        $field_table = $this->buildFieldTable($entity_type, $bundle_id, $filters);
        if ($field_table) {
          $output[$entity_type][$bundle_id]['fields'] = $field_table;
        }
        else {
          // If the entity has no fields because they've been filtered out
          // don't bother including it.
          unset($output[$entity_type]);
        }
      }
    }

    return $output;
  }

  /**
   * Build a table for entity field list.
   *
   * @param string $entity_type
   *   The entity type id.
   * @param string $bundle_id
   *   The entity bundle id.
   * @param array $filters
   *   Filters
   *
   * @return array
   *   Renderable array for field list table.
   */
  protected function buildFieldTable($entity_type, $bundle_id, $filters) {
    $rows = $this->collector->listFields($entity_type, $bundle_id, $filters);

    if (count($rows) == 0) {
      return NULL;
    }

    // Sort rows by field name.
    ksort($rows);

    $table = [
      '#type' => 'table',
      '#header' => [t('Name'), t('Type'), t('Right to access'), t('Right to be forgotten'), t('Notes'), ''],
      '#sticky' => TRUE,
    ];

    $i = 0;
    foreach ($rows as $row) {
      $table[$i]['title'] = [
        '#plain_text' => $row['title'],
      ];

      $table[$i]['type'] = [
        '#markup' => $row['is_id'] || $row['type'] == 'entity_reference' ? "<strong>{$row['type']}</strong>" : $row['type'],
      ];

      $table[$i]['gdpr_rta'] = [
        '#plain_text' => $row['gdpr_rta'],
      ];

      $table[$i]['gdpr_rtf'] = [
        '#plain_text' => $row['gdpr_rtf'],
      ];

      $table[$i]['notes'] = [
        '#markup' => empty($row['notes']) ? '' : '<span class="notes" data-icon="?"></span><div>' . $row['notes'] . '</div>',
      ];

      $table[$i]['edit'] = [
        '#markup' => !empty($row['edit']) ? $row['edit']->toString() : '',
      ];

      $i++;
    }

    return $table;
  }

  /**
   * Builds data for Right to Access data requests.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to fetch data for.
   *
   * @return array
   *   Structured array of user related data.
   */
  public function rtaData(UserInterface $user) {
    $rows = [];
    $entities = [];
    $this->collector->getValueEntities($entities, 'user', $user);

    foreach ($entities as $entity_type => $bundles) {
      foreach ($bundles as $bundle_entity) {
        $rows += $this->collector->fieldValues($entity_type, $bundle_entity, ['rta' => 'rta']);
      }
    }

    // Sort rows by field name.
    ksort($rows);
    return $rows;
  }

  /**
   * Builds data for Right to be Forgotten data requests.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to fetch data for.
   *
   * @return array
   *   Structured array of user related data.
   */
  public function rtfData(UserInterface $user) {
    $rows = [];
    $entities = [];
    $this->collector->getValueEntities($entities, 'user', $user);

    foreach ($entities as $entity_type => $bundles) {
      foreach ($bundles as $bundle_entity) {
        $rows += $this->collector->fieldValues($entity_type, $bundle_entity, ['rtf' => 'rtf']);
      }
    }

    // Sort rows by field name.
    ksort($rows);
    return $rows;
  }

}
