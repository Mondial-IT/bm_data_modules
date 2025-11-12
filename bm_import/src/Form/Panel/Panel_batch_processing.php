<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-16 at 11:25:57
 */

namespace bmDataModules\bm_import\src\Form\Panel;

use Drupal;
use bmDataModules\bm_import\src\BMImport\BMImport;
use bmDataModules\bm_import\src\BMImport\BMImportBatch;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Exception;

class Panel_batch_processing extends FormBase {

  private BMImport $BMImport;

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'panel_batch_processing';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    bm_d('', instantiateColor('instantiated Panel_mandatory_fields'));

    $this->BMImport = \Drupal::service('bm_import.manager')->getInstance();
    $this->form_panel_batch_processing($form);

    return $form;
  }

  /**
   * #### form_panel_batch_processing
   *
   * @param $form
   *
   * @return void
   */
  public function form_panel_batch_processing(&$form): void {
    bm_d('', traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));


    $title = 'Batch import panel';
    $helpText = 'Use this panel to import a large number of entries (>1000)';
    $form['panel_inspector_batch'] = [
      '#prefix' => '<div id="panel-inspector-batch" class="panel gap-20">' . bm_main_panel_title_and_help($title, $helpText),
      '#suffix' => '</div>',
    ];
    // reference to the csvContent
    $csvContent = $this->BMImport->BMImportXLSXhandler->get_excelContent();
    $numberOfRows = 0;
    if (!empty($csvContent['options'])) {
      $numberOfRows = count($csvContent['options']);
    }
    [$chunkSize, $totalChunks, $chunkMessage] = $this->chunkCalculator($numberOfRows, 0);
    $form['panel_inspector_batch']['progress_message'] = [
      '#type' => 'markup',
      '#markup' => '<div id="progress-message">Ready to process ... ' . $chunkMessage . '</div>',
    ];

    $progressBar = Markup::create(
      '<div id="progress-bar" >
          <div class="progress-bar-background">
                <div class="progress-bar-text">0%</div>
                <div class="progress-bar-slider"></div>
            </div>
        </div>');

    $form['panel_inspector_batch']['progress_bar'] = [
      '#type' => 'markup',
      '#markup' => $progressBar,
    ];

    $form['panel_inspector_batch']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start processing'),
      '#ajax' => [
        'callback' => '::submit_AjaxCallback_batchProcess',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Processing...'),
        ],
      ],
    ];
    $form['panel_inspector_batch']['pauze'] = [
      '#type' => 'submit',
      '#value' => $this->t('Pauze'),
      //   '#attributes'=>['class'=>['displaynone']],
      '#ajax' => [
        'callback' => '::submit_AjaxCallback_batchProcess',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Processing...'),
        ],
      ],
    ];
    $form['panel_inspector_batch']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel processing'),
      //  '#attributes'=>['class'=>['displaynone']],
      '#ajax' => [
        'callback' => '::submit_AjaxCallback_batchProcess',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Processing...'),
        ],
      ],
    ];

    $form['panel_inspector_batch']['current_chunk'] = [
      '#type' => 'hidden',
      '#default_value' => '0',
      '#attributes' => [
        'id' => 'current-chunk',
      ],
    ];

    $form['panel_inspector_batch']['progress_percentage'] = [
      '#type' => 'hidden',
      '#default_value' => '0',
      '#attributes' => [
        'id' => 'current-chunk-progress',
      ],
    ];
  }
  /**
   * @param $numberOfRows
   * @param $currentChunk
   *
   * @return array [$chunkSize, $totalChunks, $chunkMessage];
   */
  private function chunkCalculator($numberOfRows, $currentChunk): array {
    $chunkSize = 50; // Adjust chunk size as needed
    $totalChunks = 0;
    $start = 0;
    $progressPercentage = 0;

    if ($numberOfRows) {
      $totalChunks = ceil($numberOfRows / $chunkSize);
      $start = $currentChunk * $chunkSize;
      $progressPercentage = round(($currentChunk) / $totalChunks * 100);
    }
    $chunkMessage = "$progressPercentage% Chunk - $currentChunk of $totalChunks, Row $start of $numberOfRows, $chunkSize Rows per Chunk";

    return [$chunkSize, $totalChunks, $chunkMessage];
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    // note, usually we handle actions in the buildForm,
    // however this button, is not ajax'd and handled here.
    $te = $form_state->getTriggeringElement()['#id'];
    if (isset($te) && $te === 'edit-create-nodes-for-selected-rows-via-batch') {
      $tableSelect = $input['import-data-table-select'] ?? [];
      $this->act_on_batchImport_button($tableSelect);
    }
    $form_state->setRebuild(true);
  }

  private function duration($time): float {
    return round(microtime(TRUE) - $time, 2);
  }

  /**
   * @throws Exception
   */
  public function submit_AjaxCallback_batchProcess(array &$form, FormStateInterface $form_state): AjaxResponse {
    $startTime = microtime(TRUE);
    $lastTime = $_SESSION['profiling'][__FUNCTION__]['time'] ?? microtime(TRUE);
    $_SESSION['profiling'][__FUNCTION__]['time'] = microtime(TRUE);
    $_SESSION['profiling'][__FUNCTION__]['idles'][] = $this->duration($lastTime);


    $BMImport = \Drupal::service('bm_import.manager')->getInstance();

    $time = microtime(TRUE);
    $csvContent = $BMImport->BMImportXLSXhandler->get_excelContent();

    $input = $form_state->getUserInput();

    $rowNums = array_keys($csvContent['options']); // all
    $ajax_response = new AjaxResponse();

    // Get the current chunk from the hidden field.
    $currentChunk = (int) $input['current_chunk'];
    [$chunkSize, $totalChunks, $chunkMessage] = $this->chunkCalculator(count($rowNums), $currentChunk);
    \Drupal::logger('BMImport form')->debug('current chunk ' . $chunkMessage . ' time:' . time());

    $progressMessage = 'To process ';
    $action = 'next_chunk';
    $te = $input['_triggering_element_value'];// name as in form array definition

    switch ($te) {
      case 'Start processing':
        $progressMessage = 'Processed chunk';
        break;
      case 'Cancel processing':
        $action = 'cancel';
        $progressMessage = 'Canceled on chunk';
        $currentChunk = 0;
        $_SESSION['profiling'][__FUNCTION__] = [];// restart
        break;
      case 'Pauze':
        bm_d('', 'PAUZE');
        $progressMessage = 'Pauzed on chunk';
        $action = 'pauze';
        $existingNodesMatchTable = $BMImport->BMImportInspector->get_cached_existingNodesMatchTable();
        break;
      default:
        $action = 'pauze';
        $progressMessage = 'Pauzed on chunk';
        break;
    }

    if ($currentChunk < $totalChunks) {

      $progress = round(($currentChunk) / $totalChunks * 100);
      $nextChunk = $currentChunk;

      if ($action <> 'pauze' && $action <> 'cancel') {

        $start = $currentChunk * $chunkSize;
        $rowsToProcess = array_slice($rowNums, $start, $chunkSize);

        // current chunk 0 means initialize again
        $BMImport->BMImportInspector->rowsIterator_findExistingNodeIdsForRows($rowsToProcess, $currentChunk);

        $progress = round(($currentChunk + 1) / $totalChunks * 100);
        // Increment the chunk and update the hidden field value
        $nextChunk = $currentChunk + 1;
      }

      bm_d('', $progressMessage, 'progress message:');

      $ajax_response->addCommand(new HtmlCommand('#progress-message', $progressMessage . ' @' . $chunkMessage));
      $ajax_response->addCommand(new HtmlCommand('#progress-bar',
        '<div class="progress-bar-background">
                        <div class="progress-bar-text">' . $progress . '%</div>
                        <div class="progress-bar-slider" style="width:' . $progress . '%;"></div>
                    </div>'));

      $ajax_response->addCommand(new InvokeCommand('#current-chunk', 'val', [$nextChunk]));
      $ajax_response->addCommand(new InvokeCommand('#current-chunk-progress', 'val', [$progress]));
      $form_state->setRebuild(TRUE);
      $ajax_response->addCommand(
        new ReplaceCommand('#panel-inspector', $form['panel_inspector'])
      );
    }
    else {
      $ajax_response->addCommand(new HtmlCommand('#progress-message', 'Processing complete!'));
      $ajax_response->addCommand(new InvokeCommand('#current-chunk', 'val', [0])); // Reset chunk counter
      $form_state->setRebuild(FALSE);

      $ajax_response->addCommand(
        new ReplaceCommand('#import-inspector', $form['panel_inspector'])
      );

    }

    $this->submit_ajax_response__refresh_panels($ajax_response, $form, $input);

    // display message when clicked on pauze.
    if ($action == 'pauze') {
      bm_main_response_messages($ajax_response);
    }

    return $ajax_response;
  }

  /**
   * #### submit_ajax_response__refresh_panels
   * Check and refresh the visible panels
   *
   * @param $ajax_response
   * @param $form
   * @param $input
   *
   * @return void
   */
  private function submit_ajax_response__refresh_panels($ajax_response, $form, $input): void {
    $message = ' trace:' . __FUNCTION__;
    bm_d('', traceColor($message));

    if (isset($form['panel_preflight'])) {
      $ajax_response->addCommand(
        new ReplaceCommand('#preflight-panel', $form['panel_preflight'])
      );
    }

    if (isset($form['problem_solving_panel'])) {
      $ajax_response->addCommand(
        new ReplaceCommand('#problem-solving-panel', $form['problem_solving_panel'])
      );
    }
    if (isset($form['panel_select'])) {
      $ajax_response->addCommand(
        new ReplaceCommand('#panel-select-canvass', $form['panel_select'])
      );
    }
    if (isset($form['panel_buttons'])) {
      $ajax_response->addCommand(
        new ReplaceCommand('#panel-buttons', $form['panel_buttons'])
      );
    }
    if (isset($form['panel_inspector']['existing-nids-table-select'])) {
      $ajax_response->addCommand(
        new ReplaceCommand('#panel-inspector', $form['panel_inspector']['existing-nids-table-select'])
      );
    }

    if (isset($form['import_data'])) {
      bm_d('', 'ajax refresh table data');

      $ajax_response->addCommand(
        new ReplaceCommand('#import-data', $form['import_data'])//$form['import_data'])
      );

      // iterate #default_value and force the checkboxes accordingly
      $this->submit_ajax_response__check_uncheck_boxes($form, 'import-data', $ajax_response);

    }



  }

  /**
   * #### submit_ajax_response__check_uncheck_boxes
   *
   * @param $form
   * @param $tableSelectId
   * @param $ajax_response
   *
   * @return void
   */
  private function submit_ajax_response__check_uncheck_boxes($form, $tableSelectId, &$ajax_response): void {
    // reset all
    $checkbox_id = '[data-drupal-selector*="edit-' . $tableSelectId . '-table-select-"]';
    $ajax_response->addCommand(new InvokeCommand($checkbox_id, 'prop', ['checked', FALSE]));

    // set the required ones
    if (isset($form[$tableSelectId]['#default_value'])) {
      $default_values = $form[$tableSelectId]['#default_value'];
      bm_d('', $default_values, 'ajax sync default checkboxes');

      foreach ($default_values as $row_num => $default_value) {
        if ($default_value) {
          $checkbox_id = '[data-drupal-selector="edit-' . $tableSelectId . '-table-select-' . $row_num . '"]';
          $ajax_response->addCommand(new InvokeCommand($checkbox_id, 'prop', ['checked', TRUE]));
        }
      }
    }
  }

  /**
   * Import in batch, when button batch import is clicked.
   *
   * @param $tableSelect
   *
   * @return void
   */
  private function act_on_batchImport_button($tableSelect): void {
    $message = ' trace:' . ' ' . __FUNCTION__ . ' ' . time();
    bm_d('', traceColor($message));

    // find selected rows
    $rowNums = [];
    foreach ($tableSelect as $n => $set) {
      if ($set !== NULL) {
        $rowNums[] = $n;
      }
    }
    if (!count($rowNums)) {
      $message = ' -- nothing selected - DONE';
      bm_d('', violetColor($message));
      return;
    }

    Drupal::messenger()
      ->addStatus('<div class="bgcolor-blue fgcolor-white">BATCH IMPORT STARTING - ' . count($rowNums) . ' selected rows"</div>');

    $ImportBatch = new BMImportBatch();
    //$ImportBatch->setMyBatch($rowNums, $this->BMImport->importRows);

  }

}
