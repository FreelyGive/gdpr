<?php

namespace Drupal\gdpr_tasks;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\gdpr_tasks\Event\GetAnonymizersEvent;
use Drupal\gdpr_fields\GDPRCollector;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Anonymizes or removes field values for GDPR.
 */
class Anonymizer {

  private $collector;

  private $dispatcher;

  private $db;

  private $entityTypeManager;

  /**
   * Anonymizer constructor.
   */
  public function __construct(GDPRCollector $collector, EventDispatcherInterface $dispatcher, Connection $db, EntityTypeManagerInterface $entity_manager) {
    $this->collector = $collector;
    $this->dispatcher = $dispatcher;
    $this->db = $db;
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * Runs anonymization routines against a user.
   */
  public function run($user_id) {

    // Make sure we load a fresh copy of the entity (bypassing the cache)
    // so we don't end up affecting any other references to the entity.

    $user = $this->entityTypeManager->getStorage('user')
      ->loadUnchanged($user_id);

    $errors = [];
    $entities = [];
    $successes = [];
    $failures = [];

    $this->collector->getValueEntities($entities, 'user', $user);

    foreach ($entities as $entity_type => $bundles) {
      foreach ($bundles as $bundle_entity) {
        // Re-load a fresh copy of the bundle entity from storage so we don't
        // end up modifying any other references to the entity in memory.
        $bundle_entity = $this->entityTypeManager->getStorage($bundle_entity->getEntityTypeId())
          ->loadUnchanged($bundle_entity->id());

        $entity_success = TRUE;

        foreach ($this->getFieldsToProcess($bundle_entity) as $field_info) {
          /** @var \Drupal\Core\Field\FieldItemListInterface $field */
          $field = $field_info['field'];
          $mode = $field_info['mode'];

          $success = TRUE;
          $msg = NULL;

          if ($mode == 'anonymise') {
            list($success, $msg) = $this->anonymize($field);
          }
          elseif ($mode == 'remove') {
            list($success, $msg) = $this->remove($field);
          }

          if ($success === FALSE) {
            // Could not anonymize/remove field. Record to errors list.
            // Prevent entity from being saved.
            $entity_success = FALSE;
            $errors[] = $msg;
          }
        }

        if ($entity_success) {
          $successes[] = $bundle_entity;
        }
        else {
          $failures[] = $bundle_entity;
        }
      }
    }

    if (count($failures) === 0) {
      $tx = $this->db->startTransaction();

      try {
        foreach ($successes as $entity) {
          $entity->save();
        }
      }
      catch (\Exception $e) {
        $tx->rollBack();
        $errors[] = $e->getMessage();
      }
    }

    // @todo this
    // Now we've finished processing any defined fields.
    // We must always process the following:
    // - Anonymize the username
    // - Anonymize the email
    // - Remove the password
    // - Remove all roles
    // - Block the user
    // - Store that they've been anonymized.

    return $errors;
  }

  /**
   * Removes the field value.
   */
  private function remove(FieldItemListInterface $field) {
    try {
      $field->setValue(NULL);
      return [TRUE, NULL];
    }
    catch (ReadOnlyException $e) {
      return [FALSE, $e->getMessage()];
    }
  }

  /**
   * Anonymizes a field.
   *
   * Uses the GetAnonymizersEvent to find an appropriate anonymization function.
   */
  private function anonymize(FieldItemListInterface $field) {
    // For string fields, generate a random string the same length.
    $type = $field->getFieldDefinition()->getType();
    $event = new GetAnonymizersEvent();
    // Dispatch the event to allow other modules
    // to register anonymization functions for particular entity types.
    $this->dispatcher->dispatch(GetAnonymizersEvent::EVENT_NAME, $event);

    if (isset($event->anonymizers[$type]) && is_callable($event->anonymizers[$type])) {
      try {
        call_user_func($event->anonymizers[$type], $field);
        return [TRUE, NULL];
      }
      catch (\Exception $e) {
        return [FALSE, $e->getMessage()];
      }
    }
    else {
      return [
        FALSE,
        "Could not anonymize field {$field->getName()}. Please consider changing this field from 'anonymize' to 'remove', or register a custom anonymizer function for the type {$type}.",
      ];
    }
  }

  /**
   * Gets fields to anonymize/remove.
   */
  private function getFieldsToProcess(EntityInterface $entity) {
    $bundle_id = $entity->bundle();

    // Get fields for entity.
    $fields = [];
    foreach ($entity as $field_id => $field) {
      /** @var \Drupal\Core\Field\FieldItemListInterface $field */
      $field_definition = $field->getFieldDefinition();

      $config = $field_definition->getConfig($bundle_id);

      if (!$config->getThirdPartySetting('gdpr_fields', 'gdpr_fields_enabled', FALSE)) {
        continue;
      }

      $rtf_value = $config->getThirdPartySetting('gdpr_fields', 'gdpr_fields_rtf', FALSE);

      if ($rtf_value && $rtf_value !== 'no') {
        $fields[] = [
          'entity_type' => $entity->getEntityTypeId(),
          'bundle' => $bundle_id,
          'field' => $field,
          'mode' => $rtf_value,
        ];
      }

    }

    return $fields;
  }

  /**
   * Replaces string field value with a random value if the field is non-empty.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public static function anonymizeString(FieldItemListInterface $field) {
    $value = $field->getString();
    $max_length = $field->getDataDefinition()->getSetting("max_length");

    if (!empty($value)) {
      // Generate a prefixed random string.
      $value = "anon_" . self::generateRandomString(4);
      // If the value is too long, tirm it.
      if (isset($max_length) && strlen($value) > $max_length) {
        $value = substr(0, $max_length);
      }

      $field->setValue($value);
    }
  }

  /**
   * Replaces date field value with a random value if the field is non-empty.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public static function anonymizeDate(FieldItemListInterface $field) {
    if (isset($field->value)) {
      $field->setValue(date('1000-01-01'));
    }
  }

  /**
   * Generates a random string of a specified length.
   */
  private static function generateRandomString($length) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

}
