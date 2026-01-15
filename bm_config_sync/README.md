# Blue Marloc Config Sync

## Overview
Blue Marloc Config Sync provides developer-facing tools to inspect, export, and reconcile Drupal configuration and entity relationship metadata. It centralizes the most common synchronization operations so multiple environments can stay aligned during rapid schema changes.

## Features
- Batch exporter that writes curated configuration (content types, paragraphs, taxonomies, displays) into the Drupal config sync directory.
- JSON endpoints for bundle field definitions and combined ERD metadata.
- UI panels for building per-bundle ERD tables and visualizing nested references.
- Targeted export helpers for nodes, paragraphs, and vocabularies, including dependent field storage and display configs.
- UUID reconciliation utilities (planned via the settings form scaffold).

## Technical Stack
- **Drupal Core:** 11.x
- **Runtime:** PHP 8.3 on DDEV
- **Dependencies:** `drupal:config`, `bm_main`, `bm_tooltip`
- **Client assets:** CSS-only tooltip library via `bm_tooltip/tooltip`

## Installation
1. Start the project stack inside DDEV:
   ```bash
ddev start
   ```
2. Install dependencies and apply patches:
   ```bash
ddev composer install
   ```
3. Enable the module (along with its dependencies if not already enabled):
   ```bash
ddev exec drush en bm_config_sync -y
   ```
4. Rebuild caches:
   ```bash
ddev exec drush cr
   ```

## Configuration
- The module relies on Drupal’s `config_sync_directory` setting. Ensure it points to a writable path (e.g., `sites/default/files/config_HASH/sync`).
- Grant the `administer bm_config_sync configuration` permission to the roles that need access to the UI and JSON endpoints.
- UUID comparison files live in a module-managed private storage area; no configuration changes are required on the settings form.
- The module attempts to create its private storage during install and via a post-update hook. Ensure the web server/DDEV user can write to the private stream so the directory can be created automatically.

## Usage
- Navigate to **Configuration → Development → Config Sync** for the primary UI. Panels let you select bundles, inspect ERDs, and export related configuration.
- Run the batch exporter from `/admin/config/system/bm_config_sync_batch_exporter`. It streams the export inside an iframe and writes YAML into the sync directory.
- JSON endpoints under `/json-api/config/*` return bundle lists, field lists, combined ERDs, and field paths for integration testing or documentation.

## Code Examples
Export a bundle’s configuration programmatically:
```php
/** @var \Drupal\bm_config_sync\Handler\BMConfigSyncHandler $handler */
$handler = \Drupal::service('bm_config_sync.handler');
$yaml = $handler->exportNodeConfigurations('listing');
```
Trigger the batch exporter from custom code:
```php
$exporter = new \Drupal\bm_config_sync\BMConfigSyncBatchExporter();
$batch = $exporter->batchPrepare();
batch_set($batch);
return batch_process();
```

## File Structure
- `bm_config_sync.module` – attaches custom batch styling.
- `bm_config_sync.routing.yml` – UI forms, batch controller, and JSON endpoints.
- `src/Handler/BMConfigSyncHandler.php` – ERD aggregation and targeted export helpers.
- `src/BMConfigSyncBatchExporter.php` – batch controller that writes curated configuration to the sync directory.
- `src/Form/*` – Panel-driven forms for ERD building, exports, and UUID utilities.
- `help_topics/` – Help topic describing module usage.

## Testing
Manual verification steps:
1. Refresh the local database/files via project scripts (see `codex/agents/dev-workflow.md`).
2. Visit `/admin/config/system/bm_config_sync_batch_exporter` and confirm the batch iframe streams without errors.
3. Hit `/json-api/config/bundles` and ensure a JSON payload with bundle names is returned.
4. Run automated tests (once available) via:
   ```bash
   ddev exec phpunit -c web/core/phpunit.xml.dist modules/custom/bm_data_modules/bm_config_sync/tests
   ```
(Currently no automated tests ship with the module; add Kernel coverage when extending handlers.)

## Coding Standards
- Follow PSR-12 + Drupal standards (`ddev exec phpcs web/modules/custom --standard=Drupal,DrupalPractice`).
- Forms attach shared tooltip libraries via `bm_tooltip/tooltip`; avoid theme-specific assets.
- Instantiate services via dependency injection (`bm_config_sync.handler`) instead of `new`.

## Security Considerations
- Exporters honor the configured `config_sync_directory`; verify it resides outside the docroot and is writable only by DDEV/CLI users.
- JSON endpoints require `administer bm_config_sync configuration`; do not expose them to anonymous traffic.
- Batch exports clear the sync directory before writing new YAML—ensure this fits your deployment workflow and that the directory is version-controlled or backed up.

## Versioning
Internal semantic versioning follows the Git repository history. Align releases with Drupal 11 minor versions as needed.

## Contributing Guidelines
1. Open an issue describing the change, linking to any relevant tickets.
2. Work inside DDEV; run `ddev composer install` and `ddev exec phpcs …` before opening a PR.
3. Include regression tests whenever handlers/controllers change.
4. Follow the commit style `bm_config_sync - <action>`.

## License
Proprietary to Mondial-IT BV / Blue Marloc. Do not redistribute without permission.

## Maintainers / Contact
- **Blue Marloc engineering** – <engineering@bluemarloc.com>
- See `codex/agents/README.md` for process owners and escalation paths.
