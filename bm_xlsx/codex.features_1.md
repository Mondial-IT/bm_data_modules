# Feature development
The bm_xlsx module:
* [x] 1. creates and manages an 'Excel drupal media type' to be used with the media library
- for this it has been build with `schuchkin` library to handle the excel
- Create the code to create a dedicated Media type for Excel (or repurpose Media: Document with mime/extension restriction)
- and store sheet/limit metadata (or a reference to cached parsing) on that entity.
- Let the form select a Media item via Media Library, then parse/cache by media id.
- update the excel form, help_topic and readme accordingly.


* [x] Feature 2.0 It provides an `ExcelUploadAndSheetSelectForm.php` which is meant to be included in other modules forms to:
 a. have the user select an excel file, either from the media library or a direct upload file element the form
 b. To the user rich select list is displayed with media id, Excel file name, available sheets and rows in sheets.
 c. The user can select an existing file, or choose `upload` and is then presented with the upload form element to upload a file.
 d. in case of upload, after upload, then is presented (via ajax) the list again with the filename, sheets, and available rows selection.
 e. in case of upload, the last uploaded file is displayed, so the user can see when a file has been uploaded again.
codex: Added source toggle, file metadata + sheet/row summary, and last-upload display to ExcelUploadAndSheetSelectForm.
codex: Updated help topic and README to describe the new selection flow and preview details.

* [x] Feature 3.1 Excel Media Library Admin View (Dedicated Page)
- Create a dedicated admin page listing only Excel media items (Media bundle = Excel) so users can browse Excel assets outside the generic Media Library modal.
- End result: a new admin page like `/admin/content/media/excel` showing only Excel uploads.

* [x] Feature 3.2 Excel Media Library Table Columns (Excel Metadata Fields)
- Extend the View to show Excel-specific fields such as `sheet_name`, `table_name`, `rows_count` as columns so the list becomes meaningful for Excel management.
- End result: a table view where each Excel file row shows its key metrics at a glance.

* [x] Feature 3.3 Excel Media Library Exposed Filters + Sorting
- Add exposed filters (eg min sheet_name, table_name, rows_count, filename search) and sortable columns so users can quickly find relevant Excel files.
- End result: filter controls above the table and sortable headers for metadata columns
* [ ] Feature 3.4 Excel Media Library Permissions + Menu Link
- Add a menu entry and permissions so only the right roles can access/manage the Excel listing.
- End result: a “Excel Library” link in the admin content menu, visible only to allowed roles.

* [ ] Feature 3.5 Media Library Modal: Enhance Excel Card Display (Media library view mode)
- Configure the Excel media “Media library” view mode to display metadata in the selection modal so editors can pick the right Excel file during content editing.
- End result: Excel cards in the Media Library modal show sheet/table/row counts.

* [ ] Feature 3.6 Excel Metadata Extraction Pipeline (Populate sheetcount/tablecount/rows_in_sheet)
- Implement automatic extraction of Excel properties on upload/update so metadata is always filled reliably and consistently.
- End result: after uploading an Excel file, the media entity automatically shows populated Excel metrics without manual input.

* [ ] Feature 3.9 Excel Media “Inspector” Detail Page (Tabbed UI + raw metadata dump)
- Provide a detail/inspector page for an Excel media item showing the computed metadata plus optionally a structured dump (sheets, tables) for troubleshooting.
- End result: an “Excel details” tab on the media page with a clear readable metadata panel.

* [ ] Feature 3.10 Automated Tests (Kernel + Functional + Permission coverage)
- Add automated coverage to ensure extraction, validation, permissions, and View output remain stable through Drupal updates.
- End result: test suite that verifies Excel uploads populate metadata and that the Excel admin page works for allowed roles only.






* [ ] Feature 3.0 It provides API's to other modules to provide excel data to them, the api's are a thin layer (decoupling) over the `schuchkin` library to handle the excel
- It is to provide api interface to modules like bm_import and bm_config_sync.
- Analyse which interfaces are currently available and place them as developer information in a help_topics twig page.
* [ ] Features 4.2 implement the design improvements
- Expose a stable API surface: keep sheet metadata (getSheetNames(), getRowHeader()), row accessors (getRows(), getRowsKeyed(), getRowsWithoutHeader()), and styling helpers (getColors()) under one service; avoid directly calling cached globals.
* [ ] Features 4.3 implement the design improvements
-  Tighten file handling: prefer managed files, validate MIME/extension, and store the selected fid/sheet/limit via config or cache with clear lifetime; bubble predictable exceptions instead of messenger-only notices.
* [ ] Features 4.4 implement the design improvements
-  Offer lightweight read paths: for large uploads, consider streaming CSV as a fallback to avoid memory spikes; accept row limits and column whitelists to keep previews responsive.
* [ ] Features 4.5 implement the design improvements
- The site runs with an AI (MCP) integration: when available, expose hooks that let other modules request column summaries, anomaly detection, or schema suggestions on uploaded sheets without bypassing Drupal’s permissions.
