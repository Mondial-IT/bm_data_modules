<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-16 at 11:1:24
 */

namespace bmDataModules\bm_import\src\Form\Panel;

use Drupal;
use bmDataModules\bm_import\src\BMImport\BMImport;
use bmDataModules\bm_import\src\BMImport\BMImportInspector;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;

class Panel_inspector extends FormBase{
  private BMImport $BMImport;
  protected $instanceStorage;

  public function __construct() {
    $this->instanceStorage = \Drupal::service('bm_import.manager');
  }
  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'panel_inspector';
  }

  /**
   * @inheritDoc
   * @throws Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
   // bm_d('', instantiateColor('instantiated Panel_mandatory_fields'));
    // Retrieve the BMImport instance from the shared service
    $this->BMImport = $this->instanceStorage->getInstance();

    $this->form_panel_inspector($form);
    $form_state->setRebuild(true);
    return $form;
  }

  /**
   * #  form_panel_inspector
   *
   * @param $form
   *
   * @return void
   * @throws Exception
   */
  public function form_panel_inspector(&$form): void {

    bm_d('', traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));


    $title = '"Duplicates and alike" issues inspector panel';
    $helpText = '
        <p>The inspector verifies if the upload listing is already present in the database.</p>
        <p>It uses various method to identifies duplicates.</p>';

    $form['panel_inspector'] = [
      '#type' => 'markup',
      '#title' => $this->t('Collapsible Section'),
      '#prefix' => '<div class="panel" id="panel-inspector-wrapper">' . bm_main_panel_title_and_help($title, $helpText) . '
                    <details id="panel-inspector" class="bm-collapsible"  open>
                    <summary>Workbook entries matched with listings.</summary>',
      '#suffix' => '</div>'

    ];

    // when displayed after running inspect it has data, at first it is hidden.
    $nodesMatchedTable=[];
//    $nodesMatchedTable = $this->BMImport->BMImportInspector->get_cached_existingNodesMatchTable();
    if (!empty($nodesMatchedTable)) {
      $tableSelect['header'] = $nodesMatchedTable[0];
      $tableSelect['options'] = array_slice($nodesMatchedTable, 1);
      $tableSelect['default_value'] ??= [];

      $form['panel_inspector']['existing-nids-table-select'] = [
        '#type' => 'tableselect',
        '#header' => $tableSelect['header'],
        '#options' => $tableSelect['options'],
        '#default_value' => $tableSelect['default_value'],
        '#suffix'=>'</details'
      ];
    }

    $form['panel_inspector']['inspect_the_excel'] = [
      '#type' => 'submit',
      '#value' => t('Inspect'),
      '#submit'=>['::submitForm'],
      '#ajax' => [
        'callback' => '::submit_AjaxCallback',
        'wrapper' => 'panel-inspector-wrapper',
        'method'=>'replace'
      ],
    ];


  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
   // bm_d('',traceColor(' trace: ' . __FUNCTION__ . ' panel_inspector ' . time()));

    $form_state->setRebuild(true);
  }

  public function submit_AjaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    //bm_d('',traceColor(' trace: ' . __FUNCTION__ . ' panel_inspector ' . time()));

    $ajax_response = new AjaxResponse();

  //  $this->submit_ajax_response__refresh_panels($ajax_response, $form);

    $ajax_response->addCommand(new ReplaceCommand('#panel-inspector-wrapper', $form['panel_inspector']));

    //bm_main_response_messages($ajax_response);

    return $ajax_response;
  }

  private function submit_ajax_response__refresh_panels(AjaxResponse &$ajax_response, array $form): void {
    if (isset($form['panel_inspector']['existing-nids-table-select'])) {
      $ajax_response->addCommand(
        new ReplaceCommand('#panel-inspector', $form['panel_inspector']['existing-nids-table-select'])
      );
    }
  }

  /**
   * @throws Exception
   */
  private function act_on_inspect_button_inspector_match_nids(): void {
    // current chunk 0 means initialize again
  //  $csvContent = $this->BMImport->BMImportXLSXhandler->get_excelContent();
 //   $rowsToProcess = array_keys($csvContent['options']);
 //   $this->BMImport->BMImportInspector->rowsIterator_findExistingNodeIdsForRows($rowsToProcess, 0);
  }

}
