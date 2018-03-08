<?php

namespace Drupal\gdpr_tasks\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Event for the construction of anonymizer functions.
 *
 * Used to build a structured array that maps field types to a corresponding
 * anonymizer. The array's key is the field type and the array's value should
 * be a callable. The callable will be passed the field instance of type
 * \Drupal\Core\Field\FieldItemListInterface.
 */
class GetAnonymizersEvent extends Event {
  const EVENT_NAME = 'gdpr.get_anonymizers';

  /**
   * Registered collection of anonymizers.
   *
   * Provides a structured array where the key is the field type
   * and the value is a callable used to perform the anonymization.
   *
   * @var array
   */
  public $anonymizers;

  /**
   * GetAnonymizersEvent constructor.
   */
  public function __construct() {
    $this->anonymizers = [
      'string' => ['\Drupal\gdpr_tasks\Anonymizer', 'anonymizeString'],
      'datetime' => ['\Drupal\gdpr_tasks\Anonymizer', 'anonymizeDate'],
    ];
  }
}