<?php

namespace Drupal\gdpr_tasks;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\gdpr_fields\GDPRCollector;
use Drupal\gdpr_tasks\Entity\TaskInterface;
use Drupal\user\Entity\User;

/**
 * Anonymizes or removes field values for GDPR.
 */
class Anonymizer {

  private $collector;

  private $db;

  private $entityTypeManager;

  private $moduleHandler;

  private $currentUser;

  /**
   * Anonymizer constructor.
   */
  public function __construct(GDPRCollector $collector, Connection $db, EntityTypeManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $current_user) {
    $this->collector = $collector;
    $this->db = $db;
    $this->entityTypeManager = $entity_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
  }

  /**
   * Runs anonymization routines against a user.
   */
  public function run(TaskInterface $task) {
    // Make sure we load a fresh copy of the entity (bypassing the cache)
    // so we don't end up affecting any other references to the entity.
    $user = $task->getOwner(); //$this->refetchUser($task->getOwnerId());

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
            list($success, $msg) = $this->anonymize($field, $bundle_entity, $entity_type);
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
        /* @var EntityInterface $entity */
        foreach ($successes as $entity) {
          $entity->save();
        }
        // Re-fetch the user so we see any changes that were made.
        $user = $this->refetchUser($task->getOwnerId());
        $user->block();
        $user->save();
      }
      catch (\Exception $e) {
        $tx->rollBack();
        $errors[] = $e->getMessage();
      }
    }

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
  private function anonymize(FieldItemListInterface $field, EntityInterface $bundle_entity, $entity_type) {
    // For string fields, generate a random string the same length.
    $type = $field->getFieldDefinition()->getType();
    $field_key = implode('.', array_filter(
      [$entity_type, $bundle_entity->bundle(), $field->getName()]));

    $anonymizers = [
      'field' => [
        'user.user.mail' => ['\Drupal\gdpr_tasks\Anonymizer', 'anonymizeMail'],
      ],
      'type' => [
        'string' => ['\Drupal\gdpr_tasks\Anonymizer', 'anonymizeString'],
        'datetime' => ['\Drupal\gdpr_tasks\Anonymizer', 'anonymizeDate'],
      ],
    ];

    $this->moduleHandler->alter('gdpr_anonymizers', $anonymizers);

    // Check if there's an anonymizer for this field.
    if (isset($anonymizers['field'][$field_key]) && is_callable($anonymizers['field'][$field_key])) {
      try {
        call_user_func($anonymizers['field'][$field_key], $field);
        return [TRUE, NULL];
      }
      catch (\Exception $e) {
        return [FALSE, $e->getMessage()];
      }
    }
    // If not, anonymize by type instead.
    elseif (isset($anonymizers['type'][$type]) && is_callable($anonymizers['type'][$type])) {
      try {
        call_user_func($anonymizers['type'][$type], $field);
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
   * Re-fetches the user bypassing the cache.
   *
   * @return \Drupal\user\Entity\User
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  private function refetchUser($user_id) {
    return $this->entityTypeManager->getStorage('user')
      ->loadUnchanged($user_id);
  }

  /**
   * Generates an unique email address.
   *
   * Uses the timestamp to ensure this is unique.
   *
   * @throws \Drupal\Core\TypedData\Exception\ReadOnlyException
   */
  public static function anonymizeMail(FieldItemListInterface $field) {
    $mail = 'anon_' . time() . '@example.com';
    $field->setValue($mail);
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
