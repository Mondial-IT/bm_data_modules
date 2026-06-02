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
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\file\Entity\File;
use Exception;

/**
 * The provided code defines a class for a Drupal custom form, specifically designed for uploading an Excel file and
 * selecting a sheet along with a limit for data to display. Below are some insights about its key features and
 * potential improvements:
 *
 * Key Features:
 * Excel File Handling:
 *
 * Uses BMXLSX to read and handle Excel files, providing sheet names and data rows.
 * Dynamically generates a tableselect element to display Excel data.
 * Two Panels (panel1 and panel2):
 *
 * Panel 1: Allows the user to upload or change the Excel file and select a sheet and limit.
 * Panel 2: Displays the data from the selected sheet using a tableselect element.
 * AJAX Integration:
 *
 * Updates panels dynamically without refreshing the entire page.
 * Utilizes ajax_submitForm to rebuild specific parts of the form.
 * Configuration Management:
 *
 * Saves and retrieves sheet and limit preferences using custom BMXLSX::get_config_sheet_selection_and_limit() and
 * BMXLSX::save_config_sheet_selection_and_limit() methods. Custom Styling:
 *
 * Inline CSS is added to modify the appearance of the modal and buttons.
 * Potent
 */
class ExcelUploadAndSheetSelectForm extends FormBase {

  public function __construct(private readonly ?EntityTypeManagerInterface $entityTypeManager = NULL) {

  }

  /**
   * {}
   */
  public function getFormId(): string {
    return 'bm_xlsx_upload_and_sheet_select_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create($container) {
    return new static(
      $container->get('entity_type.manager')
    );
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

    // Save values in the form state.
    $input = $form_state->getUserInput();

    $form_state->setValue('sheets', $input['sheets'] ?? 0);
    $form_state->setValue('limit', $input['limit'] ?? 10);
    $media_library_available = $this->entityTypeManager
      && $this->entityTypeManager->getDefinition('media', FALSE)
      && Drupal::moduleHandler()->moduleExists('media_library');
    $excel_source = $input['excel_source'] ?? ($media_library_available ? 'media' : 'upload');
    if (!$media_library_available) {
      $excel_source = 'upload';
    }
    $form_state->setValue('excel_source', $excel_source);

    $selected_media_id = NULL;
    if ($excel_source === 'media') {
      $selected_media_ids = $input['excel_media'] ?? [];
      $selected_media_id = is_array($selected_media_ids) ? reset($selected_media_ids) : NULL;
      $form_state->setValue('excel_media', $selected_media_id ? [$selected_media_id] : []);
    }
    else {
      $form_state->setValue('excel_media', []);
    }

    $fid = NULL;
    $file_path = NULL;
    if ($excel_source === 'media' && $selected_media_id && $this->entityTypeManager) {
      $media = $this->entityTypeManager->getStorage('media')->load($selected_media_id);
      if ($media instanceof MediaInterface && $media->hasField('field_media_file') && !$media->get('field_media_file')->isEmpty()) {
        $file = $media->get('field_media_file')->entity;
        if ($file) {
          $fid = (int) $file->id();
          $file_path = $file->getFileUri();
        }
      }
    }

    $file = NULL;
    if ($excel_source === 'upload' && !$fid) {
      $uploads = $form_state->getValue('excel_upload') ?? [];
      $upload_fid = is_array($uploads) ? reset($uploads) : NULL;
      if ($upload_fid) {
        $file = File::load($upload_fid);
        if ($file) {
          $file->setPermanent();
          $file->save();
          $fid = (int) $file->id();
          $file_path = $file->getFileUri();
        }
      }
    }

    if ($excel_source === 'upload' && $fid && $this->entityTypeManager) {
      if (!$file) {
        $file = File::load($fid);
      }
      $media_type_storage = $this->entityTypeManager->getStorage('media_type');
      $media_storage = $this->entityTypeManager->getStorage('media');
      if ($media_type_storage->load('bm_xlsx_excel')) {
        $existing = $media_storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('bundle', 'bm_xlsx_excel')
          ->condition('field_media_file.target_id', $fid)
          ->range(0, 1)
          ->execute();
        $existing_id = $existing ? reset($existing) : NULL;
        if ($existing_id) {
          $selected_media_id = (int) $existing_id;
        }
        else {
          $media = $media_storage->create([
            'bundle' => 'bm_xlsx_excel',
            'name' => $file ? $file->getFilename() : $this->t('Excel upload'),
            'status' => 1,
            'field_media_file' => [
              'target_id' => $fid,
            ],
          ]);
          $media->save();
          $selected_media_id = (int) $media->id();
        }
      }
    }

    BMXLSX::cache_config_sheet_selection_and_limit($input['sheets'], $input['limit'], $fid, $file_path, $selected_media_id ?: NULL);

    // Mark the form for rebuild.
    $form_state->setRebuild(TRUE);


  }

  /**
   * {@inheritdoc}
   * @noinspection PhpMultipleClassDeclarationsInspection
   * @throws Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][]= 'bm_xlsx/bm_xlsx';
    $form['#attached']['library'][] = 'bm_tooltip/tooltip';
    $cached = BMXLSX::get_config_sheet_selection_and_limit();

    $media_library_available = $this->entityTypeManager
      && $this->entityTypeManager->getDefinition('media', FALSE)
      && Drupal::moduleHandler()->moduleExists('media_library');

    $selected_media_value = $form_state->getValue('excel_media') ?? NULL;
    $selected_media_id = is_array($selected_media_value) ? reset($selected_media_value) : $selected_media_value;
    if (!$selected_media_id) {
      $selected_media_id = $cached->media_id ?? NULL;
    }

    $upload_value = $form_state->getValue('excel_upload');
    if ($upload_value === NULL) {
      $user_input = $form_state->getUserInput();
      $upload_value = $user_input['excel_upload'] ?? NULL;
    }
    $upload_fid = is_array($upload_value) ? reset($upload_value) : $upload_value;

    $excel_source = $form_state->getValue('excel_source');
    if (!$excel_source) {
      $excel_source = $media_library_available ? 'media' : 'upload';
    }
    if (!$media_library_available) {
      $excel_source = 'upload';
    }

    $media_file_path = NULL;
    $file_entity = NULL;
    $file_path = NULL;
    $file_name = NULL;
    $resolved_fid = NULL;
    $resolved_media_id = NULL;

    if ($excel_source === 'media' && $selected_media_id) {
      $resolved_media_id = (int) $selected_media_id;
      $media = $this->entityTypeManager?->getStorage('media')->load($selected_media_id);
      if ($media instanceof MediaInterface && $media->hasField('field_media_file') && !$media->get('field_media_file')->isEmpty()) {
        $file_entity = $media->get('field_media_file')->entity;
        $media_file_path = $file_entity?->getFileUri();
        $resolved_fid = $file_entity?->id();
      }
    }

    if ($excel_source === 'upload') {
      $resolved_fid = $upload_fid ?: ($cached->fid ?? NULL);
      if ($resolved_fid) {
        $file_entity = File::load($resolved_fid);
        $file_path = $file_entity?->getFileUri();
      }
    }
    if (!$resolved_media_id && !empty($cached->media_id)) {
      $resolved_media_id = (int) $cached->media_id;
    }

    // Retrieve saved file path (legacy).
    $file_path = $media_file_path ?: $file_path ?: $cached->file_path ?: bm_xlsx_get_last_uploaded_file_path();
    $file_name = $file_entity?->getFilename();
    $last_upload_fid = $upload_fid ?: ($cached->fid ?? bm_xlsx_get_last_uploaded_fid());
    $last_upload_file = $last_upload_fid ? File::load($last_upload_fid) : NULL;

    // Retrieve values from the form state or provide defaults.
    $sheet = $form_state->getValue('sheets');
    if ($sheet === NULL) {
      $sheet = $cached->sheet ?? 0;
    }
    $limit = $form_state->getValue('limit');
    if ($limit === NULL) {
      $limit = $cached->limit ?? 10;
    }

    $form['panels'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bm-xlsx-upload'],
      ],
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
      '#type' => 'container',
      '#attributes' => [
        'id' => 'panel1-wrapper',
        'class' => ['bm-xlsx-upload__panel'],
      ],
    ];

    $form['panels']['panel1-wrapper']['panel1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bm-xlsx-upload__panel-inner'],
      ],
      'header' => [
        '#markup' => '<div class="bm-xlsx-upload__header">' . bm_main_panel_title_and_help('Blue Marloc Excel API module', $helpText) .
          '<p><i>Example use of Excel files. The file is imported into the import folder.
                The Excel data can then be used by getting the information using:  bm_xlsx_get_last_uploaded_fid()
                With features such color coding, row selection and limit.</i>
            </p></div>',
      ],
    ];

    $sheetNames = [];
    $header = [];
    $rows = [];
    $sheet_summary_rows = [];
    if ($file_path) {
      try {
        $xlsx = new BMXLSX($file_path);
        $sheetNames = $xlsx->getSheetNames();
        if ($sheet !== NULL && !array_key_exists($sheet, $sheetNames)) {
          $sheet = 0;
        }
        $header = $xlsx->getRowHeader($sheet);
        $rows = $xlsx->getRowsKeyed($sheet, $limit);
        $colors=$xlsx->getColors($sheet,1,$limit,0);
        // set row colors
        foreach($rows as $n=>&$row){
          if(isset($colors[$n])) {
            $rows[$n-1]['#attributes']['style'] = $colors[$n];
          }
        }
        $simple_xlsx = $xlsx->getXLSX();
        if ($simple_xlsx) {
          foreach ($sheetNames as $index => $sheet_name) {
            $dimension = $simple_xlsx->dimension((int) $index);
            $row_total = $dimension[1] ?? 0;
            $sheet_summary_rows[] = [
              'data' => [
                $sheet_name,
                $row_total,
              ],
            ];
          }
        }

      } catch (Exception $e) {
        Drupal::messenger()->addError($e->getMessage());
      }
    }

    $current_label = $file_name ?: $file_path;
    $current_status = $current_label
      ? Html::escape((string) $current_label)
      : Html::escape((string) $this->t('Upload or pick a Media item'));
    $form['panels']['panel1-wrapper']['panel1']['status'] = [
      '#markup' => '<p class="bm-xlsx-upload__status">' . $this->t('Current Excel:') . ' ' . $current_status . '</p>',
    ];
    if ($last_upload_file) {
      $last_upload_label = Html::escape($last_upload_file->getFilename());
      $last_upload_time = Drupal::service('date.formatter')->format($last_upload_file->getChangedTime(), 'short');
      $form['panels']['panel1-wrapper']['panel1']['last_upload'] = [
        '#markup' => '<p class="bm-xlsx-upload__status bm-xlsx-upload__status--muted">' . $this->t('Last upload:') . ' ' . $last_upload_label . ' (' . Html::escape($last_upload_time) . ')</p>',
      ];
    }

    $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bm-xlsx-upload__controls'],
      ],
    ];

    if ($media_library_available) {
      $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['excel_source'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Excel source'),
        '#options' => [
          'media' => $this->t('Media library'),
          'upload' => $this->t('Direct upload'),
        ],
        '#default_value' => $excel_source,
        '#attributes' => [
          'class' => ['bm-xlsx-upload__source-toggle'],
        ],
        '#ajax' => [
          'callback' => '::ajax_submitForm',
          'event' => 'change',
        ],
      ];
    }

    // Media selection first-class when media is available.
    if ($media_library_available) {
      $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['excel_media_details'] = [
        '#type' => 'details',
        '#title' => $this->t('Media library'),
        '#open' => $excel_source === 'media',
        '#states' => [
          'visible' => [
            ':input[name="excel_source"]' => ['value' => 'media'],
          ],
        ],
      ];
      $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['excel_media_details']['excel_media'] = [
        '#type' => 'media_library',
        '#allowed_bundles' => ['bm_xlsx_excel'],
        '#title' => $this->t('Choose Excel from media library'),
        '#description' => $this->t('Choose an existing Excel media item or upload a new one.'),
        '#default_value' => $selected_media_id ? [$selected_media_id] : [],
        '#cardinality' => 1,
        '#required' => FALSE,
        '#ajax' => [
          'callback' => '::ajax_submitForm',
          'event' => 'change',
        ],
      ];
    }

    // Fallback direct upload.
    $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['excel_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Excel (direct)'),
      '#upload_location' => 'public://bm_xlsx',
      '#upload_validators' => [
        'FileExtension' => ['xlsx'],
      ],
      '#default_value' => $upload_fid ?: (!empty($cached->fid) ? [$cached->fid] : NULL),
      '#description' => $this->t('Use this if you are not selecting a Media Library item.'),
      '#required' => FALSE,
      '#states' => $media_library_available ? [
        'visible' => [
          ':input[name="excel_source"]' => ['value' => 'upload'],
        ],
      ] : [],
      '#ajax' => [
        'callback' => '::ajax_submitForm',
        'event' => 'change',
      ],
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
    }

    if ($file_path) {
      $file_url = Drupal::service('file_url_generator')->generateAbsoluteString($file_path);
      $form['panels']['panel1-wrapper']['panel1']['sheet-selection-wrapper']['view-excel'] = [
        '#type' => 'link',
        '#title' => $this->t('View current Excel'),
        '#url' => Url::fromUri($file_url),
        '#attributes' => [
          'class' => ['button', 'bm-xlsx-upload__button', 'bm-xlsx-upload__button--secondary'],
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
        ],
      ];
    }

    if ($file_path) {
      $source_label = $excel_source === 'media' ? $this->t('Media library') : $this->t('Direct upload');
      $meta_markup = '<dl class="bm-xlsx-upload__meta">';
      $meta_markup .= '<div><dt>' . $this->t('Source') . '</dt><dd>' . Html::escape((string) $source_label) . '</dd></div>';
      if ($resolved_media_id) {
        $meta_markup .= '<div><dt>' . $this->t('Media ID') . '</dt><dd>' . Html::escape((string) $resolved_media_id) . '</dd></div>';
      }
      if ($resolved_fid) {
        $meta_markup .= '<div><dt>' . $this->t('File ID') . '</dt><dd>' . Html::escape((string) $resolved_fid) . '</dd></div>';
      }
      if ($file_name) {
        $meta_markup .= '<div><dt>' . $this->t('Filename') . '</dt><dd>' . Html::escape($file_name) . '</dd></div>';
      }
      $meta_markup .= '</dl>';

      $form['panels']['panel1-wrapper']['panel1']['file_summary'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['bm-xlsx-upload__summary'],
        ],
        'meta' => [
          '#markup' => Markup::create($meta_markup),
        ],
        'sheets' => [
          '#type' => 'table',
          '#header' => [
            $this->t('Sheet'),
            $this->t('Rows'),
          ],
          '#rows' => $sheet_summary_rows,
          '#empty' => $this->t('No sheet metadata available.'),
          '#attributes' => [
            'class' => ['bm-xlsx-upload__sheet-table'],
          ],
        ],
        'note' => [
          '#markup' => '<p class="bm-xlsx-upload__note">' . $this->t('Row counts are derived from worksheet dimensions and include the header row.') . '</p>',
        ],
      ];
    }

    $form['panels']['panel2'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'panel2-wrapper',
        'class' => ['bm-xlsx-upload__panel', 'bm-xlsx-upload__panel--preview'],
      ],
      'inner' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['bm-xlsx-upload__panel-inner'],
        ],
        'title' => [
          '#markup' => '<div class="bm-xlsx-upload__header">' . bm_main_panel_title_and_help('Excel API', '') . '</div>',
        ],
      ],
    ];

    if ($file_path) {
      // Table display.
      $form['panels']['panel2']['inner']['excel_table'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $rows,
        '#empty' => $this->t('No content available.'),
        '#multiple' => TRUE,
      ];
    }

    return $form;
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
  //  bm_main_response_messages($response);
    return $response;
  }


}
