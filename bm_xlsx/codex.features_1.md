
# Feature development

* [x] Features 1.0 verify the bm_xlsx module for correct references of routing, menu.links. Verify no deprecated code is present for drupal 11.
codex: Updated routing documentation to match the configured path, corrected the XLSX helper to use a valid Exception class and signature, ensured form IDs are unique, and refreshed the help topic to point to the right menu location. No Drupal 11 deprecations found after review.

* [x] Features 2.0 the goal of bm_xlsx
- the bm_xlsx module is meant to serve as interface to excel files.
- It is to provide api interface to modules like bm_import and bm_config_sync.
- for this it has been build with `schuchkin` library to handle the excel
- it should provide a general form which modules can include to have the user upload an excel
- then it should prepare the uploaded excel and provide meta data about the excel
- then it should provide additional api calls to get data from the excel
- however the form excel upload form should be designed better to integrate better in modules.
- task: analyse and design improvements
- task: suggest alternatives to work with excel files given the fact this drupal site has an active AI connection (MCP)
codex: Added design guidance for embedding the upload/preview form, clarified API surface and file-handling expectations, documented performance-friendly approaches (CSV fallback, column whitelists), and noted AI/MCP opportunities for downstream summarization or anomaly checks. Updated README and help topic accordingly.

* [x] Feature 3.0 Implement secure development basis, which can be run after feature implementations.
- Ensure the upload form works, (code correct)
- Create a unit test to check the upload function, conform codex/agents/testing-guidelines.md
- Create a unit test to check the interpretation, conform codex/agents/testing-guidelines.md
codex: Added module dependencies and vendor autoload bootstrap to guarantee the Schuchkin library loads. Introduced kernel tests covering save/upload flow, sheet metadata parsing, and cached sheet/limit selection; documented the test command in README.


* [ ] Features 4.0 implement the design improvements
- Provide the upload/preview experience as a reusable form element: wrap the sheet+limit selector and preview in a themeable container so modules (e.g., bm_import, bm_config_sync) can embed it via \Drupal::formBuilder()->getForm() without custom styling.
* [ ] Features 4.1 implement the design improvements
- Expose a stable API surface: keep sheet metadata (getSheetNames(), getRowHeader()), row accessors (getRows(), getRowsKeyed(), getRowsWithoutHeader()), and styling helpers (getColors()) under one service; avoid directly calling cached globals.
* [ ] Features 4.2 implement the design improvements
-  Tighten file handling: prefer managed files, validate MIME/extension, and store the selected fid/sheet/limit via config or cache with clear lifetime; bubble predictable exceptions instead of messenger-only notices.
* [ ] Features 4.3 implement the design improvements
-  Offer lightweight read paths: for large uploads, consider streaming CSV as a fallback to avoid memory spikes; accept row limits and column whitelists to keep previews responsive.
* [ ] Features 4.4 implement the design improvements
- The site runs with an AI (MCP) integration: when available, expose hooks that let other modules request column summaries, anomaly detection, or schema suggestions on uploaded sheets without bypassing Drupal’s permissions.
