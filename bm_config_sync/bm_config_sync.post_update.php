<?php

use Drupal\bm_config_sync\Utility\BMConfigSyncPathHelper;

/**
 * Ensure the UUID sync directory exists.
 */
function bm_config_sync_post_update_9001_create_uuid_directory(): string {
  BMConfigSyncPathHelper::ensureConfiguredDirectoryExists();
  return t('Verified the bm_config_sync UUID directory exists.');
}

/**
 * Remove legacy uuid_sync_directory configuration overrides.
 */
function bm_config_sync_post_update_9002_lock_uuid_directory(): string {
  $config = \Drupal::configFactory()->getEditable('bm_config_sync.settings');
  if ($config->get('uuid_sync_directory') !== NULL) {
    $config->clear('uuid_sync_directory')->save();
  }

  return t('Locked the UUID directory to private://bm_config_sync.');
}
