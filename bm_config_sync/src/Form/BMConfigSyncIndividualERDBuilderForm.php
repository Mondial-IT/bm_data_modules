<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace Drupal\bm_config_sync\Form;


use Drupal;
use Drupal\bm_config_sync\Handler\BMConfigSyncHandler;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class BMTemplateForm for a Drupal settings form with AJAX table select functionality.
 */
class BMConfigSyncIndividualERDBuilderForm extends FormBase {


  const PANEL_NODE = 'panel_node';

  const TABLE_SELECT_NODE = 'table_select_node';

  const BTN_EXPORT_NODE = 'btn_export_node';

  const PANEL_NODE_FIELDS = 'panel_node_fields';

  const ID_PANEL_NODE_FIELDS = 'panel-node-fields';

  const TABLE_SELECT_NODE_FIELDS = 'table_select_node_fields';

  const BTN_EXPORT_NODE_FIELD = 'btn_export_node_fields';


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

  const ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE = 'ID_AJAX_EXPORT_NODE_FIELDS_RESPONSE';

  const ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE = 'ID_AJAX_EXPORT_TAXONOMY_FIELDS_RESPONSE';

  // table with combined erd data of all related entities
  const PANEL_ERD = 'panel_erd';

  const ID_PANEL_ERD = 'panel-erd';

  const TABLE_ERD = 'table_erd';

  const BTN_PROCESS_ERD = 'btn_process_erd';

  const ID_AJAX_ERD_RESPONSE = 'id_ajax_erd_response';

  // grid of individual erd panels
  const ERD_PANELS = 'erd_panels';

  const ID_ERD_PANELS = 'id-erd-panels';

  const TABLE_SELECT_COLUMNS = 'select_columns';

  const ID_TABLE_SELECT_COLUMNS = 'id_table_select_columns';

  private mixed $fields;

  private mixed $bundles;

  public array $ERD;

  public Config $config;

  public string $exportedYML;

  protected BMConfigSyncHandler $configSyncHandler;

  public array $form;// keep base of form available in panels

  public static function create(ContainerInterface $container): self {
    /** @var self $instance */
    $instance = parent::create($container);
    $instance->configSyncHandler = $container->get('bm_config_sync.handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bm_config_sync_erd_builder_form';
  }

  /**
   * Called first
   * - then build form
   * - then ajax submitters
   *
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->ERD = [];
    $this->exportedYML = '';
    $this->config = \Drupal::configFactory()->getEditable('bm_config_sync.settings');
    // called , build, then ajax on sumit
    // on onChange it is not called.
    $input = $form_state->getValue('input');
  }


  /**
   * Builds the settings form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'bm_tooltip/tooltip';
    $form['#attached']['library'][] = 'bm_main/datatablesjs';
    $form['#attached']['library'][] = 'bm_config_sync/admin_styles';
    $form['#attributes']['class'][] = 'bm-config-sync-form';


    $this->panel_select($form, $form_state, 'panel1');

    $form['panels'] = [
      '#prefix' => '<div id=' . self::ID_ERD_PANELS . ' class="grid-fr-fr-fr gap-20 bm-config-sync-grid">',
      '#suffix' => '</div>',
    ];
    // entity selector

    // display resulting ERDs
    $this->panel_ERD_grid($form['panels'], $form_state);

    return $form;
  }

  private function panel_select(array &$form, FormStateInterface $form_state, $gridClass): void {

    $form['select'] =
      [
        '#prefix' => Markup::create('<div id="id-selector-panel" class="panel panel-item-selector" >'
          . bm_main_panel_title_and_help(
            "DISPLAY ENTITY RELATIONS TABLES",
            "Display the content type and its fields. Or click and select to filter the fields of the content type in the list below."
          )),
        '#suffix' => '</div>',
      ];

    $this->nodeSelector($form, $form_state);

    $this->taxonomySelector($form, $form_state);

    $this->columnSelector($form, $form_state);

  }

  private function nodeSelector(&$form, $form_state): void {
    #options=[];
    $entitiesList = [];
    try {
      $contentTypes = \Drupal::entityTypeManager()->getStorage('node_type')->loadMultiple();
      foreach ($contentTypes as $machineName => $contentType) {
        $entitiesList[$machineName] = $contentType->label();
      }
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }
    $options[NULL] = ['--' => '-- Choose one --'];
    foreach ($entitiesList as $entityMName => $entityLabel) {
      $options[$entityMName] = $entityMName;
    }
    $form['select'][self::TABLE_SELECT_NODE] = [
      '#type' => 'select',
      '#title' => 'Content type',
      '#options' => $options,
      '#empty' => $this->t('No items available.'),
      '#multiple' => FALSE,
      '#ajax' => [
        'callback' => '::ajaxOnChangeRadioButton',
        'wrapper' => self::ID_PANEL_NODE_FIELDS,
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => t('Loading... Content Type ERD')],
      ],
    ];
  }

  private function taxonomySelector(&$form, $form_state): void {
    // TAXONOMY SELECTOR

    $entitiesList = [];
    try {
      $taxonomies = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
      $entitiesList = [];
      foreach ($taxonomies as $machineName => $contentType) {
        $entitiesList[$machineName] = $contentType->label();
      }
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }
    $options[NULL] = ['--' => '-- Choose one --'];
    foreach ($entitiesList as $entityMName => $entityLabel) {
      $options[$entityMName] = $entityMName;
    }
    $form['select'][self::TABLE_SELECT_TAXONOMY] = [
      '#type' => 'select',
      '#title' => 'Taxonomy',
      '#options' => $options,
      '#multiple' => FALSE,
      '#empty' => $this->t('No items available.'),
      '#ajax' => [
        'callback' => '::ajaxOnChangeRadioButton',
        'wrapper' => self::ID_PANEL_TAXONOMY_FIELDS,
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => t('Loading... vocabulary ERD')],
      ],
    ];


  }

  private function columnSelector(&$form, $form_state): void {
    //  $this->config->set('displayed_columns', ['id'])->save(); // to reset a wrong config
    // currently set column filter
    $displayedColumns = $this->config->get('displayed_columns') ?? ['id', 'field_name', 'label'];

    // which fields are available to select?
    $options = $this->configSyncHandler->getERDColumnNames();

    // is a selection made for the column filter
    // so we need to alter the displayed columns list
    $trigger = $form_state->getTriggeringElement();
    if ($trigger && $trigger['#name'] === 'select_columns') {
      $columnFilter = $form_state->getUserInput()[self::TABLE_SELECT_COLUMNS] ?? '';
      // new selection?
      if ($columnFilter) {
        if (in_array($columnFilter, $displayedColumns)) {
          // yes remove
          $index = array_search($columnFilter, $displayedColumns);
          if ($index !== FALSE) {
            unset($displayedColumns[$index]);
          }
        }
        else {
          // no add
          $displayedColumns[] = $columnFilter;
        }
        // save as permanent setting
        $this->config->set('displayed_columns', $displayedColumns)->save();
      }
    }
    // display the set columns with a - to make a next select remove it.
    foreach ($options as $option) {
      if (in_array($option, $displayedColumns)) {
        $options[$option] = Markup::create('- ' . $option);
      }
    }


    $form['select'][self::TABLE_SELECT_COLUMNS] = [
      '#type' => 'select',
      '#title' => 'Columns',
      '#options' => $options,
      '#multiple' => FALSE,
      '#empty' => $this->t('No items available.'),
      '#prefix' => '<div id="' . self::ID_TABLE_SELECT_COLUMNS . '">',
      '#suffix' => '</div>',
      '#ajax' => [
        'callback' => '::ajaxOnChangeRadioButton',
        'event' => 'change',
        'progress' => ['type' => 'throbber', 'message' => t('Loading ... ')],
      ],
    ];
  }

  private function dataTablesEnhancement(&$form, $CSSSelector): void {

    $configuration = [
      'stateSave' => TRUE,
      // select single rows (not multiple)
      'select' => TRUE,
      //'single',
      // Save the state of the table (e.g., pagination, sorting)
      'scrollX' => TRUE,
      // Enable horizontal scrolling
      'processing' => TRUE,
      // Show processing indicator while loading data
      'info' => TRUE,
      // Show table information (e.g., number of entries)
      'pageLength' => 7,
      // Set the number of rows per page
      'rowReorder' => TRUE,
      //'serverSide' => true,              // Enable server-side processing
      'colReorder' => TRUE,
      // Enable column reordering
      // Enable responsive behavior, which will place overflow fields hidden until click on column to display them in a second row.
      //'responsive' => true,
      'ordering' => TRUE,
      // Enable sorting
      'layout' => [
        'topstart' => [
          'id' => 'MYID',
          'className' => 'MYCLASS',
          'pageLength',
        ],
        'topend' => [
          'search' => [
            'placeholder' => 'Search',
          ],
        ],
        'top2' =>
          [
            'buttons' => [
              'colvis',
              'spacer',
              'searchBuilder',

            ],
          ],
        //        'top2end'=>
        //          [
        //          'buttons' => [
        //            'searchBuilder',
        //            ],
        //          ],
        'bottomStart' => 'info',
        'bottomEnd' => 'paging',
        'bottom2' => [
          'buttons' => [
            //  'pageLength',
            'copy',
            'spacer',
            'excel',
            'spacer',
            'pdf',
            'spacer',
            'csv',
          ],
        ],

      ],

      //      'dom' => 'frtipB',
      // Define the layout of the table
      /*
        B: Buttons
        f: Filter input
        r: Processing display element
        t: The table
        i: Table information summary
        p: Pagination control
       */
      //      'responsive' => [
      //        'details' => [
      //          'type' => 'column',           // Use a column for responsive details
      //          'target' => 'tr',             // Target the table row for responsive details
      //        ],
      //      ],
      'columnDefs' => [                  // Define column properties
        [
          'targets' => '_all',
          'resizable' => TRUE,         // Enable resizing for all columns
        ],

      ],
      //      'columns' => [                    // Define the columns
      //        ['data' => 'depth', 'name' => 'Depth'],
      //        ['data' => 'children', 'name' => 'Children'],
      //        ['data' => 'field_name', 'name' => 'field_name'],
      //        ['data' => 'field_type', 'name' => 'Field_type'],
      //      ],
      //      'order' => [[0, 'desc']],         // Set the default sorting order
    ];
    $minimum_configuration = [
      // persist filters and settings
      'stateSave' => TRUE,
      // Set the number of rows per page
      'rowReorder' => TRUE,
      //'serverSide' => true,              // Enable server-side processing
      'colReorder' => TRUE,
      // Enable column reordering
      'ordering' => TRUE,
      'language'=>[
        'searchBuilder'=>[
          'button'=>'<i class="fas fa-filter fa-2x"></i>'
        ]
      ],
      'pageLength'=> 100,
      'lengthMenu'=> [10, 50, 100, 500, 1000],
      // Enable sorting
      'layout' => [
        'topstart' => [
          'pageSize' => 7,
        ],
        'top2start' =>
          [
            'buttons' => [
              [
              'extend' =>'colvis',
               'text'=> '<i class="fa fa-2x fa-table"></i>',
               'titleAttr'=>'Column visibility'
              ],
            ],

            //            'buttons' => [
            //              'colvis',
            //            ],
          ],
        'top2end' =>
          [
            'buttons' => [
              [
                'extend' => 'searchBuilder',
                'className' => 'buttons-searchBuilder',
              ],
            ],
          ],
        'bottom2start' => [
          'buttons' => [
            //  'pageLength',
            'copy',
            'spacer',
            'excel',
            'spacer',
            'pdf',
            'spacer',
            'csv',
          ],
        ],
      ],
    ];

    $form['#attached']['drupalSettings']['datatables']['config'][] = [
      'selector' => $CSSSelector,
      'configuration' => $minimum_configuration,
    ];

  }

  /**
   * Individual ERD panels
   *
   * @param array              $form
   * @param FormStateInterface $form_state
   *
   * @return void
   */
  public function panel_ERD_grid(array &$form, FormStateInterface $form_state): void {


    $bundleFilter = '';
    // panel ERD is triggered by change in column, in taxonomy and in content type.
    // here we figure out, which bundle filter is to be used to create ERD's for.
    $trigger = $form_state->getTriggeringElement();
    if ($trigger && $trigger['#name'] === 'table_select_taxonomy') {
      $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_TAXONOMY] ?? '';
    }
    else {
      // retrieve what entity is selected to be displayed as ERD panels
      $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_NODE] ?? "--";
      if ($bundleFilter === '--') {
        $bundleFilter = $form_state->getUserInput()[self::TABLE_SELECT_TAXONOMY] ?? '';
      }
      if ($bundleFilter === "") {
        return;
      }
    }

    // Fetch all field configurations i.e. listing|field_bm_id, listing|field_call_report
    $fields = $this->configSyncHandler->get_type_fields($bundleFilter ?? '');
    // Fetch field data
    $this->configSyncHandler->populate_combinedERD_ERD_per_bundle($fields);

    $tables = $this->configSyncHandler->getERDs();
    // create individual ERDs and display a table for each entity

    foreach ($tables as $entity => $rows) {

      $form[self::PANEL_ERD][$entity] =
        [
          '#prefix' =>
            Markup::create('<div  class="panel ">'
              . bm_main_panel_title_and_help($entity, '')
            ),
          '#suffix' => '</div>',
        ];

      // displayed columns as selected by the select columns select element
      $displayedColumns = $this->config->get('displayed_columns') ?? ['id', 'field_name'];

      // Create the header
      $header = [];
      foreach (reset($rows) as $key => $value) {
        $addClass = '';
        if (in_array($key, $displayedColumns)) {
          // <i class='fa-1x fa-arrow-alt-circle-left fa'></i>
          $header[$key] = Markup::create('<div class="erd-field-' . $key . ' ' . $addClass . '">' . $key . '</div>');
        }
      }

      // sort header by $displayedColumns
      $headerSorted = [];
      foreach ($displayedColumns as $dC) {
        $headerSorted[$dC] = $header[$dC] ?? "Unknown " . $dC;
      }

      // add classes
      $rowsColumnFiltered = [];
      foreach ($rows as $index => $row) {
        foreach ($row as $field => $value) {
          // keep, but hide not selected columns
          $addClass = '';
          if (in_array($field, $displayedColumns)) {
            $rowsColumnFiltered[$index][$field] = Markup::create('<div class="erd-field-' . $field . ' ' . $addClass . '">' . $value . '</div>');
          }
        }
      }

      $entityToClass = str_replace(['_', '.'], ['-', '-'], $entity);
      $this->dataTablesEnhancement($form, '.' . $entityToClass);

      $form[self::PANEL_ERD][$entity]['table_' . $entity] = [
        '#type' => 'table',
        '#attributes' => ['class' => ['DataTableJS', $entityToClass]],
        '#header' => $headerSorted,
        '#rows' => $rowsColumnFiltered,
        '#multiple' => TRUE,
        '#empty' => $this->t('No fields available.'),
      ];
    }
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
  public
  function ajaxOnChangeRadioButton(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $input = $form_state->getValue('input');
    $errors = $form_state->getErrors();


    $selector = '#' . self::ID_ERD_PANELS;
    $response->addCommand(new ReplaceCommand('.form-item-select-columns', $form['select'][self::TABLE_SELECT_COLUMNS]));
    $response->addCommand(new ReplaceCommand($selector, $form['panels']));
    $response->addCommand(new InvokeCommand($selector, 'fadeOut', [200])); // Fade out for 500ms
    $response->addCommand(new InvokeCommand($selector, 'fadeIn', [1000]));  // Fade in for 500ms


    // Display any messages in a modal popup.
    bm_main_response_messages($response);
    return $response;
  }


}
