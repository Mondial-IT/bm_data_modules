<?php /** @noinspection PhpPropertyOnlyWrittenInspection */

namespace Drupal\bm_import\BMImport;

use Drupal;
use Drupal\bm_debug\BMDebugInfo\BMDebugInfo;
use Drupal\bm_import\BMImportHandlers\BMImportYMLHandler;
use Drupal\bm_listings\BMListings\BMListings;
use Drupal\bm_taxonomies\BMTaxonomies\BMTaxonomies;
use Drupal\bm_xlsx\BMXLSX\BMXLSX;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Render\Markup;
use Drupal\taxonomy\Entity\Term;
use Exception;

/**
 * # BMImport
 * Main import controller
 * * new BMImport instantiates:
 * *  - Excel workbook
 * *  - the handlers
 * *  - $this->ExcelContent
 * *
 */
class BMImport {

  const EXCEPTION_HEADING_MISSING_IN_YAML = 9990;


  private array|false $state;

  private string|null $canvassChain;

  private string|null $canvassTid;

  private string|null $categoryTid;

  /**
   * # csvContent
   * The Excel content
   * 'header' the headings
   * 'options' the rows
   * 'names' names of all yml entities and fields
   *
   * @var array|mixed|null
   */
  public array|null $ExcelContent;

  /**
   * @var mixed|null
   */
  private mixed $categoryChain;

  // this class uses bm_d() to display debug messages using the BMDebug class
  // This key can be enabled/disabled from the BMDebug settings form

  const BM_D_KEY = __CLASS__ . '-algemeen';


  public float $construct_time;

  public float $rowDurationTime;

  /**
   * @var int|mixed
   */
  private mixed $sheet;

  /**
   * @var int|mixed
   */
  private mixed $limit;

  const BM_D_DETAILS = __CLASS__ . '-details';

  const BM_D_TRACE = __CLASS__ . '-trace';


  /** # bm_d
   */
  static public function bm_d_callback($key, $BMDbgInfo): BMDebugInfo|false {

    if ($key == self::BM_D_KEY) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImport - summary';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    if ($key == self::BM_D_DETAILS) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImport - details.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    if ($key == self::BM_D_TRACE) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImport - trace function calls.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    return FALSE;
  }



  /**
   * # SELF BMImport
   *
   * @var BMImport
   */
  private static BMImport $instance;

  public ?string $importType = NULL;


  /**
   * #### type_settings_yml
   * Data from import.settings.yaml
   * @var array
   */
  public array $type_settings_yml = [];

  // data from the specific yaml file for each import type

  /**
   * #### system_settings_ynk
   * @var array
   */
  public array $system_settings_yml = [];

  public string $language_id = 'en';

  private bool $reUseExisting = FALSE;

  /**
   * #### BMImportYMLHandler
   *
   * @var BMImportYMLHandler
   */
  public BMImportYMLHandler $BMImportYMLHandler;

  /**
   * #### BMImport_todo_with_headers
   * @var BMImport_todo_with_headers
   */
  public BMImport_todo_with_headers $BMImport_todo_with_headers;

  /**
   * #### BMImportInspector
   *
   * @var BMImportInspector
   */
  public BMImportInspector $BMImportInspector;
  /**
   * #### BMImport_todo_before_import
   * Main section in the YAML.
   * * handling what needs to be present
   * and creating new entities required.
   *
   * @var BMImport_todo_before_import
   */
  private BMImport_todo_before_import $BMImport_todo_before_import;
  /**
   * #### BMImport_todo_with_columns
   * Main section in the yaml
   * * processing each column and creating the entity field(s) and values
   *
   * @var BMImport_todo_with_columns
   */
  private BMImport_todo_with_columns $BMImport_todo_with_columns;
  /**
   * #### BMImportXLSXhandler
   * class managing CSV handling
   * * support class
   *
   * @var BMImportXLSXHandler
   */
  public BMImportXLSXHandler $BMImportXLSXhandler;
  /**
   * # BMImport_activeResult
   * A class which maintains relations between entities
   * example a contact_reference field awaits the creation of the contact node
   * this class tracks the awaiting and resolving references
   *
   * @var BMImportActiveResult|null
   */
  public ?BMImportActiveResult $activeResult;

  private ?array $headers;

  private string $filePath;

  private ?bool $hasCategoryColumn = FALSE;

  private ?bool $hasCanvassColumn = FALSE;

  private ?bool $hasCategory = FALSE;

  private mixed $ignoreNames;

  /**
   * #### ignoredNamesList
   * A list of names (Excel) which are in YML but not in Excel, or marked as action=ignore
   * 1. when in YAML, names have action: ignore, the field is placed on the ignored list
   * 2. when a field in YAML is not present in Excel, the name is placed on the ignored list
   */
  public array $ignoredNamesList;
  /**
   * #### verbose
   * When true, display additional information (debug)
   * @var bool
   */
  public bool $verbose = FALSE;

  /**
   * #### YMLNames
   * List of names (column definitions) in YML
   * @var mixed|array
   */
  public mixed $YMLNames = [];

  /**
   * can be set to a field name, on which debug statements will be executed
   * when set with a yaml -name, it will only process this name
   */
  private ?string $DEBUG_FIELDNAME = NULL;

  /**
   * Array of YML keys =>True then they are BMImport, false ignored (debugging)
   */
  private array $importEnabled;

  /**
   * # __construct
   *
   * @throws EntityStorageException
   * @throws Exception
   */
  public function __construct($input=[], $language_id = 'en') {
    bm_d('', '', instantiateColor('Instantiating BMImport'), 'screen');
    // todo: BMListings::check__listing_presave_handler_disabled()
    $this->construct_time = microtime(TRUE);
    $this->rowDurationTime = microtime(TRUE);// see todo with columns

    // DEV HELPERS
    $this->verbose = FALSE;// a lot of debug info
    $this->importEnabled = [
      'node_create' => TRUE,
      'node_fields' => TRUE,
      'node_paragraph_fields' => TRUE,
      'paragraph_paragraph' => TRUE,
    ];
    $this->DEBUG_FIELDNAME = NULL;// when set to a -name it will only do that field

    // keep track of what we create in activeResult
    $this->activeResult = new BMImportActiveResult($this);

    $this->reUseExisting = TRUE;
    $this->importType = 'unknown';
    $this->hasCategoryColumn = NULL;
    $this->hasCanvassColumn = NULL;

    $currentLanguage = Drupal::languageManager()->getCurrentLanguage();
    $this->language_id = $language_id;
    $this->ignoreNames = [];

    // Handler classes
    $this->BMImportXLSXhandler = new BMImportXLSXHandler($this);

    // The previous state.
    //    filePath
    //    ExcelContent
    //    limit
    //    sheet
    //    canvassTid
    //    canvassChain
    //    categoryTid
    //    categoryChain
    //    languageId
    //    importType
    //    csvContent
    $this->state = $this->restoreState();


    // xlsx config
    $this->filePath = $this->state['filePath'] ?? '';
    $this->sheet = $this->state['sheet'] ?? 0;
    $this->limit = $this->state['limit'] ?? 0;

    $this->canvassTid = $this->state['canvassTid'] ?? NULL;
    $this->canvassChain = $this->state['canvassChain'] ?? '';
    $this->categoryTid = $this->state['categoryTid'] ?? NULL;
    $this->categoryChain = $this->state['categoryChain'] ?? NULL;
    $this->language_id = $this->state['language_id'] ?? 'nl';
    // deprecated
    $this->importType = $this->state['importType'] ?? 'jiggr';

    // Settings YML
    $this->type_settings_yml = [];
    $this->BMImportYMLHandler = new BMImportYMLHandler($this);
    // prime it
    $this->type_settings_yml = $this->BMImportYMLHandler->get_system_settings_yml();


    // Prepare headers
    $this->BMImport_todo_with_headers = new BMImport_todo_with_headers($this);
    // Get checked input[select_Excel_columns_to_ignore] names
    // and store them into cache[importableNames]
    $this->BMImport_todo_with_headers->act_on__select_columns_to_ignore($input);

    $this->BMImport_todo_before_import = new BMImport_todo_before_import($this);
    $this->BMImport_todo_with_columns = new BMImport_todo_with_columns($this);
    $this->BMImportInspector = new BMImportInspector($this);

    // new Excel uploaded?
    bm_d('',instantiateColor('Instantiating BMXLSX'),'', 'screen');

    // Instantiate BMXLSX,
    // make $xlsx->getRowsKeys available,
    $xlsx = new BMXLSX();// uses cached values.
    if (!$xlsx) {
      Drupal::messenger()->addWarning('First upload an excel workbook');
    }
    // Did something change with the uploaded Excel
    // user can have uploaded a new file
    // debugging change in file, sheet, or limit detection
    $unchanged = $xlsx->getFileUri() === $this->filePath &&
      $xlsx->get_current_sheet() === $this->sheet &&
      $xlsx->get_current_limit() === $this->limit;

    $message = $unchanged ?
      greenWColor( 'uri[: '.$xlsx->getFileUri() .'===' . $this->filePath . ']' .
        ' sheet[: ' .$xlsx->get_current_sheet() . '==='. $this->sheet . ']' .
        ' limit[ ' . $xlsx->get_current_limit() . '==='. $this->limit.']'):
      orangeWColor( 'uri[: '.$xlsx->getFileUri() .'===' . $this->filePath . ']' .
        ' sheet[: ' .$xlsx->get_current_sheet() . '==='. $this->sheet . ']' .
        ' limit[ ' . $xlsx->get_current_limit() . '==='. $this->limit.']');

    bm_d('',$message);

    if ($unchanged) {
      $this->ExcelContent = $this->state['ExcelContent'] ?? [];
      bm_d('', '<div class="bgcolor-lightgreen">Excel File is still the same - continue with cached data</div>');
      // Modify names to make matching yml names less dependent on field spelling
      $this->BMImport_todo_with_headers->todo_with_headers();
    }
    else {
      // when, filePath, Sheet or Limit have changed,
      // initialize the data for the Excel to be imported.
      $this->newExcelLoadedInitialisation($xlsx);
    }

    bm_d(self::BM_D_KEY, 'BMImport instantiated. this ExcelContent and state should be actual to xlsx', '', 'screen');
  }

  /**
   * Initializes and processes a newly uploaded Excel file.
   *
   * This method is responsible for handling the changes when a new Excel
   * file is uploaded. It updates internal states, clears cached data, and
   * prepares the newly uploaded file for further operations.
   *
   * @param BMXLSX $xlsx The object representing the newly uploaded Excel file,
   *                     containing information such as file path, sheet data,
   *                     and row limits.
   *
   * @return void
   */
  public function newExcelLoadedInitialisation(BMXLSX $xlsx): void {

    /**
     * # New data has arrived
     *
     */
    bm_d('', '<div class="bgcolor-orange">Uploaded file CHANGED - processing new Excel file data</div>');

    bm_d('',
      $xlsx->getFileUri() . ' <uri> ' . $this->filePath . '|' .
      $xlsx->get_current_sheet() . ' <sheet> ' . $this->sheet . '|' .
      $xlsx->get_current_limit() . ' <limit> ' . $this->limit);

    // forget the prepared queries of the inspector
    // and rebuild again.
    BMImportInspector::bm_inspector_clear_cache();

    // put Excel sheet in the right format
    try {
      // use the new situation.
      $this->filePath = $xlsx->getFileUri();
      $this->sheet = $xlsx->get_current_sheet();
      $this->limit = $xlsx->get_current_limit();

      // reload sheet data.
      $this->ExcelContent = ['header' => $xlsx->getRowHeader(), 'options' => $xlsx->getRows(), 'names' => []];

      // Modify names to make matching yml names less dependent on field spelling
      $this->BMImport_todo_with_headers->todo_with_headers();

      // modify rows to use the heading key as key
      // to make rows associate with the ymlMatch name as key
      $headerKeys = array_keys($this->ExcelContent['header']);
      $rows = [];

      foreach ($this->ExcelContent['options'] ?? [] as $row) {
        $newRow = [];
        foreach ($row as $idx => $value) {
          $headingKey = $headerKeys[$idx] ?? 'no-key-check BMImport 362';
          $newRow[$headingKey] = $value;
        }
        $rows[] = $newRow;
      }
      $this->ExcelContent['options'] = $rows;

      // add CANVASS, ETL, CATEGORY when not present
      $this->add_mandatory_columns_to_ExcelContent();

      // update state
      // filePath
      // limit
      // sheet nr
      $this->BMImportXLSXhandler->set_cache_ExcelContent();
      $this->set_cache_filePath($xlsx->getFileUri());
      $this->set_cache_xlsxConfig();


    } catch (\Exception $e) {
      Drupal::messenger()->addWarning($e->getMessage());
    }
  }

  /**
   * # getInstance
   *
   * @return self
   */
  public static function getInstance(): self {
    if (!self::$instance) {
      // Obtain the necessary dependencies, e.g., RendererInterface.
      $renderer = Drupal::service('renderer');
      self::$instance = new self();
    }
    return self::$instance;
  }


  /**
   *  set_cache_filePath
   * * store filePath in cache
   *
   * @param $filePath
   *
   * @return void
   */
  public function set_cache_filePath($filePath): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $data = $cache->data;
    }
    $data['filePath'] = $filePath;
    Drupal::cache('bluemarloc')->set('BMImport', $data);
  }

  /**
   * Store the current Excel properties in BMImport
   * to test if user has uploaded a new Excel in the constructor.
   *
   * @return void
   */
  public function set_cache_xlsxConfig(): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $data = $cache->data;
    }
    $data['filePath'] = $this->filePath;
    $data['sheet'] = $this->sheet;
    $data['limit'] = $this->limit;

    Drupal::cache('bluemarloc')->set('BMImport', $data);
    bm_d('', 'cache xlsxConfig ' . $this->filePath . '[' . $this->sheet . ',' . $this->limit . ']');
  }

  public function set_cache_importType($importType): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $data = $cache->data;
    }
    $data['importType'] = $importType;
    $this->importType = $importType;
    Drupal::cache('bluemarloc')->set('BMImport', $data);
  }

  private function set_cache_etlTid($etlTid, $etlName): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    $cache['etlTid'] = $etlTid;
    $cache['etlName'] = $etlName;
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }

  static public function get_cached_etlTid() {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
      return $cache['etlTid'] ?? NULL;
    }
    return NULL;
  }

  static public function get_cached_etlName() {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
      return $cache['etlName'] ?? "";
    }
    return "";
  }

  private function set_cache_canvassTid($canvassTid, $canvassChain): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    $cache['canvassTid'] = $canvassTid;
    $cache['canvassChain'] = $canvassChain;
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }

  static public function get_cached_categoryTid() {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
      return $cache['categoryTid'];
    }
    return NULL;
  }

  static public function get_cached_canvassTid() {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
      return $cache['canvassTid'];
    }
    return NULL;
  }


  private function set_cache_categoryTid($categoryTid, $categoryChain): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    $cache['categoryTid'] = $categoryTid;
    $cache['categoryChain'] = $categoryChain;
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }

  private function set_cache_languageId($languageId): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    $cache['languageId'] = $languageId;
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }


  /**
   * # restoreState
   * * restore a import state currently in cache.
   *
   * @return array|false
   */
  public function restoreState(): array|false {
    $state = [];
    $state['ExcelContent'] = $this->BMImportXLSXhandler->get_cached_ExcelContent();

    $state['filePath'] = $this->get_cached_filePath();

    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    $state['sheet'] = $cache->data['sheet'] ?? 0;
    $state['limit'] = $cache->data['limit'] ?? 100;

    $state['canvassTid'] = $cache->data['canvassTid'] ?? NULL;
    $state['canvassChain'] = $cache->data['canvassChain'] ?? NULL;
    $state['categoryTid'] = $cache->data['categoryTid'] ?? NULL;
    $state['categoryChain'] = $cache->data['categoryChain'] ?? NULL;
    $state['languageId'] = $cache->data['languageId'] ?? NULL;

    $state['importType'] = $cache->data['importType'] ?? NULL;

    bm_d('', 'restoreState ' . $state['filePath'] . '[' . $state['sheet'] . ',' . $state['limit'] . ']');

    if (empty($state['ExcelContent'])) {
      return FALSE;
    }
    return $state;
  }

  /**
   * # clear_BMImport_cache
   * ## start fresh
   *
   * @return void
   */
  public function clear_BMImport_cache(): void {
    bm_d(self::BM_D_KEY, '<div class=fgcolor-red">-------------------CLEAR-CACHE ----------------------</div>');
    Drupal::cache('bluemarloc')->delete('BMImport');
    //dsm($this->get_cached_ExcelContent(),'cachedcsvcontent');
    $this->markup_messages_html_save();
  }


  static public function get_cached_importType(): ?string {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if (isset($cache->data['importType'])) {
      return $cache->data['importType'];
    }
    return NULL;
  }

  /**
   * # get_cached_filePath
   *
   * @return mixed|null
   */
  public function get_cached_filePath(): ?string {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if (isset($cache->data['filePath'])) {
      return $cache->data['filePath'];
    }
    return NULL;
  }

  /**
   * # get_has_which_mandatory_columns
   * Canvass and Category are mandatory.
   * When they are not in the import csv, they are provided by the module
   * The user is then presented with a select form to choose the canvass or category.
   *
   * @return array [canvass, category]
   */
  public function get_has_which_mandatory_columns(): array {
    return ['canvass' => $this->hasCanvassColumn, 'category' => $this->hasCategoryColumn];
  }

  /**
   * # get_filePath
   *
   * @return string
   */
  public function get_filePath(): string {
    return $this->filePath;
  }

  public function get_importType() {
    if (isset($this->importType)) {
      return $this->importType;
    }
    return self::get_cached_importType();
  }


  /**
   * #### processUploadedExcel
   * **get the file, read the file, transform to array**
   * * register the file type,
   * * load the yml with specific import settings
   *
   * @param array $excelContent Returns the data as ['header'=>,'options'=>]
   *
   * @return bool
   * @throws Exception
   */
  public function processUploadedExcel(): bool {
    bm_d(self::BM_D_KEY, __LINE__ . ' BMImport: Process Uploaded Excel ');

    // retrieve the YAML information list.
    // $list = $this->Import->getYAMLInformationList();
    // get paragraph and taxonomy handlers
    // $this->loadSettingsYamlFile();
    //$this->load_system_settings_yml();
    $fid = bm_xlsx_get_last_uploaded_fid();
    if ($fid) {

      Drupal::messenger()
        ->addMessage(__LINE__ . ' BMImport:  Registering the Excel for YML definition: ' . $this->filePath);

      $noOfRows = count($this->ExcelContent['options']);
      Drupal::messenger()
        ->addMessage(' BMImport:  The Excel sheet contains ' . $noOfRows . ' rows');

      // add:
      // CANVASS
      // ETL
      //
      $this->add_mandatory_columns_to_ExcelContent();

      // cache to BMImport{ExcelContent}
      $this->BMImportXLSXhandler->set_cache_ExcelContent();

      return $noOfRows > 0;
    }
    return FALSE;
  }

  /**
   * # registerImportType (filePath)
   * * From the settings yml, the importTypes are retrieved
   * * and matched with the file path, to identify the type of csv to import
   * * The type is expected to be found somewhere in the csv file path
   *
   * Example settings:
   * * type adfiz: bm_import.adfiz.settings.yml
   * * type googlePlaces: bm_import.googlePlaces.settings.yml
   *
   * @param $filePath
   *
   * @return void
   */
  public function registerImportType($filePath): void {

  }

  /**
   * Add mandatory columns to header and rows, in case they are missing
   *
   * @return void
   */
  private function add_mandatory_columns_to_ExcelContent(): void {
    bm_d(self::BM_D_KEY, ' trace: ' . __FUNCTION__);
    $header = $this->ExcelContent['header'];
    // add headings
    $prepend = [
      '_INDEX' => 'n',
      '_EXISTS' => 'Exists',
    ];
    // add row keys
    $prependR = [
      '_INDEX' => 'n',
      '_EXISTS' => '',
    ];

    // no column CANVASS
    if (!in_array('CANVASS', $header)) {
      $prepend['CANVASS'] = 'CANVASS';
      $prependR['CANVASS'] = "";
    }

    if (!in_array('ETL', $header)) {
      $prepend['ETL'] = 'ETL file';
      $prependR['ETL'] = '';
    }
    if (!in_array('CATEGORY', $header)) {
      $prepend['CATEGORY'] = 'Category';
      $prependR['CATEGORY'] = '';
    }
    if (!in_array('BM_ID', $header)) {
      $prepend['BM_ID'] = 'BM id';
      $prependR['BM_ID'] = '';
    }
    // Manually prepend system columns to headers.
    $this->ExcelContent['header'] = array_merge($prepend, $this->ExcelContent['header']); // Correct for headers.

    // Ensure system columns are prepended to each row correctly.
    foreach ($this->ExcelContent['options'] as $n => &$row) {
      // Correctly combine the arrays to ensure prepend keys are at the front.
      $row += $prependR; // This should work correctly for associative arrays.
      $row['_INDEX'] = $n;
    }

    $this->markup_messages_html_save();
  }


  /**
   * ### get_preflight_panel_form_content__todo_before_import
   * #### todo_before_import: run the todo_before_import actions
   *
   * * processes yaml section 'todo_before_import',
   * * it checks if the mentioned taxonomy, paragraphs, and fields are present.
   *  * create paragraph types and fields
   *  * creates vocabulary types and fields
   *  * creates node types and fields
   *
   * Returns: returns a form element informing the user
   * Returns: a "ready" variable which is set when there are no missing parts
   *
   * @return array
   * @throws EntityStorageException
   */
  public function get_preflight_panel_form_content__todo_before_import(): array {
    return $this->BMImport_todo_before_import->preflight_checks_panel__create_required_fields();
  }

  /**
   * #### get_field_defaults_panel_form_content__fieldIssuesAndRepairs
   *
   * * return input form element of missing fields
   *
   *
   * find the fields which require input which are not given in the file
   * * for fields missing from csv which are defined in the yaml
   * * and return form element to set default field entries
   *
   * @return array
   */
  public function get_field_defaults_panel_form_content__fieldIssuesAndRepairs(): array {
    $form = [];

    // contains uppercase table column names
    $this->headers = $this->BMImport_todo_with_headers->get_cached_headers();
    // Table headers
    if (isset($this->headers)) {
      $header = $this->headers;

      $YMLNames = $this->BMImportYMLHandler->getAllAvailableColumnNamesFromYML();
      bm_d('', $YMLNames, 'LIST OF ALL AVAILABLE COLUMN NAMES IN YML ', 'dsm');

      // build a form with column_names where user can set defaults to
      foreach ($YMLNames as $YMLName) {
        // skip _ names
        if (str_starts_with($YMLName, '_')) {
          continue;
        }// skip ETL, CANVASS e.d. which are handled by import itself

        // only names in the YML, with at least an uppercase character are user fields, the others system names
        if (preg_match('/[A-Z]/', str_replace('_', '', $YMLName))) {
          if (in_array($YMLName, $header)) {
            //          bm_d(self::BM_D_KEY,'BMImport get_fieldIssues: no issue - have name:' . $name);
          }
          else {
            bm_d(self::BM_D_DETAILS, '<span class="fgcolor-orange"> Warning BMImport get_fieldIssues: Field defined in YAML, but is not in the Excel. <b>Name:' . $YMLName . '</b></span>');

            $form[$YMLName] = [
              '#type' => 'textfield',
              '#default_value' => '',
              '#placeholder' => 'Enter a default value or leave empty',
              '#title' => $YMLName,
            ];

          }
        }
      }
    }
    return ['form' => $form, 'ready' => FALSE];
  }

  //  public function get_cached_csvContentDefaultValue() {
  //    $cache = Drupal::cache('bluemarloc')->get('BMImport');
  //    return $cache->data['ExcelContent']['default_value'] ?? [];
  //  }


  /**
   * # importRows
   * ## Entry point for interactive and batch
   * #### method controlling the import of rows.
   *
   * * creates etl term using the file name when not set
   * * creates canvass with the filename when canvass is not set
   * * creates category x when not set
   * * sets language to 'en' when not set otherwise
   *
   *
   *
   * @throws Exception
   */
  public function importRows($rowNums, $input): void {
    // get paragraph and taxonomy handlers
    // $this->load_system_settings_yml();
    // remember the selected rows, for coloring rows, and pre-selecting the rows
    $this->BMImportXLSXhandler->set_cache_ExcelContent_rowNums_as_DefaultValue($rowNums);

    //  if (function_exists("BMListings::disable__bump_last_interaction_dates")) {
    bm_d("", "BMListings has disabled bumping last interaction dates - check ");
    BMListings::disable__bump_last_interaction_dates();
    BMListings::disable__listing_presave_handler();
    //   }
    try {
      // RETRIEVE FILEPATH
      $filePath = $this->get_cached_filePath();
      if (!isset($filePath)) {
        bm_d("", errorColor('<b>Import ENDED - no file path</b>'));
        throw new Exception('No file path is present.');
      }
      bm_d("", violetColor('- Filepath ' . $filePath));

      // HANDLE ETL DEFAULT SETTING, (and cache it)
      [$etlName, $etlTermId] = $this->determineDefault_ETL($filePath);

      // HANDLE CANVASS DEFAULT SETTING, (and cache it)
      $canvassTid = $this->determineDefault_canvassTid($input, $etlName);

      // HANDLE CATEGORY DEFAULT SETTING, (and cache it)
      $categoryTid = $this->determineDefault_categoryTid($input);

      // CHECK LANGUAGE IS SET
      $languageId = $input['panel_select_language'] ?? 'nl';
      bm_d(self::BM_D_KEY, violetColor('- default Language:' . $languageId));
      $this->set_cache_languageId($languageId);


      bm_d('', " Defaults canvass[$canvassTid] Category[$categoryTid] ETL[$etlTermId]");

      // RETRIEVE CSV
      $this->ExcelContent = $this->BMImportXLSXhandler->get_excelContent();
      if (!isset($this->ExcelContent)) {
        throw new Exception('NO csv content was found');
      }

      // PREPARE LIST OF NAMES TO IGNORE
      $ignoreNames = $this->BMImport_todo_with_headers->get_cached_importableNames();
      if (isset($ignoreNames['ignoreNames'])) {
        $this->ignoreNames = $ignoreNames['ignoreNames'];
      }
      Drupal::logger('BMImport row')->debug('Starting row ' . $rowNums[0] ?? 0);
      // IMPORT ROWS
      foreach ($rowNums as $rowNum) {
        $startTime = time();
        bm_d(self::BM_D_KEY, violetColor('ROW:' . $rowNum . ' timestamp:' . $startTime));
        try {

          $this->ExcelContent['options'][$rowNum]['#attributes']['class'] = ['bgcolor-lightgreen'];
          $this->BMImport_todo_with_columns->todo_with_columns($rowNum, $filePath, $canvassTid, $categoryTid, $languageId, $etlTermId);

          // uncheck checkbox
          $this->ExcelContent['default_value'][$rowNum] = FALSE;


        } catch (Exception $e) {

          $this->ExcelContent['options'][$rowNum]['#attributes']['class'] = ['bgcolor-red fgcolor-white'];
          $this->BMImportXLSXhandler->set_cache_ExcelContent($this->ExcelContent);

          $duration = ((time() - $startTime));
          $phpFile = explode('/', $e->getFile());
          $phpFile = array_pop($phpFile);
          bm_d(self::BM_D_KEY, '<p class="bgcolor-red fgcolor-white">TERMINATED ON EXCEPTION ROW:' . $rowNum . ' Took: ' . $duration . 's.</p>
                                          <p class="fgcolor-red">' . $e->getCode() . ' File:' . $phpFile . ' Line:' . $e->getLine() . '</p>
                                          <p class="fgcolor-red"><b>' . $e->getMessage() . '</b></p>');

          throw $e;// will be handled higher up

        }
        $this->BMImportXLSXhandler->set_cache_ExcelContent($this->ExcelContent);

        $duration = ((time() - $startTime));
        bm_d('', violetColor(' IMPORTED ROW:' . $rowNum . ' Took: ' . $duration . 's.'));
      }

    } catch (Exception $e) {
      bm_d('', ' <div class="bgcolor-lightpink fgcolor-black"><b>Import ENDED </b></div>Message: ' . $e->getMessage());
    }

    // todo ENABLE OTHER METHODS WORKING ON LISTINGS
    // BMListings::restore__bump_last_interaction_dates();


    $this->markup_messages_html_save();
    Drupal::logger('BMImport')->debug(__LINE__ . ' importRows() end');
  }

  /**
   * Create an ETL term with the filename as label
   * and cache it as etlTid
   *
   * @param $filePath
   *
   * @return array etlName, etlTermId
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws Exception
   */
  private function determineDefault_ETL($filePath): array {
    // SET ETL = FILENAME

    $etlTermId = 0;
    $etlName = explode('/', $filePath);
    $etlName = array_pop($etlName);

    // nothing to stop us now, lets set the ETL name.
    $term = BMTaxonomies::getTerm_using_nameAndVid($etlName, 'listing_etl_sources');
    if (!$term) {
      $termProperties = [];
      $termProperties['vid'] = 'listing_etl_sources';
      $termProperties['name'] = $etlName;
      $term = Term::create($termProperties);
      $term->save();
    }
    $etlTermId = $term->id();
    if (empty($etlTermId)) {
      bm_d(self::BM_D_KEY, '<div class="bgcolor-red">ETL Term not created error</div>');
      throw new Exception('ETL Term could not be created in the taxonomy listing_etl_sources.');
    }
    bm_d(self::BM_D_KEY, violetColor('- default ETL source:' . $etlName));
    $this->set_cache_etlTid($etlTermId, $etlName);
    return [$etlName, $etlTermId];
  }

  /**
   * Check if a default canvass has been set by user, and cache it as canvassTid
   *
   * @param array  $input   the form userInput key values
   *
   * @param string $etlName the latest csv filename
   *
   * @return int canvassTid
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws Exception
   */
  private function determineDefault_canvassTid(array $input, string $etlName): int {

    $canvassTid = 0;
    //    dsm($input, 'input');
    $chain = $input['drupal_form_selected_canvass_hs'] ?? NULL;
    //    dsm($chain,'drupal_form_selectedCANVASS_hs');

    if ($chain !== 'none' && $chain !== NULL) {
      $count = count(explode('>', $chain));
      if ($count !== 3) {
        bm_d('', '<span class="fgcolor-red">SELECT A CANVASS, currently a campaign is selected.</span>');
        throw new Exception('No canvass selected: ');

      }
      $canvassTid = BMTaxonomies::get_ChainEndTermId($chain);
      $canvass = Term::load($canvassTid);
      if ($canvass) {
        bm_d('', violetColor('- default Canvass:' . $canvass->label()));
      }
    }
    // when canvass not set, create a canvass with ETL name
    if (empty($canvassTid)) {
      //$termCompany = BMTaxonomies::getTerm_using_nameAndVid('General Company for Imports', 'company_campaign_canvas_tags');
      $termCampaign = BMTaxonomies::getTerm_using_nameAndVid('General Campaign for Imports', 'company_campaign_canvas_tags');
      if (!$termCampaign) {
        Drupal::messenger()
          ->addError("In taxonomy 'company_campaign_canvas_tags' the campaign 'General Campaign for Imports' is missing. It is required when no canvass is specified. Add it an try again.");
        throw new Exception("Blue Marloc message: In taxonomy 'company_campaign_canvas_tags' the campaign 'General Campaign for Imports' is missing. It is required when no canvass is specified. Add it an try again.", 200);
      }

      $term = BMTaxonomies::getTerm_using_nameAndVid($etlName, 'company_campaign_canvas_tags');
      if (!$term) {
        $termProperties = [];
        $termProperties['vid'] = 'company_campaign_canvas_tags';
        $termProperties['name'] = $etlName;
        $termProperties['parent'] = [$termCampaign->id()];
        $term = Term::create($termProperties);
        $term->save();
        // prepend tid to the canvass name.
        $new_name = /*$term->id() . ' ' .*/
          $term->getName();
        $term->setName($new_name);
        $term->save();
      }
      $canvassTid = $term->id();
      if (empty($canvassTid)) {
        bm_d('', '<div class="bgcolor-red">Exception. Canvass term, using ETL name, was not created!.</div>');
        throw new Exception('Could not create canvass using ETL name.');

      }
      $name = $new_name ?? $etlName;
      bm_d('', violetColor('- default Canvass:' . $name));
    }
    $this->set_cache_canvassTid($canvassTid, $chain);
    return $canvassTid;

  }

  /**
   * Check if a category has been set by user, and cache it as categoryTid
   *
   * @param array $input the form userInput key values
   *
   * @return int categoryTid
   * @throws Exception
   */
  private function determineDefault_categoryTid(array $input): int {

    $category = NULL;// the term
    // the value in the HS dropdown list
    $chain = $input['drupal_form_selected_category_hs'] ?? NULL;

    if ($chain !== 'none' && $chain !== NULL) {
      $categoryTid = BMTaxonomies::get_ChainEndTermId($chain);
      $category = Term::load($categoryTid);
      if ($category) {
        bm_d('', violetColor('- default Category from HS:' . $category->label()));
      }
      else {
        throw new Exception('A category could not be set');
      }
    }
    // alternative, where hs is not available it is an input text box
    $categoryTid = $input['input_category_tid'] ?? NULL;
    if ($categoryTid) {
      $chain = $categoryTid;
    }
    // store the default
    $this->set_cache_categoryTid($categoryTid, $chain);

    if (is_int($categoryTid)) {
      return $categoryTid;
    }
    else {

      //      bm_d('', $categoryTid, '<span class="fgcolor-red">Waarom is deze category tid niet een integer</span>');
      return (int) $categoryTid;
    }


  }


  /**
   * @return bool
   */
  public function isVerbose(): bool {
    return $this->verbose;
  }

  /**
   * **set BMImport->hasCanvassColumn variable**
   *
   * @param bool|null $hasCanvassColumn
   */
  public function setHasCanvassColumn(?bool $hasCanvassColumn): void {
    $this->hasCanvassColumn = $hasCanvassColumn;
  }

  public function markup_messages_html_save(): void {
    $messenger = Drupal::messenger();

    // Get all messages, keeping track of types.
    $all_messages = $messenger->all();
    $messenger->deleteAll(); // Clear all current messages.

    foreach ($all_messages as $type => $messages) {
      foreach ($messages as $message_text) {
        // Process each message as needed. Here, we're simply re-adding it.
        // You could include your logic here to add HTML or manipulate the message.
        $message_with_markup = Markup::create($message_text);

        // Requeue the message with markup.
        $messenger->addMessage($message_with_markup, $type);
      }
    }
  }


}
