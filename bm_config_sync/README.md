# bm_config_sync

Module purpose
- Synchronizes or helps manage configuration between environments.
- Provides admin UI for exporting/importing or managing specific configuration items.

Compatibility
- Target Drupal versions: Drupal 10 and Drupal 11 (update core_version_requirement accordingly).
- PHP: Compatible with PHP 8.3 (ensure you run static analysis after changes).

Installation
1. Place the module in the modules/custom/bm_config_sync directory or install via Composer if packaged.
2. Run `composer install` (if the module has a composer.json).
3. Enable the module: `drush en bm_config_sync -y` or use the admin UI.

Upgrade notes (D9 -> D11)
- Updated APIs: This module may use deprecated Drupal 9 APIs (drupal_set_message(), variable_*(), db_*). These must be replaced with the messenger, Configuration API, and Database connection or injected services.
- Routing: Ensure all routes in bm_config_sync.routing.yml use `_controller` and `_permission` (no access callback).
- Services: Update bm_config_sync.services.yml to use autowire where possible and review constructor signatures on service classes.
- PHP 8.x: Declare class properties explicitly to avoid dynamic property deprecation warnings.

Developer notes
- Prefer dependency injection for services in controllers and services.
- Use StringTranslationTrait in service/controller classes and `$this->t()` for translation.
- Escape all user output (Html::escape() or templates auto-escape).
- If changing APIs or service IDs, update dependent code and documentation.

Operational/DevOps notes
- Config sync: If this module stores configuration that should be exported, commit exported YML into config repository.
- Cron: If the module relies on periodic imports or tasks, ensure a cron job is configured and tested with the new PHP version.
- PHP configuration: Test with PHP 8.3 and enable error reporting or monitoring to catch runtime deprecations.

Troubleshooting
- If routing or permissions fail: clear caches `drush cr`.
- If services fail instantiation: check service class constructor signatures vs services.yml.
- If you see dynamic property deprecation warnings: inspect classes that assign properties at runtime and add explicit property declarations or refactor.

Recommended checks (run locally)
- drupal-check (or upgrade_status)
- Rector (Drupal Rector set)
- PHPStan with Drupal extensions
- Functional tests where available

Contact & Maintainers
- Module created by Mondial-IT for internal use. For questions contact the repo owner.
