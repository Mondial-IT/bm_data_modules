<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace Drupal\bm_import\Form;

use Consolidation\SiteAlias\SiteAliasInterface;
use Drupal;
use Drupal\bm_import\BMExportConfigHandler\BMExportConfigHandler;
use Drupal\bm_import\BMImportHandlers\BMImportNodeHandler;
use Drupal\bm_import\BMImportHandlers\BMImportParagraphHandler;
use Drupal\bm_import\BMImportHandlers\BMImportVocabularyHandler;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Render\Markup;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Theme\ThemeHandler;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class BMTemplateForm for a Drupal settings form with AJAX table select functionality.
 */
class BMImportConfigExportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bm_import_export_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bm_template.settings'];
  }

  /**
   * Builds the settings form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Attach the bm_tippy library to the form.
    $form['#attached']['library'][] = 'ziston/tippyjs';
    $form['#attached']['library'][] = 'ziston/bm_tippyjs';

    // Inject CSS styles directly into the page.
    $form['custom_styles'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('<style>
        .ajax-response { font-weight: bold; color: green; max-height:300px;overflow:auto; }
        .table-select-panel { margin: 10px; border: 1px solid #ccc; padding: 15px; }
        .button-container button { margin-right: 5px; }
      </style>'.
      '<p>run: <i>/drupal_root/web/sites/bmlistings: drush config:export --destination=sites/bmlistings/files/configexport</i></p>'.
      '<p>ends up here: <i>/home/bluemarloc/drupal_devj/docker_volume_app/git_root/drupal_root/web/bmlfiles</i></p>'),
    ];
// /home/bluemarloc/drupal_devj/docker_volume_app/git_root/drupal_root/web/bmlfiles
    // Detect the button clicked and call the action function.
    if ($form_state->getTriggeringElement()) {
      $button_clicked = $form_state->getTriggeringElement()['#name'];
      $this->execute_button_action($button_clicked, $form, $form_state);
    }

    $form['intro'] = [
      '#markup' => '<p>Exporting configurations.</p>',
    ];

    // Panel with table select and buttons.
    $form['panels']['#prefix'] = Markup::create('<div style="display: grid;grid-template-columns: repeat(3, 1fr);gap: 10px;">');


    // Example usage:
    $content_type_id = 'listing'; // Replace 'article' with the machine name of your content type.
    $dependencies = $this->getContentTypeDependencies($content_type_id);
dsm($dependencies);

    $this->panel_export_configs($form, $form_state);

//    $this->panel_table_contenttypes($form['panels'], $form_state);
//    $this->panel_table_taxonomies($form['panels'], $form_state);
//    $this->panel_table_paragraphs($form['panels'], $form_state);
//    $this->panel_table_fields($form['panels'], $form_state);
    $form['panels']['#suffix'] = '</div>';

    return $form;
  }

private function panel_export_configs(array &$form, FormStateInterface $form_state) {
  $form['table_select_panel0']['#prefix'] = '<div class="panel"><h2>SITE CONFIG EXPORT</h2><hr>';
  $form['table_select_panel0']['#suffix'] = '</div>';

  $panel = [
    '#attributes' => ['class' => ['table-select-panel']],
  ];
  $nodeHandler = new BMImportNodeHandler();
  $entitiesList = [];
  try {
    $entitiesList = $nodeHandler->get_contentTypes();
  } catch (\Exception $exception) {
    Drupal::messenger()->addError($exception->getMessage());
  }

  $header = [
    'id' => $this->t('ID'),
    'name' => $this->t('Name'),
    'label' => $this->t('Label'),
  ];
  $rows = [];
  $n = 1;
  foreach ($entitiesList as $entityMName => $entityLabel) {
    $rows[] = ['id' => $n++, 'name' => $entityMName, 'label' => $entityLabel];
  }
  // Response div for displaying processed rows.
  $form['table_select_panel0']['response-0'] = [
    '#type' => 'container',
    '#attributes' => ['id' => 'ajax-response-0', 'class' => ['ajax-response']],
  ];

  // Separate AJAX buttons.
  $form['table_select_panel0']['config_export'] = [
    '#type' => 'button',
    '#value' => $this->t('Export'),
    '#name' => 'config_export',
    '#ajax' => [
      'callback' => '::ajaxConfigExport',
      'wrapper' => 'ajax-response',
    ],
  ];

}


  /**
   * Export a content type and its dependencies to a directory.
   *
   * @param string $content_type
   *   The machine name of the content type to export.
   * @param string $export_directory
   *   The base directory where the content type configurations will be exported.
   *
   * @throws \Exception
   *   If the export fails.
   */


  /**
   * Export a content type and its dependencies to the sync directory.
   *
   * @param string $content_type
   *   The machine name of the content type to export.
   * @param string $base_directory
   *   The base directory for organized exports.
   *
   * @throws \Exception
   *   If the export fails.
   */
  function export_content_type_with_dependencies($content_type, $base_directory) {
    // Get the active configuration storage.
    $activeStorage = \Drupal::service('config.storage');

    // Get the sync directory path from settings.php.
    $syncDirectory = \Drupal::service('settings')->get('config_sync_directory');

    // Ensure the sync directory is defined, exists, and is writable.
    if (!$syncDirectory || !is_dir($syncDirectory) || !is_writable($syncDirectory)) {
      throw new \Exception("The sync directory is either undefined, missing, or not writable: $syncDirectory");
    }

    // Create a FileStorage instance for the sync directory.
    $syncStorage = new FileStorage($syncDirectory);

    // Create a StorageComparer to compare active and sync storage.
    $storageComparer = new StorageComparer($activeStorage, $syncStorage, \Drupal::service('config.manager'));

    // Generate a changelist.
    $storageComparer->createChangelist();

    // Initialize the Filesystem utility for creating directories.
    $filesystem = new Filesystem();

    // Content type directory within the base directory.
    $contentTypeDir = "$base_directory/$content_type";
    if (!$filesystem->exists($contentTypeDir)) {
      $filesystem->mkdir($contentTypeDir, 0755);
    }

    // Initialize the list of configurations to export.
    $configurations_to_export = [];

    // Export the content type configuration.
    $content_type_config_name = "node.type.$content_type";
    if (!$activeStorage->exists($content_type_config_name)) {
      throw new \Exception("Content type '$content_type' does not exist.");
    }
    $configurations_to_export[] = $content_type_config_name;

    // Export related field storage and field configurations.
    foreach ($activeStorage->listAll('field.storage.node') as $field_storage_config_name) {
      $field_storage_config = $activeStorage->read($field_storage_config_name);
      if (!empty($field_storage_config['entity_type']) && $field_storage_config['entity_type'] === 'node') {
        $configurations_to_export[] = $field_storage_config_name;
      }
    }

    foreach ($activeStorage->listAll('field.field.node') as $field_config_name) {
      $field_config = $activeStorage->read($field_config_name);
      if (!empty($field_config['bundle']) && $field_config['bundle'] === $content_type) {
        $configurations_to_export[] = $field_config_name;
      }
    }

    // Add form and view display configurations.
    $form_display_config_name = "core.entity_form_display.node.$content_type.default";
    if ($activeStorage->exists($form_display_config_name)) {
      $configurations_to_export[] = $form_display_config_name;
    }

    $view_display_config_name = "core.entity_view_display.node.$content_type.default";
    if ($activeStorage->exists($view_display_config_name)) {
      $configurations_to_export[] = $view_display_config_name;
    }

    // Resolve additional dependencies for each configuration.
    foreach ($configurations_to_export as $config_name) {
      $config_entity = $activeStorage->read($config_name);
      if (!empty($config_entity['dependencies']['config'])) {
        foreach ($config_entity['dependencies']['config'] as $dependency) {
          if (!$syncStorage->exists($dependency) && !in_array($dependency, $configurations_to_export)) {
            $configurations_to_export[] = $dependency;
          }
        }
      }
    }

    // Write configurations to the content type directory.
    foreach ($configurations_to_export as $config_name) {
      $data = $activeStorage->read($config_name);
      if ($data) {
        $filename = "$contentTypeDir/$config_name.yml";
        file_put_contents($filename, \Drupal\Component\Serialization\Yaml::encode($data));
        \Drupal::logger('custom_module')->info('Exported configuration: @config_name to @directory', [
          '@config_name' => $config_name,
          '@directory' => $contentTypeDir,
        ]);
      }
    }

    \Drupal::logger('custom_module')->info('Export of content type @content_type and dependencies completed.', [
      '@content_type' => $content_type,
    ]);
  }


  /**
   * Export all active configurations to the sync directory.
   *
   * @throws \Exception
   *   If the export fails.
   */
  function export_all_configuration() {
    // Get the active configuration storage.
    $activeStorage = \Drupal::service('config.storage');

    // Get the sync directory path from settings.php.
    $syncDirectory = \Drupal::service('settings')->get('config_sync_directory');

    // Ensure the sync directory is defined, exists, and is writable.
    if (!$syncDirectory || !is_dir($syncDirectory) || !is_writable($syncDirectory)) {
      throw new \Exception("The sync directory is either undefined, missing, or not writable: $syncDirectory");
    }

    // Create a FileStorage instance for the sync directory.
    $syncStorage = new FileStorage($syncDirectory);

    // Create a StorageComparer to compare active and sync storage.
    $storageComparer = new StorageComparer($activeStorage, $syncStorage, \Drupal::service('config.manager'));

    // Generate a changelist.
    $storageComparer->createChangelist();

    // Check if there are configuration changes to export.
    if (!$storageComparer->hasChanges()) {
      \Drupal::messenger()->addMessage('No configuration changes to export.see '.$syncDirectory);
      return;
    }

    // Export the configurations.
    try {
      foreach ($storageComparer->getAllCollectionNames() as $collection) {
        foreach ($storageComparer->getSourceStorage($collection)->listAll() as $name) {
          $data = $activeStorage->read($name);
          $syncStorage->write($name, $data);
          \Drupal::messenger()->addMessage('Exported configuration:'. $name);
        }
      }

      \Drupal::messenger()->addMessage('Configuration export completed successfully. see '.$syncDirectory);
    } catch (\Exception $e) {
      \Drupal::messenger()->addError('Configuration export failed: '.$e->getMessage());

    }
  }

  /**
   * Get all configuration dependencies for a given content type.
   *
   * @param string $content_type_id
   *   The machine name of the content type (e.g., 'article').
   *
   * @return array
   *   An associative array containing all dependencies:
   *   - 'content_type': Content type configuration (node.type.*).
   *   - 'field_storage': Field storage configurations.
   *   - 'field_instances': Field instance configurations.
   *   - 'form_displays': Entity form display configurations.
   *   - 'view_displays': Entity view display configurations.
   */
  function getContentTypeDependencies($content_type_id) {
    // Initialize dependency array.
    $dependencies = [
      'content_type' => [],
      'field_storage' => [],
      'field_instances' => [],
      'form_displays' => [],
      'view_displays' => [],
    ];

    // Get services.
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $config_manager = \Drupal::service('config.manager');
    $config_factory = \Drupal::service('config.factory');

    // Get the content type configuration.
    $content_type_config_name = "node.type.$content_type_id";
    if ($config_factory->get($content_type_config_name)) {
      $dependencies['content_type'][] = $content_type_config_name;
    }

    // Get fields attached to the content type.
    $field_storage_definitions = $entity_type_manager->getStorage('field_storage_config')->loadByProperties([
      'entity_type' => 'node',
    ]);

    foreach ($field_storage_definitions as $field_storage) {
      $field_name = $field_storage->getName();
      $field_instance_config_name = "field.field.node.$content_type_id.$field_name";

      // Add field storage configuration.
      $dependencies['field_storage'][] = "field.storage.node.$field_name";

      // Add field instance configuration if it exists.
      if ($config_factory->get($field_instance_config_name)) {
        $dependencies['field_instances'][] = $field_instance_config_name;
      }
    }

    // Get form display configurations.
    $form_display_config_name = "core.entity_form_display.node.$content_type_id.default";
    if ($config_factory->get($form_display_config_name)) {
      $dependencies['form_displays'][] = $form_display_config_name;
    }

    // Get view display configurations.
    $view_display_config_name = "core.entity_view_display.node.$content_type_id.default";
    if ($config_factory->get($view_display_config_name)) {
      $dependencies['view_displays'][] = $view_display_config_name;
    }

    return $dependencies;
  }




  /**
   * Export all configuration to the sync directory using ConfigImporter.
   */
  /**
   * Import configuration from the sync directory.
   *
   * @throws \Exception
   *   If the import fails.
   */
  function import_all_configuration() {
    // Get the active configuration storage.
    $activeStorage = \Drupal::service('config.storage');

    // Get the sync storage (e.g., the `config/sync` directory).
    $syncDirectory = \Drupal::service('config.storage.sync')->getStorageDirectory();
    $syncStorage = new FileStorage($syncDirectory);

    // Ensure the sync directory exists.
    if (!is_dir($syncDirectory)) {
      throw new \Exception("The sync directory does not exist: $syncDirectory");
    }

    // Create a StorageComparer to compare active and sync storage.
    $storageComparer = new StorageComparer($syncStorage, $activeStorage, \Drupal::service('config.manager'));

    // Ensure there are changes to import.
    if (!$storageComparer->createChangelist()->hasChanges()) {
      \Drupal::logger('custom_module')->info('No configuration changes to import.');
      return;
    }

    // Get services needed for ConfigImporter.
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $config_manager = \Drupal::service('config.manager');
    $lock = \Drupal::service('lock');
    $typed_config = \Drupal::service('config.typed');
    $module_handler = \Drupal::service('module_handler');
    $module_installer = \Drupal::service('module_installer');
    $theme_handler = \Drupal::service('theme_handler');
    $string_translation = \Drupal::service('string_translation');
    $extension_list_module = \Drupal::service('extension.list.module');

    // Create the ConfigImporter.
    $configImporter = new ConfigImporter(
      $storageComparer,
      $event_dispatcher,
      $config_manager,
      $lock,
      $typed_config,
      $module_handler,
      $module_installer,
      $theme_handler,
      $string_translation,
      $extension_list_module
    );

    // Run the import process.
    try {
      // Validate configuration before importing.
//      if ($configImporter->validate() !== ConfigImporter::VALIDATION_SUCCESS) {
//        throw new \Exception('Configuration validation failed.');
//      }

      // Import the configuration.
      $configImporter->import();

      \Drupal::logger('custom_module')->info('Configuration import completed successfully.');
    } catch (\Exception $e) {
      \Drupal::logger('custom_module')->error('Configuration import failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }


  private function panel_table_contenttypes(array &$form, FormStateInterface $form_state): void {
    $form['table_select_panel1']['#prefix'] = '<div class="panel"><h2>NODE CONFIG EXPORT</h2><hr>';
    $form['table_select_panel1']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];
    $nodeHandler = new BMImportNodeHandler();
    $entitiesList = [];
    try {
      $entitiesList = $nodeHandler->get_contentTypes();
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'label' => $this->t('Label'),
    ];
    $rows = [];
    $n = 1;
    foreach ($entitiesList as $entityMName => $entityLabel) {
      $rows[] = ['id' => $n++, 'name' => $entityMName, 'label' => $entityLabel];
    }

    $form['table_select_panel1']['table_select_1'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // Response div for displaying processed rows.
    $form['table_select_panel1']['response-1'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-1', 'class' => ['ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel1']['node_export'] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => 'node_export',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];



  }

  private function panel_table_taxonomies(array &$form, FormStateInterface $form_state): void {
    $form['table_select_panel2']['#prefix'] = '<div class="panel"><h2>VOCABULARY CONFIG EXPORT</h2><hr>';
    $form['table_select_panel2']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $handler = new BMImportVocabularyHandler();
    $entitiesList = [];
    try {
      $entitiesList = $handler->get_types();
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'label' => $this->t('Label'),
    ];
    $rows = [];
    $n = 1;
    foreach ($entitiesList as $entityMName => $entityLabel) {
      $rows[] = ['id' => $n++, 'name' => $entityMName, 'label' => $entityLabel];
    }


    $form['table_select_panel2']['table_select_2'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // Response div for displaying processed rows.
    $form['table_select_panel2']['response_2'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-2', 'class' => ['ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel2']['vocabulary_export'] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => 'vocabulary_export',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];




  }

  private function panel_table_paragraphs(array &$form, FormStateInterface $form_state): void {
    $form['table_select_panel3']['#prefix'] = '<div class="panel"><h2>PARAGRAPH CONFIG EXPORT</h2><hr>';
    $form['table_select_panel3']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $handler = new BMImportParagraphHandler();
    $entitiesList = [];
    try {
      $entitiesList = $handler->get_types();
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'label' => $this->t('Label'),
    ];
    $rows = [];
    $n = 1;
    foreach ($entitiesList as $entityMName => $entityLabel) {
      $rows[] = ['id' => $n++, 'name' => $entityMName, 'label' => $entityLabel];
    }


    $form['table_select_panel3']['table_select_3'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // ajax Response div for displaying processed rows.
    $form['table_select_panel3']['response_3'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-3', 'class' => ['ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel3']['paragraph_export'] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => 'paragraph_export',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];



  }
  /**
   * Retrieve all fields from the system.
   *
   * @return array
   *   An associative array where the key is the field machine name, and the value is the field label.
   */
  private function get_all_fields(): array {
    $fields = [];
    try {
      // Load all field storage configurations.
      $fieldStorages = \Drupal::entityTypeManager()->getStorage('field_storage_config')->loadMultiple();
      foreach ($fieldStorages as $fieldStorage) {
        /** @var \Drupal\field\Entity\FieldStorageConfig $fieldStorage */
        $fields[$fieldStorage->getName()] = $fieldStorage->get('label') ?: $fieldStorage->getName();
      }
    } catch (\Exception $e) {
      Drupal::messenger()->addError($this->t('Failed to retrieve fields: @error', ['@error' => $e->getMessage()]));
    }
    return $fields;
  }
  private function panel_table_fields(array &$form, FormStateInterface $form_state): void {
    $form['table_select_panel4']['#prefix'] = '<div class="panel"><h2>FIELD CONFIG EXPORT</h2><hr>';
    $form['table_select_panel4']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $entitiesList = $this->get_all_fields(); // Fetch all field configurations

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Field Name'),
      'label' => $this->t('Label'),
    ];
    $rows = [];
    $n = 1;
    foreach ($entitiesList as $fieldName => $fieldLabel) {
      $rows[$n] = ['id' => $n++, 'name' => $fieldName, 'label' => $fieldLabel];
    }

    $form['table_select_panel4']['table_select_4'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No fields available.'),
    ];

    // AJAX Response div for displaying processed rows.
    $form['table_select_panel4']['response_4'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-4', 'class' => ['ajax-response']],
    ];

    // Export button for fields.
    $form['table_select_panel4']['field_export'] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => 'field_export',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];
  }

  public function ajaxConfigExport(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement()['#name'];
    $selected_rows = [];
    $selector = '#ajax-response';
    $response_message = '';
    switch ($trigger) {
      case 'config_export':
        try {
          $this->export_all_configuration();
//          $response_message = $this->run_drush_command('config:export');
        }catch(\Exception $e) {
          Drupal::messenger()->addError($e->getMessage());
        }
        $selector = '#ajax-response-0';
        break;
      default:
        break;
    }
    $response_message = str_replace("\n", '<br />', $response_message);
    $response = new AjaxResponse();

    $result_message='success';
    // Update the response div.
    $response->addCommand(new HtmlCommand($selector, $result_message . $response_message));

    // Display any messages in a modal popup.
    $this->response_messages($response);

    return $response;
  }

  public function ajaxProcessSelectedRows(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement()['#name'];
    $selected_rows = [];
    $selector = '#ajax-response';
    $response_message = '';
    switch ($trigger) {
      case 'node_export':
        $selected_rows = array_filter($form_state->getValue('table_select_1'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel1']['table_select_1']['#options'][$index];
          $handler = new BMExportConfigHandler();
          $yaml = $handler->save_content_type_configuration_to_file($row['name']);
          $response_message = $yaml;
        }
        $selector = '#ajax-response-1';
        break;
      case 'vocabulary_export':
        $selected_rows = array_filter($form_state->getValue('table_select_2'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel2']['table_select_2']['#options'][$index];
          $handler = new BMExportConfigHandler();
          $yaml = $handler->save_taxonomy_configuration_to_file($row['name']);
          $response_message = $yaml;
        }
        $selector = '#ajax-response-2';
        break;
      case 'paragraph_export':
        $selected_rows = array_filter($form_state->getValue('table_select_3'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel3']['table_select_3']['#options'][$index];
          $handler = new BMExportConfigHandler();
          $yaml = $handler->save_paragraph_configuration_to_file($row['name']);
          $response_message = $yaml;
        }
        $selector = '#ajax-response-3';
        break;
      case 'field_export':
        $selected_rows = array_filter($form_state->getValue('table_select_4'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel4']['table_select_4']['#options'][$index];
          $handler = new BMExportConfigHandler();
          $yaml = $handler->save_field_configuration_to_file($row['name']);
          $response_message = $yaml;

          $message = 'Config saved to: public://bm_import_config as <br />'.$yaml;
          Drupal::messenger()->addMessage($message);

          bm_d('',$response_message,'response message');
        }
        $selector = '#ajax-response-4';
        break;
      default:
        break;
    }
    $response_message = str_replace("\n", '<br />', $response_message);
    $response = new AjaxResponse();

    $result_message = empty($selected_rows)
      ? $this->t('No rows selected.')
      : $this->t('Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);

    // Update the response div.
    $response->addCommand(new HtmlCommand($selector, $result_message . $response_message));

    // Display any messages in a modal popup.
    $this->response_messages($response);

    return $response;
  }

  /**
   * AJAX callback to process selected rows and display the results.
   */
  public function xajaxProcessSelectedRows(array &$form, FormStateInterface $form_state): AjaxResponse {

    $trigger = $form_state->getTriggeringElement()['#name'];
    $selected_rows = [];
    $selector = '#ajax-response';
    $response_message = '';
    switch ($trigger) {
      case 'node_export':
        // Process selected rows and display response message.
        $selected_rows = array_filter($form_state->getValue('table_select_1'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel1']['table_select_1']['#options'][$index];
          $handler = new BMExportConfigHandler();
          $yaml = $handler->save_content_type_configuration_to_file($row['name']);
          $response_message = $yaml;
        }
        $selector = '#ajax-response-1';
        break;
      case 'vocabulary_export':
        $selected_rows = array_filter($form_state->getValue('table_select_2'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel2']['table_select_2']['#options'][$index];
          $handler = new BMExportConfigHandler();
          $yaml = $handler->save_taxonomy_configuration_to_file($row['name']);
          $response_message = $yaml;
        }
        $selector = '#ajax-response-2';
        break;
      case 'paragraph_export':
        $selected_rows = array_filter($form_state->getValue('table_select_3'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel3']['table_select_3']['#options'][$index];
          $handler = new BMExportConfigHandler();
          $yaml = $handler->save_paragraph_configuration_to_file($row['name']);
          $response_message = $yaml;
        }
        $selector = '#ajax-response-3';
        break;
      default:
        break;
    }
    $response_message= str_replace("\n",'<br />',$response_message);
    $response = new AjaxResponse();

    $result_message = empty($selected_rows)
      ? $this->t('No rows selected.')
      : $this->t('Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);

    // Update the response div.
    $response->addCommand(new HtmlCommand($selector, $result_message . $response_message));

    // Display any messages in a modal popup.
    $this->response_messages($response);

    return $response;
  }

  /**
   * Executes the button action based on the clicked button.
   */
  private function execute_button_action($button_clicked, &$form, FormStateInterface $form_state): void {


    // Process the selected rows and provide different messages for each button.
    $response_message = '';

    switch ($button_clicked) {
      case 'node_export':
        if (!empty($selected_rows)) {
          $response_message = $this->t('Button 1 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);
        }
        else {
          $response_message = $this->t('No rows selected.');

        }
        // Update the form state with the response message.
        $form['table_select_panel1']['response']['#markup'] = $response_message;
        break;
      case
      'taxonomy_export':
        if (!empty($selected_rows)) {
          $response_message = $this->t('Button 2 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);
        }
        else {
          $response_message = $this->t('No rows selected.');
        }
        // Update the form state with the response message.
        $form['table_select_panel2']['response']['#markup'] = $response_message;
        break;
      case
      'paragraph_export':
        if (!empty($selected_rows)) {
          $response_message = $this->t('Button 3 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);
        }
        else {
          $response_message = $this->t('No rows selected.');

        }
        // Update the form state with the response message.
        $form['table_select_panel3']['response']['#markup'] = $response_message;
        break;
      default:
        break;
    }
    // Clear the table select checkboxes after processing.
    $form_state->setRebuild();
  }


  /**
   * Prepares messages to display as a popup.
   *
   * @param AjaxResponse $response
   */
  protected
  function response_messages(AjaxResponse $response): void {
    $noMessages = TRUE;

    $message_markup = '<div id="status-messages">';
    $message_markup .= '<div role="contentinfo" aria-label="Status message" class="messages messages--status">
      <h2 class="visually-hidden">Status message</h2>
      <ul class="messages__list">';

    foreach (Drupal::messenger()->messagesByType('status') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-green">' . $message . '</li>';
    }
    foreach (Drupal::messenger()->messagesByType('warning') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-orange">' . $message . '</li>';
    }
    foreach (Drupal::messenger()->messagesByType('error') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-red">' . $message . '</li>';
    }

    $message_markup .= '</ul></div></div>';

    // Clear messages once processed.
    Drupal::messenger()->deleteByType('status');
    Drupal::messenger()->deleteByType('error');
    Drupal::messenger()->deleteByType('warning');

    // Only open the modal if there are messages to display.
    if (!$noMessages) {
      $dialog_options = ['width' => 700, 'resizable' => TRUE];
      $settings = ['sizeAdjustModal' => '#messages-modal'];
      $response->addCommand(new OpenModalDialogCommand('Messages', $message_markup, $dialog_options, $settings));
    }
  }

}
