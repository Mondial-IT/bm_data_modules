<?php

declare(strict_types=1);

namespace Drupal\bm_config_sync\Form;

use Drupal;
use Drupal\bm_config_sync\Controller\BMConfigSyncController;
use Drupal\bm_config_sync\Handler\BMConfigSyncHandler;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Diff\Diff as TextDiff;
use Drupal\Component\Diff\Renderer\UnifiedDiffFormatter;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class BMConfigSyncExportForm extends FormBase {

  public const PANEL_NODE = 'panel_node';

  public const TABLE_SELECT_NODE = 'table_select_node';

  public const BTN_EXPORT_NODE = 'btn_export_node';

  public const PANEL_NODE_FIELDS = 'panel_node_fields';

  public const ID_PANEL_NODE_FIELDS = 'panel-node-fields';

  public const TABLE_SELECT_NODE_FIELDS = 'table_select_node_fields';

  public const BTN_EXPORT_NODE_FIELD = 'btn_export_node_fields';

  public const PANEL_PARAGRAPH = 'panel_paragraph';

  public const TABLE_SELECT_PARAGRAPH = 'table_select_paragraph';

  public const BTN_EXPORT_PARAGRAPH = 'btn_export_paragraph';

  public const PANEL_PARAGRAPH_FIELDS = 'panel_paragraph_fields';

  public const ID_PANEL_PARAGRAPH_FIELDS = 'panel-paragraph-fields';

  public const TABLE_SELECT_PARAGRAPH_FIELDS = 'table_select_paragraph_fields';

  public const BTN_EXPORT_PARAGRAPH_FIELD = 'btn_export_paragraph_fields';

  public const PANEL_TAXONOMY = 'panel_taxonomy';

  public const TABLE_SELECT_TAXONOMY = 'table_select_taxonomy';

  public const BTN_EXPORT_TAXONOMY = 'btn_export_taxonomy';

  public const PANEL_TAXONOMY_FIELDS = 'panel_taxonomy_fields';

  public const ID_PANEL_TAXONOMY_FIELDS = 'panel-taxonomy-fields';

  public const TABLE_SELECT_TAXONOMY_FIELDS = 'table_select_taxonomy_fields';

  public const BTN_EXPORT_TAXONOMY_FIELDS = 'btn_export_taxonomy_fields';

  public const ID_AJAX_NODE_EXPORT_RESPONSE = 'id-ajax-node-export-response';

  public const ID_AJAX_TAXONOMY_EXPORT_RESPONSE = 'ID_AJAX_TAXONOMY_EXPORT_RESPONSE';

  public const ID_AJAX_EXPORT_PARAGRAPH_RESPONSE = 'ID_AJAX_EXPORT_PARAGRAPH_RESPONSE';

  public const ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE = 'ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE';

  public const ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE = 'ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE';

  public const ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE = 'ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE';

  public const PANEL_DIFFS = 'panel_diffs';

  public const BTN_SHOW_DIFFS = 'btn_show_diffs';

  public const ID_AJAX_DIFFS_RESPONSE = 'ID_AJAX_DIFFS_RESPONSE';

  private BMConfigSyncHandler $configSyncHandler;

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(BMConfigSyncHandler::class)
    );
  }

  public function __construct(BMConfigSyncHandler $configSyncHandler) {
    $this->configSyncHandler = $configSyncHandler;
  }

  public function getFormId(): string {
    return 'bm_config_sync_export_form';
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // no-op
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'ziston/tippyjs';
    $form['#attached']['library'][] = 'ziston/bm_tippyjs';

    $style = Markup::create('<style>
        .panel1 { grid-column: 1 / 2; grid-row: 1 / 2; }
        .panel2 { grid-column: 2 / 3; grid-row: 1 / 2; }
        .panel3 { grid-column: 3 / 4; grid-row: 1 / 2; }
        .panel4 { grid-column: 1 / 2; grid-row: 2 / 3; }
        .panel5 { grid-column: 2 / 3; grid-row: 2 / 3; }
        .panel6 { grid-column: 3 / 4; grid-row: 2 / 3; }
        .panel7 { grid-column: 1 / 2; grid-row: 3 / 4; }
        .panel8 { grid-column: 2 / 3; grid-row: 3 / 4; }
        .panel9 { grid-column: 3 / 4; grid-row: 3 / 4; }
        .diff pre { background:#0b0b0b; color:#e6e6e6; padding:10px; overflow:auto; max-height:420px; }
        .diff ins { color:#3fb950; text-decoration:none; }
        .diff del { color:#f85149; text-decoration:none; }
      </style>');

    $form['panels'] = [
      '#markup' => $style,
      '#prefix' => '<div class="grid-fr-fr-fr gap-20">',
      '#suffix' => '</div>',
    ];

    $form['custom_styles'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('<style>
        .ajax-response { color: green;font-family:"monospace"; max-height:300px;overflow:auto; }
        .table-select-panel { margin: 10px; border: 1px solid #ccc; padding: 15px; }
        .table-select-panel { max-height:500px;overflow-y:auto;}
        .button-container button { margin-right: 5px; }
        hr{ clear:both;}
      </style>
      <p>run: <i>/drupal_root/web/sites/bmlistings: drush config:export --destination=sites/bmlistings/files/configexport</i></p>
      <p>ends up here: <i>/home/bluemarloc/drupal_devj/docker_volume_app/git_root/drupal_root/web/bmlfiles</i></p>'),
    ];

    if ($form_state->getTriggeringElement()) {
      $button_clicked = $form_state->getTriggeringElement()['#name'];
      $this->execute_button_action($button_clicked, $form, $form_state);
    }

    $form['intro'] = ['#markup' => '<p>Exporting configurations.</p>'];

    $this->panel_node($form['panels'], $form_state, 'panel1');
    $this->panel_paragraph($form['panels'], $form_state, 'panel2');
    $this->panel_vocabulary($form['panels'], $form_state, 'panel3');
    $this->panel_node_fields($form['panels'], $form_state, 'panel4');
    $this->panel_paragraph_fields($form['panels'], $form_state, 'panel5');
    $this->panel_taxonomy_fields($form['panels'], $form_state, 'panel6');
    $this->panel_export_configs($form['panels'], $form_state, 'panel7');
    $this->panel_show_diffs($form['panels'], $form_state, 'panel8');

    return $form;
  }

  private function panel_node(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form[self::PANEL_NODE] = [
      '#prefix' => Markup::create('<div class="panel ' . $gridClass . '" >'
        . bm_main_panel_title_and_help(
          'EXPORT CONTENT TYPE',
          'Export the content type and its fields. Or click and select to filter the fields of the content type in the list below.'
        )),
      '#suffix' => '</div>',
    ];

    $entitiesList = [];
    try {
      $contentTypes = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
      foreach ($contentTypes as $machineName => $contentType) {
        $entitiesList[$machineName] = $contentType->label();
      }
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = ['id' => $this->t('ID'), 'name' => $this->t('Name'), 'label' => $this->t('Label')];
    $n = 1;
    $rows = [];
    $rows[NULL] = ['id' => 0, 'name' => ' ', 'label' => '  '];
    foreach ($entitiesList as $entityMName => $entityLabel) {
      $rows[$entityMName] = ['id' => $n++, 'name' => $entityMName, 'label' => $entityLabel];
    }

    $form[self::PANEL_NODE][self::TABLE_SELECT_NODE] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
      '#multiple' => FALSE,
      '#prefix' => '<div class="table-select-panel"',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::ajaxOnChangeRadioButton',
        'wrapper' => self::ID_PANEL_NODE_FIELDS,
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => t('Loading... node fields')],
      ],
    ];

    $form[self::PANEL_NODE]['panel_node_response'] = [
      '#type' => 'container',
      '#attributes' => ['id' => self::ID_AJAX_NODE_EXPORT_RESPONSE, 'class' => ['ajax-response-nodes ajax-response']],
    ];

    $form[self::PANEL_NODE][self::BTN_EXPORT_NODE] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => self::BTN_EXPORT_NODE,
      '#ajax' => [
        'callback' => '::ajaxOnClickExportButton',
        'wrapper' => self::ID_AJAX_NODE_EXPORT_RESPONSE,
        'progress' => ['type' => 'throbber', 'message' => t('Exporting ... node configurations')],
      ],
    ];
  }

  private function panel_paragraph(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form[self::PANEL_PARAGRAPH] = [
      '#prefix' => Markup::create('<div class="panel ' . $gridClass . '">'
        . bm_main_panel_title_and_help('EXPORT PARAGRAPH CONFIGURATIONS', 'Export paragraph configurations or click and filter the paragraph fields below.')),
      '#suffix' => '</div>',
    ];

    $entitiesList = [];
    try {
      $paragraphs = \Drupal::entityTypeManager()->getStorage('paragraphs_type')->loadMultiple();
      foreach ($paragraphs as $machineName => $contentType) {
        $entitiesList[$machineName] = $contentType->label();
      }
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = ['id' => $this->t('ID'), 'name' => $this->t('Name'), 'label' => $this->t('Label')];
    $rows = [];
    $rows[NULL] = ['id' => 0, 'name' => ' ', 'label' => '  '];
    $n = 1;
    foreach ($entitiesList as $entityMName => $entityLabel) {
      $rows[$entityMName] = ['id' => $n++, 'name' => $entityMName, 'label' => $entityLabel];
    }

    $form[self::PANEL_PARAGRAPH][self::TABLE_SELECT_PARAGRAPH] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#multiple' => FALSE,
      '#default_value' => NULL,
      '#empty' => $this->t('No items available.'),
      '#prefix' => '<div class="table-select-panel"',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::ajaxOnChangeRadioButton',
        'event' => 'change',
        'wrapper' => self::ID_PANEL_PARAGRAPH_FIELDS,
        'progress' => ['type' => 'throbber', 'message' => t('Loading... paragraph fields')],
      ],
    ];

    $form[self::PANEL_PARAGRAPH]['response_3'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => self::ID_AJAX_EXPORT_PARAGRAPH_RESPONSE,
        'class' => ['ajax-response-paragraphs ajax-response']
      ],
    ];

    $form[self::PANEL_PARAGRAPH][self::BTN_EXPORT_PARAGRAPH] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => self::BTN_EXPORT_PARAGRAPH,
      '#ajax' => [
        'callback' => '::ajaxOnClickExportButton',
        'wrapper' => self::ID_AJAX_EXPORT_PARAGRAPH_RESPONSE,
        'progress' => ['type' => 'throbber', 'message' => t('Exporting ... paragraph configuration')],
      ],
    ];
  }

  private function panel_vocabulary(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form[self::PANEL_TAXONOMY] = [
      '#prefix' => Markup::create('<div class="panel ' . $gridClass . '">'
        . bm_main_panel_title_and_help('EXPORT VOCABULARY', 'Export the vocabulary configurations or click and filter the vocabulary fields in the panel below.')),
      '#suffix' => '</div>',
    ];

    $entitiesList = [];
    try {
      $taxonomies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
      foreach ($taxonomies as $machineName => $contentType) {
        $entitiesList[$machineName] = $contentType->label();
      }
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = ['id' => $this->t('ID'), 'name' => $this->t('Name'), 'label' => $this->t('Label')];
    $rows = [];
    $rows[NULL] = ['id' => 0, 'name' => ' ', 'label' => '  '];
    $n = 1;
    foreach ($entitiesList as $entityMName => $entityLabel) {
      $rows[$entityMName] = ['id' => $n++, 'name' => $entityMName, 'label' => $entityLabel];
    }

    $form[self::PANEL_TAXONOMY][self::TABLE_SELECT_TAXONOMY] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#multiple' => FALSE,
      '#empty' => $this->t('No items available.'),
      '#prefix' => '<div class="table-select-panel"',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::ajaxOnChangeRadioButton',
        'wrapper' => self::ID_PANEL_TAXONOMY_FIELDS,
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => t('Loading... taxonomy fields')],
      ],
    ];

    $form[self::PANEL_TAXONOMY]['response_2'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => self::ID_AJAX_TAXONOMY_EXPORT_RESPONSE,
        'class' => ['ajax-response-taxonomies ajax-response']
      ],
    ];

    $form[self::PANEL_TAXONOMY][self::BTN_EXPORT_TAXONOMY] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => self::BTN_EXPORT_TAXONOMY,
      '#ajax' => [
        'callback' => '::ajaxOnClickExportButton',
        'wrapper' => self::ID_AJAX_TAXONOMY_EXPORT_RESPONSE,
        'progress' => ['type' => 'throbber', 'message' => t('Exporting ... Taxonomy configurations')],
      ],
    ];
  }

  private function panel_node_fields(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form[self::PANEL_NODE_FIELDS] = [
      '#prefix' => Markup::create('<div id="' . self::ID_PANEL_NODE_FIELDS . '" class="panel ' . $gridClass . '">'
        . bm_main_panel_title_and_help('EXPORT NODE FIELDSs', '')),
      '#suffix' => '</div>',
    ];

    $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_NODE] ?? '';
    $entitiesList = $this->configSyncHandler->get_type_fields($bundleFilter ?: 'node');

    $header = [
      'id' => 'ID',
      'bundle' => 'Bundle',
      'label' => 'Label',
      'field_name' => 'Field name',
      'machineName' => 'Machine name',
      'description' => 'Description',
      'value_target_id' => 'Value/Target',
      'target_type' => 'Type',
    ];

    $rows = [];
    $rows[NULL] = [
      'id' => 0,
      'bundle' => '',
      'label' => '',
      'field_name' => '',
      'machineName' => '',
      'description' => '',
      'value_target_id' => '',
      'target_type' => ''
    ];
    $n = 1;
    foreach ($entitiesList as $bundle_fieldName => $data) {
      $rows[$bundle_fieldName] = array_merge(['id' => $n++], $data);
    }

    $form[self::PANEL_NODE_FIELDS][self::TABLE_SELECT_NODE_FIELDS] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#multiple' => FALSE,
      '#empty' => $this->t('No fields available.'),
      '#prefix' => '<div class="table-select-panel"',
      '#suffix' => '</div>',
    ];

    $form[self::PANEL_NODE_FIELDS]['response_4'] = [
      '#type' => 'container',
      '#attributes' => ['id' => self::ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE, 'class' => ['ajax-response-node-fields']],
    ];

    $form[self::PANEL_NODE_FIELDS]['field_export'] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => self::BTN_EXPORT_NODE_FIELD,
      '#ajax' => [
        'callback' => '::ajaxOnClickExportButton',
        'wrapper' => self::ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE,
      ],
    ];
  }

  private function panel_paragraph_fields(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form[self::PANEL_PARAGRAPH_FIELDS] = [
      '#prefix' => Markup::create('<div id="' . self::ID_PANEL_PARAGRAPH_FIELDS . '" class="panel ' . $gridClass . '">'
        . bm_main_panel_title_and_help('EXPORT PARAGRAPH FIELDS', 'Select fields for which to export the configuration.')),
      '#suffix' => '</div>',
    ];

    $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_PARAGRAPH] ?? 'paragraph';
    $entitiesList = $this->configSyncHandler->get_type_fields($bundleFilter ?: 'node');

    $header = [
      'id' => 'ID',
      'bundle' => 'Bundle',
      'label' => 'Label',
      'field_name' => 'Field name',
      'machineName' => 'Machine name',
      'description' => 'Description',
      'value_target_id' => 'Value/Target',
      'target_type' => 'Type',
    ];

    $rows = [];
    $rows[NULL] = [
      'id' => 0,
      'bundle' => '',
      'label' => '',
      'field_name' => '',
      'machineName' => '',
      'description' => '',
      'value_target_id' => '',
      'target_type' => ''
    ];
    $n = 1;
    foreach ($entitiesList as $bundle_fieldName => $data) {
      $rows[$bundle_fieldName] = array_merge(['id' => $n++], $data);
    }

    $form[self::PANEL_PARAGRAPH_FIELDS][self::TABLE_SELECT_PARAGRAPH_FIELDS] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#multiple' => FALSE,
      '#empty' => $this->t('No fields available.'),
      '#prefix' => '<div class="table-select-panel"',
      '#suffix' => '</div>',
    ];

    $form[self::PANEL_PARAGRAPH_FIELDS]['response_4'] = [
      '#type' => 'container',
      '#attributes' => ['id' => self::ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE, 'class' => ['ajax-response-fields']],
    ];

    $form[self::PANEL_PARAGRAPH_FIELDS]['field_export'] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => self::BTN_EXPORT_PARAGRAPH_FIELD,
      '#ajax' => [
        'callback' => '::ajaxOnClickExportButton',
        'wrapper' => self::ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE,
      ],
    ];
  }

  private function panel_taxonomy_fields(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form[self::PANEL_TAXONOMY_FIELDS]['#prefix'] =
      Markup::create('<div id="' . self::ID_PANEL_TAXONOMY_FIELDS . '" class="panel ' . $gridClass . '">'
        . bm_main_panel_title_and_help('EXPORT TAXONOMY FIELDS', ''));
    $form[self::PANEL_TAXONOMY_FIELDS]['#suffix'] = '</div>';

    $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_TAXONOMY] ?? '';
    $entitiesList = $this->configSyncHandler->get_type_fields($bundleFilter ?: 'node');

    $header = [
      'id' => 'ID',
      'bundle' => 'Bundle',
      'label' => 'Label',
      'field_name' => 'Field name',
      'machineName' => 'Machine name',
      'description' => 'Description',
      'value_target_id' => 'Value/Target',
      'target_type' => 'Type',
    ];

    $rows = [];
    $rows[NULL] = [
      'id' => 0,
      'bundle' => '',
      'label' => '',
      'field_name' => '',
      'machineName' => '',
      'description' => '',
      'value_target_id' => '',
      'target_type' => ''
    ];
    $n = 1;
    foreach ($entitiesList as $bundle_fieldName => $data) {
      $rows[$bundle_fieldName] = array_merge(['id' => $n++], $data);
    }

    $form[self::PANEL_TAXONOMY_FIELDS][self::TABLE_SELECT_TAXONOMY_FIELDS] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#multiple' => FALSE,
      '#empty' => $this->t('No fields available.'),
      '#prefix' => '<div class="table-select-panel"',
      '#suffix' => '</div>',
    ];

    $form[self::PANEL_TAXONOMY_FIELDS]['response_4'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => self::ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE,
        'class' => ['ajax-response-taxonomy-fields']
      ],
    ];

    $form[self::PANEL_TAXONOMY_FIELDS]['field_export'] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => self::BTN_EXPORT_TAXONOMY_FIELDS,
      '#ajax' => [
        'callback' => '::ajaxOnClickExportButton',
        'wrapper' => self::ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE,
      ],
    ];
  }

  private function panel_export_configs(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form['panel_export_configs'] = [
      '#prefix' =>
        Markup::create('<div class="panel ' . $gridClass . '">' .
          bm_main_panel_title_and_help('Export all', 'Export all configs listed above')
          . '<div style="display:flex;flex-direction: column;justify-content: space-evenly;">'),
      '#suffix' => '</div></div>',
    ];

    $form['table_select_panel0']['response-0'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-0', 'class' => ['ajax-response']],
    ];

    $form['panel_export_configs']['export_all_configuration_diffs'] = [
      '#type' => 'fieldset',
      '#title' => t('Exporting system and entity configurations'),
      '#collapsible' => TRUE,
      '#description' => Markup::create('<p>This starts a batch operation to export configurations to the private configurations folder.</p>'),
      '#group' => 'formvt',
    ];

    $form['panel_export_configs']['export_all_configuration_diffs']['button'] = [
      '#type' => 'button',
      '#value' => $this->t('Start batch export'),
      '#name' => 'export_all_configuration_diffs',
      '#ajax' => [
        'callback' => '::ajaxPanelExportAllConfigs',
        'wrapper' => 'ajax-response',
      ],
      '#prefix' => '<br /><br />',
    ];

    $form['panel_export_configs']['export_entity_configs'] = [
      '#type' => 'fieldset',
      '#title' => t('Exporting Entity related configurations'),
      '#description' => Markup::create('<p>This starts a batch to export all configurations to the private configurations folder.</p>'),
      '#collapsible' => TRUE,
      '#group' => 'formvt',
    ];

    $form['panel_export_configs']['export_entity_configs']['button'] = [
      '#type' => 'button',
      '#value' => $this->t('Start Batch Export'),
      '#name' => 'export_entity_configurations',
      '#ajax' => [
        'callback' => '::ajax_startBMConfigSyncBatchExporter',
        'wrapper' => 'export-status',
      ],
      '#prefix' => '<br /><br />',
    ];

    $form['export_status'] = [
      '#markup' => Markup::create('
        <dialog id="export-status">
            <p>Batch process runs here</p>
            <button onclick="this.closest(\'dialog\').close()">Close</button>
        </dialog>'),
    ];
  }

  private function panel_show_diffs(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form[self::PANEL_DIFFS] = [
      '#prefix' => Markup::create('<div class="panel ' . $gridClass . '">'
        . bm_main_panel_title_and_help('Show diffs', 'Compare Active vs Sync (private://config_sync)')),
      '#suffix' => '</div>',
    ];

    $form[self::PANEL_DIFFS]['diffs_response'] = [
      '#type' => 'container',
      '#attributes' => ['id' => self::ID_AJAX_DIFFS_RESPONSE, 'class' => ['ajax-response diff']],
    ];

    $form[self::PANEL_DIFFS][self::BTN_SHOW_DIFFS] = [
      '#type' => 'button',
      '#value' => $this->t('Display config diffs'),
      '#name' => self::BTN_SHOW_DIFFS,
      '#ajax' => [
        'callback' => '::ajaxShowConfigDiffs',
        'wrapper' => self::ID_AJAX_DIFFS_RESPONSE,
        'progress' => ['type' => 'throbber', 'message' => t('Computing diffs...')],
      ],
      '#prefix' => '<br />',
    ];
  }

  public function ajaxOnChangeRadioButton(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    switch ($form_state->getTriggeringElement()['#name']) {
      case self::TABLE_SELECT_NODE:
        $selector = '#' . self::ID_PANEL_NODE_FIELDS;
        $formIndex = self::PANEL_NODE_FIELDS;
        break;

      case self::TABLE_SELECT_PARAGRAPH:
        $selector = '#' . self::ID_PANEL_PARAGRAPH_FIELDS;
        $formIndex = self::PANEL_PARAGRAPH_FIELDS;
        break;

      case self::TABLE_SELECT_TAXONOMY:
        $selector = '#' . self::ID_PANEL_TAXONOMY_FIELDS;
        $formIndex = self::PANEL_TAXONOMY_FIELDS;
        break;

      default:
        return $response;
    }

    $response->addCommand(new ReplaceCommand($selector, $form['panels'][$formIndex]));
    $response->addCommand(new InvokeCommand($selector, 'fadeOut', [1500]));
    $response->addCommand(new InvokeCommand($selector, 'fadeIn', [1500]));

    return $response;
  }

  public function ajaxOnClickExportButton(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    $exportedYML = '';
    $responseSelector = '';

    switch ($form_state->getTriggeringElement()['#name']) {

      case self::BTN_EXPORT_NODE:
        $responseSelector = '#' . self::ID_AJAX_NODE_EXPORT_RESPONSE;
        if ($name = $form_state->getValue(self::TABLE_SELECT_NODE)) {
          $exportedYML .= $this->configSyncHandler->exportNodeConfigurations($name);
          $exportedYML .= $this->appendDiffBlock($name);
        }
        break;

      case self::BTN_EXPORT_TAXONOMY:
        $responseSelector = '#' . self::ID_AJAX_TAXONOMY_EXPORT_RESPONSE;
        if ($name = $form_state->getValue(self::TABLE_SELECT_TAXONOMY)) {
          $exportedYML .= $this->configSyncHandler->exportTaxonomyConfigurations($name);
          $exportedYML .= $this->appendDiffBlock($name);
        }
        break;

      case self::BTN_EXPORT_PARAGRAPH:
        $responseSelector = '#' . self::ID_AJAX_EXPORT_PARAGRAPH_RESPONSE;
        if ($name = $form_state->getValue(self::TABLE_SELECT_PARAGRAPH)) {
          $exportedYML .= $this->configSyncHandler->exportParagraphConfigurations($name);
          $exportedYML .= $this->appendDiffBlock($name);
        }
        break;

      case self::BTN_EXPORT_NODE_FIELD:
        $responseSelector = '#' . self::ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE;
        if ($key = $form_state->getValue(self::TABLE_SELECT_NODE_FIELDS)) {
          $exportedYML .= $this->exportFieldTripletWithDiff($key);
        }
        break;

      case self::BTN_EXPORT_PARAGRAPH_FIELD:
        $responseSelector = '#' . self::ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE;
        if ($key = $form_state->getValue(self::TABLE_SELECT_PARAGRAPH_FIELDS)) {
          $exportedYML .= $this->exportFieldTripletWithDiff($key);
        }
        break;

      case self::BTN_EXPORT_TAXONOMY_FIELDS:
        $responseSelector = '#' . self::ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE;
        if ($key = $form_state->getValue(self::TABLE_SELECT_TAXONOMY_FIELDS)) {
          $exportedYML .= $this->exportFieldTripletWithDiff($key);
        }
        break;
    }

    $result = empty($exportedYML)
      ? $this->t('No data available.')
      : Markup::create('<textarea cols="61" rows="10" class="panel" style="margin-left:10px;">' . $exportedYML . '</textarea>');

    $response->addCommand(new HtmlCommand($responseSelector, $result));
    bm_main_response_messages($response);

    return $response;
  }

  public function ajaxShowConfigDiffs(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    $active = \Drupal::service('config.storage');
    $sync = \Drupal::service('config.storage.sync');
    $storageComparer = new StorageComparer($active, $sync, \Drupal::service('config.manager'));
    $storageComparer->createChangelist();

    $list = $storageComparer->getChangelist();
    $ops = ['create', 'update', 'delete', 'rename'];
    $output = '';

    foreach ($ops as $op) {
      $names = $list[$op] ?? [];
      if (!$names) {
        continue;
      }
      $output .= '<h3>' . ucfirst($op) . '</h3>';
      foreach ($names as $name) {
        $old = $sync->read($name) ?? [];
        $new = $active->read($name) ?? [];
        if ($op === 'delete') {
          $new = [];
        }
        $output .= $this->renderDiffBlock($name, $old, $new);
      }
    }

    if ($output === '') {
      $output = '<p>No differences between Active and Sync.</p>';
    }

    $response->addCommand(new HtmlCommand('#' . self::ID_AJAX_DIFFS_RESPONSE, Markup::create($output)));
    return $response;
  }

  private function exportFieldTripletWithDiff(string $fieldsKey): string {
    $data = $this->configSyncHandler->fields[$fieldsKey] ?? NULL;
    if (!$data) {
      return '';
    }
    $out = $this->configSyncHandler->configuration_item_exporter('field.storage.' . $data['machineName']);
    $out .= $this->configSyncHandler->configuration_item_exporter('field.field.' . $data['bundle'] . '.' . $data['field_name']);
    $out .= $this->configSyncHandler->configuration_item_exporter('core.base_field_override.' . $data['bundle'] . '.' . $data['field_name']);

    $out .= $this->appendDiffBlock('field.storage.' . $data['machineName']);
    $out .= $this->appendDiffBlock('field.field.' . $data['bundle'] . '.' . $data['field_name']);
    $out .= $this->appendDiffBlock('core.base_field_override.' . $data['bundle'] . '.' . $data['field_name']);
    return $out;
  }

  private function appendDiffBlock(string $configName): string {
    $active = \Drupal::service('config.storage');
    $sync = \Drupal::service('config.storage.sync');
    $old = $sync->read($configName) ?? [];
    $new = $active->read($configName) ?? [];
    return $this->renderDiffBlock($configName, $old, $new);
  }

  private function renderDiffBlock(string $title, array $oldData, array $newData): string {
    $old = Yaml::encode($oldData ?? []);
    $new = Yaml::encode($newData ?? []);
    $diff = $this->renderUnifiedDiff($old, $new);
    $html = '<details><summary><strong>' . $this->escape($title) . '</strong></summary><pre><code>' . $this->escape($diff) . '</code></pre></details>';
    return $html;
  }

  private function renderUnifiedDiff(string $old, string $new): string {
    $oldLines = preg_split("/\r\n|\n|\r/", $old) ?: [];
    $newLines = preg_split("/\r\n|\n|\r/", $new) ?: [];
    $diff = new TextDiff($oldLines, $newLines);
    $fmt = new UnifiedDiffFormatter();
    return $fmt->format($diff);
  }

  private function escape(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }

  private function execute_button_action($button_clicked, &$form, FormStateInterface $form_state): void {
    $selected_rows = [];
    $response_message = '';

    switch ($button_clicked) {
      case self::BTN_EXPORT_NODE:
        $response_message = !empty($selected_rows) ? $this->t('Button 1 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]) : $this->t('No rows selected.');
        $form['table_select_panel1']['response']['#markup'] = $response_message;
        break;
      case 'taxonomy_export':
        $response_message = !empty($selected_rows) ? $this->t('Button 2 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]) : $this->t('No rows selected.');
        $form['table_select_panel2']['response']['#markup'] = $response_message;
        break;
      case self::BTN_EXPORT_PARAGRAPH:
        $response_message = !empty($selected_rows) ? $this->t('Button 3 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]) : $this->t('No rows selected.');
        $form['table_select_panel3']['response']['#markup'] = $response_message;
        break;
      default:
        break;
    }

    $form_state->setRebuild();
  }

  public function ajax_startBMConfigSyncBatchExporter(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $controller = new BMConfigSyncController();
    $iframe_html = $controller->startBMConfigSyncBatchExporter();

    $selector = '#messages-modal';
    $dialog_options = ['width' => 700, 'resizable' => TRUE];
    $settings = ['sizeAdjustModal' => $selector];
    $response->addCommand(new OpenModalDialogCommand('RUNNING', '<h1>Just a sec. loading batches ...</h1>' . $iframe_html, $dialog_options, $settings));
    return $response;
  }

  public function ajaxPanelExportAllConfigs(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#ajax-response-0', 'success'));
    bm_main_response_messages($response);
    return $response;
  }

  private function get_all_fields_and_bundle_names(): array {
    $fields = [];
    $allBundles = [];
    try {
      $fieldStorages = \Drupal::entityTypeManager()->getStorage('field_storage_config')->loadMultiple();
      foreach ($fieldStorages as $fieldStorage) {
        $name = $fieldStorage->getName();
        $bundles = $fieldStorage->getBundles();
        $description = $fieldStorage->getDescription();
        $machineName = $fieldStorage->getLabel();
        $value_target_id = $fieldStorage->getMainPropertyName();
        $target_type = $fieldStorage->getSettings()['target_type'] ?? NULL;

        if (str_starts_with($machineName, 'node.field') ||
          str_starts_with($machineName, 'taxonomy_term.field') ||
          str_starts_with($machineName, 'paragraph.field')) {
          foreach ($bundles as $bundle) {
            $type = explode('.', $machineName);
            $allBundles[$type[0] . '.' . $bundle] = $bundle;

            $fields[$bundle . '|' . $fieldStorage->getName()] = [
              'bundle' => $type[0] . '.' . $bundle,
              'machineName' => $machineName,
              'field_name' => $name,
              'label' => $name,
              'description' => $description,
              'value_target_id' => $value_target_id,
              'target_type' => $target_type,
            ];
          }
        }
      }
    } catch (\Exception $e) {
      Drupal::messenger()->addError($this->t('Failed to retrieve fields: @error', ['@error' => $e->getMessage()]));
    }
    return ['fields' => $fields, 'bundles' => $allBundles];
  }

  public function export_content_type_with_dependencies(string $content_type, string $base_directory): void {
    $activeStorage = \Drupal::service('config.storage');
    $syncDirectory = \Drupal::service('settings')->get('config_sync_directory');

    if (!$syncDirectory || !is_dir($syncDirectory) || !is_writable($syncDirectory)) {
      throw new \Exception("The sync directory is either undefined, missing, or not writable: $syncDirectory");
    }

    $syncStorage = new FileStorage($syncDirectory);
    $storageComparer = new StorageComparer($activeStorage, $syncStorage, \Drupal::service('config.manager'));
    $storageComparer->createChangelist();

    $filesystem = new FileSystem(NULL);
    $contentTypeDir = "$base_directory/$content_type";
    if (!$filesystem->exists($contentTypeDir)) {
      $filesystem->mkdir($contentTypeDir, 0755);
    }

    $configurations_to_export = [];
    $content_type_config_name = "node.type.$content_type";
    if (!$activeStorage->exists($content_type_config_name)) {
      throw new \Exception("Content type '$content_type' does not exist.");
    }
    $configurations_to_export[] = $content_type_config_name;

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

    $form_display_config_name = "core.entity_form_display.node.$content_type.default";
    if ($activeStorage->exists($form_display_config_name)) {
      $configurations_to_export[] = $form_display_config_name;
    }

    $view_display_config_name = "core.entity_view_display.node.$content_type.default";
    if ($activeStorage->exists($view_display_config_name)) {
      $configurations_to_export[] = $view_display_config_name;
    }

    foreach ($configurations_to_export as $config_name) {
      $config_entity = $activeStorage->read($config_name);
      if (!empty($config_entity['dependencies']['config'])) {
        foreach ($config_entity['dependencies']['config'] as $dependency) {
          if (!$syncStorage->exists($dependency) && !in_array($dependency, $configurations_to_export, TRUE)) {
            $configurations_to_export[] = $dependency;
          }
        }
      }
    }

    foreach ($configurations_to_export as $config_name) {
      $data = $activeStorage->read($config_name);
      if ($data) {
        $filename = "$contentTypeDir/$config_name.yml";
        file_put_contents($filename, Yaml::encode($data));
        \Drupal::logger('bm_config_sync')->info('Exported configuration: @config_name to @directory', [
          '@config_name' => $config_name,
          '@directory' => $contentTypeDir,
        ]);
      }
    }

    \Drupal::logger('bm_config_sync')->info('Export of content type @content_type and dependencies completed.', [
      '@content_type' => $content_type,
    ]);
  }

  public function getContentTypeDependencies(string $content_type_id): array {
    $dependencies = [
      'content_type' => [],
      'field_storage' => [],
      'field_instances' => [],
      'form_displays' => [],
      'view_displays' => [],
    ];

    $entity_type_manager = \Drupal::service('entity_type.manager');
    $config_factory = \Drupal::service('config.factory');

    $content_type_config_name = "node.type.$content_type_id";
    if ($config_factory->get($content_type_config_name)) {
      $dependencies['content_type'][] = $content_type_config_name;
    }

    $field_storage_definitions = $entity_type_manager->getStorage('field_storage_config')->loadByProperties([
      'entity_type' => 'node',
    ]);

    foreach ($field_storage_definitions as $field_storage) {
      $field_name = $field_storage->getName();
      $field_instance_config_name = "field.field.node.$content_type_id.$field_name";
      $dependencies['field_storage'][] = "field.storage.node.$field_name";
      if ($config_factory->get($field_instance_config_name)) {
        $dependencies['field_instances'][] = $field_instance_config_name;
      }
    }

    $form_display_config_name = "core.entity_form_display.node.$content_type_id.default";
    if ($config_factory->get($form_display_config_name)) {
      $dependencies['form_displays'][] = $form_display_config_name;
    }

    $view_display_config_name = "core.entity_view_display.node.$content_type_id.default";
    if ($config_factory->get($view_display_config_name)) {
      $dependencies['view_displays'][] = $view_display_config_name;
    }

    return $dependencies;
  }

  public function import_all_configuration(): void {
    $activeStorage = \Drupal::service('config.storage');
    $syncDirectory = \Drupal::service('config.storage.sync')->getStorageDirectory();
    $syncStorage = new FileStorage($syncDirectory);

    if (!is_dir($syncDirectory)) {
      throw new \Exception("The sync directory does not exist: $syncDirectory");
    }

    $storageComparer = new StorageComparer($syncStorage, $activeStorage, \Drupal::service('config.manager'));

    if (!$storageComparer->createChangelist()->hasChanges()) {
      \Drupal::logger('bm_config_sync')->info('No configuration changes to import.');
      return;
    }

    $event_dispatcher = \Drupal::service('event_dispatcher');
    $config_manager = \Drupal::service('config.manager');
    $lock = \Drupal::service('lock');
    $typed_config = \Drupal::service('config.typed');
    $module_handler = \Drupal::service('module_handler');
    $module_installer = \Drupal::service('module_installer');
    $theme_handler = \Drupal::service('theme_handler');
    $string_translation = \Drupal::service('string_translation');
    $extension_list_module = \Drupal::service('extension.list.module');

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

    try {
      $configImporter->import();
      \Drupal::logger('bm_config_sync')->info('Configuration import completed successfully.');
    } catch (\Exception $e) {
      \Drupal::logger('bm_config_sync')->error('Configuration import failed: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw $e;
    }
  }

}
