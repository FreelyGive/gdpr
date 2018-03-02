<?php

namespace Drupal\gdpr_fields\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\gdpr_fields\GDPRCollector;
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
    $output = [];
    $entities = [];
    $this->collector->getEntities($entities);

    foreach ($entities as $entity_type => $bundles) {
      $output[$entity_type] = array(
        '#type' => 'details',
        '#title' => t($entity_type),
        '#description' => t('@configure entity @entity_type for GDPR.', [
          // @todo Create ability to exclude entity type from GDPR in configuration.
          '@configure' => Link::fromTextAndUrl('Configure', Url::fromUri('internal:/'))->toString(),
          '@entity_type' => ucfirst($entity_type),
        ]),
        '#open' => TRUE,
      );

      if (count($bundles) > 1) {
        foreach ($bundles as $bundle_id) {
          $output[$entity_type][$bundle_id] = array(
            '#type' => 'details',
            '#title' => t($bundle_id),
            '#open' => TRUE,
          );
          $output[$entity_type][$bundle_id]['fields'] = $this->buildFieldTable($entity_type, $bundle_id);
        }
      }
      else {
        // Don't add another collapsible wrapper around single bundle entities.
        $bundle_id = reset($bundles);
        $output[$entity_type][$bundle_id]['fields'] = $this->buildFieldTable($entity_type, $bundle_id);
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
   *
   * @return array
   *   Renderable array for field list table.
   */
  protected function buildFieldTable($entity_type, $bundle_id) {
    $rows = $this->collector->listFields($entity_type, $bundle_id);
    // Sort rows by field name.
    ksort($rows);

    return [
      '#type' => 'table',
      '#header' => [t('Name'), t('Type'), t('Right to access'), t('Right to be forgotten'), ''],
      '#rows' => $rows,
      '#sticky' => TRUE,
      '#empty' => t('There are no GDPR fields for this entity.'),
    ];
  }

}
