<?php

namespace Drupal\gdpr_fields;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a common interface for dependency container injection.
 *
 * This interface gives classes who need services a factory method for
 * instantiation as well as the entity to be traversed.
 */
interface EntityTraversalInterface extends EntityTraversalContainerInjectionInterface {

  /**
   * Traverses the entity relationship tree if not done before.
   *
   * @return bool
   *   Whether or not the traversal was successful.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function traverse();

  /**
   * Get the traversal results.
   *
   * @return array|null
   *   Results collected by the traversal.
   *   By default this will be a nested array. The first dimension is
   *   keyed by entity type and contains an array keyed by entity ID.
   *   The values will be the entity instances (although this can be changed by
   *   overriding the handleEntity method).
   */
  public function getResults();

}
