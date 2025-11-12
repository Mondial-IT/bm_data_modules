# Blue Marloc Config Sync

## Overview
The Config Sync module streamlines exporting, inspecting, and synchronizing configuration between environments. It includes tooling for building entity relationship diagrams (ERDs) of content model definitions and supports curated export flows for nodes, taxonomies, and paragraph types.

## Compatibility
- **Drupal core:** 11.x
- **PHP:** 8.3+
- Depends on the standard Drupal configuration system. Ensure `config_sync_directory` is writable for web and CLI users.

## Installation
1. Copy the module into `modules/custom/bm_config_sync`.
2. Enable it via the Drupal UI or run:
   ```bash
   drush en bm_config_sync
   ```
3. Confirm that the site configuration sync directory is configured in `settings.php`.

## Configuration & Usage
- Navigate to **Configuration → Development → Config Sync** to access the ERD builder, UUID inspector, and batch export forms.
- Use the settings form (`/admin/config/development/bm-config-sync/settings`) to access module level defaults.
- Batch exports write into the configured sync directory. Ensure filesystem permissions allow both the web server and deployment automation to read/write.

## Developer Notes
- Services are registered in `bm_config_sync.services.yml`. Dependency injection is available; avoid using the global `\Drupal` facade when extending functionality.
- Review `src/Handler/BMConfigSyncHandler.php` for the ERD aggregation logic. Guard long-running operations with batch API if they are extended.
- Twig overrides for batch progress live in `src/templates` and are registered through the module’s libraries file.

## DevOps Guidance
- The module writes YAML files into the sync directory. Include that directory in deployment artifacts or manage it through configuration management pipelines.
- Cron and Drush batch operations use the same export routines; ensure command line PHP has permission to the sync directory.
- Log messages are emitted through Drupal’s messenger and logger services. Integrate with your centralized logging stack as required.

## Testing
- Run static analysis after changes:
  ```bash
  php -l $(git ls-files "*.php")
  ```
- Where possible, validate exports on a staging environment before promoting to production.
