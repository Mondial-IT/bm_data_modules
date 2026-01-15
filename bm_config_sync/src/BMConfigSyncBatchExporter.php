<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2025
   Created on 2025-6-7 at 3:50:45
 */

namespace Drupal\bm_config_sync;

use Drupal;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;

/**
 * Handles exporting Drupal configuration entities via the batch API.
 */
class BMConfigSyncBatchExporter {

  protected StorageInterface $activeStorage;

  protected FileStorage $syncStorage;

  public function __construct() {
  }

  /**
   * Prepares a batch definition for exporting curated configuration.
   */
  public function batchPrepare(): array {
    try {
      $this->activeStorage = \Drupal::service('config.storage');
      $configStorage = \Drupal::service('config.storage');
      $syncDirectory = $this->ensureSyncDirectoryExists();
      $this->clearSyncDirectory($syncDirectory);
      $this->syncStorage = new FileStorage($syncDirectory);

      $batch = [
        'title' => t('Exporting configurations...'),
        'operations' => [],
        'finished' => '\\Drupal\\bm_config_sync\\BMConfigSyncBatchExporter::exportBatchFinished',
      ];

      $configNames = $configStorage->listAll();
      foreach ($configNames as $name) {
        if (!$this->isTrackedConfig($name)) {
          continue;
        }
        $data = $configStorage->read($name);
        $batch['operations'][] = [
          '\\Drupal\\bm_config_sync\\BMConfigSyncBatchExporter::batchOperationCallback',
          [$name, $data, $this->syncStorage],
        ];
      }

      foreach ($configStorage->getAllCollectionNames() as $collection) {
        foreach ($configStorage->listAll($collection) as $name) {
          if (in_array($name, $configNames, TRUE)) {
            continue;
          }
          if (!$this->isTrackedConfig($name)) {
            continue;
          }
          $data = $configStorage->read($name);
          $batch['operations'][] = [
            '\\Drupal\\bm_config_sync\\BMConfigSyncBatchExporter::batchOperationCallback',
            [$name, $data, $this->syncStorage],
          ];
        }
      }

      return $batch;
    }
    catch (\Exception $e) {
      Drupal::messenger()->addError($e->getMessage());
      return [];
    }
  }

  protected function ensureSyncDirectoryExists(): string {
    $configured = Settings::get('config_sync_directory');
    if (!$configured) {
      throw new \RuntimeException('The config_sync_directory setting is not defined.');
    }
    $fileSystem = \Drupal::service('file_system');
    $fileSystem->prepareDirectory($configured, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $realPath = $fileSystem->realpath($configured);
    if (!$realPath) {
      throw new \RuntimeException(sprintf('Unable to resolve config sync directory path: %s', $configured));
    }
    if (!is_writable($realPath)) {
      throw new \RuntimeException(sprintf('The config sync directory is not writable: %s', $realPath));
    }
    return $realPath;
  }

  protected function clearSyncDirectory(string $syncDirectory): void {
    if (!is_dir($syncDirectory)) {
      throw new \RuntimeException("The sync directory does not exist: $syncDirectory");
    }

    $files = array_diff(scandir($syncDirectory), ['.', '..']);
    foreach ($files as $file) {
      $filePath = $syncDirectory . DIRECTORY_SEPARATOR . $file;
      if (is_file($filePath)) {
        unlink($filePath);
      }
      elseif (is_dir($filePath)) {
        $this->deleteDirectoryRecursive($filePath);
      }
    }

    Drupal::messenger()->addMessage('Sync directory cleared: ' . $syncDirectory);
  }

  protected function deleteDirectoryRecursive(string $directory): void {
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

  protected function isTrackedConfig(string $name): bool {
    $prefixes = [
      'views.view.',
      'node.type.',
      'taxonomy.vocabulary.',
      'paragraphs.paragraph_type.',
      'field.storage.',
      'field.field.node',
      'field.field.paragraph',
      'field.field.taxonomy_term',
      'core.base_field_override.node.',
      'core.base_field_override.paragraph.',
      'core.base_field_override.taxonomy_term.',
      'core.entity_form_display.node',
      'core.entity_form_display.paragraph',
      'core.entity_form_display.taxonomy_term',
      'core.entity_view_display.node',
      'core.entity_view_display.paragraph',
      'core.entity_view_display.taxonomy_term',
      'core.entity_view_mode.node',
      'core.entity_view_mode.paragraph',
      'core.entity_view_mode.taxonomy_term',
      'language.content_settings.node',
      'language.content_settings.paragraph',
      'language.content_settings.taxonomy_term',
    ];
    foreach ($prefixes as $prefix) {
      if (str_starts_with($name, $prefix)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public static function batchOperationCallback($name, $data, FileStorage $syncStorage, array &$context): void {
    try {
      $syncStorage->write($name, $data);
      $context['results'][] = t('Exported configuration: @name', ['@name' => $name]);
      Drupal::logger('bm_config_sync')->debug('Exported configuration: @name', ['@name' => $name]);
    }
    catch (\Exception $e) {
      $context['results'][] = t('Failed to export configuration: @name', ['@name' => $name]);
      Drupal::logger('bm_config_sync')->error('Failed to export configuration: @name. @error', ['@name' => $name, '@error' => $e->getMessage()]);
    }
  }

  public static function exportBatchFinished($success, $results, $operations): void {
    if ($success) {
      Drupal::messenger()->addMessage(t('Configuration export completed successfully.'));
    }
    else {
      Drupal::messenger()->addError(t('Configuration export failed.'));
    }

    foreach ($results as $result) {
      Drupal::messenger()->addMessage($result);
    }
  }

}
