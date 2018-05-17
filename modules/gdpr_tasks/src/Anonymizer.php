<?php

/**
 * Anonymizes or removes field values for GDPR.
 */
class Anonymizer {

  protected $plugins = [];
  protected $plugin_data = [];

  /**
   * Runs anonymization routines against a user.
   *
   * @param GDPRTask $task
   *   The current task being executed.
   *
   * @return array
   *   Returns array containing any error messages.
   */
  public function run(GDPRTask $task) {
    // Make sure we load a fresh copy of the entity (bypassing the cache)
    // so we don't end up affecting any other references to the entity.
    $user = $task->getOwner();

    $errors = [];
    $entities = [];
    $successes = [];
    $failures = [];
    $log = [];

    if (!$this->checkExportDirectoryExists()) {
      $errors[] = 'An export directory has not been set. Please set this under Configuration -> GDPR -> Right to be Forgotten';
    }

    foreach (gdpr_tasks_collect_rtf_data($user, TRUE) as $plugin_id => $data) {
      $plugin = $data['plugin'];
      unset($data['plugin']);

      $this->plugins[$plugin->entity_type][$plugin_id] = $plugin;
      $this->plugin_data[$plugin->entity_type][$plugin_id] = $data;
    }
    $entities = gdpr_fields_collect_gdpr_entities('user', $user);

    foreach ($entities as $entity_type => $bundles) {
      foreach ($bundles as $entity_bundle => $entities) {
        foreach ($entities as $bundle_entity_id => $bundle_entity) {

          // Re-load a fresh copy of the bundle entity from storage so we don't
          // end up modifying any other references to the entity in memory.
          $bundle_entity = entity_load_unchanged($entity_type, $bundle_entity_id);
          $entity_success = TRUE;

          foreach ($this->getFieldsToProcess($entity_type, $bundle_entity) as $field_info) {
            $mode = $field_info['mode'];

            $success = TRUE;
            $msg = NULL;
            $sanitizer = '';

            if ($mode == 'anonymise') {
              list($success, $msg, $sanitizer) = $this->anonymize($field_info, $bundle_entity);
            }
            elseif ($mode == 'remove') {
              list($success, $msg) = $this->remove($field_info, $bundle_entity);
            }

            if ($success === TRUE) {
              $log[] = 'success';
              $log[] = [
                'entity_id' => $bundle_entity_id,
                'entity_type' => $entity_type . '.' . $entity_bundle,
                'field_name' => $field_info['field'],
                'action' => $mode,
                'sanitizer' => $sanitizer,
              ];
            }
            else {
              // Could not anonymize/remove field. Record to errors list.
              // Prevent entity from being saved.
              $entity_success = FALSE;
              $errors[] = $msg;
              $log[] = 'error';
              $log[] = [
                'error' => $msg,
                'entity_id' => $bundle_entity_id,
                'entity_type' => $entity_type . '.' . $entity_bundle,
                'field_name' => $field_info['field'],
                'action' => $mode,
                'sanitizer' => $sanitizer,
              ];
            }
          }

          if ($entity_success) {
            $successes[$entity_type][$bundle_entity_id] = $bundle_entity;
          }
          else {
            $failures[] = $bundle_entity;
          }
        }
      }
    }

    // @todo Better log field.
    $task->wrapper()->gdpr_tasks_removal_log = json_encode($log);

    if (count($failures) === 0) {
      $tx = db_transaction();

      try {
        /* @var EntityInterface $entity */
        foreach ($successes as $entity_type => $entities) {
          foreach ($entities as $entity) {
            entity_save($entity_type, $entity);
          }
        }
        // Re-fetch the user so we see any changes that were made.
        $user = entity_load_unchanged('user', $task->user_id);
        user_save($user, array('status' => 0));

        // @todo Write a log to file system.
//        $this->writeLogToFile($task, $log);
      }
      catch (\Exception $e) {
        $tx->rollback();
        $errors[] = $e->getMessage();
      }
    }

    return $errors;
  }

  /**
   * Removes the field value.
   *
   * @param string $field_info
   *   The current field to process.
   * @param EntityInterface|stdClass $entity
   *   The current field to process.
   *
   * @return array
   *   First element is success boolean, second element is the error message.
   */
  private function remove($field_info, $entity) {
    try {
      /* @var EntityDrupalWrapper $wrapper */
      $wrapper = entity_metadata_wrapper($field_info['entity_type'], $entity);

      /* @var EntityMetadataWrapper $field */
      $field = $wrapper->{$field_info['field']};
      $this->clearField($field);
      return [TRUE, NULL];
    }
    catch (Exception $e) {
      return [FALSE, $e->getMessage()];
    }
  }

  /**
   * Removes the field value.
   *
   * @param EntityMetadataWrapper $field
   *   The current field to process.
   *
   * @return array
   *   First element is success boolean, second element is the error message.
   */
  protected function clearField($field) {
    if ($field instanceof EntityValueWrapper) {
      // @todo Add any other types that come up.
      switch ($field->info()['type']) {
        case 'text':
          $field->set('');
          break;

        default:
          $field->set(NULL);
      }
    }
    elseif ($field instanceof EntityListWrapper) {
      $field->set(array());
    }
    elseif ($field instanceof EntityStructureWrapper) {
      $list = $field->getPropertyInfo();
      if (!empty($list) && !empty($field->value())) {
        foreach (array_keys($field->value()) as $key) {
          if (!empty($list) && in_array($key, array_keys($list))) {
            $sub_field = $field->{$key};
            $this->clearField($sub_field);
          }
        }
      }
    }
  }

  /**
   * Runs anonymize functionality against a field.
   *
   * @param string $field_info
   *   The field to anonymise.
   * @param $entity
   *   The parent entity.
   *
   * @return array
   *   First element is success boolean, second element is the error message.
   */
  private function anonymize($field_info, $entity) {
    $sanitizer_id = $this->getSanitizerId($field_info, $entity);

    if (!$sanitizer_id) {
      return [
        FALSE,
        "Could not anonymize field {$field_info['field']}. Please consider changing this field from 'anonymize' to 'remove', or register a custom sanitizer.",
        NULL,
      ];
    }

    try {
      $plugin = gdpr_dump_get_gdpr_sanitizer($sanitizer_id);
      $class = ctools_plugin_get_class($plugin, 'handler');
      /* @var GDPRSanitizerDefault $sanitizer */
      $sanitizer = $class::create($plugin);

      $wrapper = entity_metadata_wrapper($field_info['entity_type'], $entity);

      $wrapper->{$field_info['field']} = $sanitizer->sanitize($field_info['value'], $wrapper->{$field_info['field']});
      return [TRUE, NULL, $sanitizer_id];
    }
    catch (\Exception $e) {
      return [FALSE, $e->getMessage(), NULL];
    }
  }


  /**
   * Checks that the export directory has been set.
   *
   * @return bool
   *   Indicates whether the export directory has been configured and exists.
   */
  private function checkExportDirectoryExists() {
    // @todo Configure export directory.
    $directory = 'private://gdpr-export';

    return !empty($directory) && file_prepare_directory($directory, FILE_CREATE_DIRECTORY);
  }

  /**
   * Gets fields to anonymize/remove.
   *
   * @param $entity
   *   The entity to anonymise.
   *
   * @return array
   *   Array containing metadata about the entity.
   *   Elements are entity_type, bundle, field, value, mode and plugin.
   */
  private function getFieldsToProcess($entity_type, $entity) {
    if (!isset($this->plugins[$entity_type])) {
      return array();
    }

    $plugins = $this->plugins[$entity_type];
    list(, , $bundle) = entity_extract_ids($entity_type, $entity);

    // Get fields for entity.
    $fields = [];
    foreach ($entity as $field_id => $field) {
      $plugin_name = "{$entity_type}|{$bundle}|{$field_id}";

      if (isset($plugins[$plugin_name])) {
        $plugin = $plugins[$plugin_name];
        $data = $this->plugin_data[$entity_type][$plugin_name];

        if (!empty($data['rtf']) && $data['rtf'] !== 'no') {
          $fields[] = array(
            'entity_type' => $entity_type,
            'bundle' => $bundle,
            'field' => $field_id,
            'value' => $data['value'],
            'mode' => $data['rtf'],
            'plugin' => $plugin,
          );
        }
      }
    }

    return $fields;
  }

  /**
   * Gets the ID of the sanitizer plugin to use on this field.
   *
   * @param string $field_info
   *   The field to anonymise.
   * @param $entity
   *   The parent entity.
   *
   * @return string
   *   The sanitizer ID or null.
   */
  private function getSanitizerId($field_info, $entity) {
    // First check if this field has a sanitizer defined.
    $sanitizer = $field_info['plugin']->settings['gdpr_fields_sanitizer'];

    // @todo Allow sanitizers to fall back to type selection relevant for the field type.
    if (!$sanitizer) {
      $sanitizer = 'gdpr_sanitizer_text';
    }
    return $sanitizer;
  }

}
