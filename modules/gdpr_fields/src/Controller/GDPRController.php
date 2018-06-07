<?php

namespace Drupal\gdpr_fields\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\gdpr_fields\Entity\GdprFieldConfigEntity;
use Drupal\gdpr_fields\GDPRCollector;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\gdpr_fields\Form\GdprFieldFilterForm;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * Used to get bundle info metadata.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a new GDPRController.
   *
   * @param \Drupal\gdpr_fields\GDPRCollector $collector
   *   The GDPR collector service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   Entity bundle info.
   */
  public function __construct(GDPRCollector $collector, EntityTypeBundleInfoInterface $bundle_info, RequestStack $request_stack) {
    $this->collector = $collector;
    $this->bundleInfo = $bundle_info;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('gdpr_fields.collector'),
      $container->get('entity_type.bundle.info'),
      $container->get('request_stack')
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
    $output['filter'] = $this->formBuilder()->getForm('Drupal\gdpr_fields\Form\GdprFieldFilterForm');
    $output['#attached']['library'][] = 'gdpr_fields/field-list';
    $all_bundles = $this->bundleInfo->getAllBundleInfo();

    foreach ($this->entityTypeManager()->getDefinitions() as $entity_type_id => $definition) {
      // Skip non-fieldable/config entities.
      if (!$definition->entityClassImplements(FieldableEntityInterface::class)) {
        continue;
      }

      // If a filter is active, exclude any entities that don't match.
      if (!empty($filters['gdpr_entity']) && !in_array($entity_type_id, $filters['gdpr_entity'])) {
        continue;
      }

      $bundles = isset($all_bundles[$entity_type_id]) ? $all_bundles[$entity_type_id] : [$entity_type_id => []];

      $output[$entity_type_id] = [
        '#type' => 'details',
        '#title' => $definition->getLabel() . " [$entity_type_id]",
        '#open' => TRUE,
      ];

      if (count($bundles) > 1) {
        $at_least_one_bundle_has_fields = FALSE;
        foreach ($bundles as $bundle_id => $bundle_info) {
          $field_table = $this->buildFieldTable($definition, $bundle_id, $filters);

          if ($field_table) {
            $at_least_one_bundle_has_fields = TRUE;
            $output[$entity_type_id][$bundle_id] = [
              '#type' => 'details',
              '#title' => $bundle_info['label'] . " [$bundle_id]",
              '#open' => TRUE,
            ];
            $output[$entity_type_id][$bundle_id]['fields'] = $field_table;
          }
        }

        if (!$at_least_one_bundle_has_fields) {
          unset($output[$entity_type_id]);
        }
      }
      else {
        // Don't add another collapsible wrapper around single bundle entities.
        $bundle_id = $entity_type_id;
        $field_table = $this->buildFieldTable($definition, $bundle_id, $filters);
        if ($field_table) {
          $output[$entity_type_id][$bundle_id]['fields'] = $field_table;
        }
        else {
          // If the entity has no fields because they've been filtered out
          // don't bother including it.
          unset($output[$entity_type_id]);
        }
      }
    }

    return $output;
  }

  /**
   * Build a table for entity field list.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type id.
   * @param string $bundle_id
   *   The entity bundle id.
   * @param array $filters
   *   Filters.
   *
   * @return array
   *   Renderable array for field list table.
   */
  protected function buildFieldTable(EntityTypeInterface $entity_type, $bundle_id, array $filters) {
    $rows = $this->collector->listFields($entity_type, $bundle_id, $filters);

    if (count($rows) == 0) {
      return NULL;
    }

    // Sort rows by field name.
    ksort($rows);

    $table = [
      '#type' => 'table',
      '#header' => [
        t('Name'),
        t('Type'),
        t('Right to access'),
        t('Right to be forgotten'),
        t('Notes'),
        '',
      ],
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
    $this->collector->getEntities($entities, 'user', $user);

    foreach ($entities as $entityType => $bundles) {
      foreach ($bundles as $bundle_entity) {
        $rows += $this->collector->fieldValues($bundle_entity, $entityType, ['rta' => 'rta']);
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
    $this->collector->getEntities($entities, 'user', $user);

    foreach ($entities as $entityType => $bundles) {
      foreach ($bundles as $bundle_entity) {
        $rows += $this->collector->fieldValues($bundle_entity, $entityType, ['rtf' => 'rtf']);
      }
    }

    // Sort rows by field name.
    ksort($rows);
    return $rows;
  }

}
