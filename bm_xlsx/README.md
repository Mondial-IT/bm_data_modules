# Blue Marloc Excel API

## Overview
Blue Marloc Excel API exposes helper services and forms for uploading XLSX spreadsheets, inspecting sheet metadata, and extracting row level data used by other Blue Marloc modules.

## Compatibility
- **Drupal core:** 11.x
- **PHP:** 8.3+
- Bundles the `shuchkin/simplexlsx` library within the module’s `vendor` directory.

## Installation
1. Copy the module to `modules/custom/bm_xlsx`.
2. Enable via the UI or using Drush:
   ```bash
   drush en bm_xlsx
   ```

## Configuration & Usage
- The settings form at `/admin/config/system/bm-xlsx/settings` lets administrators upload an XLSX file, select a worksheet, and preview styled data.
- Uploaded files are managed through Drupal’s file API; ensure allowed file extensions include `xlsx`.
- The `BMXLSX` service exposes methods such as `getSheetNames()` and `getColors()` for developers to consume in custom workflows.

## Developer Notes
- Service definitions live in `bm_xlsx.services.yml`. Reuse `Drupal\bm_xlsx\BMXLSX\BMXLSX` in other modules through dependency injection.
- The included vendor library has been adjusted for PHP 8.3 nullable parameter requirements. Keep local modifications in mind when updating the library.

## Design & Integration Guidance
- Provide the upload/preview experience as a reusable form element: wrap the sheet+limit selector and preview in a themeable container so modules (e.g., bm_import, bm_config_sync) can embed it via `\Drupal::formBuilder()->getForm()` without custom styling.
- Expose a stable API surface: keep sheet metadata (`getSheetNames()`, `getRowHeader()`), row accessors (`getRows()`, `getRowsKeyed()`, `getRowsWithoutHeader()`), and styling helpers (`getColors()`) under one service; avoid directly calling cached globals.
- Tighten file handling: prefer managed files, validate MIME/extension, and store the selected fid/sheet/limit via config or cache with clear lifetime; bubble predictable exceptions instead of messenger-only notices.
- Offer lightweight read paths: for large uploads, consider streaming CSV as a fallback to avoid memory spikes; accept row limits and column whitelists to keep previews responsive.
- The site runs with an AI (MCP) integration: when available, expose hooks that let other modules request column summaries, anomaly detection, or schema suggestions on uploaded sheets without bypassing Drupal’s permissions.

## DevOps Guidance
- Temporary uploads are stored in Drupal’s default file scheme. Confirm that cron jobs or CLI importers use the same scheme to avoid missing files.
- Large spreadsheets may require increased PHP memory limits; monitor usage during heavy imports.

## Testing
- Validate PHP syntax after modifications:
  ```bash
  php -l $(git ls-files "bm_xlsx/*.php" "bm_xlsx/src/**/*.php")
  ```
- Run module kernel tests:
  ```bash
  php ./vendor/bin/phpunit web/modules/custom/bm_data_modules/bm_xlsx/tests/src/Kernel/BMXLSXKernelTest.php
  ```
- Exercise the upload form in a non-production environment to confirm server level dependencies (file permissions, max upload size) are satisfied.
