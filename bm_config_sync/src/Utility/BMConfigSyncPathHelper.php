<?php

namespace Drupal\bm_config_sync\Utility;

use Drupal;
use Drupal\Core\File\FileSystemInterface;

/**
 * Centralizes logic around UUID sync directory paths.
 */
class BMConfigSyncPathHelper {

  /**
   * Default private path used for UUID exports.
   */
  public const DEFAULT_UUID_DIRECTORY = 'private://bm_config_sync';

  /**
   * Returns the fixed directory for UUID assets.
   */
  public static function getConfiguredDirectory(): string {
    return self::DEFAULT_UUID_DIRECTORY;
  }

  /**
   * Resolves a directory path or stream wrapper to an absolute filesystem path.
   */
  public static function resolveDirectoryPath(string $directory): ?string {
    $directory = trim($directory);
    if ($directory === '') {
      return NULL;
    }

    if (str_contains($directory, '://')) {
      [$scheme, $target] = explode('://', $directory, 2);
      $scheme_base = Drupal::service('file_system')->realpath($scheme . '://');
      if ($scheme_base === FALSE) {
        return NULL;
      }
      $scheme_base = rtrim($scheme_base, DIRECTORY_SEPARATOR);
      $target = trim($target, DIRECTORY_SEPARATOR);
      return $target === '' ? $scheme_base : $scheme_base . DIRECTORY_SEPARATOR . $target;
    }

    return $directory;
  }

  /**
   * Ensures the configured directory exists (creates stream-wrapper directories).
   */
  public static function ensureConfiguredDirectoryExists(): bool {
    $directory = self::getConfiguredDirectory();
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = Drupal::service('file_system');
    try {
      return $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    }
    catch (\Exception $e) {
      Drupal::logger('bm_config_sync')->error('Unable to prepare UUID directory %directory: @message', [
        '%directory' => $directory,
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
  }

  /**
   * Provide both configured and resolved directories.
   */
  public static function getDirectoryInfo(): ?array {
    $configured = self::getConfiguredDirectory();
    $resolved = self::resolveDirectoryPath($configured);
    if ($resolved === NULL) {
      return NULL;
    }

    return [
      'configured' => $configured,
      'resolved' => $resolved,
    ];
  }

}
