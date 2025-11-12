<?php

namespace Drupal\bm_import\BMImport;

use Drupal;
use Drupal\bm_debug\BMDebugInfo\BMDebugInfo;
use Drupal\bm_import\BMImportHandlers\BMImportFieldCreator;
use Drupal\bm_import\BMImportHandlers\BMImportImagesHandler;
use Drupal\bm_import\BMImportHandlers\BMImportNodeHandler;
use Drupal\bm_import\BMImportHandlers\BMImportParagraphHandler;
use Drupal\bm_import\BMImportHandlers\BMImportVocabularyHandler;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Exception;

class BMImport_todo_with_columns {

  private BMImport $BMImport;

  // this file uses bm_d() to display debug messages using the BMDebug class
  // This key can be enabled/disabled from the BMDebug settings form

  const BM_D_KEY = 'BMImport-algemeen';

  const BM_D_KEY_DETAILS = 'BMImport-details';

  private BMImportCreator $BMImportCreator;

  private BMImportParagraphHandler $BMImportParagraphHandler;

  private BMImportVocabularyHandler $BMImportVocabularyHandler;

  private BMImportImagesHandler $BMImportImagesHandler;

  private BMImportNodeHandler $BMImportNodeHandler;

  /**
   * @var true
   */
  private bool $reUseExisting;

  private BMImportCellHandler $BMImportCellHandler;

  private mixed $ignoreList;

  private $BMImportInspector;

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
    if ($key == self::BM_D_KEY_DETAILS) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImport - details.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    return FALSE;
  }

  private function inColor($text): string {
    return '<span class="fgcolor-violet">' . $text . '</span>';
  }

  /**
   * # __construct
   * #### instantiated by BMImport which controls the rows and interactions
   *
   * @param BMImport $BMImport
   */
  public function __construct(BMImport $BMImport) {
    $this->BMImport = $BMImport;


  }

  /**
   * a list user has created with csv columns that should not be imported
   *
   * @param $columnName
   *
   * @return bool
   */
  private function isOnIgnoreList($columnName): bool {
    // user can select columns which should not be used in the import.
    $importable = BMImport::get_cached_importableNames();
    if($importable)$this->ignoreList= $importable['ignoreNames'];
    return in_array($columnName, $this->ignoreList);
  }

  /**
   * # todo_with_columns
   * process the 'todo_with_columns' section of the yaml definition per CSV row
   *
   * * add system_columns
   * * create_EntitiesFromRow
   *
   * @throws Exception
   */
  public function todo_with_columns($rowNum, $file_path, $canvassTid, $categoryTid, $languageId, $etlTermId): void {
    bm_d(BMImport::BM_D_KEY, 'BMImport_todo_with_columns processing ' . __FUNCTION__ . ' ');

    if(!isset($this->BMImport->construct_time))$this->BMImport->construct_time=microtime(true);
    $sessionDuration=round((microtime(true)-$this->BMImport->construct_time),2);

    if(!(isset($this->BMImport->rowDurationTime)))$this->BMImport->rowDurationTime=microtime(true);
    $rowDuration=round(microtime(true)-$this->BMImport->rowDurationTime,2);
    $this->BMImport->rowDurationTime=microtime(true);

    if($sessionDuration>60){
      Drupal::messenger()->addStatus("2 minuten voorbij, start de rest.");
      throw new Exception("2 minuten voorbij, start de rest.",8000);
    }

    Drupal::logger('BMImport row')->debug("row:$rowNum duration:".$rowDuration.' sess:'.$sessionDuration);

    // get the yml of this type of import
    $this->BMImport->registerImportType($file_path);

    // invalidate BMImports hasCanvassColumn.
    $this->BMImport->setHasCanvassColumn(FALSE);
    // methods working on the ExcelContent (table cell)
    // The table is updated with results from the import,
    // for example defaults and cells like exists and location.
    // Before being displayed to the user.
    $cellHandler = $this->getBMImportCellHandler();

    $cellHandler->initRow($this->BMImport, $rowNum);
    // display Canvass selection
    $cellHandler->set_CANVASS_to_ExcelTable($canvassTid);
    // display ETL
    $cellHandler->set_ETL_to_ExcelTable($etlTermId);
    // display Category
    $cellHandler->set_CATEGORY_to_ExcelTable($categoryTid);


    $limit= 3*60;
    set_time_limit(3*60);
    bm_d('', $limit,'Time limit is set to 3 minutes.');
    // FIND MATCHING-LISTINGS TO UPDATE, INSTEAD OF CREATE NEW
    // $nids =BMImportInspector::get()->executeMatchQueries($row);
    // $cellHandler->set_nids_to_EXISTS_in_ExcelTable($nids);

    // process the csv columns and import
    $this->create_EntitiesFromRow($rowNum);

    // when column LOCATION is present, and a location was created from country, province, city
    // then display the location in the table cell as a result.

  }
  /**
   * ####  this traverses the YAML 'todo_with_columns' for one row
   * **it detects and defines the 'create' node actions**
   *
   *
   * **Format:**
   *
   * #### node_create
   * * This initiates a Node creation of type listing, where title = row[NAAM]
   * * this entity is used until a new node_create is met.
   * ```
   * - node_create
   *    type: listing
   *    name: NAAM
   *    field: title
   * ```
   * #### node_fields
   *```
   * - node_fields
   *   - name: OMSCHRIJVING
   *     field: body
   *    format: full_html
   *  - name: ADRES
   *    field: field_listing_address
   * ```
   * **these initiate a set_node_fieldValue(field, row[name] )**
   *
   * #### node_paragraph_fields
   * ```
   *  - node_paragraph_fields:
   *    field: field_listing_para
   *    paragraph:
   *      name: finadvisors_listing_paragraph
   *      fields:
   *      - name: VERGUNNINGEN
   *      - field: field_para_licensenumber
   *      - name etc.
   * ```
   * #### these initiate a set_node_paragraph_fieldValue
   * * set_node_paragraph_fieldValue(
   *   * nodeField:field_listing_para,
   *   * paragraph: finadvisors_listing_paragraph,
   *   * field: field_para_licensenumber
   *   * value: row[VERGUNNING]
   *   * )
   *
   * ## paragraph_paragraph_fields
   * This group sets values to fields in a paragraph reference by a parent paragraph
   * * for this the child paragraph must be created first, then the parent, then the node field
   * * this should be reflected in the order of the definitions in YAML
   * #### special cases:
   * `_id_{name of paragraph of entity or taxonomy}`
   * * it contains the last created id
   * #### _auto_chain
   * * when setting a value to one field, we often need to set another in relation to it.
   * * for example setting POST ADRES requires address type to be set to 'post address'
   * * auto_chain is a way to display_result_in_form the importer to set the field in that entity to the value.
   * ## action: add term
   * * see below, it adds a term before inserting it into a field value
   *
   * ### Taxonomies
   * ```
   * - name: SPAARREKENINGEN
   *   field: field_para_adv_products
   *   action: add term
   *   taxonomy: financial_advisor_products
   *   term: Spaar rekeningen
   *  ```
   * this first adds the term 'spaar rekeningen' to taxonomy 'financial_advisor_products'
   * * then and adds the term-Id to field_para_adv_products. When term existed, it just used the tid.
   *
   *
   * @param $row
   *
   * @return void
   * @throws Exception
   */
  public function create_EntitiesFromRow($rowNum): void {
    $message = ' trace:' . ' ' . __FUNCTION__ . ' ' . time();
    bm_d(self::BM_D_KEY, $this->inColor($message));

    $row= $this->BMImport->get_excelContent()['options'][$rowNum];


    // get YAML columns definition
//    $columns = $this->BMImport->get_type_settings_yml()['todo_with_columns'];
    $columns=$this->BMImport->type_settings_yml['todo_with_columns'];

    $node = NULL;
    foreach ($columns as $entry) {

      foreach ($entry as $key => $subEntry) {

        $key = explode('#', $key)[0];
        bm_d(self::BM_D_KEY, 'Working with key:<b>' . $key . '</b>');

        switch ($key) {
          case 'node_create':
            $node = $this->ymlKey__node_create($row, $subEntry);
            break;
          case 'node_fields':
            $this->ymlKey__node_fields__and_specials($node, $row, $subEntry);
            break;
          case 'node_paragraph_fields':
            // paragraph:
            //    name:
            //    fields
            //      - name:
            //        field:
            $this->ymlKey__node_paragraph_fields($node, $row, $subEntry);
            break;
          case 'paragraph_paragraph':
            Drupal::messenger()
              ->addWarning(__LINE__ . 'Info: The YAML contains `paragraph_paragraph`. That functionality is not yet available.');
            break;
          default:
            Drupal::messenger()->addError(__LINE__ . ' Unexpected value - main groups - key:' . $key);
        }

      }

    }
    if ($this->BMImport->verbose) {
      bm_d(self::BM_D_KEY, ' Wrapping up resolving awaits');
    }

    $this->BMImport->activeResult->resolveAwaits();

    bm_d('', '<span class="bgcolor-green fgcolor-white"> -- IMPORT ROW -- <b>SUCCESS</b></span>');
    Drupal::logger('bm_import')->debug(__LINE__ . ' create_EntitiesFromRow() end');
  }
  /**
   * # todo_with_columns clause: node_create
   * ```
   *  node_create:
   *    type: listing
   *    name: TITLE
   *    field: title
   * ```
   *
   * @param $row
   * @param $subEntry
   *
   * @return NodeInterface|null
   * @throws Exception
   */
  private function ymlKey__node_create($row, $subEntry): Drupal\node\NodeInterface|null {
    // CREATE (IF NOT EXISTING)
    $type = $subEntry['type'];
    $name = $subEntry['name'];
    $field = $subEntry['field'];
    $id = $subEntry['id'] ?? NULL;// is set to display_result_in_form another field of this creation (contact referenced by listing)


    if (!isset($row[$name])) {
      $message = 'ymlKey__node_create: did not create ' . $type . ' node as csv row[' . $name . '] has an error or is empty. Fix and try again.';
      bm_d("", '<div class="fgcolor-red">' . $message . '</div>');
      throw new Exception($message);
    }

    $title = $row[$name];
    if($this->isOnIgnoreList($title)){
      $message = 'ymlKey__node_create: did not create ' . $type . ' node as you have marked CSV column[' . $name . '] to be ignored. Uncheck and try again.';
      bm_d('', '<div class="fgcolor-red">' . $message . '</div>');
      throw new Exception($message);
    }
    bm_d('', '<div class="bgcolor-blue fgcolor-white"><b>-------- ymlKey Node create ----------</b>Type:' . $type . ' For:' . $title . '</div>');

    $this->reUseExisting = TRUE;
    $reUseNid = 0;// becomes set with the reuse nid when re-use an existing node.
    /* @var $node Drupal\node\NodeInterface */
    //    $node= $this->getBMImportNodeHandler()->create_node($title, $type, $language_id, $reUseExisting, $re_usedNid);
    $node = $this->getBMImportNodeHandler()
      ->create_node($title, $type, $this->BMImport->language_id, $this->reUseExisting, $reUseNid);
    if (!$node) {
      $message = " node_create: exception node not found $type node for: $title.Fix and try again.";
      throw new Exception($message);
    }
    else {
      $id = $node->id();

      if ($node->hasField('field_bm_id')) {
        $node->set('field_bm_id', $id);
        // display nid in the table cell
        $this->getBMImportCellHandler()->set_BM_ID($id);
        $this->getBMImportCellHandler()->set_nids_to_EXISTS_in_ExcelTable([$id]);
      }
      if (!$reUseNid) {
        bm_d('', '<div class="bgcolor-lightgreen">' . __LINE__ . ' - ' . $title . ' create_node(type:' . $type . ', ' . $field . ', ' . $title . '):' . $id .  ' - <b>SUCCESS</b></div><hr>');
        Drupal::logger('BMImport')
          ->debug(__LINE__ . ' NEW create_node(type:' . $type . ', ' . $field . ', ' . $title . '):' . $id);
      }
      else {
        Drupal::logger('BMImport')
          ->debug(__LINE__ . ' REUSED create_node(type:' . $type . ', ' . $field . ', ' . $title . '):' . $id);
        bm_d('', 'twc:<div class="bgcolor-green fgcolor-orange">BMImport <span class="bgcolor-orange fgcolor-green">RE-USE -  node:' . $id . '</span> of: ' . $title . '): - <b>SUCCESS</b></div><hr>');
      }
    }
    // maintain a list of things created and their id's
    $this->BMImport->activeResult->setMainEntity($node);
    $this->BMImport->activeResult->setCreatedNode($name, $node->id(), $id);
    return $node;
  }
  /**
   * ```
   *  node_fields:
   *    - name:
   *      field:
   *      format:
   *
   *      action: add term
   *      taxonomy:
   *      term:
   *      termLanguageId:
   * ```
   *
   * @param $node
   * @param $row
   * @param $subEntry
   *
   * @return void
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws Exception
   */
  private function ymlKey__node_fields__and_specials($node, $row, $subEntry): void {
    bm_d('', '<div class="bgcolor-blue fgcolor-white"><b>-------- ymlKey Node fields ----------</b></div>');
    $prevValue = NULL;


    foreach ($subEntry as $fieldDef) {
      $CSV_YML_value = NULL;
      if (!isset($fieldDef['name'])) {
        // SKIP name without further items
        // dit is mogelijk een yml fout
        // bijvoorbeeld
        // node_fields:
        //  -# comment
        //  -# comment
        // die - horen hier niet.
        $message = 'ymlKey__node_fields__and_specials: ' . json_encode($fieldDef) . ' Check de yml, bij node_fields. Lege (array) entries. Mogelijk door -# ipv # comment. ';
        throw new Exception($message, 345);
      }
      $name = $fieldDef['name'];
      bm_d(self::BM_D_KEY_DETAILS, '<span class="fgcolor-blue"><b>' . $name . '</b></span>');
      bm_d('',$name.' node field (BMImportTodoWithColumns)','','log');
      if(in_array($name,['SBI','field_para_business_companyname'])){
        bm_d('',$name,'BMImportTodoWithColumns 2 SKIPPED ','log');

      }
      // CHECK ALLOWED TO IMPORT?
      if($this->isOnIgnoreList($name)) {
        bm_d('',$name,' is on the ignore fields list and is skipped');
        continue;
      }

      // SPECIALS
      // when canvass not set in CSV use the saved canvassTid from the UI.
      if($name==='CANVASS'){
        if(empty($row[$name])){
          $row[$name]=BMImport::get_cached_canvassTid();
          $this->BMImportCellHandler->set_CANVASS_to_ExcelTable(BMImport::get_cached_canvassTid());
        }
        else{
          // when a tid check it exists
          // when text add the hierarchy
          if(is_numeric($row[$name])){
            $term = Term::Load($row[$name]);
            Drupal::messenger()->addWarning('The canvass ID does not exist, default has been used');
            if($term->bundle()<>'company_campaign_canvas_tags')$row[$name]=BMImport::get_cached_canvassTid();
          }else{
            Drupal::messenger()->addStatus('Canvass is placed in the General company and campaign');
            $row[$name]='General Company for Imports, General Campaign for Imports,'.$row[$name];
          }
        }
      }

      if($name==='ETL'){
        if(empty($row[$name])){
          $row[$name]=BMImport::get_cached_etlTid();
        }
      }

      // check does row have category
      // a bit of confusion with CATEGORY and CATEGORIE
      // need to be fixed
      if($name==='CATEGORY'||$name==='CATEGORIE'){
        // no category in row, then use the default set category
        if(empty($row[$name])){
          $row['CATEGORY'] = BMImport::get_cached_categoryTid();
          Drupal::messenger()->addStatus('No category given for row using default: '.$row[$name]);
        }
        else{
          // standardize to CATEGORY
          $row['CATEGORY']=$row[$name];
        }
        $this->getBMImportCellHandler()->set_CATEGORY_to_ExcelTable($row['CATEGORY']);
      }


      // create address from stad, straat, huisnummer en postcode when they are valid and present
      if($name === 'ADRES'){
        $createAddress = true;
        $check=['STRAAT','HUISNUMMER','POSTCODE','STAD','PROVINCIE','LAND'];
        foreach($check as $c){
          if(empty($row[$name])){
            $createAddress = false;
          }
        }
        if($createAddress) {
          $address = trim(
            $row['STRAAT'] ?? '') . ' '. trim($row['HUISNUMMER'] ?? '')
          . ', ' .trim($row['POSTCODE'] ?? ''). ' ' .trim($row['PLAATS'] ?? '')
          . ', ' .trim($row['PROVINCIE'] ?? '')
          . ', ' .trim($row['LAND'] ?? '');

          // update adres when address is different
          if ($row['ADRES'] <> $address) {
            $row['ADRES'] = $address;

            $this->getBMImportCellHandler()->set_LOCATION_to_ExcelTable();
          }
          Drupal::messenger()->addMessage($address .' was created out of straat, huisnummer, postcode and stad');
        }
      }

      // CREATE LOCATION
      // special case location created from land, provincie en stad
      if ($name == 'STAD') {
        try {
          if (!isset($row['LAND'])) {
            throw new Exception('ymlKey__node_fields__and_specials: Kolom Land mag niet leeg zijn');
          }
          if (!isset($row['PROVINCIE'])) {
            throw new Exception('ymlKey__node_fields__and_specials: Kolom Provincie mag niet leeg zijn');
          }
          if (!isset($row['STAD'])) {
            throw new Exception('ymlKey__node_fields__and_specials: Kolom Stad mag niet leeg zijn');
          }
          if (!trim($row['PROVINCIE'])) {
            throw new Exception('ymlKey__node_fields__and_specials: Kolom Provincie mag niet leeg zijn');
          }
          if (!trim($row['STAD'])) {
            throw new Exception('ymlKey__node_fields__and_specials: Kolom Stad mag niet leeg zijn');
          }

          $cleanLabels = [$row['LAND'], $row['PROVINCIE'], $row['STAD']];
          $tids = $this->getBMImportVocabularyHandler()
            ->add_update_taxonomy_hierarchy('listing_locations', $cleanLabels);

          if (count($tids)) {
            // see BMImportCreator, who changes it into a target_id of the field
            $node->set('field_listing_location', ['target_id' => array_pop($tids)]);

            $message = 'SUCCESS - ' . $node->id() . ' LOCATION field_listing_location hierarchy [';
            //$fillLength = max(86 - strlen($message), 0);
            bm_d('', "<span class=\"bgcolor-lightgreen\">" . $message . implode(',', $cleanLabels) . ']</span>');

            // update the table row with the created entry.
            $this->getBMImportCellHandler()->set_LOCATION_to_ExcelTable();
          }
        }
        catch(Exception $e){
          $message = 'FAIL - ' . $node->id() . ' LOCATION field_listing_location not set, missing LAND, PROVINCIE OF STAD';
          bm_d('', "<span class=\"fgcolor-orange\">" . $message  . '</span>');

        }
      }

      // SBI.A.01.4.6.2
      // piggyback update taxonomy
      // piggyback update field_listing_sbi_hierarchy
      if ($name == 'SBI') {
        // save sbi code to taxonomy
        $sbiCodes = explode('.', $row[$name]);
        $sbiArr = $this->getBMImportVocabularyHandler()->add_update_taxonomy_hierarchy('listing_sbi_codes', $sbiCodes);

        // and save to hierarchy field.
        $tid = $this->getBMImportVocabularyHandler()->get_bottom_tid_resembling_hierarchy($sbiCodes, 'listing_sbi_codes');
        if ($node->hasField('field_listing_sbi_hierarchy')) {
          $node->set('field_listing_sbi_hierarchy', ['target_id' => $tid]);
          $message = 'SUCCESS - ' . $node->id() . ' SBI field_listing_sbi_hierarchy [';
          // $fillLength = max(86 - strlen($message), 0);
          bm_d('', "<span class=\"bgcolor-lightgreen\">" . $message . $tid . ']</span>');
        }

      }

      // pass through auto_chain
      if ($name !== '_auto_chain') {
        // validation
        // if row does not support the CSV field skip it.
        if (!isset($row[$name])) {
          $this->BMImport->set_ignoredNamesList($name);
          continue;
        }
        // if yaml doesn't support it, (action: ignored)
        if (isset($fieldDef['action']) && $fieldDef['action'] === 'ignore') {
          $this->BMImport->set_ignoredNamesList($name);
          continue;
        }
      }

      // check entry has a field
      if (!isset($fieldDef['field'])) {
        // SKIP name without further items
        $message = 'ymlKey__node_fields__and_specials:  Error in your YAML. ' . json_encode($fieldDef) . ' should be followed by `name` or `field`. ';
        throw new Exception($message);
      }

      // names given by user, to ignore
      if (isset($this->ignoreNames[$name])) {
        bm_d('', '<span class="bgcolor-violet fgcolor-white"> ' . __LINE__ . ' ' . $name . ' is on IGNORE list and is ignored.</span>');
        continue;// ignore this CSV column
      }

      /**
       * INTERCEPT SPECIAL CASES
       * NOTE PARTLY DUPLICATED AT FIELD SET LEVEL (BMImportCreate)
       *
       * # _auto_chain
       *   means, set a field with a value  given somewhere 'else' caused by a given field value.
       *   * usage 1. when 1 row:column must be set to multiple fields
       *   * usage 2. when one field causes chain reaction in other fields
       *
       *   * example usage 2.
       *   Example when CSV column header is BEZOEK ADRES
       *
       *   goal: we must set adres and type of address to 'visiting address' as well
       *
       * ```
       * - name: BEZOEK ADRES
       *    field: field_listing_address
       *  -name: _auto_chain
       *    field: field_listing_visiting_address
       *    value: 'visiting address'
       * ```
       *   sets it to visiting address, and when value not given, it would have used row[BEZOEK ADRES] as value
       * # prepare_action
       * * add term
       * * add term hierarchy
       * # action
       *
       * * await
       *
       *   value is a name, waits for that name to be resolved and enters the id
       *   Is used for a contact node to be referenced by listing node.
       *   listing awaits the contact nid.
       *
       * * set select
       *  nothing changes, it will set the value of the select
       *  what happens when it is not defines in the field? will save fail?
       *
       * * set id
       *
       *   in combination with `prepare_action: add term`
       *   which sets $value to a list of tids
       *   the first label, becomes a tid in a field
       *
       * * set ids
       *
       *   in combination with `prepare_action: add term` which sets $value to a list of tids
       *   a list if labels which become the tids in a field.
       *
       * * set date
       *
       *  Expects date as 'dd-mm-yyyy' or timestamp and converts to appropriate for field
       *
       *
       * * modify
       *   *  operator google_map_control
       *
       * * append id
       *
       *   handled in BMImportCreator meaning add a tid to the "multiple field"
       * * append
       *
       *   meaning next delta in a multiple
       *
       * * upload_image
       *
       *   name: field of image field, the content is an url which is uploaded and inserted
       */

      // from example, use 'visiting address' or previously used row value
      if ($name == '_auto_chain') {
        $CSV_YML_value = $fieldDef['value'] ?? $prevValue;
      }

      $fieldName = $fieldDef['field'];

      /** various actions:
       * * modify (accompany by operator)
       *
       * @example
       *   * action: modify
       *   * operator: google_map_control lat
       *  sets lowest level
       *
       */
      $action = $fieldDef['action'] ?? NULL;
      //
      $operator = $fieldDef['operator'] ?? NULL;// concat "|"

      // execute before action and modify or prepare actions
      $prepare_action = $fieldDef['prepare_action'] ?? NULL;
      // string type format
      $format = $fieldDef['format'] ?? NULL;// exa. full_html

      // mandatory required vocabulary
      $taxonomy = $fieldDef['taxonomy'] ?? NULL;

      // code en, fr, nl or a csv column name
      $termLanguageId = $fieldDef['termLanguageId'] ?? 'en';
      if (strlen($termLanguageId) > 2) {
        // get language from columns, and when not present assume en
        $termLanguageId = $row[$termLanguageId] ?? 'en';
      }
      //$termLabel = $fieldDef['term_label'] ?? NULL;
      // HANDLE ACTIONS
      $continue = FALSE;

      // use fgcolor-navy for prepare logs
      if ($prepare_action) {

        // note $value, gets passed on to the field setters
        // so "prepare_action" can create a term, and pass the tid to the field

        switch ($prepare_action) {
          // The yaml or row contains term labels
          // term:(from the yml), could be the label (hardcoded) or comma separated list of labels
          // row[name]  could be the label (hardcoded) or comma separated list of labels
          case 'add term':
            if(empty($taxonomy)){
              Drupal::messenger()->addError("Error in config YAML, 'add term' needs to be accompanied by 'taxonomy:' Improve the YML. ");
              break;
            }
            //dsm($row, 'add term');
            //dsm($fieldDef,' fieldDef');
            if (!$CSV_YML_value) {
              $CSV_YML_value = $fieldDef['value'] ?? $row[$name];// hard coded in YAML or from csv
            }
            //dsm($value,'value');

            $values = [];
            // empty term label in yml means, use labels given in row
            $cleanLabels = [];
            $termLabels = explode(',', $CSV_YML_value);
            foreach ($termLabels as $label) {
              $cleanLabels[] = trim($label);
            }
            if ($cleanLabels) {
              foreach ($cleanLabels as $termLabel) {
                // Create a term when not yet present
                // $taxonomy, $termLabel, $termLanguageId
                $term = $this->getBMImportCreator()->create_term_if_notExist($termLabel, $taxonomy, $termLanguageId);
                if ($term) {
                  $values[] = $term->id();
                }
                else {
                  Drupal::messenger()
                    ->addStatus(__LINE__ . '<span class="fgcolor-red"> Could not create/get term ' . $termLabel . ':' . $taxonomy . '</span>');
                  Drupal::messenger()
                    ->addWarning(__LINE__ . ' Could not create/get term ' . $termLabel . ' ' . $taxonomy);
                  break;
                }
                // todo: add icon, color, userLabel, tippy
                bm_d('', "<span class=\"bgcolor-lightgreen\">SUCCESS - set_taxonomy_term('$taxonomy',<b>'".$term->getName().' id:'.$term->id()."'</b>,'$termLanguageId') </span>");
              }
            }
            // add term followed by set id
            $CSV_YML_value = $values;
            // list of tids
            break;

          // means the YML or CSV contains labels in a hierarchy
          // 'Nederland, Groningen, Kropswolde'
          case 'add term hierarchy':
            if(empty($taxonomy)){
              Drupal::messenger()->addError("Error in config YAML, 'add term hierarchy' needs to be accompanied by 'taxonomy:' Improve the YML. ");
              break;
            }
            $hierarchyTermLabels = $row[$name];
            if (!$hierarchyTermLabels) {
              break;
            }
            if(empty($row[$name]))break;
            if(is_numeric($row[$name])){
              // default tid is passed, nothing to prepare.

              break;

            }
            bm_d(self::BM_D_KEY, ' <span class="bgcolor-navy fgcolor-white">BMImport - Add term hierarchy taxonomy: ' . $taxonomy . ' terms:<b>' . $hierarchyTermLabels . '</b></span>');

            // empty term label in yml means, use labels given in row
            $cleanLabels = [];

            $termLabels = explode(',', $hierarchyTermLabels);
            foreach ($termLabels as $label) {
              $cleanLabels[] = trim($label);
            }
            $tids = $this->getBMImportVocabularyHandler()->add_update_taxonomy_hierarchy($taxonomy, $cleanLabels);
            dsm($tids, 'Created this HIERARCHY in ' . $taxonomy);
            if (count($tids)) {
              // see BMImportCreator, who changes it into a target_id of the field
              $CSV_YML_value = array_pop($tids);
            }
            else {
              Drupal::messenger()
                ->addWarning(__LINE__ . ' Could not create/get hierarchy of terms ' . $taxonomy . ':' . $hierarchyTermLabels);
            }
            // value is an array of tids

            break;

          default:
            Drupal::messenger()
              ->addWarning(__LINE__ . 'Unexpected prepare_action value:' . $prepare_action);
        }
      }

      if ($action) {
        switch ($action) {
          case 'await':
            // informs that this field awaits creation of something else.
            $this->BMImport->activeResult->setAwait($name, $fieldName, 'node_fields');
            $continue = TRUE;
            break;
          case 'set select':  // nothing changes, it will set the value of the select
            // what happens when it is not defines in the field? will save fail?
            break;
          // action: set id
          // prepare_action: add term hierarchy
          // taxonomy: listing_location
          case 'set id':
            // in combination with `prepare_action: add term`  which sets $value to a list of tids
            // the first label, becomes a tid in a field
            break;
          case 'set ids':
            // in combination with `prepare_action: add term` which sets $value to a list of tids
            // a list if labels which become the tids in a field.
            break;
          // consists of `modify` and an `operator` to display_result_in_form concatenating columns in a field
          case 'modify':
            $modified = FALSE;
            // field_listing_map where value = [lat=>,lon=>, name=>]
            // in yml as action modify, operator key lat
            if (str_contains($operator, 'google_map_control')) {

              $key = explode(' ', $operator)[1];
              $currentNodeValue = $node->get($fieldName)->getValue();


              if (!$currentNodeValue) {
                // initialize a google maps control object
                $currentNodeValue[] = [
                  'name' => 'Groningen',
                  'lat' => '53.1435452',
                  'lon' => '5.4702615',
                  'zoom' => '9',
                  'type' => 'roadmap',
                  'width' => '100%',
                  'height' => '450px',
                  'marker' => '1',
                  'traffic' => '0',
                  'marker_icon' => '',
                  'controls' => '1',
                  'infowindow' => '',
                ];
              }
              // replace with the CSV value (and change lat, lon to decimal point)
              $currentNodeValue[0][$key] = str_replace(',', '.', $row[$name]);

              // save the whole array at once
              $CSV_YML_value = $currentNodeValue;
              $modified = TRUE;

            }

            // field reviews concat review, date and language
            if (str_contains($operator, 'concat')) {
              $CSV_YML_value = $node->get($fieldName)->getValue();
              $CSV_YML_value = $CSV_YML_value[0]['value'] ?? '';
              $separator = explode(' ', $operator)[1];
              $CSV_YML_value = $CSV_YML_value . $separator . $row[$name];
              $modified = TRUE;
            }

            $action = NULL;// display_result_in_form field handler it is a regular field value
            if (!$modified) {
              bm_d('', '<span class="fgcolor-red">' . $name . ' Exception: Yaml key "modified" without valid "operator" key');
              continue 2;// goto next item
            }
            break;// store value to field
          case 'append id':
            // handled in BMImportCreator
            // meaning add a tid to the multiple field
            break;
          case 'append':
            // meaning next delta in a multiple
            break;
          // name, field of image field, the content is an url
          // which is uploaded and inserted
          case 'upload_image':
            // get image_id to put into value
            $fid = $this->getBMImportImagesHandler()->upload_and_set_image_fid($node, $fieldName, $row[$name]);
            $CSV_YML_value = $fid;
            if ($CSV_YML_value) {
              break;
            }
            continue 2;// skip field, when no fid is found.

          // csv contains image string in format:
          // u={url};w={width};h={height}, next image
          // from Google places export for example
          // this is a hard coded function to set listing_image to first image
          // and listing_gallery to the other images
          // the function retrieves the images from the given url and adds them as fid.
          case 'image gallery in u_w_h format':
            bm_d('', '<span class="fgcolor-blue"><b>-------- ADD IMAGE AND GALLERY ----------</b></span>');
            $this->getBMImportImagesHandler()->import_u_w_h__listing_image__listing_gallery($row[$name], $node);
            continue 2;// skip other processing

          // todo: append                   (reviews)
          // todo: clear_first              (reviews)
          default:
            Drupal::messenger()
              ->addWarning(__LINE__ . ' <span class="fgcolor-red">Unknown action: ' . $action . '</span>');
        }
        if ($continue) {
          continue;
        }
      }


      $actions = [$action];
      // extra field information
      if ($format) {
        $actions[] = $format;
      }
      // not in rows:
      // $name == '_auto_chain'
      // $name == '__contact' `__` names refer to an id: __contact in an await action
      //
      if ($name != '_auto_chain' && !str_starts_with($name, '__')) {
        if ($CSV_YML_value === NULL) {// not set in YAML, or actions, so use row value
          if (!isset($row[$name])) {
            //                  dsm($row,' Looking for '.$name);
            Drupal::messenger()
              ->addStatus('<div  class="bgcolor-yellow">' . __LINE__ . ' Row does not contain YAML field: ' . $name . '</div>');
            Drupal::messenger()->addWarning(__LINE__ . ' Row does not contain YAML field: ' . $name);
            continue;
          }
          $prevValue = $CSV_YML_value;// save as previous value in case an auto_chain follows
          // Drupal::messenger()->addStatus(' ---- hier zit een issue --- '.$name.' ---'.json_encode($row[$name]));
          $CSV_YML_value = $row[$name];
        }
      }


      try {
        $node = $this->BMImport->activeResult->getMainEntity();
        if($fieldName =='field_listing_category') {
          bm_d('', $CSV_YML_value, ' fieldName: ' . $fieldName.' VALUE?');
          if($CSV_YML_value==='') {
            throw new exception($fieldName. ' has no value? set');
          }

        }
        $this->getBMImportCreator()->set_node_fieldValues($node, $fieldName, $CSV_YML_value, [$action]);


        $prevValue = $CSV_YML_value;
        // display result with max. length line
        $message = 'SUCCESS - ' . $node->id() . " <b>$name</b> $fieldName $action [";
        $fillLength = max(86 - strlen($message), 0);
        bm_d('', "<span class=\"bgcolor-lightgreen\">" . $message . substr(json_encode($CSV_YML_value), 0, $fillLength) . ']</span>');

      } catch (Exception $e) {
        bm_d('','<span class="fgcolor-orange">' . __LINE__ . ' Nid:' . $node->id() . ' set(<b>' . $fieldName . '</b>, ' . json_encode($CSV_YML_value) . ',' . $action . ') - FAILED <br/>' . $e->getMessage() . '</span>');

        Drupal::messenger()
          ->addError(__LINE__ . 'Nid:' . $node->id() . ' set_node_fieldValue(<b>' . $fieldName . '</b>, ' . json_encode($CSV_YML_value) . ',' . $action . ') - FAILED');
      }
    }
  }

  /**
   * # ymlKey__node_paragraph_fields
   *  ```
   *  node_paragraph_fields:
   *  - field=>'field_listing_paragraph',
   *    paragraph=>'business paragraph'
   *    name=>'business_paragraph'
   *    fields=>[
   *      0=> [name=>'KVK nummer',field=> field_para_business_coc]
   *      1=> ...
   *  ```
   *
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws Exception
   */
  private function ymlKey__node_paragraph_fields($node, $row, $paragraphsFieldsEntries): void {
    bm_d('', '<div class="bgcolor-blue fgcolor-white"><b>-------- ymlKey Node PARAGRAPH fields ----------</b></div>');
    //    dsm($row,' row');
    //    dsm($paragraphsFieldsEntries,__LINE__ .'paragraphs fields entries');

    foreach ($paragraphsFieldsEntries as $subEntry) {
      //dsm($subEntry,__LINE__ . ' subEntry');
      if (!isset($subEntry['field'])) {
        continue;
      }

      $nodeFieldName = $subEntry['field'];  // field_listing_specifics_para

      if(in_array($nodeFieldName,['SBI','field_para_business_companyname'])){
        bm_d('',$nodeFieldName,'BMImportTodoWithColumns 1 SKIPPED ','log');
      }

      Drupal::logger('bmImport')->debug(__LINE__ . " ymlKey__node_paragraph_fields($nodeFieldName)");


      $paragraphDefinition = $subEntry['paragraph'];  //
      $paragraphType = $paragraphDefinition['name'] ?? NULL;  // finadvisors_listing_paragraph
      if (!$paragraphType) {
        $message = ' node_paragraph_fields: YAML paragraph definition or name not defined? ' . json_encode($paragraphDefinition);
        bm_d('', $message);
        Drupal::messenger()->addWarning(__LINE__ . $message);
        return;
      }

      $paragraph = $this->getBMImportCreator()->addNodeParagraphIfNotExists($node, $nodeFieldName, $paragraphType);
      $pid = $paragraph->id();

      bm_d(self::BM_D_KEY, '<div class="bgcolor-green fgcolor-white">___________________' . $paragraphType . ' created or reusing: ' . $pid . ' SUCCESS</div>');

      if (!isset($paragraphDefinition['fields'])) {
        bm_d(self::BM_D_KEY, ' node_paragraph_fields: YAML was expecting key "fields" for ' . json_encode($paragraphDefinition));

        Drupal::messenger()
          ->addWarning(__LINE__ . ' node_paragraph_fields: YAML was expecting key "fields" for ' . json_encode($paragraphDefinition));
        return;
      }

      $paraFields = $paragraphDefinition['fields'];
      if ($this->BMImport->verbose) {
        ///  dsm($paraFields, __LINE__ . ' List of paragraph fields todo.');
      }
      foreach ($paraFields as $fieldDef) {
        //           - name: PRODUCTEN
        //             field: field_para_fa_products
        //             action: add term
        //             taxonomy: financial_advisor_products
        //             # term: Dienst  // or enable with a term name as value
        //             termLanguageId: nl
        if ($this->BMImport->verbose) {
          ///  dsm($fieldDef, __LINE__ . 'expecting name, field, action, taxonomy, ');
        }

        // - name: expected
        if (!isset($fieldDef['name']) || !isset($fieldDef['field'])) {
          bm_d(self::BM_D_KEY, ' node_paragraph_fields: YAML was expecting key "name" or "field" for ' . json_encode($fieldDef) . ' - SKIPPED');

          Drupal::messenger()
            ->addWarning(__LINE__ . ' node_paragraph_fields: YAML was expecting key "name" or "field" for ' . json_encode($fieldDef));
          continue;
        }

        $name = $fieldDef['name'];

        if(in_array($name,['SBI','field_para_business_companyname'])){
          bm_d('',$name,'BMImportTodoWithColumns SKIPPED ','log');

        }

        // names given by user, to ignore
        if (isset($this->ignoreNames[$name])) {
          bm_d('', '<span class="bgcolor-violet fgcolor-white"> ' . __LINE__ . '  ' . $name . ' is on IGNORE list and is ignored.</span>');
          continue;// ignore this CSV column
        }


        $fieldName = $fieldDef['field'];
        $action = $fieldDef['action'] ?? NULL;
        $prepare_action = $fieldDef['prepare_action'] ?? NULL;
        $operator = $fieldDef['operator'] ?? NULL;
        $format = $fieldDef['format'] ?? NULL;// a rich text format
        $taxonomy = $fieldDef['taxonomy'] ?? NULL;
        $termLanguageId = $fieldDef['termLanguageId'] ?? NULL;
        $termLabel = $fieldDef['term'] ?? NULL; // a hard coded term label
        $value = $fieldDef['value'] ?? NULL; // a hard coded TID or value for a field


        bm_d(self::BM_D_KEY_DETAILS, $this->inColor(' YAML definition: ' . $name . ' ' . $fieldName . ' ' . $action . ' ' . $format));


        // INTERCEPT SPECIAL HANDLING
        switch ($name) {
          //# _auto_chain means, set a field with a value
          //# somewhere 'else' caused by a given field value
          //# Example when column header is BEZOEK ADRES
          //# we must set adres and type of address to 'visiting address'
          //# - name: BEZOEK ADRES
          //    field: field_listing_address
          //  -name: _auto_chain
          //    field: field_listing_visiting_address
          //    value: 'visiting address'
          // sets it to visiting address
          // when value not given, it would have used row[BEZOEK ADRES] as value

          case '_auto_chain':
            $value = $fieldDef['value'];
            break;

          case 'SBI':
            // SBI.A.01.4.6.2
            // piggyback update taxonomy
            // piggyback update field_listing_sbi_hierarchy

            // save sbi code to taxonomy
            $sbiCodes = explode('.', $row[$name]);
            $sbiArr = $this->getBMImportVocabularyHandler()
              ->add_update_taxonomy_hierarchy('listing_sbi_codes', $sbiCodes);

            // and save to hierarchy field.
            $value = $this->getBMImportVocabularyHandler()
              ->get_bottom_tid_resembling_hierarchy($sbiCodes, 'listing_sbi_codes');
            break;
          default:
        }


        if ($value === NULL) {
          // no hard coded value, then look for row value
          // check: PRODUCTEN
          if (!isset($row[$name])) {

            bm_d(self::BM_D_KEY, ' Row does not have YAML-field requested in node_paragraph_fields:[<b>' . $name . '</b>]');

            Drupal::messenger()->addWarning(__LINE__ . ' Row does not have YAML p.field [<b>' . $name . '</b>]');
            continue;
          }
          $value = $row[$name]; // comma separate list of producten or tid
        }
        // Value hard coded or from row

        // look for special actions

        if ($prepare_action) {
          switch ($prepare_action) {
            // means the yaml or row contains term labels
            // term:(from the yml), could be the label (hardcoded) or comma separated list of labels
            // row[name]  could be the label (hardcoded) or comma separated list of labels
            case 'add term':
              $values = [];

              // empty term label in yml means, use labels given in row
              $cleanLabels = [];
              if (empty($termLabel)) { // defined in row
                $termLabels = explode(',', $value);
                foreach ($termLabels as $label) {
                  $cl = trim($label);
                  if (!empty($cl)) {
                    $cleanLabels[] = $cl;
                  }
                }
                //            dsm($cleanLabels,'cleanLabel');
              }
              else { // defined in YML
                $cleanLabels = explode(',', $termLabel);
              }
              if ($cleanLabels) {

                foreach ($cleanLabels as $termLabel) {

                  // Create a term when not yet present
                  // $taxonomy, $termLabel, $termLanguageId
                  // dsm($termLabel,'check and create term');
                  $term = $this->getBMImportCreator()->create_term_if_notExist($termLabel, $taxonomy, $termLanguageId);

                  if ($term) {
                    $values[] = ['target_id' => $term->id()];
                  }
                  else {
                    Drupal::messenger()
                      ->addWarning(__LINE__ . ' Could not create/get term ' . $termLabel . ' ' . $taxonomy);
                    break;
                  }
                  // todo add icon, color, userLabel, tippy
                  Drupal::messenger()
                    ->addStatus('<div class="bgcolor-lightgreen">' . __LINE__ . " set_taxonomy_term('$taxonomy',<b>'$termLabel'</b>,'$termLanguageId') - SUCCESS</div>", TRUE);
                }

                $value = $values;

              }
              break;
            // 'Nederland, Groningen, Kropswolde'
            case 'add term hierarchy':
              $hierarchyTermLabels = $row[$name];
              if (!$hierarchyTermLabels) {
                break;
              }

              Drupal::messenger()
                ->addStatus(__LINE__ . 'action: add term hierarchy taxo: ' . $taxonomy . ' terms:' . $hierarchyTermLabels);

              // empty term label in yml means, use labels given in row
              $cleanLabels = [];

              $termLabels = explode(',', $hierarchyTermLabels);
              foreach ($termLabels as $label) {
                $cleanLabels[] = trim($label);
              }


              $tids = $this->getBMImportVocabularyHandler()->add_update_taxonomy_hierarchy($taxonomy, $cleanLabels);
              if (count($tids)) {
                // see BMImportCreator, who changes it into a target_id of the field
                $value = array_pop($tids);
              }
              else {
                Drupal::messenger()
                  ->addWarning(__LINE__ . ' Could not create/get hierarchy of terms ' . $taxonomy . ':' . $hierarchyTermLabels);
              }
              break;
            default:
              Drupal::messenger()
                ->addWarning(__LINE__ . 'Unexpected prepare_action value:' . $prepare_action);
          }
        }
        if ($action) {
          switch ($action) {
            case 'modify':
              $value = $paragraph->get($fieldName)->getValue();
              if (isset($value[0]['value'])) {
                $value = $value[0]['value'];
                if (str_contains($operator, 'concat')) {
                  $separator = explode(' ', $operator)[1];
                  $value = $value . $separator . $row[$name];
                }
                $action = NULL;// display_result_in_form field handler it is a regular field value
              }
              else {
                bm_d(self::BM_D_KEY, 'A problem in the yaml modify, ' . $fieldName . ' has not value in the paragraph');
                continue 2;
              }
              break;
            case 'append':
              // let it pass through to field handler which handles the appending
              break;
            // prepare so the terms exists

            case 'upload_image':
              // get image_id to put into value
              $fid = $this->getBMImportImagesHandler()->upload_and_set_image_fid($node, $fieldName, $row[$name]);
              $value = $fid;
              if ($value) {
                break;
              }
              continue 2;// when no fid found, skip setting the field.
            // todo: append
            // todo: clear_first
            // todo: concat "|"
            default:
              bm_d(self::BM_D_KEY, ' Unexpected value for node_paragraph_fields action. Value: ' . $action);
            //throw new \Exception(__LINE__ . ' Unexpected value for node_paragraph_fields action. Value: ' . $action);
          }
        }

        $actions = [$action];
        if ($format) {
          $actions[] = $format;
        }

        // WE HAVE THE VALUE LETS GO SET IT

        // set field values
        $this->getBMImportParagraphHandler()->set_paragraph_fieldValues($pid, $fieldName, $value,[$action], true);

        $node->save();
        // $fn = $node->get($nodeFieldName);
        // $target_id = $fn->getValue()[0]['target_id'];
        //      dsm($target_id,'ymlKey__node_paragraph_fields target_id');
        $paragraph = Paragraph::load($pid);
        //      dsm($paragraph->id(),'ymlKey__node_paragraph_fields paragraph id');

        $message = 'SUCCESS - ' . $pid . " $name $fieldName $action [";
        $fillLength = max(86 - strlen($message), 0);
        bm_d('', "<span class=\"bgcolor-lightgreen\">" . $message . substr(json_encode($value), 0, $fillLength) . ']</span>');

      }

    }

  }

  /**
   * A handler which sets table cells.
   *

   *
   * @return BMImportCellHandler
   */
  public function getBMImportCellHandler(): BMImportCellHandler {

    if (!isset($this->BMImportCellHandler)) {
      $this->BMImportCellHandler = new BMImportCellHandler();
    }
    return $this->BMImportCellHandler;
  }

  /**
   * @return BMImportVocabularyHandler
   */
  public function getBMImportVocabularyHandler(): BMImportVocabularyHandler {
    if (!isset($this->BMImportVocabularyHandler)) {
      bm_d('', '', "instantiating " . __FUNCTION__);
      $this->BMImportVocabularyHandler = new BMImportVocabularyHandler();
    }
    return $this->BMImportVocabularyHandler;
  }

  public function getBMImportNodeHandler(): BMImportNodeHandler {


    if (!isset($this->BMImportNodeHandler)) {
      $this->BMImportNodeHandler = new BMImportNodeHandler();
    }
    return $this->BMImportNodeHandler;
  }

  /**
   * @return BMImportParagraphHandler
   */
  public function getBMImportParagraphHandler(): BMImportParagraphHandler {
    bm_d('', '', 'instantiating ' . __FUNCTION__);

    if (!isset($this->BMImportParagraphHandler)) {
      $this->BMImportParagraphHandler = new BMImportParagraphHandler();
    }
    return $this->BMImportParagraphHandler;
  }

  private function getBMImportImagesHandler(): BMImportImagesHandler {
    bm_d('', '', 'instantiating ' . __FUNCTION__);

    if (!isset($this->BMImportImagesHandler)) {
      $this->BMImportImagesHandler = new BMImportImagesHandler();
    }
    return $this->BMImportImagesHandler;
  }

  /**
   * @return BMImportCreator
   */
  public function getBMImportCreator(): BMImportCreator {
    bm_d('', '', 'instantiating ' . __FUNCTION__);

    if (!isset($this->BMImportCreator)) {
      $this->BMImportCreator = new BMImportCreator();
    }
    return $this->BMImportCreator;
  }

  /**
   * @return BMImportFieldCreator
   */
  public function getBMImportFieldCreator(): BMImportFieldCreator {
    if (!isset($this->BMImportFieldCreator)) {
      bm_d('', '', 'instantiating ' . __FUNCTION__);

      $this->BMImportFieldCreator = new BMImportFieldCreator();
    }

    return $this->BMImportFieldCreator;
  }

}
