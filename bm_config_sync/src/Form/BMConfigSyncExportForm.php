<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace Drupal\bm_config_sync\Form;


use Drupal;
use Drupal\bm_config_sync\Controller\BMConfigSyncController;
use Drupal\bm_config_sync\Handler\BMConfigSyncHandler;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\ConfigImporter;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageComparer;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Render\Markup;

/**
 * Class BMTemplateForm for a Drupal settings form with AJAX table select functionality.
 */
class BMConfigSyncExportForm extends FormBase {


  const PANEL_NODE = 'panel_node';

  const TABLE_SELECT_NODE = 'table_select_node';

  const BTN_EXPORT_NODE = 'btn_export_node';

  const PANEL_NODE_FIELDS = 'panel_node_fields';

  const ID_PANEL_NODE_FIELDS = 'panel-node-fields';

  const TABLE_SELECT_NODE_FIELDS = 'table_select_node_fields';

  const BTN_EXPORT_NODE_FIELD = 'btn_export_node_fields';

  const PANEL_PARAGRAPH = 'panel_paragraph';

  const TABLE_SELECT_PARAGRAPH = 'table_select_paragraph';

  const BTN_EXPORT_PARAGRAPH = 'btn_export_paragraph';

  const PANEL_PARAGRAPH_FIELDS = 'panel_paragraph_fields';

  const ID_PANEL_PARAGRAPH_FIELDS = 'panel-paragraph-fields';

  const TABLE_SELECT_PARAGRAPH_FIELDS = 'table_select_paragraph_fields';

  const BTN_EXPORT_PARAGRAPH_FIELD = 'btn_export_paragraph_fields';

  const PANEL_TAXONOMY = 'panel_taxonomy';

  const TABLE_SELECT_TAXONOMY = 'table_select_taxonomy';

  const BTN_EXPORT_TAXONOMY = 'btn_export_taxonomy';

  const PANEL_TAXONOMY_FIELDS = 'panel_taxonomy_fields';

  const ID_PANEL_TAXONOMY_FIELDS = 'panel-taxonomy-fields';

  const TABLE_SELECT_TAXONOMY_FIELDS = 'table_select_taxonomy_fields';

  const BTN_EXPORT_TAXONOMY_FIELDS = 'btn_export_taxonomy_fields';

  const ID_AJAX_NODE_RESPONSE = 'ajax-node-response';

  const ID_AJAX_NODE_EXPORT_RESPONSE = 'id-ajax-node-export-response';

  const ID_AJAX_TAXONOMY_EXPORT_RESPONSE = 'ID_AJAX_TAXONOMY_EXPORT_RESPONSE';

  const ID_AJAX_EXPORT_PARAGRAPH_RESPONSE = 'ID_AJAX_EXPORT_PARAGRAPH_RESPONSE';

  const ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE = 'ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE';

  const ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE = 'ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE';

  const ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE = 'ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE';

  private BMConfigSyncHandler $configSyncHandler;

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bm_config_sync_export_form';
  }

  public function __construct() {
    $configSyncHandler = new BMConfigSyncHandler();
  }

  /**
   * Called first
   * - then build form
   * - then ajax submitters
   *
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $trigger=$form_state->getTriggeringElement();
    // called before build, then ajax
  }


  /**
   * Builds the settings form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Attach the bm_tippy tooltip library to the form.
    $form['#attached']['library'][] = 'ziston/tippyjs';
    $form['#attached']['library'][] = 'ziston/bm_tippyjs';

    // configurable grid, $gridClasses.
    $style = Markup::create('<style>
        /* row 1, column 1, 2 and 3. */
        .panel1 { grid-column: 1 / 2; grid-row: 1 / 2; }
        .panel2 { grid-column: 2 / 3; grid-row: 1 / 2; }
        .panel3 { grid-column: 3 / 4; grid-row: 1 / 2; }
        /* row 2 */
        .panel4 { grid-column: 1 / 2; grid-row: 2 / 3; }
        .panel5 { grid-column: 2 / 3; grid-row: 2 / 3; }
        .panel6 { grid-column: 3 / 4; grid-row: 2 / 3; }
        /* row 3 */
        .panel7 { grid-column: 1 / 2; grid-row: 3 / 4; }
        .panel8 { grid-column: 2 / 3; grid-row: 3 / 4; }
        .panel9 { grid-column: 3 / 4; grid-row: 3 / 4; }
    </style>
    ');

    $form['panels'] = [
      '#markup' => $style,
      '#prefix' => '<div class="grid-fr-fr-fr gap-20">',
      '#suffix' => '</div>',
    ];

    // Inject CSS styles directly into the page.
    $form['custom_styles'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('<style>

        .ajax-response { color: green;font-family:"monospace"; max-height:300px;overflow:auto; }

        .table-select-panel { margin: 10px; border: 1px solid #ccc; padding: 15px; }
        .table-select-panel { max-height:500px;overflow-y:auto;}
        .button-container button { margin-right: 5px; }
        hr{ clear:both;}
      </style>' .
        '<p>run: <i>/drupal_root/web/sites/bmlistings: drush config:export --destination=sites/bmlistings/files/configexport</i></p>' .
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


    // Example usage:
    //$content_type_id = 'listing';
    //$dependencies = $this->getContentTypeDependencies($content_type_id);

    // dsm($dependencies,'contenttype listing dependencies');

    $this->panel_node($form['panels'], $form_state, 'panel1');
    $this->panel_paragraph($form['panels'], $form_state, 'panel2');
    $this->panel_vocabulary($form['panels'], $form_state, 'panel3');




    $this->panel_node_fields($form['panels'], $form_state, 'panel4');
    $this->panel_paragraph_fields($form['panels'], $form_state, 'panel5');
    $this->panel_taxonomy_fields($form['panels'], $form_state, 'panel6');

    $this->panel_export_configs($form['panels'], $form_state, 'panel7');

    return $form;
  }

  private function panel_node(array &$form, FormStateInterface $form_state, $gridClass): void {

    $form[self::PANEL_NODE] =
      [
        '#prefix' => Markup::create('<div class="panel ' . $gridClass . '" >'
          . bm_main_panel_title_and_help(
            "EXPORT CONTENT TYPE",
            "Export the content type and its fields. Or click and select to filter the fields of the content type in the list below."
          )),
        '#suffix' => '</div>',
      ];


    $entitiesList = [];
    try {
      $contentTypes = \Drupal::entityTypeManager()
        ->getStorage('node_type')
        ->loadMultiple();
      // Prepare options for the select dropdown.
      $options = [];
      foreach ($contentTypes as $machineName => $contentType) {
        $entitiesList[$machineName] = $contentType->label();
      }

    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'label' => $this->t('Label'),
    ];


    $n = 1;
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
        'progress' => ['type' => 'throbber', 'message' => t('Loading... node fields')]
      ],
    ];

    // Response div for displaying resulting yaml
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
        'progress' => ['type' => 'throbber', 'message' => t('Exporting ... node configurations')]
      ],
    ];


  }

  private function panel_paragraph(array &$form, FormStateInterface $form_state, $gridClass): void {
    $form[self::PANEL_PARAGRAPH] =
      [
        '#prefix' => Markup::create('<div class="panel ' . $gridClass . '">'
          . bm_main_panel_title_and_help("EXPORT PARAGRAPH CONFIGURATIONS", "Export paragraph configurations or click and filter the paragraph fields below.")),
        '#suffix' => '</div>',
      ];

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];


    $entitiesList = [];
    try {
      $paragraphs = \Drupal::entityTypeManager()
        ->getStorage('paragraphs_type')
        ->loadMultiple();

      foreach ($paragraphs as $machineName => $contentType) {
        $entitiesList[$machineName] = $contentType->label();
      }
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'label' => $this->t('Label'),
    ];
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
        'progress' => ['type' => 'throbber', 'message' => t('Loading... paragraph fields')]
      ],
    ];

    // ajax Response div for displaying processed rows.
    $form[self::PANEL_PARAGRAPH]['response_3'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => self::ID_AJAX_EXPORT_PARAGRAPH_RESPONSE,
        'class' => ['ajax-response-paragraphs ajax-response']
      ],
    ];

    // Separate AJAX buttons.
    $form[self::PANEL_PARAGRAPH][self::BTN_EXPORT_PARAGRAPH] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => self::BTN_EXPORT_PARAGRAPH,
      '#ajax' => [
        'callback' => '::ajaxOnClickExportButton',
        'wrapper' => self::ID_AJAX_EXPORT_PARAGRAPH_RESPONSE,
        'progress' => ['type' => 'throbber', 'message' => t('Exporting ... paragraph configuration')]
      ],
    ];
  }

  private function panel_vocabulary(array &$form, FormStateInterface $form_state, $gridClass): void {
    $form[self::PANEL_TAXONOMY] = [
      '#prefix' => Markup::create('<div class="panel ' . $gridClass . '">'
        . bm_main_panel_title_and_help('EXPORT VOCABULARY', 'Export the vocabulary configurations or click and filter the vocabulary fields in the panel below.')),
      '#suffix' => '</div>',
    ];

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];


    $entitiesList = [];
    try {
      $taxonomies = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_vocabulary')
        ->loadMultiple();

      $entitiesList = [];
      foreach ($taxonomies as $machineName => $contentType) {
        $entitiesList[$machineName] = $contentType->label();
      }
      //      $entitiesList = $handler->get_types();
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'label' => $this->t('Label'),
    ];
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
        'progress' => ['type' => 'throbber', 'message' => t('Loading... taxonomy fields')]
      ],
    ];

    // Response div for displaying processed rows.
    $form[self::PANEL_TAXONOMY]['response_2'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => self::ID_AJAX_TAXONOMY_EXPORT_RESPONSE,
        'class' => ['ajax-response-taxonomies ajax-response']
      ],
    ];

    // Separate AJAX buttons.
    $form[self::PANEL_TAXONOMY][self::BTN_EXPORT_TAXONOMY] = [
      '#type' => 'button',
      '#value' => $this->t('Export'),
      '#name' => self::BTN_EXPORT_TAXONOMY,
      '#ajax' => [
        'callback' => '::ajaxOnClickExportButton',
        'wrapper' => self::ID_AJAX_TAXONOMY_EXPORT_RESPONSE,
        'progress' => ['type' => 'throbber', 'message' => t('Exporting ... Taxonomy configurations')]
      ],
    ];


  }

  private function panel_node_fields(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form[self::PANEL_NODE_FIELDS] =
      [
        '#prefix' =>
          Markup::create('<div id="' . self::ID_PANEL_NODE_FIELDS . '" class="panel ' . $gridClass . '">'
            . bm_main_panel_title_and_help('EXPORT NODE FIELDSs', '')
          ),
        '#suffix' => '</div>'
      ];

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];
    $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_NODE] ?? '';
    $entitiesList = $this->get_all_fields_and_bundle_names($bundleFilter ?? 'node'); // Fetch all field configurations
    //sort($entitiesList);

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

    $rows[NULL] = [
      'id' => 0,
      'bundle' => '',
      'label' => '',
      'field_name' => '',
      'machineName' => '',
      'description' => '',
      'value_target_id' => '',
      'target_type' => '',
    ];

    $n = 1;
    foreach ($entitiesList as $bundle_fieldName => $data) {
      $data = array_merge(['id' => $n++], $data);
      $rows[$bundle_fieldName] = $data;
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

    // AJAX Response div for displaying processed rows.
    $form[self::PANEL_NODE_FIELDS]['response_4'] = [
      '#type' => 'container',
      '#attributes' => ['id' => self::ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE, 'class' => ['ajax-response-node-fields']],
    ];

    // Export button for fields.
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
    $form[self::PANEL_PARAGRAPH_FIELDS] =
      [
        '#prefix' =>
          Markup::create('<div id="' . self::ID_PANEL_PARAGRAPH_FIELDS . '" class="panel ' . $gridClass . '">'
            . bm_main_panel_title_and_help('EXPORT PARAGRAPH FIELDS', 'Select fields for which to export the configuration.'
            )),
        '#suffix' => '</div>',
      ];

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_PARAGRAPH] ?? 'paragraph';
    $entitiesList = $this->get_type_fields($bundleFilter ?? 'paragraph'); // Fetch all field configurations
    //sort($entitiesList);

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
    $rows[NULL] = [
      'id' => 0,
      'bundle' => '',
      'label' => '',
      'field_name' => '',
      'machineName' => '',
      'description' => '',
      'value_target_id' => '',
      'target_type' => '',
    ];

    $n = 1;
    foreach ($entitiesList as $bundle_fieldName => $data) {
      $data = array_merge(['id' => $n++], $data);
      $rows[$bundle_fieldName] = $data;
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

    // AJAX Response div for displaying processed rows.
    $form[self::PANEL_PARAGRAPH_FIELDS]['response_4'] = [
      '#type' => 'container',
      '#attributes' => ['id' => self::ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE, 'class' => ['ajax-response-fields']],
    ];

    // Export button for fields.
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
      Markup::create('<div id="' . self::ID_PANEL_TAXONOMY_FIELDS . '" class="panel ' . $gridClass . '">' . bm_main_panel_title_and_help('EXPORT TAXONOMY FIELDS', ''));

    $form[self::PANEL_TAXONOMY_FIELDS]['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_TAXONOMY] ?? '';
    $entitiesList = $this->get_type_fields($bundleFilter ?? 'taxonomy'); // Fetch all field configurations


    //sort($entitiesList);

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
    $rows[NULL] = [
      'id' => 0,
      'bundle' => '',
      'label' => '',
      'field_name' => '',
      'machineName' => '',
      'description' => '',
      'value_target_id' => '',
      'target_type' => '',
    ];

    $n = 1;
    foreach ($entitiesList as $bundle_fieldName => $data) {
      $data = array_merge(['id' => $n++], $data);
      $rows[$bundle_fieldName] = $data;
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

    // AJAX Response div for displaying processed rows.
    $form[self::PANEL_TAXONOMY_FIELDS]['response_4'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => self::ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE,
        'class' => ['ajax-response-taxonomy-fields']
      ],
    ];

    // Export button for fields.
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

  /**
   * # ajaxOnChangeRadioButton
   * * On selecting a Node content type, a paragraph bundle or a vocabulary
   * * lookup and display the fields corresponding to that selection.
   *
   * @param array              $form
   * @param FormStateInterface $form_state
   *
   * @return AjaxResponse
   */
  public function ajaxOnChangeRadioButton(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    switch ($form_state->getTriggeringElement()['#name']) {
      // on change of the radio buttons, filter the fields panel tables
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
        break;
    }

    $response->addCommand(new ReplaceCommand($selector, $form['panels'][$formIndex]));
    $response->addCommand(new InvokeCommand($selector, 'fadeOut', [1500])); // Fade out for 500ms
    $response->addCommand(new InvokeCommand($selector, 'fadeIn', [1500]));  // Fade in for 500ms

    return $response;
  }


  /**
   * # ajaxOnClickExportButton
   * * On clicking the export button, export the node, paragraph, taxonomy or fields configuration.
   *
   * @param array              $form
   * @param FormStateInterface $form_state
   *
   * @return AjaxResponse
   */
  public function ajaxOnClickExportButton(array &$form, FormStateInterface $form_state): AjaxResponse {

    $response = new AjaxResponse();

    $exportedYML = '';
    $response_message = '';
    $responseSelector = '';

    switch ($form_state->getTriggeringElement()['#name']) {

      case self::BTN_EXPORT_NODE:
        // where to display it.
        $responseSelector = '#'.self::ID_AJAX_NODE_EXPORT_RESPONSE;
        $configName = $form_state->getValue(self::TABLE_SELECT_NODE);
        if ($configName) {
          $exportedYML .= $this->configSyncHandler->exportNodeConfigurations( $configName);
        }
        break;

      case self::BTN_EXPORT_TAXONOMY:
        $responseSelector = '#'.self::ID_AJAX_TAXONOMY_EXPORT_RESPONSE;
        $configName = $form_state->getValue(self::TABLE_SELECT_TAXONOMY);
        if ($configName) {
          $exportedYML .= $this->configSyncHandler->exportTaxonomyConfigurations($configName);
        }
        break;

      case self::BTN_EXPORT_PARAGRAPH:
        $responseSelector = '#'.self::ID_AJAX_EXPORT_PARAGRAPH_RESPONSE;
        $configName = $form_state->getValue(self::TABLE_SELECT_PARAGRAPH);
        if ($configName) {
          $exportedYML .= $this->configSyncHandler->exportParagraphConfigurations($configName);
        }

        break;

      case self::BTN_EXPORT_NODE_FIELD:
        $responseSelector = '#'.self::ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE;
        $fieldsKey = $form_state->getValue(self::TABLE_SELECT_NODE_FIELDS);
        if ($fieldsKey) {
          $fieldData = $this->configSyncHandler->fields[$fieldsKey];
          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('field.storage.' . $fieldData['machineName']);
          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('field.field.' . $fieldData['bundle'].'.'.$fieldData['field_name']);
          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('core.base_field_override.' . $fieldData['bundle'].'.'.$fieldData['field_name']);
        }

        break;

      case self::BTN_EXPORT_PARAGRAPH_FIELD:
        $responseSelector = '#'.self::ID_AJAX_EXPORT_PARAGRAPH_FIELDS_RESPONSE;
        $fieldsKey = $form_state->getValue(self::TABLE_SELECT_PARAGRAPH_FIELDS);
        if ($fieldsKey) {
          $fieldData = $this->configSyncHandler->fields[$fieldsKey];

          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('field.storage.' . $fieldData['machineName']);
          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('field.field.' . $fieldData['bundle'].'.'.$fieldData['field_name']);
          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('core.base_field_override.' . $fieldData['bundle'].'.'.$fieldData['field_name']);
        }

        break;

      case self::BTN_EXPORT_TAXONOMY_FIELDS:
        $responseSelector= '#'.self::ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE;
        $fieldsKey = $form_state->getValue(self::TABLE_SELECT_TAXONOMY_FIELDS);
        if ($fieldsKey) {
          $fieldData = $this->configSyncHandler->fields[$fieldsKey];

          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('field.storage.' . $fieldData['machineName']);
          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('field.field.' . $fieldData['bundle'].'.'.$fieldData['field_name']);
          $exportedYML .= $this->configSyncHandler->configuration_item_exporter('core.base_field_override.' . $fieldData['bundle'].'.'.$fieldData['field_name']);
        }

        break;
      default:
        break;
    }



    $result_message = empty($exportedYML)
      ? $this->t('No data available.')
      : Markup::create('<textarea cols="61" rows="10" class="panel" style="margin-left:10px;">' . $exportedYML . '</textarea>');

    // Update the response div.
    $response->addCommand(new HtmlCommand($responseSelector, $result_message . $response_message));

    // Display any messages in a modal popup.
    bm_main_response_messages($response);

    return $response;
  }


  public function ajaxConfigExport(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement()['#name'];
    $selected_rows = [];
    $selector = '#ajax-response';
    $response_message = '';
    switch ($trigger) {
      case 'config_export':
        try {
          // $this->configurations_update_exporter('');
          //          $response_message = $this->run_drush_command('config:export');
        } catch (\Exception $e) {
          Drupal::messenger()->addError($e->getMessage());
        }
        $selector = '#ajax-response-0';
        break;
      default:
        break;
    }
    $response_message = str_replace("\n", '<br />', $response_message);
    $response = new AjaxResponse();

    $result_message = 'success';
    // Update the response div.
    $response->addCommand(new HtmlCommand($selector, $result_message . $response_message));

    // Display any messages in a modal popup.
    bm_main_response_messages($response);

    return $response;
  }


  /**
   * Executes the button action based on the clicked button.
   */
  private function execute_button_action($button_clicked, &$form, FormStateInterface $form_state): void {


    // Process the selected rows and provide different messages for each button.
    $response_message = '';

    switch ($button_clicked) {
      case self::BTN_EXPORT_NODE:
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
      self::BTN_EXPORT_PARAGRAPH:
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
   * Display panel with two buttons, one to export all and one to export only entity related configs.
   *
   * @param array              $form
   * @param FormStateInterface $form_state
   * @param string             $gridClass the gridClass determines the position the css grid. See buildForm CSS
   *
   * @return void
   */
  private function panel_export_configs(array &$form, FormStateInterface $form_state, string $gridClass): void {
    $form['panel_export_configs'] = [
      '#prefix' =>
        Markup::create('<div class="panel ' . $gridClass . '">' .
          bm_main_panel_title_and_help('Export all', 'Export all configs listed above')
          . '<div style="display:flex;flex-direction: column;justify-content: space-evenly;"'),
      '#suffix' => '</div></div>',
    ];

    // $nodeHandler = new BMImportNodeHandler();

    //  // get the content types available
    //  $entitiesList = [];
    //  try {
    //    $entitiesList = $nodeHandler->get_contentTypes();
    //  } catch (\Exception $exception) {
    //    Drupal::messenger()->addError($exception->getMessage());
    //  }
    //
    //  $header = [
    //    'id' => $this->t('ID'),
    //    'name' => $this->t('Name'),
    //    'label' => $this->t('Label'),
    //  ];
    //  $rows = [];
    //  $n = 1;
    //  foreach ($entitiesList as $entityMName => $entityLabel) {
    //    $rows[] = ['id' => $n++, 'name' => $entityMName, 'label' => $entityLabel];
    //  }
    //
    //  $form['entities']['table_select_entities'] =
    //      [
    //        '#type'    => 'tableselect',
    //  		'#header'  => ['id'=>'id','name'=>'Name','label'=>'Label'],
    //  		'#options' => $rows,
    //  		'#empty'   => t('No content available.'),
    //  		'#multiple'=> false,     // false makes is radios
    //  	];

    // Response div for displaying processed rows.
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

    // Separate AJAX buttons.
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

    // ajax start batch process via the controller
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
            <p>Batch process runs here</p>>
            <button onclick="this.closest(\'dialog\').close()">Close</button>
        </dialog>'),
    ];
  }


  /**
   * # start batch exporter
   * Export configurations of nodes, taxonomies, paragraphs, fields
   * * to private://config_sync/ i.e. /drupal_root/private/{instance}/config_sync
   *
   * @throws \Exception
   *   If the export fails.
   */
  public function ajax_startBMConfigSyncBatchExporter(array &$form, FormStateInterface $form_state): AjaxResponse {

    $input = $form_state->getUserInput();
    dsm($input);

    $response = new AjaxResponse();
    // Trigger the batch process
    $controller = new BMConfigSyncController();
    $iframe_html = $controller->startBMConfigSyncBatchExporter(); // Get the response
    // AJAX response to update the div with the iframe
    $response = new AjaxResponse();
    // pops up a modal with the messages
    // The selector of the dialog.
    $selector = '#messages-modal';// js sees this in settings of behaviour call
    $dialog_options = [
      //'minHeight' => '700px',
      'width' => 700,
      'resizable' => TRUE,
    ];
    $settings = ['sizeAdjustModal' => $selector];
    $response->addCommand(new OpenModalDialogCommand('RUNNING', '<h1>Just a sec. loading batches ...</h1>' . $iframe_html, $dialog_options, $settings));
    return $response; // Return the AJAX response
  }

  /**
   *
   * Display a button to export all configurations listed on the above panels.
   *
   * @param array              $form
   * @param FormStateInterface $form_state
   *
   * @return AjaxResponse
   */
  public function ajaxPanelExportAllConfigs(array &$form, FormStateInterface $form_state): AjaxResponse {

    $trigger = $form_state->getTriggeringElement()['#name'];
    $selected_rows = [];
    $selector = '#ajax-response';
    $response_message = '';
    switch ($trigger) {

      // interactive processing
      case 'export_all_configuration_diffs':
        try {
         // $this->configurations_update_exporter('');
          //          $response_message = $this->run_drush_command('config:export');
        } catch (\Exception $e) {
          Drupal::messenger()->addError($e->getMessage());
        }
        $selector = '#ajax-response-0';
        break;

      // batch processing
      case 'export_entity_configurations':
        try {
          $this->ajax_startBMConfigSyncBatchExporter($form, $form_state);
          //          $response_message = $this->run_drush_command('config:export');
        } catch (\Exception $e) {
          Drupal::messenger()->addError($e->getMessage());
        }
        $selector = '#ajax-response-0';
        break;
      default:
        break;
    }
    $response_message = str_replace("\n", '<br />', $response_message);
    $response = new AjaxResponse();

    $result_message = 'success';
    // Update the response div.
    $response->addCommand(new HtmlCommand($selector, $result_message . $response_message));

    // Display any messages in a modal popup.
    bm_main_response_messages($response);

    return $response;
  }


  /**
   * Retrieve all fields from the system.
   *
   * @return array
   *   An associative array where the key is the field machine name, and the value is the field label.
   */
  private function get_all_fields_and_bundle_names(): array {
    $fields = [];
    $allBundles = [];
    try {

      // Load all field storage configurations.
      $fieldStorages = \Drupal::entityTypeManager()->getStorage('field_storage_config')->loadMultiple();
      foreach ($fieldStorages as $fieldStorage) {
        $name = $fieldStorage->getName();
        $bundles = $fieldStorage->getBundles();
        $dependencies = $fieldStorage->getDependencies();
        $description = $fieldStorage->getDescription();
        $Oid = $fieldStorage->getOriginalId();// node.field_call_report
        $Eid = $fieldStorage->getEntityTypeId();// field_storage_config
        $type = $fieldStorage->getEntityType();
        $machineName = $fieldStorage->getLabel();
        $value_target_id = $fieldStorage->getMainPropertyName();
        $parent_target_type = $fieldStorage->getTargetEntityTypeId();// node
        $target_type = $fieldStorage->getSettings()['target_type'] ?? NULL;// taxonomy_term
        if (str_starts_with($machineName, 'node.field') ||
          str_starts_with($machineName, 'taxonomy_term.field') ||
          str_starts_with($machineName, 'paragraph.field')) {

          foreach ($bundles as $bundle) {
            $type = explode('.', $machineName);
            $allBundles[$type[0] . '.' . $bundle] = $bundle;


            /** @var \Drupal\field\Entity\FieldStorageConfig $fieldStorage */
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
    return ['fields' => $fields, 'bundles' => $allBundles];// and bundles
  }

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
  function export_content_type_with_dependencies(string $content_type, string $base_directory): void {
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
    $filesystem = new Filesystem(null);

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
  function getContentTypeDependencies(string $content_type_id): array {
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
   * Import configuration from the sync directory.
   *
   * @throws \Exception
   *   If the import fails.
   */
  function import_all_configuration(): void {
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


}
