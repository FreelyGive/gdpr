<?php

namespace Drupal\gdpr_consent\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provies a list controller for gdpr_consent entities.
 *
 * @ingroup gdpr_consent
 */
class ConsentAgreementListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'title' => 'Title',
      'mode' => 'Implicit/Explicit',
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [
      'title' => $entity->get('title')->value,
      'mode' => $entity->get('mode')->value,
    ];
    return $row + parent::buildRow($entity);
  }

}
