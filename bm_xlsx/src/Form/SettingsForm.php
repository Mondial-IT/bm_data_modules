<?php
/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:11
 */ /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Drupal\bm_xlsx\Form;


use Drupal;
use Drupal\bm_tooltip\Service\TooltipService;
use Drupal\bm_xlsx\BMXLSX\BMXLSX;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\file\Entity\File;
use Drupal\media\MediaInterface;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function bm_main_panel_title_and_help;

/**
 * bm_xlsx/SettingsForm.
 *
 * ## Purpose
 * - Provide an AJAX-first admin experience for uploading Excel files.
 * - Let administrators pick a sheet + row limit and preview styled rows.
 * - Offer a synchronisation action for legacy uploads.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * ## Constructor
   * Wires config services plus the bm_tooltip helper service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected TooltipService $tooltipService,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * ## Service container factory
   * Pulls required services for config and tooltip rendering.
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('bm_tooltip.tooltip_service'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * ## Form ID
   * Returns the unique form ID for the settings screen.
   */
  public function getFormId(): string {
    return 'bm_xlsx_settings_form';
  }
  /**
   * {@inheritdoc}
   *
   * ## Editable config
   * Declares the configuration object this form writes to.
   */
  protected function getEditableConfigNames(): array {
    return ['bm_xlsx.settings'];
  }

  /**
   * {@inheritdoc}
   *
   * ## Validation
   * Keeps validation AJAX-friendly while surfacing a debug message.
   */
  public
  function validateForm(array &$form, FormStateInterface $form_state): void {
    Drupal::messenger()->addMessage(__FUNCTION__);

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * ## Submit (main)
   * Persists the currently selected file, sheet, and row limit.
   */
  public
  function submitForm(array &$form, FormStateInterface $form_state): void {
    Drupal::messenger()->addMessage(__FUNCTION__);
    // Save values in the form state.
    $input = $form_state->getUserInput();

    $form_state->setValue('sheets', $input['sheets'] ?? 0);
    $form_state->setValue('limit', $input['limit'] ?? 10);

    // Normalize media selection from the widget.
    $selected_media_ids = $input['excel_media'] ?? [];
    $selected_media_id = is_array($selected_media_ids) ? reset($selected_media_ids) : NULL;
    $form_state->setValue('excel_media', $selected_media_id ? [$selected_media_id] : []);

    $fid = NULL;
    $file_path = NULL;
    if ($selected_media_id) {
      // Resolve file details from the chosen Media item.
      $media = \Drupal::entityTypeManager()->getStorage('media')->load($selected_media_id);
      if ($media instanceof MediaInterface && $media->hasField('field_media_file') && !$media->get('field_media_file')->isEmpty()) {
        $file = $media->get('field_media_file')->entity;
        if ($file) {
          $fid = (int) $file->id();
          $file_path = $file->getFileUri();
        }
      }
    }

    if (!$fid) {
      // Fall back to direct uploads when no Media item is selected.
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

    BMXLSX::cache_config_sheet_selection_and_limit($input['sheets'], $input['limit'], $fid, $file_path, $selected_media_id ?: NULL);

    // Mark the form for rebuild.
    $form_state->setRebuild(TRUE);

    parent::submitForm($form, $form_state);
  }

  /**
   * ## Submit: Synchronize Excel files
   * Runs the migration from legacy uploads into Media items.
   */
  public function submit_sync_actions(array &$form, FormStateInterface $form_state): void {
    $created = bm_xlsx_import_excel_files_to_media();
    $message = $created
      ? $this->t('Synchronized @count Excel file(s) into the Media Library.', ['@count' => $created])
      : $this->t('No Excel files required synchronization.');
    Drupal::logger('bm_xlsx')->notice('Manual Excel synchronization run. @message', ['@message' => $message]);
    Drupal::messenger()->addStatus($message);
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   *
   * ## Build form
   * Assembles AJAX-first panels for upload, preview, and synchronization logs.
   *
   * @noinspection PhpMultipleClassDeclarationsInspection
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    Drupal::messenger()->addMessage(__FUNCTION__);
    $form['#attached']['library'][]= 'bm_xlsx/bm_xlsx';
    $form['#attached']['library'][] = 'bm_tooltip/tooltip';

    // Pull cached selections to keep the form state in sync on AJAX rebuilds.
    $cached = BMXLSX::get_config_sheet_selection_and_limit();

    // Media selection takes precedence over legacy file selection.
    $selected_media_value = $form_state->getValue('excel_media') ?? NULL;
    $selected_media_id = is_array($selected_media_value) ? reset($selected_media_value) : $selected_media_value;
    if (!$selected_media_id) {
      $selected_media_id = $cached->media_id ?? NULL;
    }
    $media_file_path = NULL;
    if ($selected_media_id) {
      $media = \Drupal::entityTypeManager()->getStorage('media')->load($selected_media_id);
      if ($media instanceof MediaInterface && $media->hasField('field_media_file') && !$media->get('field_media_file')->isEmpty()) {
        $media_file = $media->get('field_media_file')->entity;
        $media_file_path = $media_file?->getFileUri();
      }
    }

    // Retrieve saved file path (legacy fallback).
    $file_path = $media_file_path ?: ($cached->file_path ?? bm_xlsx_get_last_uploaded_file_path());

    // Retrieve values from the form state or provide defaults.
    $sheet = $form_state->getValue('sheets', 0); // Default sheet is 0.
    $limit = $form_state->getValue('limit', 10); // Default limit is 10.

    // Build data previews from the current file, when available.
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
        // Apply row colors when style metadata is available.
        foreach($rows as $n=>&$row){
          if(isset($colors[$n])) {
            $rows[$n-1]['#attributes']['style'] = $colors[$n];
          }
        }

      } catch (Exception $e) {
        Drupal::messenger()->addError($e->getMessage());
      }
    }

    // First-level panels are composed through panel_* helper methods.
    $form['panels'] = $this->panel_layout($file_path, $sheetNames, $header, $rows, $selected_media_id, $cached, $sheet, $limit);

    $log_rows = [];
    if (Drupal::moduleHandler()->moduleExists('dblog')) {
      $database = Drupal::database();
      if ($database->schema()->tableExists('watchdog')) {
        $query = $database->select('watchdog', 'w')
          ->fields('w', ['timestamp', 'severity', 'message', 'variables'])
          ->condition('type', 'bm_xlsx')
          ->orderBy('timestamp', 'DESC')
          ->range(0, 10);
        $results = $query->execute();
        $levels = RfcLogLevel::getLevels();
        $parser = Drupal::service('logger.log_message_parser');
        $formatter = Drupal::service('date.formatter');
        foreach ($results as $entry) {
          $variables = [];
          if (!empty($entry->variables)) {
            $variables = unserialize($entry->variables) ?: [];
          }
          $message = (string) $entry->message;
          $variables = $parser->parseMessagePlaceholders($message, $variables);
          $log_rows[] = [
            $formatter->format((int) $entry->timestamp, 'short'),
            $levels[$entry->severity] ?? (string) $entry->severity,
            (string) new FormattableMarkup($message, $variables),
          ];
        }
      }
    }
    $form['sync_section'] = $this->panel_sync_section($log_rows);

    return parent::buildForm($form, $form_state);
  }


  /**
   * ## AJAX refresh callback
   * Rebuilds the upload panel, preview panel, and log panel.
   *
   * ### Call order
   * - ValidateForm
   * - SubmitForm
   * - buildForm
   * - AjaxSubmitForm
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
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
    $response->addCommand(new HtmlCommand('#bm-xlsx-sync-section', $form['sync_section']));
    bm_main_response_messages($response);
    return $response;
  }

  /**
   * ## Panel: Layout wrapper
   * Provides the two-column panel layout for upload + preview.
   */
  private function panel_layout(
    ?string $file_path,
    array $sheetNames,
    array $header,
    array $rows,
    ?int $selected_media_id,
    object $cached,
    int $sheet,
    int $limit,
  ): array {
    // Layout container keeps upload + preview side-by-side.
    $panels = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bm-xlsx-upload'],
      ],
    ];

    // Layout header uses the shared panel title helper for consistency.
    $panels['layout_header'] = [
      '#markup' => '<div class="bm-xlsx-upload__layout-header">' . bm_main_panel_title_and_help(
        $this->t('Excel upload workspace'),
        $this->t('Upload, preview, and synchronize Excel data from one screen.')
      ) . '</div>',
    ];

    // Upload panel (selection + sync actions).
    $panels['panel1-wrapper'] = $this->panel_upload($file_path, $sheetNames, $selected_media_id, $cached, $sheet, $limit);
    // Preview panel (tableselect when file exists).
    $panels['panel2'] = $this->panel_preview($file_path, $header, $rows);

    return $panels;
  }

  /**
   * ## Panel: Upload and selection
   * Renders the upload controls and sheet/limit selectors.
   */
  private function panel_upload(
    ?string $file_path,
    array $sheetNames,
    ?int $selected_media_id,
    object $cached,
    int $sheet,
    int $limit,
  ): array {
    // Tooltip-enabled header text for the upload panel.
    $helpText = '<i>Example of uploading an Excel workbook and how it can be used in the Blue Marloc platform.</i>
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

    $panel = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'panel1-wrapper',
        'class' => ['bm-xlsx-upload__panel'],
      ],
    ];
    // Inner container for header + controls.
    $panel['panel1'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bm-xlsx-upload__panel-inner'],
      ],
      'header' => [
        '#markup' => '<div class="bm-xlsx-upload__header">' . bm_main_panel_title_and_help('Blue Marloc Excel API module', $helpText) . '<p><i>Example use of Excel files. The file is imported into the import folder.
        The Excel data can then be used by getting the information using:  bm_xlsx_get_last_uploaded_fid()
        With features such color coding, row selection and limit.</i>
        </p></div>',
      ],
    ];

    $panel['panel1']['status'] = [
      '#markup' => '<p class="bm-xlsx-upload__status">Current Excel:  ' . ($file_path ?: 'Upload or pick a Media item') . ' ' . $this->tooltipService->buildIcon((string) $this->t('The active file drives the sheet list and preview table.'), [
        'theme' => 'light',
        'position' => 'right',
      ]) . '</p>',
    ];

    // Wrapper for form controls that trigger AJAX rebuilds.
    $panel['panel1']['sheet-selection-wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bm-xlsx-upload__controls'],
      ],
    ];

    if (\Drupal::moduleHandler()->moduleExists('media_library')) {
      // Media Library picker keeps uploads managed and reusable.
      $panel['panel1']['sheet-selection-wrapper']['excel_media'] = [
        '#type' => 'media_library',
        '#allowed_bundles' => ['bm_xlsx_excel'],
        '#title' => $this->t('Select Excel from media library'),
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

    // Direct upload supports quick uploads and legacy flows.
    $panel['panel1']['sheet-selection-wrapper']['excel_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload Excel (direct)'),
      '#upload_location' => 'public://bm_xlsx',
      '#upload_validators' => [
        'FileExtension' => ['xlsx'],
      ],
      '#default_value' => !empty($selected_media_id) ? NULL : (!empty($cached->fid) ? [$cached->fid] : NULL),
      '#description' => $this->t('Upload an Excel file if you prefer not to pick from the media library.'),
      '#required' => FALSE,
      '#ajax' => [
        'callback' => '::ajax_submitForm',
        'event' => 'change',
      ],
    ];

    if ($file_path) {
      // Only show sheet/limit selection when a file is available.
      $panel['panel1']['sheet-selection-wrapper']['sheets'] = [
        '#type' => 'select',
        '#title' => $this->t('Select one of the sheets'),
        '#options' => $sheetNames,
        '#default_value' => $sheet,
        '#ajax' => [
          'callback' => '::ajax_submitForm',
          'event' => 'change',
        ],
      ];

      $panel['panel1']['sheet-selection-wrapper']['limit'] = [
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
        ],
      ];

      // Provide a direct link to the active workbook.
      $panel['panel1']['sheet-selection-wrapper']['view-excel'] = [
        '#type' => 'link',
        '#title' => $this->t('View current Excel'),
        '#url' => Url::fromUri(file_create_url($file_path)),
        '#attributes' => [
          'class' => ['button', 'bm-xlsx-upload__button', 'bm-xlsx-upload__button--secondary'],
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
        ],
      ];
    }

    return $panel;
  }

  /**
   * ## Panel: Preview
   * Shows the table preview when a sheet is available.
   */
  private function panel_preview(?string $file_path, array $header, array $rows): array {
    // Preview panel wrapper with a tooltip header.
    $panel = [
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
      // Render the table when a workbook is selected.
      $panel['inner']['excel_table'] = [
        '#type' => 'tableselect',
        '#header' => $header,
        '#options' => $rows,
        '#empty' => $this->t('No content available.'),
        '#multiple' => TRUE,
      ];
    }

    return $panel;
  }

  /**
   * ## Panel: Synchronize section
   * Groups the manual sync action and log in one container.
   */
  private function panel_sync_section(array $log_rows): array {
    // Wrap sync actions and log together for a single AJAX refresh.
    return [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'bm-xlsx-sync-section',
        'class' => ['bm-xlsx-sync-section'],
      ],
      'sync_actions' => $this->panel_sync_actions(),
      'sync_log' => $this->panel_sync_log($log_rows),
    ];
  }

  /**
   * ## Panel: Synchronization log
   * Displays the latest synchronization entries from watchdog.
   */
  private function panel_sync_log(array $log_rows): array {
    // Collapsible log panel for recent synchronization actions.
    $panel = [
      '#type' => 'details',
      '#title' => bm_main_panel_title_and_help(
        $this->t('Excel synchronisation log'),
        $this->t('Tracks recent synchronization runs for Excel files.')
      ),
      '#open' => FALSE,
      '#attributes' => [
        'id' => 'bm-xlsx-sync-log',
      ],
    ];
    $panel['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Time'),
        $this->t('Severity'),
        $this->t('Message'),
      ],
      '#rows' => $log_rows,
      '#empty' => $this->t('No log entries found.'),
    ];

    return $panel;
  }

  /**
   * ## Panel: Synchronize actions
   * Groups the manual synchronization button in its own details fieldset.
   */
  private function panel_sync_actions(): array {
    // Dedicated details panel for the sync button.
    $panel = [
      '#type' => 'details',
      '#title' => bm_main_panel_title_and_help(
        $this->t('Synchronize'),
        $this->t('Create Media items for legacy Excel uploads.')
      ),
      '#open' => FALSE,
    ];

    // AJAX-only submit to keep the form responsive.
    $panel['synchronize_files'] = [
      '#type' => 'submit',
      '#value' => $this->t('Synchronize Excel files'),
      '#submit' => ['::submit_sync_actions'],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => '::ajax_submitForm',
      ],
      '#attributes' => [
        'class' => ['bm-xlsx-upload__button', 'bm-xlsx-upload__button--secondary'],
      ],
    ];

    return $panel;
  }

}
