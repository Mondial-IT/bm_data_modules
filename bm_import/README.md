# Blue Marloc Import

## Overview
The Blue Marloc Import module orchestrates batch creation and updating of Drupal content using structured spreadsheets, CSV files, and YAML configuration. It coordinates node, taxonomy, and paragraph operations while offering inspection and preflight tooling for editors.

## Compatibility
- **Drupal core:** 11.x
- **PHP:** 8.3+
- Requires the Blue Marloc auxiliary modules that provide debugging (`bm_debug`), taxonomy helpers (`bm_taxonomies`), and listing features where referenced.

## Installation
1. Place the module in `modules/custom/bm_import`.
2. Enable required dependencies, then enable the module:
   ```bash
   drush en bm_import
   ```
3. Grant access to the administration permissions exposed by the module.

## Configuration & Usage
- The main management UI lives at **Content → Blue Marloc Import** and walks operators through upload, validation, and batch processing panels.
- Define import blueprints under `config/install` and ensure referenced vocabularies, paragraphs, and content types exist before running imports.
- Use the configuration export/import forms to sync import definitions between environments.

## Developer Notes
- PHP namespaces follow Drupal standards (`Drupal\bm_import\…`). Custom services are declared in `bm_import.services.yml`.
- Core import logic resides in `src/BMImport`. Handlers for specific entity types are under `src/BMImportHandlers`.
- Debugging utilities rely on `bm_d()` hooks. Guard new calls with `function_exists('bm_d')` when integrating with deployments that may not include the debugging module.

## DevOps Guidance
- Batch imports can be long-running; configure the PHP CLI timeout appropriately for Drush executions.
- File uploads are stored via Drupal’s file API. Ensure temporary and permanent directories have sufficient space.
- For reproducibility, store import spreadsheets and YAML definitions in version control or managed object storage.

## Testing
- Run static linting:
  ```bash
  php -l $(git ls-files "bm_import/*.php" "bm_import/src/**/*.php")
  ```
- Exercise batch flows on a staging environment. Monitor watchdog logs for exception messages during dry runs.
