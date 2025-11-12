<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2025
   Created on 2025-6-7 at 3:50:45
 */

namespace Drupal\bm_config_sync;


use Drupal;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\facets\Exception\Exception;

/**
 * Class ConfigurationExporter
 *
 * Handles exporting of configuration entities such as nodes, fields, paragraphs, and taxonomies in batches.
 */
class BMConfigSyncBatchExporter {

  /**
   * @var StorageInterface
   */
  protected $activeStorage;

  /**
   * @var \Drupal\Core\Config\FileStorage
   */
  protected $syncStorage;

  /**
   * @var \Drupal\Core\Config\StorageComparer
   */
  protected $storageComparer;

  /**
   * @var MessengerInterface
   */
  protected $messenger;

  /**
   * Constructor.
   *
   */
  public function __construct() {

  }

  /**
   * Exports configurations related to nodes, fields, paragraphs, and taxonomies.
   *
   * Create a list of batch operations to perform
   *
   * @throws \Exception
   */
  public function batchPrepare(): array {
    try {
      // Get the config factory service to access configuration storage
      $config_factory = \Drupal::configFactory();  // Get the config factory service

      // Get editable configuration storage (for reading or writing)
      $this->activeStorage = \Drupal::service('config.storage');  // Use the config storage service

      // Get the sync directory path.
      $file_system = \Drupal::service('file_system');
      $syncDirectory = 'private://config_sync/';

      // Ensure the sync directory exists and is writable
      $this->ensureSyncDirectoryExistsAndWriteFile($syncDirectory);

      // Clear the sync directory before exporting configurations.
      $this->clearSyncDirectory($syncDirectory);

      // Create a FileStorage instance for the sync directory.
      $this->syncStorage = new FileStorage($syncDirectory);

      // Define the batch
      $batch = [
        'title' => t('Exporting configurations...'),
        'operations' => [],
        'finished' => '\Drupal\bm_config_sync\BMConfigSyncBatchExporter::exportBatchFinished',
        // Finish callback
      ];

      // Initialize total count for operations
      $total_operations = 0;

      // List all the configuration names that are registered in Drupal
      $configStorage =\Drupal::service('config.storage');
      $config_names = $configStorage->listAll(); // Get all configuration names

      // Add operations to the batch for relevant configurations
      foreach ($config_names as $name) {
        // Read each configuration data
        $data = $configStorage->read($name);

        // Only include configurations related to nodes, paragraphs, taxonomies, and fields

        if (
          !str_starts_with($name, 'views.view.') &&
          !str_starts_with($name, 'node.type.') &&
          !str_starts_with($name, 'taxonomy.vocabulary.') &&
          !str_starts_with($name, 'paragraphs.paragraph_type.') &&

          !str_starts_with($name, 'field.storage.') &&
          !str_starts_with($name, 'field.field.node') &&
          !str_starts_with($name, 'field.field.paragraph') &&
          !str_starts_with($name, 'field.field.taxonomy_term') &&

          !str_starts_with($name, 'core.base_field_override.node.') &&
          !str_starts_with($name, 'core.base_field_override.paragraph.') &&
          !str_starts_with($name, 'core.base_field_override.taxonomy_term.') &&

          !str_starts_with($name, 'core.entity_form_display.node') &&
          !str_starts_with($name, 'core.entity_form_display.paragraph') &&
          !str_starts_with($name, 'core.entity_form_display.taxonomy_term') &&

          !str_starts_with($name, 'core.entity_view_display.node') &&
          !str_starts_with($name, 'core.entity_view_display.paragraph') &&
          !str_starts_with($name, 'core.entity_view_display.taxonomy_term') &&

          !str_starts_with($name, 'core.entity_view_mode.node') &&
          !str_starts_with($name, 'core.entity_view_mode.paragraph') &&
          !str_starts_with($name, 'core.entity_view_mode.taxonomy_term') &&

          !str_starts_with($name, 'language.content_settings.node') &&
          !str_starts_with($name, 'language.content_settings.paragraph') &&
          !str_starts_with($name, 'language.content_settings.taxonomy_term')
        ) continue;

          $batch['operations'][] = [
            '\Drupal\bm_config_sync\BMConfigSyncBatchExporter::batchOperationCallback',
            [$name, $data, $this->syncStorage]
          ];

          $total_operations++;

      }

      // Get all  data from the remaining collections.
      foreach ($configStorage->getAllCollectionNames() as $collection) {



        // Add operations to the batch for relevant configurations
        foreach ($configStorage->listAll($collection) as $name) {

          if(in_array($name, $config_names))continue;

          // Read each configuration data
          $data = $configStorage->read($name);

          // Only include configurations related to nodes, paragraphs, taxonomies, and fields
          if (
            !str_starts_with($name, 'views.view.') &&
            !str_starts_with($name, 'node.type.') &&
            !str_starts_with($name, 'taxonomy.vocabulary.') &&
            !str_starts_with($name, 'paragraphs.paragraph_type.') &&

            !str_starts_with($name, 'field.storage.') &&
            !str_starts_with($name, 'field.field.node') &&
            !str_starts_with($name, 'field.field.paragraph') &&
            !str_starts_with($name, 'field.field.taxonomy_term') &&

            !str_starts_with($name, 'core.base_field_override.node.') &&
            !str_starts_with($name, 'core.base_field_override.paragraph.') &&
            !str_starts_with($name, 'core.base_field_override.taxonomy_term.') &&

            !str_starts_with($name, 'core.entity_form_display.node') &&
            !str_starts_with($name, 'core.entity_form_display.paragraph') &&
            !str_starts_with($name, 'core.entity_form_display.taxonomy_term') &&

            !str_starts_with($name, 'core.entity_view_display.node') &&
            !str_starts_with($name, 'core.entity_view_display.paragraph') &&
            !str_starts_with($name, 'core.entity_view_display.taxonomy_term') &&

            !str_starts_with($name, 'core.entity_view_mode.node') &&
            !str_starts_with($name, 'core.entity_view_mode.paragraph') &&
            !str_starts_with($name, 'core.entity_view_mode.taxonomy_term') &&

            !str_starts_with($name, 'language.content_settings.node') &&
            !str_starts_with($name, 'language.content_settings.paragraph') &&
            !str_starts_with($name, 'language.content_settings.taxonomy_term')
          ) continue;

          $batch['operations'][] = [
            '\Drupal\bm_config_sync\BMConfigSyncBatchExporter::batchOperationCallback',
            [$name, $data, $this->syncStorage]
          ];

          $total_operations++;

        }
      }


      // Store the total number of operations to calculate progress
      $batch['total_operations'] = $total_operations;

      // Execute the batch process
      return $batch;
    } catch (Exception $e) {
      // Handle any errors and log them
      Drupal::messenger()->addError($e->getMessage());
      return [];
    }
  }


  /**
   * Function to ensure the private sync directory exists and is writable.
   */
  function ensureSyncDirectoryExistsAndWriteFile(): array|string {
    // Get the file system service
    $file_system = \Drupal::service('file_system');

    // Get the private file system path using realpath()
    $private_path = \Drupal::service('file_system')->realpath('private://');

    // Define the path for the sync directory
    $syncDirectory = $private_path . '/config_sync';

    // Check if the sync directory exists, if not, create it
    if (!is_dir($syncDirectory)) {
      try {
        // Create the directory with the recursive flag (TRUE ensures parent directories are created if missing)
        $file_system->mkdir($syncDirectory, 0775, TRUE); // 0775 sets the permissions
        \Drupal::messenger()
          ->addMessage(t('The directory @directory has been created.', ['@directory' => $syncDirectory]));
      } catch (\Exception $e) {
        \Drupal::messenger()
          ->addError(t('Failed to create the directory @directory: @message', [
            '@directory' => $syncDirectory,
            '@message' => $e->getMessage(),
          ]));
        throw new \Exception('Failed to create the sync directory: ' . $e->getMessage());
      }
    }

    // Ensure the sync directory exists and is writable
    if (!is_writable($syncDirectory)) {
      \Drupal::messenger()->addError("The sync directory is not writable: $syncDirectory");
      return [];
    }

    // Example of writing a file in the private sync directory
    try {
      $file_name = 'example.txt';
      $file_path = $syncDirectory . '/' . $file_name;

      // Content to write to the file
      $content = 'This is a test file for configuration sync.';

      // Write content to the file
      $file_system->saveData($content, $file_path, FileSystemInterface::EXISTS_REPLACE);

      \Drupal::messenger()->addMessage(t('File written successfully to @path', ['@path' => $file_path]));
    } catch (\Exception $e) {
      \Drupal::messenger()->addError(t('Failed to write file to @path: @message', [
        '@path' => $syncDirectory,
        '@message' => $e->getMessage(),
      ]));
      throw new \Exception('Failed to write file to sync directory: ' . $e->getMessage());
    }

    return $syncDirectory;
  }


  /**
   * Clears the sync directory by deleting all configuration files.
   *
   * @param string $syncDirectory
   *
   * @throws \Exception
   */
  protected function clearSyncDirectory($syncDirectory): void {
    // Ensure that the directory exists
    if (!is_dir($syncDirectory)) {
      throw new \Exception("The sync directory does not exist: $syncDirectory");
    }

    // Open the directory and get all files/subdirectories.
    $files = array_diff(scandir($syncDirectory), ['.', '..']);

    // Loop through each file or subdirectory and delete it.
    foreach ($files as $file) {
      $filePath = $syncDirectory . DIRECTORY_SEPARATOR . $file;

      // If it's a file, delete it
      if (is_file($filePath)) {
        unlink($filePath);
      }
      // If it's a directory, recursively delete its contents
      elseif (is_dir($filePath)) {
        $this->deleteDirectoryRecursive($filePath);
      }
    }

    Drupal::messenger()->addMessage('Sync directory cleared: ' . $syncDirectory);
  }

  protected function deleteDirectoryRecursive($directory): void {
    $files = array_diff(scandir($directory), ['.', '..']);

    foreach ($files as $file) {
      $filePath = $directory . DIRECTORY_SEPARATOR . $file;
      if (is_file($filePath)) {
        unlink($filePath);
      }
      elseif (is_dir($filePath)) {
        $this->deleteDirectoryRecursive($filePath);
      }
    }

    rmdir($directory);
  }

  public static function batchOperationCallback($name, $data, FileStorage $syncStorage, array &$context): void {
    try {
     $context['batch_total_operations']=1200;
      // Update the batch progress based on the total number of operations
      $context['finished'] += (1 / $context['batch_total_operations']); // Dynamically calculate progress

      // Optionally update results
      $context['results'][] = 'Exported configuration: ' . $name;

      $syncStorage->write($name, $data);
      $context['results'][] = t('Exported configuration: @name', ['@name' => $name]);
      Drupal::logger(__FUNCTION__)->debug('SUCCESS processed: '.$name);

    } catch (\Exception $e) {
      $context['results'][] = t('Failed to export configuration: @name', ['@name' => $name]);
      Drupal::logger(__FUNCTION__)->debug('ERR processed: '.$name);
    }
  }


  /**
   * Callback function to execute after the batch process is finished.
   *
   * @param bool $success
   * @param array $results
   * @param array $operations
   */
  public static function exportBatchFinished($success, $results, $operations) {
    if ($success) {
      Drupal::messenger()->addMessage(t('Configuration export completed successfully.'));
    }
    else {
      Drupal::messenger()->addError(t('Configuration export failed.'));
    }

    // Provide feedback on the batch process results
    foreach ($results as $result) {
      Drupal::messenger()->addMessage($result);
    }
  }


}
