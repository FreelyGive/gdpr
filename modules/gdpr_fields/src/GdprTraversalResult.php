<?php

namespace Drupal\gdpr_fields;

/**
 * Used by the entity traversal mecahnism to process entities.
 *
 * @package Drupal\gdpr_fields
 */
class GdprTraversalResult {

  /**
   * Entity being traversed.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  public $entity;

  /**
   * Used for tracking recursion.
   *
   * @var array
   */
  public $progress;

  /**
   * Current row ID.
   *
   * @var string
   */
  public $rowId;

  /**
   * The collected results.
   *
   * @var array
   */
  public $results;

  /**
   * GDPR field being processed.
   *
   * @var \Drupal\gdpr_fields\Entity\GdprField
   */
  public $parentConfig;
}
