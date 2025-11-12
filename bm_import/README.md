# bm_import

Module purpose
- Import content or configuration from external sources (CSV/XLS/XLSX/other).
- Designed to be used by site administrators to populate or update site data.

Compatibility
- Target Drupal versions: Drupal 10 and Drupal 11.
- PHP: Compatible with PHP 8.3; ensure PHP dependencies (like PHPExcel alternatives) support 8.3.

Installation
1. Place the module in modules/custom/bm_import or install via Composer if packaged.
2. If the module depends on third-party libraries (e.g., PhpSpreadsheet), run Composer to install them:
  - `composer require phpoffice/phpspreadsheet`
3. Enable the module: `drush en bm_import -y`.

Upgrade notes (D9 -> D11)
- Ensure all third-party libraries are compatible with PHP 8.3.
- Replace any deprecated APIs (e.g., db_* wrappers, drupal_set_message) as noted in bm_config_sync README.
- Validate file handling: use the managed file system and temporary directories per Drupal standards.

Developer notes
- Validate imported data thoroughly.
- Use dependency injection to obtain file and database services.
- Run imports as a QueueWorker or Batch API job for large imports to avoid timeouts.

Operational/DevOps notes
- Watch resource usage when performing large imports. Prefer queue/batch processing.
- Ensure PHP memory and execution limits are adequate.
- For production: run imports during low-traffic windows.

Troubleshooting
- Logging: check watchdog / recent log messages for import errors.
- File permissions: ensure temporary directories are writable by PHP.

Recommended checks (run locally)
- Run module import endpoints with small sample files first.
- Run static analyzers (drupal-check, Rector, PHPStan).
