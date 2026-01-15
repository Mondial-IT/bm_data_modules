Refactoring features for bm_config_sync module
rule: Only alter code in `modules/custom/bm_data_modules/bm_config_sync` module, no code outside this module may be altered.


The below-mentioned list contains instructions to execute.
Each feature has a number and a markdown checkbox * [ ]
The user instructs which feature (nr) to build next or to build all unchecked features.
When a feature is created (ready) you check the box (* [x]) in this document.

* [x] feature 1. grid display of the 'menu card'
file: drupal_root/web/modules/custom/bm_data_modules/bm_config_sync/src/Form/SettingsForm.php
- displays menu options as cards with an image, then a body with title, topic and action.
- add the css to align title, topics and actions between the cards. (grid and subgrid css)

* [x] feature 1.1 use of fieldsets
  - in SettingsForm.php, place the sentences element in a collapsible fieldset. Use the word 'introduction' instead of `sentences` in that form.


* [ ] feature 2 verification
- files: drupal_root/web/modules/custom/bm_data_modules/bm_config_sync/*
- verify the module ensure the routes and links.menu.yml are correct and match SettingsForm.php routes.

* [x] feature 2 bm_panels
file:  BMConfigSyncERDBuilderForm.php
The forms use panels provided via markup.
The panels need to be refactored to use the bm_panels module methods.
Which will give resizable standardized panels.

* [ ] feature 3 bm_tooltip
- files in: drupal_root/web/modules/custom/bm_data_modules/bm_config_sync/src/Form
- The panels display the topic line with a tippy tooltip this needs to be replaced with using the bm_tooltip module method of adding tooltips.

* [ ] feature 3 datatables component extraction
files: BMConfigSyncERDBuilderForm.php, BMConfigSyncCombinedERDForm.php, BMConfigSyncIndividualERDForm.php, BMConfigSyncIndividualERDBuilderForm.php
- Each `form` duplicates the same `dataTablesEnhancement()` helper and attaches the `bm_main/datatablesjs` library directly.
- files in: drupal_root/web/modules/custom/bm_data_modules/bm_config_sync/src/Form
- Create a dedicated component inside `bm_config_sync` (service or trait) that exposes a method to:
  - Attach the  `DataTables` library and inject the configuration/selector into `drupalSettings`.
  - Refactor the forms to call this shared helper instead of duplicating the method so future tweaks to the table UX live in one place and the module no longer depends on bm_main for UI behaviors.

* [ ] feature 4 datatables compatibility for Drupal 11
files: bm_config_sync.libraries.yml, js/ (new behavior), BMConfigSync*ERD* forms
At the moment the ERD tables rely on a bm_main DataTables library that is not present in this Drupal 11 build,
so none of the `.DataTableJS` markup is enhanced.
Add a bm_config_sync-specific library that ships the needed DataTables assets (CSS, JS, button extensions)
or uses a CDN that complies with the project rules. Write a small behavior (e.g., `bm_config_sync/js/datatables.js`) that reads `drupalSettings.datatables.config` and instantiates the tables via the new library. Update the forms to attach this library (via the feature 3 helper) and verify that column visibility/search builder/buttons all work again in the admin theme.
