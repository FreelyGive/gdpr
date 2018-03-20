<?php

namespace Drupal\gdpr_consent\Plugin\Gdpr\ConsentUserResolver;

use Drupal\Core\Entity\EntityInterface;
use Drupal\gdpr_consent\ConsentUserResolver\GdprConsentUserResolverInterface;
use Drupal\user\Entity\User;


/**
 * Resolves user reference for a Profile entity.
 *
 * @GdprConsentUserResolver(
 *   id = "gdpr_consent_profile_resolver",
 *   label = "GDPR Consent Profile Resolver",
 *   entityType = "user"
 * )
 * @package Drupal\gdpr_consent\Plugin\Gdpr\ConsentUserResolver
 */
class UserResolver implements GdprConsentUserResolverInterface {

  /**
   * {@inheritdoc}
   */
  public function resolve(EntityInterface $entity): User {
    return $entity;
  }

}
