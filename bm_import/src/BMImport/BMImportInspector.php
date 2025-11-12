<?php

namespace Drupal\bm_import\BMImport;

use Drupal;
use Drupal\bm_debug\BMDebugInfo\BMDebugInfo;
use Drupal\bm_import\BMImportHandlers\BMImportYMLHandler;
use Drupal\Core\Database\Database;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Exception;

/**
 * * This class has the methods to check the Excel rows against itself and against existing nodes.
 * * It verifies rows already present as node
 * * It verifies Excel cell properties
 *
 */
class BMImportInspector {

  const BM_D_KEY = 'BMImportInspector-algemeen';

  const BM_D_KEY_DETAILS = 'BMImportInspector-details';

  static public function bm_d_callback($key, $BMDbgInfo): BMDebugInfo|false {

    if ($key == self::BM_D_KEY) {
      $BMDbgInfo->module = 'bm_import inspector';
      $BMDbgInfo->description = 'Debug BMImport Inspector - summary';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    if ($key == self::BM_D_KEY_DETAILS) {
      $BMDbgInfo->module = 'bm_import inspector';
      $BMDbgInfo->description = 'Debug BMImport Inspector - details.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    return FALSE;
  }

  static BMImportInspector $instance;

  private BMImport $BMImport;

  private array $nodeLocatorPreparedQueries = [];

  // every sql does a match with Excel columns
  // this table has the ExcelColumn names to place the found nids under
  // the names can be duplicate, the order is important and follows the yml order.
  // the reason explains the match.

  private array $matchColumnNames = [];

  private array $matchColumnReason = [];

  private array $existingNodesMatchTable;

  public function __construct(BMImport $BMImport) {
    $this->BMImport = $BMImport;
    $this->existingNodesMatchTable = [];
  }
  /**
   * Entry point for button inspect
   * called from management form
   *
   * @param $rowNums
   * @param $chunkNo
   *
   * @return void
   */
  public function rowsIterator_findExistingNodeIdsForRows($rowNums, $chunkNo): void {
    $ExcelContent = &$this->BMImport->get_excelContent();

    if ($chunkNo === 0) {
      // gets reinitialised in get_nodeLocatorPreparedQueries
      $this->existingNodesMatchTable = [];
      $this->set_cache_existingNodesMatchTable();


      bm_d('',$ExcelContent['header'],'BMInspector excel content header','dsm');

      // prepare queries
      $this->get_nodeLocatorPreparedQueries($ExcelContent['header']);
      bm_d('',$this->nodeLocatorPreparedQueries,'executeMatchQueries prepared queries','dsm');

      // add fields which have a test query connected
      $this->addFieldsToExistingNodeMatchTable();
    }
    else {
      // continue where we left of
      $this->existingNodesMatchTable = $this->get_cached_existingNodesMatchTable();
    }

    foreach ($rowNums as $rowNum) {
// debug
if($rowNum>6)break;
      $itemTime = microtime(TRUE);
      try {

        $this->executeMatchQueries($ExcelContent['options'][$rowNum]);

bm_d('',$this->existingNodesMatchTable,'existingNodesMatchTable existingNodesMatchTable','dsm');

        bm_d('', '', 'BMImportInspect. r:[' . $rowNum . '] time:' . $this->duration($itemTime), 'log');
      } catch (Exception $e) {
        bm_d('', '', 'BMImport-existing FAIL nids? row:[' . $rowNum . '] time:' . $this->duration($itemTime), 'log');
        bm_d('', $e->getMessage(), 'exception');
      }
    }


    $this->set_cache_existingNodesMatchTable();
    $this->BMImport->set_cache_ExcelContent();
  }

  /**
   * get_nodeLocatorPreparedQueries
   *
   * * Optimize the locator queries as they are registered in the YAM
   *  as the execution is repeated for every row.
   * * The ExcelColumn is the capitalized name of the Excel.
   *
   * *Example locateQueries:*
   *
   * locateQueries:
   *
   * ```
   *    sql: select entity_id as nid from paragraph__field_para_fa_licensenumber where
   *    paragraph__field_para_fa_licensenumber_value = :AFM_NUMMER
   * ```
   *
   * * variableKey: AFM_NUMMER
   * * ExcelColumn: 'AFM NUMMER'
   *
   * ```
   *   sql: select entity_id as nid from paragraph__field_para_business_registration where
   *   paragraph__field_para_business_registration_value = :KVK_NUMMER
   * ```
   *
   * *  variableKey: KVK_NUMMER
   * * ExcelColumn: 'KVK NUMMER'
   *
   * ```
   * sql: select entity_id as nid from node__field_listing_phone where node__field_listing_phone_value = :TELEFOON
   * ```
   *
   * * variableKey: TELEFOON
   * * ExcelColumn: TELEFOON
   *
   * Add more where possible.
   *
   * @param array $header the Excel header
   *
   * @return void
   */
  private function get_nodeLocatorPreparedQueries(array $header): void {
    bm_d('',__FUNCTION__,'','log');

    // set $this->nodeLocatorPreparedQueries
  //  if($this->get_cached_nodeLocatorPreparedQueries())return;

    // Load queries from the settings
    $locatorQueries = $this->BMImport->type_settings_yml['todo_with_rows']['locateQueries'] ?? [];
    $preparedQueries = [];

    bm_d(self::BM_D_KEY_DETAILS,$locatorQueries,'locatorQueries','dsm');

    // $prepared = [sql, variableKey:TELEFOON, excelColumn:0162716661, reason: TELEFOON matches with ..]
    foreach ($locatorQueries as $n => $prepared) {


      bm_d('',' Inspector is preparing locator query ['.$n.']','','log');

      // Check if the necessary Excel column exists in the row
      if (!isset($header[$prepared[BMImportYMLHandler::COLUMN_NAME]])) {
        bm_d('','<span class="fgcolor-grey">No this qry can not be run: </span>'.$prepared[BMImportYMLHandler::REASON]);
      }
      else {
        bm_d('','<span class="fgcolor-green">Yes this qry can run:</span>'.$prepared[BMImportYMLHandler::REASON]);

        // Add valid query to the prepared list
        $preparedQueries[$n] = $prepared;

        // the Excel column the query uses in finding a match
        $this->matchColumnNames[] = $prepared[BMImportYMLHandler::COLUMN_NAME] . '_' . $n;

        if (isset($prepared['reason'])) {
          // and reason
          $this->matchColumnReason[] = $prepared['reason'] ?? '';
        }
        else {
          $this->matchColumnReason[] = 'not given';
        }
      }
    }

    // Assign the valid queries back to the class property
    $this->nodeLocatorPreparedQueries = $preparedQueries;

    bm_d('',$this->nodeLocatorPreparedQueries,'These are the "node locator" prepared queries','dsm');

    // valid until excel changes sheet or file
    $this->set_cache_nodeLocatorPreparedQueries();
  }
  private function addFieldsToExistingNodeMatchTable(): void {
    // when it is the first time initialization of the 'Excel' like table
    if (count($this->existingNodesMatchTable) === 0) {
      // _INDEX, BM_ID, BEDRIJFSNAAM,TELEFOON_1,TELEFOON_2, KVK_3, etc.
      // where the columns are the columns used in the match.

      // set HEADERS
      $this->existingNodesMatchTable[] = array_merge(['_INDEX', 'BM_ID', 'BEDRIJFSNAAM'], $this->matchColumnNames);
      // set reason explanation
      $this->existingNodesMatchTable[] = array_merge(['', '', ''], $this->matchColumnReason);
      // ready to add results
    }
  }
  /**
   *  find Existing Node Ids for row
   * * finds potential existing nodes
   * * updates _EXISTS
   *
   * @throws Exception
   */
  private function executeMatchQueries(array &$row): void {
    // Check if the row is valid before proceeding
    if (!$row) {
      throw new Exception('No row was passed to the method "executeMatchQueries".');
    }

    if (empty($this->existingNodesMatchTable[0])) {
      return;
    }

    $nids = [];

    $columns = count($this->existingNodesMatchTable[0]);

    // Initialize the new row with initial values
    $newRow = [
      $row['_INDEX'] ?? 0,
      $row['BM_ID'] ?? 0,
      $row['BEDRIJFSNAAM'] ?? '',
    ];

    // Add empty values for the remaining columns
    for ($i = 3; $i < $columns; $i++) {
      $newRow[] = '';
    }

    $foundSomeNids = FALSE;

    // ITERATE OVER THE PREPARED QUERIES AND EXECUTE THEM
    // $prepared = [sql, variableKey:TELEFOON, excelColumn:0162716661, reason: TELEFOON matches with ..]
    foreach ($this->nodeLocatorPreparedQueries as $n => $prepared) {

      if (!isset($row[$prepared[BMImportYMLHandler::COLUMN_NAME]])) {
        continue;
      }
      if(empty($row[$prepared[BMImportYMLHandler::COLUMN_NAME]])){
        continue;
      }
      $queryVariables = [$prepared['variableKey'] => $row[$prepared[BMImportYMLHandler::COLUMN_NAME]]];


      // Execute the query
      $result = Database::getConnection()->query($prepared[BMImportYMLHandler::SQL], $queryVariables);
      $foundNids = $result->fetchCol();

      // display the query
      if(function_exists('bm_d_sql')) {
        bm_d_sql($foundNids,$prepared[BMImportYMLHandler::SQL], $queryVariables);
      }


      // If matching nodes are found, log the results
      if (!empty($foundNids)) {
        $foundSomeNids = TRUE;
        $nids = array_merge($nids, $foundNids);  // Accumulate NIDs
        $uniqueNids = array_unique($nids);
        sort($uniqueNids);
        $newRow[3 + $n] = implode(', ', $uniqueNids); // Place the NIDs in the corresponding column

        $message =          '<span class="fgcolor-red">'.'row:' . $row['_INDEX'] . ' nid:' . $row['BM_ID'] . ' ' . $row['BEDRIJFSNAAM'] .
          ' Match found: ' . $prepared[BMImportYMLHandler::COLUMN_NAME] . ' = ' . $row[$prepared[BMImportYMLHandler::COLUMN_NAME]] .
          ' as nodes: ' . implode(', ', $uniqueNids).'</span>';

        Drupal::messenger()->addStatus(Markup::create($message), TRUE);

      }
      else {
        $newRow[3 + $n] = '';
      }
    }

    // Push the new row to the match table
    if ($foundSomeNids) {
      $this->existingNodesMatchTable[] = $newRow;
    }

    // Ensure the NIDs are unique and assign them back to the row
    $row['_EXISTS'] = implode(',', array_unique($nids));

  }

  private function get_cached_nodeLocatorPreparedQueries():bool{
    $cache = Drupal::cache('bluemarloc')->get('BMImportInspector');
    if ($cache !== FALSE) {
      if (!empty($cache->data['nodeLocatorPreparedQueries'])) {
        $this->nodeLocatorPreparedQueries = $cache->data['nodeLocatorPreparedQueries'];
        return true;
      }
    }
    return FALSE;
  }
  /**
   * Cache prepared queries
   *
   * @return void
   */
  private function set_cache_nodeLocatorPreparedQueries(): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImportInspector');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    $cache['nodeLocatorPreparedQueries'] = $this->nodeLocatorPreparedQueries;
    Drupal::cache('bluemarloc')->set('BMImportInspector', $cache);
  }

  /**
   * # bm_inspector_clear_cache
   * When Excel file change, this cache should be cleared
   * @return void
   */
  static public function bm_inspector_clear_cache(): void {
    bm_d('','Inspector cache has been cleared');
    Drupal::cache('bluemarloc')->removeBin('BMImportInspector');

  }

  /**
   * # set existingNodesMatchTable to cache
   * set_cache_existingNodesMatchTable
   *
   * @return void
   */
  private function set_cache_existingNodesMatchTable(): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImportInspector');
    if ($cache !== FALSE) {
      $data = $cache->data;
    }
    $data['existingNodesMatchTable'] = $this->existingNodesMatchTable;
    Drupal::cache('bluemarloc')->set('BMImportInspector', $data);
  }

  /**
   * get cached existing nodes match table
   * @return array
   */
  public function &get_cached_existingNodesMatchTable() {
    // we need it later to do problem finding
    $cache = Drupal::cache('bluemarloc')->get('BMImportInspector');
    if ($cache) {
      $cache = $cache->data;
    }
    // contains row and header, later separated
    if (isset($cache['existingNodesMatchTable'])) {
      $this->existingNodesMatchTable = $cache['existingNodesMatchTable'];
    }
    return $this->existingNodesMatchTable;
  }

  private function duration($time): float {
    return round(microtime(TRUE) - $time, 2);
  }

  /**
   * static method to get the instance for dynamic properties of this class
   *
   * @param BMImport|null $BMImport $BMImport
   *
   * @return BMImportInspector
   */
  static public function get(BMImport $BMImport = NULL): BMImportInspector {
    if (!isset(BMImportInspector::$instance)) {
      BMImportInspector::$instance = new BMImportInspector($BMImport);
    }
    return BMImportInspector::$instance;
  }

}
