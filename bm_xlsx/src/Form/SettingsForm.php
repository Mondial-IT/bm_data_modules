<?php
/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:11
 */ /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Drupal\bm_xlsx\Form;


use Drupal;
use Drupal\bm_xlsx\BMXLSX\BMXLSX;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;


/**
 * bm_xlsx/SettingsForm
 * Display the "upload excel" panel, select a sheet, select number of rows and displays the uploaded Excel.
 *
 */
class SettingsForm extends ConfigFormBase {

  public function __construct($config_factory) {

    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'upload-and-sheet-select-form';
  }
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bm_xlsx.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public
  function validateForm(array &$form, FormStateInterface $form_state): void {
    Drupal::messenger()->addMessage(__FUNCTION__);

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public
  function submitForm(array &$form, FormStateInterface $form_state): void {
    Drupal::messenger()->addMessage(__FUNCTION__);
    // Save values in the form state.
    $input = $form_state->getUserInput();

    $form_state->setValue('sheets', $input['sheets'] ?? 0);
    $form_state->setValue('limit', $input['limit'] ?? 10);

    BMXLSX::cache_config_sheet_selection_and_limit($input['sheets'], $input['limit']);

    // Mark the form for rebuild.
    $form_state->setRebuild(TRUE);

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   * @noinspection PhpMultipleClassDeclarationsInspection
   * @throws Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    Drupal::messenger()->addMessage(__FUNCTION__);
    $form['#attached']['library'][] = 'ziston/tippyjs';
    $form['#attached']['library'][] = 'ziston/bm_tippyjs';
    $form['#attached']['library'][] = 'bm_main/theme_seven_modernizer';
    $form['#attached']['library'][]= 'bm_xlsx/bm_xlsx';

    // Retrieve saved file path.
    $file_path = bm_main_get_last_uploaded_file_path();

    // Retrieve values from the form state or provide defaults.
    $sheet = $form_state->getValue('sheets', 0); // Default sheet is 0.
    $limit = $form_state->getValue('limit', 10); // Default limit is 10.
    $form['style'] =
      [
        '#markup' => Markup::create('<style>
        /* clean up the drupal modal, to make it slim fit */
        .ui-dialog-titlebar{
            display:none;
        }
        #drupal-modal{margin:0;padding:0;
            max-height: fit-content!important;
            height: fit-content!important;
        }

        .sheet-selection-wrapper{
           display: flex;
          flex-direction: row;
          align-content: center;
          align-items: center;
          gap:30px;
          padding: 12px;
          width: max-content;

        }
        /* button to display the selected sheet */
       .button-display-sheet
        {
            height:35px;
            top:7px;
        }
        /* the table select space */
        #panel2-wrapper{
            width:97%;
            max-width:97%;
            overflow:auto;
        }
        /* button to open the change excel dialog */
        a.button.upload-excel-dialog{
          background: linear-gradient(270deg, var(--bm-bluemarloc-purple), var(--bm-bluemarloc-blue));
          background-size: 400% 400%;
          animation: AnimationShift 30s ease infinite;
          padding: 7px 20px !important;
          border-radius: 9px;
          box-shadow: 1px 1px 1px #d3d3d3;
          color: white;
          text-shadow: 0 1px #010101;
          top:6px;
        }
            </style>'),
      ];


    $form['panels'] = [
      '#prefix' => Markup::create('<div >'),
      '#suffix' => '</div>',
    ];

    $helpText='<i>Example of uploading an Excel workbook and how it can be used in the Blue Marloc platform.</i>
        <p>When the Excel is uploaded, the bm_xlsx/shuchkin module interprets the spreadsheet. It offers various API calls.
        <ul>
          <li>getSheetNames</li>
          <li>get_current_sheet</li>
          <li>get_current_limit</li>
          <li>get_config_sheet_selection_and_limit</li>
          <li>getRowHeader</li>
          <li>getRows</li>
          <li>getRowsWithoutHeader</li>
          <li>getRowsKeyed</li>
          <li>getColors</li>
          <li>save_as_excel</li>
        </ul>
        </p>
    ';
    $form['panels']['panel1-wrapper'] = [
      '#type' => 'markup',
      '#prefix' => '<div class="panel" id="panel1-wrapper">'.bm_main_panel_title_and_help('Blue Marloc Excel API module', $helpText).
      '<p><i>Example use of Excel files. The file is imported into the import folder.
        The Excel data can then be used by getting the information using:  bm_main_get_last_uploaded_fid()
        With features such color coding, row selection and limit.</i>
        </p>'

      ,
      '#suffix' => '</div>',
    ];

    $sheetNames = [];
    $header = [];
    $rows = [];
    if ($file_path) {
      try {
        $xlsx = new BMXLSX($file_path);
        $sheetNames = $xlsx->getSheetNames();
        $header = $xlsx->getRowHeader($sheet);
        $rows = $xlsx->getRowsKeyed($sheet, $limit);
        $colors=$xlsx->getColors($sheet,1,$limit,0);
        // set row colors
        foreach($rows as $n=>&$row){
          if(isset($colors[$n])) {
            $rows[$n-1]['#attributes']['style'] = $colors[$n];
          }
        }

      } catch (Exception $e) {
        Drupal::messenger()->addError($e->getMessage());
      }
    }

    $form['panels']['panel1-wrapper']['panel1']=[
     '#markup' => '<p>Current cached Excel:  ' . ($file_path ?: 'Upload an Excel first') . '</p>',
    ];

    $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper'] = [
      '#prefix' => Markup::create('<div class="sheet-selection-wrapper" >'),
      '#suffix' => '</div>',
    ];

    if ($file_path) {

      // Sheet selector.
      $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['sheets'] = [
        '#type' => 'select',
        '#title' => $this->t('Select one of the sheets'),
        '#options' => $sheetNames,
        '#default_value' => $sheet,
        '#ajax' => [
          'callback' => '::ajax_submitForm',
          'event' => 'change',
        ]
      ];

      // Limit selector.
      $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['limit'] = [
        '#type' => 'select',
        '#title' => $this->t('Limit'),
        '#options' => [
          10 => '10',
          100 => '100',
          500 => '500',
          1000 => '1000',
          2000 => '2000',
          5000 => '5000',
        ],
        '#default_value' => $limit,
        '#ajax' => [
          'callback' => '::ajax_submitForm',
          'event' => 'change',
        ]
      ];

/* not needed as sheet and limit are ajaxified
      // Submit button for AJAX.
      $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['display_sheet'] = [
        '#type' => 'submit',
        '#value' => $this->t('Display'),
        '#attributes'=>['class'=>['button-display-sheet','xlsx-upload-form-ajax-callback-trigger']],
        '#ajax' => [
          'callback' => '::ajax_submitForm',
        ],
      ];
*/
    }
    // DIALOG UPLOAD EXCEL FILE
    // loads the bm_main form in a modal form
    //
    $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['upload-excel'] = [
      '#type' => 'link',
      '#title' => $file_path ? 'Change Excel':'Upload Excel',
      '#url' => Url::fromRoute('bm_main.xlsx_upload_form'),
      '#attributes' => ['class' => ['button','upload-excel-dialog']],
      '#ajax' => [
        'dialogType' => 'modal',
        'dialog' => ['height' => 400, 'width' => 500],
      ],
    ];

    $form['panels']['panel2'] = [
      '#prefix' => '<div id="panel2-wrapper">'.bm_main_panel_title_and_help('Excel API', '').'',
      '#suffix' => '</div>',
    ];

    if ($file_path) {
      // Table display.
      $form['panels']['panel2']['excel_table'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $rows,
        '#empty' => $this->t('No content available.'),
        '#multiple' => TRUE,
      ];
    }

    return parent::buildForm($form, $form_state);
  }


  /**
   * Handles the AJAX form submission.
   * Remember the calling order on ajax submit is:
   * * ValidateForm
   * * SubmitForm
   * * buildForm
   * * AjaxSubmitForm
   *
   * @param array              $form
   *   The form structure.
   * @param FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return AjaxResponse
   *   The AJAX response containing the commands to execute.
   */
  public
  function ajax_submitForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    Drupal::messenger()->addMessage(__FUNCTION__);
    // Ensure the form values are updated during AJAX submission, this makes getValue have the input value.
    $form_state->setRebuild(TRUE);

    $response = new AjaxResponse();
    $response->addCommand(new HtmlCommand('#panel1-wrapper', $form['panels']['panel1-wrapper']['panel1']));
    $response->addCommand(new HtmlCommand('#panel2-wrapper', $form['panels']['panel2']));
    bm_main_response_messages($response);
    return $response;
  }


}
