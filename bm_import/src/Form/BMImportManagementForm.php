<?php /** @noinspection PhpPropertyOnlyWrittenInspection */
/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
*/

/** @noinspector PhpUnused */

namespace bmDataModules\bm_import\src\Form;

use Drupal;
use Drupal\bm_debug\BMDebugInfo\BMDebugInfo;
use bmDataModules\bm_import\src\BMImport\BMImport;
use bmDataModules\bm_import\src\BMImport\BMImportBatch;
use bmDataModules\bm_import\src\Service\BMImportInstanceStorage;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Exception;
use stdClass;


/**
 * Import a CSV file
 */
class BMImportManagementForm extends FormBase {


  // in older installation there is no HS working
  // set it to false here to fall back to an input field.
  const  USEHIERARCHICALSELECT = 1;

  private BMImport $BMImport;

  private bool $button_doImport;

  private bool $button_checkFile;

  private bool $preflight_check_ok = FALSE;

  private array $selectedCanvass;

  private bool $file_uploaded_and_excel_parsed;

  private bool $problems_solved;

  private ?string $filePath;


  /**
   * Set to true in submit on detect click batch import
   */
  private bool $button_doBatchImport;


  private mixed $canvassChain;

  private mixed $categoryChain;

  const BM_D_KEY = 'bm_import_form';

  // display flow of form
  const BM_D_TRACE = 'bm_import trace';


  static public function bm_d_callback($key, $BMDbgInfo): BMDebugInfo|false {

    if ($key == self::BM_D_KEY) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug import form.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    if ($key == self::BM_D_TRACE) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Trace functions called in import process.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }

    return FALSE;
  }

  public function getFormId(): string {

    return 'bm_import_management_form';
  }

  // simple message text opleukertje
  private function inColor($message): string {
    return '<span class="fgcolor-lightblue">' . $message . '</span>';
  }

  protected BMImportInstanceStorage $instanceStorage;

  /**
   * a service is defined to share the BMImport instance
   * once it is created.
   * Retrieve it with
   *  $bmImport = \Drupal::service('bm_import.manager');
   * or $this->instanceStorage
   */
  public function __construct() {
    $this->instanceStorage = \Drupal::service('bm_import.manager');
  }

  /**
   * # Complex form with multiple steps.
   * There are several panels in this multiform which are displayed depending on the progress of the import process.
   * #### States:
   * * condition empty($storage['filePath'])
   *    - STEP_INITIAL: display an `form_panel_file_upload`.
   *
   * * condition _triggering_element_value=Upload
   *    - STEP_EXCEL_UPLOADING: an excel is selected and the build form is running with 'Upload' as
   *    _triggering_element_value
   *    - sets $storage['fid'] with file fid
   *    - sets condition $storage['filePath']
   *
   * * on condition !empty($storage['filePath'])
   *    - (STEP_EXCEL_UPLOADED) not a real step, but from the moment we have a filePath
   *    - should display `csv_properties_panel`: 'filePath' and 'number of rows' as a result
   *    - displays button 'check file' in `buttons_panel`
   *
   * * condition _triggering_element_value='check file (no import)'
   *    - STEP_CHECK_FILE_CLICKED: button clicked
   *    - should display: `preflight checks panel` (things to do manually before import can start like adding fields,
   *    vocabularies, contenttypes)
   *    - should display: `field_defaults_panel`
   *    - should display: `mandatory fields panel`
   *    - should display: `excel_table_select_pane` with in the rows all the data from the csv
   *    - sets condition $storage['preflight is OK']
   *
   * * condition $storage['preflight is OK']
   *    - STEP_IMPORT_ENABLED: when preflight is green (no problems) it sets the state to this
   *    - displays button `import selected rows`
   *
   * * condition _triggering_element_value='import selected rows'
   *    - STEP_IMPORT_SELECTED_CLICKED: when button import selected rows is clicked
   *    - displays import result as messages
   *    - should display an update on `excel_table_select_pane`
   *
   * * condition _triggering_element_value='Remove'
   *    - STEP_EXCEL_REMOVED: when user removes the Excel, we undo and start at Initial
   *    - remove all from $storage
   *
   * @throws Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    bm_d('', '<div class="bgcolor-blue fgcolor-white">instantiating ImportManagementForm buildForm</div>');

    $time = microtime(TRUE);
    bm_d(self::BM_D_TRACE, '<div class="bgcolor-lightviolet">----------- BUILD FORM --------- </div>');

    $form['#attached']['library'][] = 'ziston/tippyjs';
    $form['#attached']['library'][] = 'ziston/bm_tippyjs';
    $form['#attached']['library'][] = 'bm_import/bm_import';
    $form['#attached']['library'][] = 'bm_hierarchical_select/bm_hierarchical_select';

    // connect to .module theme definition
    //with twig templates/bm-import-management-form.twig.html
    $form['#theme'] = 'bm_import_management_form';

    // controls panel visibility
    //    $panelVisible = new stdClass();
    //    $panelVisible->fileUpload = TRUE;
    //    $panelVisible->preflightCheck = TRUE;
    //    $panelVisible->problemSolving = TRUE;
    //    $panelVisible->mandatoryFields = TRUE;
    //    $panelVisible->buttons = TRUE;
    //    $panelVisible->tableSelectRows = TRUE;

    $this->canvassChain = NULL;
    $this->categoryChain = NULL;

    // $message = json_encode($form_state->getValue('file-upload')));
    $this->selectedCanvass = ['tid' => NULL, 'label' => NULL];
    $this->file_uploaded_and_excel_parsed = FALSE;
    $this->preflight_check_ok = FALSE; // pre-flight check OK.
    $this->button_doBatchImport ??= FALSE;

    $input = $form_state->getUserInput();

    $excelContent = [];
    $excelContent['header']['THERE IS NO DATA'] = 'there is no data';
    $excelContent['options'] = [['THERE IS NO DATA' => 'there is no data']];
    $excelContent['default_value'] = ['THERE IS NO DATA'];
    try {

      //      // load the YML file for this specific type of content
      //      if (isset($storage['filePath'])) {
      //        $message = ' buildForm filepath = ' . $storage['filePath'];
      //        bm_d(self::BM_D_KEY, $this->inColor($message));
      //        $this->BMImport->get_filePath() = $storage['filePath'];
      //      }
      //
      //      if (isset($storage['excelContent'])) {
      //        $excelContent = $storage['excelContent'];
      //        // results in displaying the pre-flight instructions/check
      //        $this->file_uploaded_and_csv_parsed = TRUE;
      //      }
      //
      //      // results in displaying the Import button
      //      if (isset($storage['preflight_check_ok'])) {
      //        $message = 'buildForm: preflight check is OK =' . $storage['preflight_check_ok'];
      //        bm_d(self::BM_D_KEY, $this->inColor($message));
      //        $this->preflight_check_ok = TRUE;
      //      }

      $language_id = $input['panel_select_language'] ?? 'en';

      // Create or retrieve the BMImport instance
      // $bmImport = \Drupal::service('bm_import.manager');
      /**
       * **Instantiate the import control class**
       * * Restores cached state of variables
       * * retrieves the Excel data in the BMImport->properties
       * * loads the `import.settings.yml` into `BMImport->type_settings_yml`
       * * Instantiates  `XLSX` and checks if `file`,  `sheet` of `limit` have changed
       * * and sets `BMImport->ExcelContent` with cache or new table data
       *
       *
       */
      $this->BMImport = new BMImport($input, $language_id);
      // Store the BMImport instance in the shared service
      $this->instanceStorage->setInstance($this->BMImport);

      // WHEN CLICKED IMPORT
      $this->button_doImport = FALSE;

      // WHEN CLICKED CHECK NO IMPORT
      $this->button_checkFile = FALSE;

      // HANDLE BUTTON CLICKS
      if (isset($input['_triggering_element_value'])) {
        //  $message = '$input[_triggering_element_value] = ' . $input['_triggering_element_value'];
        //  bm_d(self::BM_D_KEY,$this->inColor($message));

        switch ($input['_triggering_element_value']) {
          case 'Import selected rows':
            $this->act_on_import_selected_rows_button($input);
            break;
//          case 'Inspect':
//            bm_d('', 'Inspect button - find nodes matching with row');
//            $this->act_on_inspect_button_inspector_match_nids();
//            break;

          case 'Check file (no import)':
            $storage = $form_state->getStorage();

            $message = ' buildForm Clicked "check file no import"';
            bm_d(self::BM_D_KEY, $this->inColor($message));

            Drupal::messenger()->addStatus("Checking CSV file (does not import)");


            $this->button_checkFile = TRUE;
            // WHEN THERE IS AN UPLOADED FILE, USE IT TO FILL THE CONTENT WITH THE 'TEXTAREA'
            //$fid= $this->getFidsIntoStorage($form_state);

            if (!isset($this->BMImport->ExcelContent)) {
              $message = 'buildForm: there is nothing; nothing todo';
              Drupal::messenger()->addMessage($message);
              bm_d('', $this->inColor($message));
              break;
            }// nothing todo

            // runs conversion in BMImport
            $this->act_on_Excel_file_changed($excelContent);

            // note cache and storage are now a bit mixed
            // BMImport uses cache, settings uses storage as it is not BMImport class
            // which is a bit dual
            $filePath = $this->BMImport->get_filePath();
            $storage['excelContent'] = $excelContent;
            $storage['filePath'] = $filePath;
            $storage['fid'] = $fid ?? 0;
            $form_state->setStorage($storage);

            if (count($excelContent)) {
              $message = __METHOD__ . ' buildForm: Excel content is parsed and stored.';
              bm_d(self::BM_D_KEY, $this->inColor($message));
            }
            break;

          case 'Remove':
            Drupal::messenger()->addError(' buildForm: clicked "remove" BMImport caches cleared.');
            $storage = [];
            $this->BMImport->clear_BMImport_cache();
            break;

          default:
            // there can be many from the panels
        }
      }

      //
      // CREATE FILE UPLOAD PANEL
      // 250116 this is an independent Form, inserted in the bm-import-management-form.html.twig
      $this->form_panel_excel_upload($form);

      // PRE_FLIGHT CHECKS
      $this->form_panel_preflight_checks($form);

      // enable default setting for fields not in the Excel missing fields
      $this->form_panel_field_defaults($form);

      // set default CANVASS, CATEGORY and such
      $this->form_panel_mandatory_fields($form);

      // check duplicate listings
      // BMImport/BMImportInspector->table with match results of rows with existing nodes
      $this->form_panel_inspector($form);

      // alternative import with batch processing
      $this->form_panel_batch_processing($form);

      // import button
      $this->form_panel_download_example_excel($form);
      // import button
      $this->form_panel_buttons($form);

      // table with Excel data
      $this->form_panel_table_select_import_data($form);

      // used as staging to download using bm_main.js download function
      $form['download_export_area'] = [
        '#type' => 'markup',
        '#markup' => '<div id="download-export-area" class="xdisplaynone">
                        <h1>Excel download area</h1>
                        </div>',
      ];

         $form['tableselect_area'] = [
           '#type' => 'markup',
           '#markup' => '<div></div><div id="import-data" class="panel">(import data area)</div>',
         ];

      // STORE STATE FOR NEXT CYCLE
      $storage = $form_state->getStorage();
      $storage['excelContent'] = $excelContent;
      if ($this->file_uploaded_and_excel_parsed) {
        $storage['file_uploaded_and_csv_parsed'] = TRUE;
      }
      if ($this->preflight_check_ok) {
        $storage['preflight_check_ok'] = TRUE;
      }
      $form_state->setStorage($storage);

    } catch (Exception $e) {
      bm_d('', __LINE__, ' buildForm has caught Exception ' . $e->getMessage());
      $this->errorHandler($e);
    }

    $message = '<div class="bgcolor-lightviolet fgcolor-black">' . '------BUILD-FORM END-------------' . (microtime(TRUE) - $time) . ' s.</div>';
    bm_d(self::BM_D_TRACE, $message);

    // the html in the messages does not display, unless the messages are declared safe
    // this function reads the messages and makes them safe, which makes them display colors.
    $this->make_messages_html_save();

    return $form;//parent::buildForm($form, $form_state);
  }

  /**
   * @throws EntityStorageException
   */
  protected function createBMImportInstance($input, $language_id): BMImport {
    // Custom logic to create or load BMImport
    return new BMImport($input, $language_id);
  }
  /**
   * # errorHandler
   * #### display caught errors.
   *
   * @param $e
   *
   * @return void
   */
  private function errorHandler($e): void {
    $file = explode('/', $e->getFile());
    $file = array_pop($file);
    Drupal::messenger()
      ->addError(__LINE__ . ' Exception caught by BMImportManagementForm errorHandler :' . $e->getMessage() . ' ' . $file . ' line:' . $e->getLine());
    bm_d('', $e->getTrace(), 'trace', 'dsm');
  }

  /**
   * # act on Excel file changed
   * * when a file is uploaded,
   * * get the YML import definition,
   * * and read it into $ExcelContent.
   *
   * @param array $ExcelContent The parsed CSV file
   *
   * @return void
   * @throws Exception
   */
  private function act_on_Excel_file_changed(array &$ExcelContent): void {
    bm_d(self::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));
    // get the file, read it, process it
    $this->file_uploaded_and_excel_parsed = $this->BMImport->processUploadedExcel();
  }

  private function getFidsIntoStorage($form_state) {
    bm_d(self::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));


    $value = $form_state->getValue('file-upload');
    //dsm($value,__FUNCTION__);
    $fid = NULL;
    if (isset($value['fids'])) {
      $fid = $value['fids'];
      if (is_array($value['fids'])) {
        $fid = array_pop($value['fids']);
      }
    }
    else {
      if ($value) {
        $fid = array_pop($value);
      }
    }
    if ($fid) {
      $storage = $form_state->getStorage();
      $storage['fids'] = $fid;
      $form_state->setStorage($storage);

    }
    //$message = ' ' . __FUNCTION__ . ' retrieved fid:' . $fid);
    return $fid;
  }

  /**
   * # form_panel_excel_upload
   * * iframe
   * * sets its fid, in cache
   * * use the BMXLSX class to retrieve the data
   *
   * @throws Exception
   */
  private function form_panel_excel_upload(&$form): void {
    bm_d(self::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));
    $form['panel_excel_upload'] = [
      '#type' => 'fieldset',
      'form' => \Drupal::formBuilder()->getForm('bmDataModules\bm_import\src\Form\Panel\Panel_excel_upload'),
    ];
  }

  /**
   * #  preflight checks panel
   * * runs get_preflight_panel_form_content__todo_before_import
   *
   * **display Ready to start importing or "There are things to do before …"**
   * * instructing the user what to add to node, fields, paragraphs, taxonomies
   *
   * * $this->preflight_check_ok True/False
   *
   * @throws Exception
   */
  private function form_panel_preflight_checks(&$form): void {

    bm_d(self::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));
    $form['panel_preflight_checks'] = [
      '#type' => 'fieldset',
      'form' => \Drupal::formBuilder()->getForm('bmDataModules\bm_import\src\Form\Panel\Panel_preflight_checks'),
    ];

  }

  /**
   * #  Field Defaults panel
   * **display Ready to start importing or "There are things to do before …"**
   * * instructing the user what to add to node, fields, paragraphs, taxonomies
   *
   * * $this->preflight_check_ok True/False
   *
   * @throws Exception
   */
  private function form_panel_field_defaults(&$form): void {
    bm_d(self::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));
    $form['panel_field_defaults'] = [
      '#type' => 'fieldset',
      'form' => \Drupal::formBuilder()->getForm('bmDataModules\bm_import\src\Form\Panel\Panel_field_defaults'),
    ];

  }

  /**
   * #  form_panel_mandatory_fields
   * * panel select Canvass, Category and languageId
   *
   */
  private
  function form_panel_mandatory_fields(&$form): void {
    // Embed Subform 1
    $form['panel_mandatory_fields'] = [
      '#type' => 'fieldset',
      'form' => \Drupal::formBuilder()->getForm('bmDataModules\bm_import\src\Form\Panel\Panel_mandatory_fields'),
    ];
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
    $message = ' trace: ' . __FUNCTION__ . ' ' . time();
    bm_d(self::BM_D_KEY, $message);

    $form['panel_inspector'] = [
      '#type' => 'fieldset',
      'form' => \Drupal::formBuilder()->getForm('bmDataModules\bm_import\src\Form\Panel\Panel_inspector'),
    ];
  }

  /**
   * #### form_panel_batch_processing
   *
   * @param $form
   *
   * @return void
   */
  public function form_panel_batch_processing(&$form): void {
    bm_d(self::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));
    $form['panel_batch_processing'] = [
      '#type' => 'fieldset',
      'form' => \Drupal::formBuilder()->getForm('bmDataModules\bm_import\src\Form\Panel\Panel_batch_processing'),
    ];

  }

  /**
   * #### form_panel_download_example_excel
   *
   * @param $form
   *
   * @return void
   */
  public function form_panel_download_example_excel(&$form): void {
    bm_d(self::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));
    $form['panel_download_example_excel'] = [
      '#type' => 'fieldset',
      'form' => \Drupal::formBuilder()->getForm('bmDataModules\bm_import\src\Form\Panel\Panel_download_example_excel'),
    ];

  }

  /**
   * #### form_panel_buttons
   *
   * @param $form
   *
   * @return void
   */
  private function form_panel_buttons(&$form): void {
    bm_d(self::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));

    $title = 'Buttons panel';
    $helpText = '';
    $form['panel_buttons']['bts'] = [
      '#markup' => '<div id="panel-buttons" class="panel gap-20">' .
        bm_main_panel_title_and_help($title, $helpText) . '<p>&nbsp;</p>',
    ];


    // pre-flight gave OK.
    if (1 || $this->preflight_check_ok) {
      $form['panel_buttons']['create_nodes_for_selected_rows'] = [
        '#type' => 'submit',
        '#value' => t('Import selected rows'),
        '#ajax' => [
          'callback' => '::submit_AjaxCallback',
          'wrapper' => 'panel-results', // Replace with your tableselect element's wrapper ID.
        ],
      ];
    }
    $form['panel_buttons']['bte'] = ['#markup' => '</div>'];
  }

  /**
   * #### form_panel_table_select_import_data
   * * note the data is retrieved from Import->cache.
   *
   * @param $form
   *
   * @return void
   * @throws Exception
   */
  private function form_panel_table_select_import_data(&$form): void {
    $message = ' trace: ' . __FUNCTION__ . ' ' . time();
    bm_d(self::BM_D_TRACE, $this->inColor($message));

    $excelContent = $this->BMImport->BMImportXLSXhandler->get_excelContent();
    $excelContent['header'] ??= ['Nothing found'];


    $excelContent['options'] ??= [0 => 'Nothing found'];
    $excelContent['default_value'] ??= [];

    $form['import_data']['s'] = [
      '#markup' => '<div id="import-data" time="' . time() . '">',
    ];

    $form['import_data']['import-data-table-select'] = [
      '#type' => 'tableselect',
      '#header' => $excelContent['header'],
      '#options' => $excelContent['options'],
      '#default_value' => $excelContent['default_value'],
    ];

    $form['import_data']['e'] = [
      '#markup' => '</div>',
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // $message = __LINE__.' trace:'.__FUNCTION__);
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   * @throws Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    bm_d('', 'BMImportManagementForm - SubmitForm');
    bm_d('', __LINE__, __FUNCTION__, 'log');
    bm_d(self::BM_D_TRACE, traceColor('trace:' . ' ' . __FUNCTION__ . ' ' . time() . ' ----------- SUBMIT FORM ---------'));

    $startTime = microtime(TRUE);
    $skipGetFids = FALSE;

    $input = $form_state->getUserInput();
//    // note, usually we handle actions in the buildForm,
//    // however this button, is not ajax'd and handled here.
//    $te = $form_state->getTriggeringElement()['#id'];
//    if (isset($te) && $te === 'edit-create-nodes-for-selected-rows-via-batch') {
//      $tableSelect = $input['import-data-table-select'] ?? [];
//      $this->act_on_batchImport_button($tableSelect);
//    }

    if (isset($input['_triggering_element_value'])) {
      $te = $input['_triggering_element_value'];// name as in form array definition
      $skipGetFids = in_array($te, ['Start processing', 'Cancel processing', 'Pauze']);
    }

    if (!$skipGetFids) {
      // these are handled in buildForm with the signal we set here.
      $fid = $this->getFidsIntoStorage($form_state);
      if ($fid) {
        $form_state->setRebuild(TRUE);
      }
      $this->button_doImport = FALSE;
      $this->button_checkFile = FALSE;
      if (isset($input['_triggering_element_value'])) {
        switch ($input['_triggering_element_value']) {
          case 'Import selected rows':
            $this->button_doImport = TRUE;
            $form_state->setRebuild(TRUE);
            break;
          case 'Check file (no import)':
            $this->button_checkFile = TRUE;
            $form_state->setRebuild(TRUE);
            break;
          case 'Remove':
            Drupal::logger('BMImport form')->debug("submit remove detected");
            break;

          default:
            //
        }
      }
      $storage = $form_state->getStorage();
      $this->file_uploaded_and_excel_parsed = $storage['file_uploaded_and_csv_parsed'] ?? FALSE;
    }

    $form_state->setRebuild(TRUE);

    $message = 'trace-END:' . ' ' . __FUNCTION__ . ' ' . $this->duration($startTime);
    bm_d(self::BM_D_TRACE, traceColor($message));

  }

  private function duration($time): float {
    return round(microtime(TRUE) - $time, 2);
  }

  /**
   * #### set _triggering_element_value
   * This "submit" has no real purpose, other than setting the $input['_triggering_element_value'] in buildForm
   *
   *
   * @param array              $form
   * @param FormStateInterface $form_state
   *
   * @return AjaxResponse
   */
  public function submit_AjaxCallback(array $form, FormStateInterface $form_state): AjaxResponse {
    bm_d('', 'AJAXSUBMIT', microtime());

    bm_d(self::BM_D_TRACE, traceColor('trace: ' . __FUNCTION__ . ' ' . time()));

    $input = $form_state->getUserInput();

    Drupal::messenger()->addWarning($input['_triggering_element_value']);

    if (isset($input['_triggering_element_value'])) {
      switch ($input['_triggering_element_value']) {
        case 'remove':
          Drupal::logger('BMImport form')->debug('ajax submit remove detected');
          break;

        default:
          Drupal::logger('BMImport form')->debug('ajax submit remove detected' . $input['_triggering_element_value']);
          break;
      }
    }
    $ajax_response = new AjaxResponse();
    $this->submit_ajax_response__refresh_panels($ajax_response, $form, $input);

    $this->response_messages($ajax_response);

    bm_d('', __LINE__, '--- END --- ' . __FUNCTION__, 'log');
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
    bm_d(self::BM_D_TRACE, $this->inColor($message));

    if (isset($form['panel_preflight'])) {
      $ajax_response->addCommand(
        new ReplaceCommand('#preflight-panel-wrapper', $form['panel_preflight-wrapper']['panel_preflight'])
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
//    if (isset($form['panel_inspector']['existing-nids-table-select'])) {
//      $ajax_response->addCommand(
//        new ReplaceCommand('#panel-inspector', $form['panel_inspector']['existing-nids-table-select'])
//      );
//    }

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
   * @throws Exception
   */
  public function submit_AjaxCallback_importSelected($form, FormStateInterface $form_state): AjaxResponse {
    $time = time();
    $message = '<div class="bgcolor-violet fgcolor-white"> trace: ' . __FUNCTION__ . '</div>';
    bm_d(self::BM_D_TRACE, $this->inColor($message));

    Drupal::logger('bm_import setting form')->debug('submit_AjaxCallback_importSelected');
    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(
      new ReplaceCommand('#import-data', $form['import_data'])//$form['import_data'])
    );

    $message = '<div class="bgcolor-red"> trace-end:' . ' ' . __FUNCTION__ . ' ' . $time - time() . 's. </div>';
    bm_d(self::BM_D_KEY, $this->inColor($message));


    $this->response_messages($ajax_response);
    return $ajax_response;
  }


  /**
   * Prepare message to display as popup
   *
   * @param $response
   *
   * @return void
   */
  function response_messages($response): void {

    //bm_d(self::BM_D_KEY, __function__, 'trace', 'screen');
    $noMessages = TRUE;
    //    $messages = Drupal::messenger()->messagesByType('status');
    //    $messages = array_merge($messages, Drupal::messenger()->messagesByType('warning'));
    //    $messages = array_merge($messages, Drupal::messenger()->messagesByType('error'));

    $message_markup = '<div id="status-messages">';


    $message_markup .= '<div role = "contentinfo" aria-label = "Status message" class="messages messages--status">
      <h2 class="visually-hidden">Status message</h2>
      <ul class="messages__list" >';
    foreach (Drupal::messenger()->messagesByType('status') as $message) {
      //if(str_contains($message, 'Sfdump'))continue;
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-green" >' . $message . '</li>';
    }
    foreach (Drupal::messenger()->messagesByType('warning') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-orange" >' . $message . '</li>';
    }
    foreach (Drupal::messenger()->messagesByType('error') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-red" >' . $message . '</li>';
    }
    $message_markup .= '</ul></div>';

    $message_markup .= '</div>';
    $message_markup = Markup::create($message_markup);
    Drupal::messenger()->deleteByType('status');
    Drupal::messenger()->deleteByType('error');
    Drupal::messenger()->deleteByType('warning');
    // ALTERNATIVE TO POPUP
    // send them and places them under table
    // $response->addCommand(new ReplaceCommand('#status-messages', $message_markup));

    // pops up a modal with the messages
    // The selector of the dialog.
    $selector = '#messages-modal';// js sees this in settings of behaviour call
    // The title of the dialog.
    $title = 'Messages';
    // The content that will be placed in the dialog, either a render array or an HTML string.
    $content = $message_markup;
    // (optional) Array of options to be passed to the dialog implementation. Any jQuery UI option can be used. See http://api.jqueryui.com/dialog.
    $dialog_options = [
      //'minHeight' => '700px',
      'width' => 700,
      'resizable' => TRUE,
    ];
    // (optional) Array of custom settings that will be passed to the Drupal behaviours on the content of the dialog.
    // When left empty, the settings will be populated automatically from the current request. See https://www.drupal.org/docs/8/api/javascript-api/javascript-api-overview.
    $settings = ['sizeAdjustModal' => $selector];
    if (!$noMessages) {
      $response->addCommand(new OpenModalDialogCommand($title, $content, $dialog_options, $settings));
    }
    //    $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand(null, 'throbber', [true,$message,true]));
  }

  /**
   * Saves and re-adds marked-up messages to the Drupal messenger.
   */
  private function make_messages_html_save(): void {
    /*
    $messenger = \Drupal::messenger();

    // Get all messages, keeping track of types.
    $all_messages = $messenger->all();
    $messenger->deleteAll(); // Clear all current messages.

    foreach ($all_messages as $type => $messages) {
      foreach ($messages as $message_text) {
        // Check if the message contains a '<pre' tag.
        if (strpos($message_text, '<pre') !== FALSE) {
          // Extract the part after '<pre'.
          $message_content = explode('<pre', $message_text, 2)[1];
          $message = '<pre' . $message_content; // Prepend '<pre' to keep the HTML structure.

          // Add the message as a warning with proper markup.
          $messenger->addWarning(\Drupal\Core\Render\Markup::create($message));
        }
        else {
          // For messages without '<pre', just process normally with markup.
          $message_with_markup = \Drupal\Core\Render\Markup::create($message_text);

          // Requeue the message with markup.
          $messenger->addMessage($message_with_markup, $type);
        }
      }
    }

    // Optionally, add a warning message indicating completion.
    //$messenger->addWarning('I marked up all messages.');
    */
  }

  /**
   * @param array $input
   *
   * @return void
   */
  public function act_on_import_selected_rows_button(array $input): void {
    $message = 'buildForm clicked Import selected rows';
    bm_d('', $this->inColor($message));

    $this->button_doImport = TRUE;// import in progress

    // get the columns not in CSV which need to be reset
    $ignoreFields = $input['select_Excel_columns_to_ignore'] ?? [];

    // find selected rows
    $tableSelect = $input['import-data-table-select'] ?? [];

    $rowNums = [];
    foreach ($tableSelect as $n => $set) {
      if ($set !== NULL) {
        $rowNums[] = $n;

      }
    }
    if (!count($rowNums)) {
      $message = '<span class="bgcolor-yellow fgcolor-black"> -- no rows selected - select some rows to import -</span>';
      bm_d("", $this->inColor($message));
    }
    else {
      try {
        bm_d("", ' IMPORTING - [' . count($rowNums) . '] rows"');
        $this->BMImport->importRows($rowNums, $input);

        // when no error all are green
        //              foreach ($rowNums as $n) {
        //                $csvContent['options'][$n]['#attributes']['class'] = ['bgcolor-lightgreen'];
        //              }
      } catch (Exception $e) {
        Drupal::messenger()->addError($e->getMessage());
      }
    }

  }

  protected function getEditableConfigNames() {
    // TODO: Implement getEditableConfigNames() method.
  }

}
