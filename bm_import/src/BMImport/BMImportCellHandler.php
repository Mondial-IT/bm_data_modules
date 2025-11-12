<?php

namespace Drupal\bm_import\BMImport;

use Drupal;
use Drupal\taxonomy\Entity\Term;

/**
 * ## Class BMImportCellHandler
 *
 * Provides methods to handle and modify table cell values for an Excel import table in BMImport.
 * This class interacts with the BMImport instance and updates table cells based on various conditions.
 *
 * ### Responsibilities:
 * - Initializes and handles rows from the Excel table.
 * - Updates table cells with specific values (e.g., `CANVASS`, `CATEGORY`, `ETL`).
 * - Handles hierarchical taxonomy terms and their representation.
 * - Updates the BMImport instance with modified table content.
 *
 * ### Main Methods:
 *
 * - **initRow($BMImport, $rowNum)**
 *   Initializes the handler with a specific row from the BMImport's Excel content.
 *
 * - **set_nids_to_EXISTS_in_ExcelTable(array $nids)**
 *   Updates the `_EXISTS` column in the table with the provided node IDs.
 *
 * - **set_CANVASS_to_ExcelTable($canvassTid)**
 *   Sets the `CANVASS` column with a hierarchical label based on the given taxonomy term ID.
 *
 * - **set_ETL_to_ExcelTable($etlDefaultTid)**
 *   Updates the `ETL` column with a default taxonomy term ID if not already set.
 *
 * - **set_CATEGORY_to_ExcelTable($defaultTid)**
 *   Assigns a value to the `CATEGORY` column based on the selected taxonomy term ID.
 *
 * - **set_LOCATION_to_ExcelTable()**
 *   Sets the `LOCATION` column using concatenated values from `LAND`, `PROVINCIE`, and `STAD`.
 *
 * - **set_BM_ID($nid)**
 *   Updates the `BM_ID` column with the node ID of a created node.
 *
 * - **get_TermHierarchy_Labels(Term $term)**
 *   Retrieves a hierarchical label string for a given taxonomy term.
 *
 * - **update_ExcelTable()**
 *   Updates the BMImport instance with modified Excel table content.
 */
class BMImportCellHandler {
  // row from the tableSelect
  private array $row;

  private int $rowNum;

  private mixed $BMImport;

  private array $ExcelContent;

  public function __construct() {
    $this->row = [];
    $this->rowNum = 0;
  }

  public function initRow($BMImport, $rowNum): void {
    $this->rowNum=$rowNum;
    $this->BMImport = $BMImport;
    $this->ExcelContent=$this->BMImport->get_ExcelContent();
    if(!empty($this->ExcelContent['options'][$rowNum])) {
      $this->row = $this->ExcelContent['options'][$rowNum];
    }
    // why clear it?
//    if (isset($this->row['_EXISTS'])) {
//      $this->row['_EXISTS'] = '';
//    }

  }
  /**
   * # set_nids_to_EXISTS_in_ExcelTable
   * executeMatchQueries and set the result in column EXISTS.
   *
   * @param array $nids
   *
   * @return void
   */
  public function set_nids_to_EXISTS_in_ExcelTable(array $nids): void {
    sort($nids);
    $this->row['_EXISTS'] = implode(',', $nids);
    if ($nids) {
      bm_d(BMImport::BM_D_KEY, ' EXISTING NODE ' . implode(',', $nids));
      Drupal::messenger()->addWarning(' EXISTING NODE ' . implode(',', $nids));
    }
    $this->update_ExcelTable();
  }
  /**
   * # get_TermHierarchy_Labels
   * Get "labelGP, labelP, labelC" given Term
   * @param Term $term
   *
   * @return string
   */
  private function get_TermHierarchy_Labels(Term $term): string {
    $labels = [];

    // Loop through the term hierarchy.
    while ($term) {
      $labels[] = $term->label();

      // Load the parent term, if any.
      $parent_tid = $term->get('parent')->target_id;
      $term = $parent_tid ? Term::load($parent_tid) : null;
    }

    // Return labels concatenated with commas.
    return implode(', ', array_reverse($labels));
  }
  /**
   * use canvass set in panel when no canvass is given.
   * Update table with company, campaign, canvass labels for next time imports
   *
   * @param $canvassTid
   *
   * @return void
   */
 public function set_CANVASS_to_ExcelTable($canvassTid): void {

    if (isset($this->row['CANVASS'])) {
      if (trim($this->row['CANVASS']) == '' || $this->row['CANVASS'] == '0') {
        $canvassTerm = Term::load($canvassTid);

        if ($canvassTerm) {
          // Build the hierarchy label string.
          $this->row['CANVASS'] = $this->get_TermHierarchy_Labels($canvassTerm);
        } else {
          Drupal::logger('bm_rinkel')->error('Could not load term with TID: ' . $canvassTid);
          $this->row['CANVASS'] = t('Unknown');
        }
        $this->update_ExcelTable();
      }
    }

  }
  /**
   * use the default ETL tid, when not given in the csv row
   * @param $etlDefaultTid
   *
   * @return void
   */
  public function set_ETL_to_ExcelTable($etlDefaultTid): void {
    if (isset($this->row['ETL'])) {
     $this->row['ETL'] = $etlDefaultTid;
      $this->update_ExcelTable();
    }
  }
  /**
   * Set row category to value selected in UI select box.
   *
   * todo mit jbx 240908 there is a problem atm with having CATEGORY and CATEGORY
   * only one is to remain, the other to be removed.
   * @param $defaultTid
   *
   * @return void
   */
  public function set_CATEGORY_to_ExcelTable($defaultTid): void {
    if(!empty($defaultTid)) {
      if (isset($this->row['CATEGORY'])) {
          $this->row['CATEGORY'] = $defaultTid;
          $this->update_ExcelTable();
      }else{
        dsm($this->row,'there is no CATEGORY in row');
      }
    }
  }

  /**
   * # set_LOCATION_to_ExcelTable
   *
   * when a location has been created and the cell is present in the table
   *  update the table and display the location chain.
   * @return void
   */
  public function set_LOCATION_to_ExcelTable(): void {
    if (isset($this->row['LOCATION'])) {
      if (isset($this->row['LAND'], $this->row['PROVINCIE'], $this->row['STAD'])) {
        $this->row['LOCATION'] = $this->row['LAND'] . ',' . $this->row['PROVINCIE'] . ',' . $this->row['STAD'];
      }
    }
    $this->update_ExcelTable();
  }

  /**
   * # update_ExcelTable
   *
   * Update the cached Excel table with the 'new information'
   * @return void
   */
  private function update_ExcelTable(): void {
    $this->ExcelContent['options'][$this->rowNum]= $this->row;
    $this->BMImport->ExcelContent=$this->ExcelContent;
    $this->BMImport->set_cache_ExcelContent($this->ExcelContent);

  }
  /**
   * Display the nid of the created node when the row has a bm_id column.
   * @param $nid
   *
   * @return void
   */
  public function set_BM_ID($nid): void {

   // bm_d('',$this->row,'set bm id row','dsm');
    if (isset($this->row['BM_ID'])) {
      $this->row['BM_ID'] = $nid;
      $this->update_ExcelTable();
    }

  }
}
