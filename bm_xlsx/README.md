# Blue Marloc Excel API

## Overview
The bm_xlsx module provides an admin workflow and reusable services for uploading XLSX spreadsheets, inspecting sheet metadata, and extracting row-level data used by other Blue Marloc modules.

## Features
- Excel media type (`bm_xlsx_excel`) with metadata stored on linked Paragraph entries.
- Admin settings form for upload/selection, sheet/limit selection, and preview.
- Dedicated Excel library page at `/admin/content/media/excel` with metadata columns and filters.
- Shared BMXLSX service wrapper around the `shuchkin/simplexlsx` library.

## Technical Stack
- Drupal 11.2.x
- PHP 8.3+
- Bundled `shuchkin/simplexlsx` vendor library

## Installation
1. Start the DDEV environment:
   ```bash
   ddev start
   ```
2. Enable the module:
   ```bash
   ddev exec drush en bm_xlsx -y
   ```
3. Run database updates (creates the Excel media type/fields and the library view):
   ```bash
   ddev exec drush updb -y
   ```
4. Clear caches:
   ```bash
   ddev exec drush cr
   ```

## Configuration
- Settings form: `/admin/config/system/bm-xlsx/settings`
- Excel library view: `/admin/content/media/excel`
- Required core modules: `file`, `media`, `media_library`

## Usage
- Upload or select an Excel file via the settings form.
- Choose a sheet and row limit to preview data.
- Use the Excel library view to filter/sort uploads by filename and metadata counts.

## Code Examples
PHP (service usage):
```php
use Drupal\bm_xlsx\BMXLSX\BMXLSX;

$xlsx = new BMXLSX($file_uri);
$sheet_names = $xlsx->getSheetNames();
$header = $xlsx->getRowHeader(0);
$rows = $xlsx->getRowsKeyed(0, 25);
```

PHP (embed the reusable form):
```php
$form = \Drupal::formBuilder()->getForm(\Drupal\bm_xlsx\Form\ExcelUploadAndSheetSelectForm::class);
```

JS (attach lightweight behavior for selection changes):
```js
((Drupal, once) => {
  Drupal.behaviors.bmXlsxPreviewHint = {
    attach(context) {
      once('bm-xlsx-preview', '.bm-xlsx-upload__controls select', context).forEach((el) => {
        el.addEventListener('change', () => {
          console.info('Excel selection changed; AJAX refresh will follow.');
        });
      });
    },
  };
})(Drupal, once);
```

## File Structure
- `src/Form/SettingsForm.php`: Admin settings form + preview panel
- `src/Form/ExcelUploadAndSheetSelectForm.php`: Embeddable upload/selection form
- `help_topics/`: Drupal help topic templates
- `config/install/views.view.bm_xlsx_excel_library.yml`: Excel library view config

## Testing
```bash
ddev exec phpunit web/modules/custom/bm_data_modules/bm_xlsx/tests/src/Kernel/BMXLSXKernelTest.php
```

## Coding Standards
```bash
ddev exec phpcs --standard=Drupal web/modules/custom/bm_data_modules/bm_xlsx
```

## Security Considerations
- Only administrators should access the Excel library view and settings form.
- Uploaded files are managed via Drupal’s file API; ensure file permissions and upload limits are configured appropriately.

## Versioning
- Module versions track the parent Drupal project versioning policy.

## Contributing Guidelines
- Follow the repository Codex rules in `codex/agents/`.
- Update help topics and README when adding user-facing features.

## License
- Proprietary (Mondial-IT / Blue Marloc).

## Maintainers / Contact
- Blue Marloc engineering team.
