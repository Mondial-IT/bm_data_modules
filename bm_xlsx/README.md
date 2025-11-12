# bm_xlsx

Module purpose
- Helper utilities for reading/writing XLSX files, or providing integration with spreadsheet imports/exports.

Compatibility
- Target Drupal versions: Drupal 10 and Drupal 11.
- PHP: Compatible with PHP 8.3. Use a maintained library for XLSX handling (eg. phpoffice/phpspreadsheet).

Installation
1. Place the module in modules/custom/bm_xlsx.
2. Install library dependencies, e.g.:
  - `composer require phpoffice/phpspreadsheet`
3. Enable the module: `drush en bm_xlsx -y`.

Upgrade notes (D9 -> D11)
- Ensure the XLSX library supports PHP 8.3.
- Replace any deprecated Drupal APIs in module code.
- Use dependency injection and ensure all properties are declared (PHP 8.3 dynamic property issues).

Developer notes
- Use streamed reads for large spreadsheets to reduce memory usage.
- Provide configuration options for column mappings and character encodings.

Operational/DevOps notes
- Ensure enough memory for large exports or use chunked processing.
- Verify PHP extensions (zip, xml) required by spreadsheet libraries are enabled.

Troubleshooting
- If reading fails, inspect file format (sometimes CSV renamed as XLSX).
- Ensure libraries are correctly installed via Composer.

Recommended checks (run locally)
- Create sample XLSX files and run import/export flows.
- Run static analyzers and unit tests if present.
