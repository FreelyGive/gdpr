<?php

namespace Drupal\gdpr_tasks;

use Drupal\Component\Annotation\Plugin;
use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TypedData\Exception\ReadOnlyException;
use Drupal\gdpr_dump\Sanitizer\GdprSanitizerFactory;
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

  private $sanitizerFactory;

  /**
   * Anonymizer constructor.
   */
  public function __construct(GDPRCollector $collector, Connection $db, EntityTypeManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, AccountProxyInterface $current_user, GdprSanitizerFactory $sanitizer_factory) {
    $this->collector = $collector;
    $this->db = $db;
    $this->entityTypeManager = $entity_manager;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->sanitizerFactory = $sanitizer_factory;
  }

  /**
   * Runs anonymization routines against a user.
   */
  public function run(TaskInterface $task) {
    // Make sure we load a fresh copy of the entity (bypassing the cache)
    // so we don't end up affecting any other references to the entity.
    $user = $task->getOwner();

    $errors = [];
    $entities = [];
    $successes = [];
    $failures = [];

    $log = [];

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

          if ($success === TRUE) {
            $log[] = [
              'entity_id' => $bundle_entity->id(),
              'entity_type' => $bundle_entity->getEntityTypeId() . '.' . $bundle_entity->bundle(),
              'field_name' => $field->getName(),
              'action' => $mode,
            ];
          }
          else {
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

    $task->get('removal_log')->setValue($log);

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

  private function anonymize(FieldItemListInterface $field, EntityInterface $bundle_entity, $entity_type) {
    $sanitizer_id = $this->getSanitizerId($field, $bundle_entity);

    if (!$sanitizer_id) {
      return [
        FALSE,
        "Could not anonymize field {$field->getName()}. Please consider changing this field from 'anonymize' to 'remove', or register a custom sanitizer.",
      ];
    }

    try {
      $sanitizer = $this->sanitizerFactory->get($sanitizer_id);
      $field->setValue($sanitizer->sanitize($field->value, $field));
      return [TRUE, NULL];
    }
    catch (\Exception $e) {
      return [FALSE, $e->getMessage()];
    }

  }

  private function getSanitizerId(FieldItemListInterface $field, EntityInterface $bundle_entity) {
    // First check if this field has a sanitizer defined.
    $fieldDefinition = $field->getFieldDefinition();
    $type = $fieldDefinition->getType();
    $sanitizer = $fieldDefinition
      ->getConfig($bundle_entity->bundle())
      ->getThirdPartySetting('gdpr_fields', 'gdpr_fields_sanitizer');

    if (!$sanitizer) {
      // No sanitizer defined directly on the field. Instead try and get one for the datatype.
      $sanitizers = [
        'string' => 'gdpr_text_sanitizer',
        'datetime' => 'gdpr_date_sanitizer',
      ];

      $this->moduleHandler->alter('gdpr_type_sanitizers', $sanitizers);
      $sanitizer = $sanitizers[$type];
    }
    return $sanitizer;
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
