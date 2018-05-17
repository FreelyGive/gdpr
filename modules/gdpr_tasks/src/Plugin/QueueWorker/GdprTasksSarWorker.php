<?php


/**
 * Queue worker callback for processing SARs requests.
 */
class GdprTasksSarWorker {

  /**
   * Process the SARs request.
   */
  public function processItem($data) {
    /* @var \GDPRTask $task */
    $task = gdpr_task_load($data);

    // Work out where we are up to and what to do next.
    switch ($task->status) {
      // Received but not initialised.
      case 'requested':
        $this->initialise($task);
        break;

      // Processed by staff and ready to compile.
      case 'processed':
        $this->compile($task);
        break;
    }
  }

  /**
   * Initialise our request.
   *
   * @param \GDPRTask $task
   *   The task.
   */
  protected function initialise(\GDPRTask $task) {
    $field_info = field_info_field('gdpr_tasks_sar_export');
    $directory = $field_info['settings']['uri_scheme'] . '://gdpr_sars';
    file_prepare_directory($directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Get a suitable namespace for gathering our files.
    do {
      // Generate a UUID.
      $uuid = ctools_uuid_generate();

      // Check neither the file exists nor the directory.
      if (file_exists("{$directory}/{$uuid}.zip") || file_exists("{$directory}/{$uuid}/")) {
        continue;
      }

      // Generate the zip file to reserve our namespace.
      $file = file_save_data('', "{$directory}/{$uuid}.zip", FILE_EXISTS_ERROR);
    } while (!$file);

    // Prepare the directory for our sub-files.
    $content_directory = "{$directory}/{$uuid}";
    file_prepare_directory($content_directory, FILE_CREATE_DIRECTORY | FILE_MODIFY_PERMISSIONS);

    // Store the file against the task.
    $task->gdpr_tasks_sar_export[LANGUAGE_NONE][0] = [
      'fid' => $file->fid,
      'display' => TRUE,
    ];
    $task->status = 'building';
    $task->save();

    // Start the build process.
    $this->build($task);
  }

  /**
   * Build the export files.
   *
   * @param \GDPRTask $task
   *   The task.
   */
  protected function build(\GDPRTask $task) {
    $field_info = field_info_field('gdpr_tasks_sar_export');
    $directory = $field_info['settings']['uri_scheme'] . '://gdpr_sars/';
    $directory .= basename($task->wrapper()->gdpr_tasks_sar_export->file->url->value(), '.zip');

    // Gather our entities.
    $all_data = gdpr_tasks_collect_rta_data($task->getOwner());

    // Build our export files.
    $csvs = array();
    foreach ($all_data as $plugin_id => $data) {
      // Skip if we don't need this data.
      if (!in_array($data['rta'], array('inc', 'maybe'))) {
        continue;
      }

      // Build the header if required.
      if (!isset($csvs[$data['file']]['_header'][$plugin_id])) {
        $csvs[$data['file']]['_header'][$plugin_id] = $data['label'];
      }

      // Initialise and fill out the row to make sure things come in a
      // consistent order.
      if (!isset($csvs[$data['file']][$data['entity_id']])) {
        $csvs[$data['file']][$data['entity_id']] = array();
      }
      $csvs[$data['file']][$data['entity_id']] += array_fill_keys(array_keys($csvs[$data['file']]['_header']), '');

      // Put our piece of information in place.
      $csvs[$data['file']][$data['entity_id']][$plugin_id] = $data['value'];
    }

    // Gather existing files.
    $files = array();
    foreach ($task->wrapper()->gdpr_tasks_sar_export_parts as $item) {
      $filename = basename($item->file->url->value(), '.csv');
      $files[$filename] = $item->file->value();
    }

    // Write our CSV files.
    foreach ($csvs as $filename => $data) {
      if (!isset($files[$filename])) {
        // Create an empty file.
        $file = file_save_data('', "{$directory}/{$filename}.csv", FILE_EXISTS_REPLACE);

        // Track the file.
        $task->gdpr_tasks_sar_export_parts[LANGUAGE_NONE][] = [
          'fid' => $file->fid,
          'display' => TRUE,
        ];
      }

      $handler = fopen($file->uri, 'w');
      foreach ($data as $row) {
        fputcsv($handler, $row);
      }
      fclose($handler);
    }

    // Update the status.
    $task->status = 'reviewing';
    $task->save();
  }

  /**
   * Compile the SAR into a downloadable zip.
   *
   * @param \GDPRTask $task
   *   The task.
   */
  protected function compile(\GDPRTask $task) {
    // Compile all files into a single zip.
    $zipfile = drupal_realpath($task->wrapper()->gdpr_tasks_sar_export->file->value()->uri);
    dpm($zipfile);
    $zip = new ZipArchive();
    if (!$zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
      drupal_set_message('error opening file', 'error');
      return;
    }

    foreach ($task->wrapper()->gdpr_tasks_sar_export_parts as $item) {
      $filename = basename($item->file->value()->uri);
      dvm($zip->addFile(drupal_realpath($item->file->value()->uri), $filename), "Adding $filename");
    }

    dvm($zip->close(),'close');

    // Update the status as completed.
    $task->status = 'closed';
    $task->save();
  }

}