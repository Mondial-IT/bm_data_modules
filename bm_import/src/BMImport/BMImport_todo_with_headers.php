<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-15 at 5:34:3
 */

namespace bmDataModules\bm_import\src\BMImport;

use bmDataModules\bm_import\src\BMImport\BMImport;
use Drupal;
use Exception;

/**
 * # BMImport_todo_with_headers
 * This class processes the Excel headers and processes YML names.
 * ## Main purpose:
 * * alterations to be made to the Excel headers (in yml definition todo_with_headers)
 * * Manage which headers are imported (ignored list)
 * * Maintain a cached list of Excel headers
 * * Maintain a cached list of headers to import
 *
 * ## Methods
 * * todo_with_headers: process the Excel headers and YML directions
 * * form_element__ignorable_columns: panel form element for user to check/uncheck what to import
 * * act_on__select_columns_to_ignore: on form submit, handle checked 'ignore column' boxes
 *
 * ## managed properties (in BMImport)
 * * originalExcelHeaders : as in the Excel file
 * * ExcelContent['header'] : the current cached Excel table
 *   * after update through `todo_with_headers()`
 *   * added mandatory headers
 *   * added information column headers
 * * importableHeaders  : list of headers to import
 *
 */
class BMImport_todo_with_headers {

  protected BMImport $BMImport;

  public function __construct(BMImport $BMImport) {
    $this->BMImport = $BMImport;
  }

  /**
   * #### todo_with_headers
   * Format and clean up Headers
   * * (makes them uppercase) to match with yml. name
   * * sets: `BMImport->ExcelContent[header]`
   * * checks for duplicate headers
   * * checks for missing headers
   *
   * @throws Exception
   */
  public function todo_with_headers(): void {
    $newHeaders = [];
    $allHeaders = [];

    foreach ($this->BMImport->ExcelContent['header'] as $header) {

      if (in_array($header, $allHeaders)) {
        Drupal::messenger()
          ->addError(__LINE__ . ' The Excel sheet column header contains a name twice: ' . $header . ' please fix and try again.');
        throw new Exception('The header contains the same name in multiple columns: '. $header);
      }

      $allHeaders[$header] = $header;

      // STANDARDIZE FOR LATER MATCH WITH YMLNames
      $ymlMatchName = trim($header);
      $ymlMatchName = preg_replace('/[^0-9A-Z_ ]/', '', strtoupper($ymlMatchName));
      if ($ymlMatchName == '') {
        Drupal::messenger()
          ->addError(__LINE__ . ' The header contains empty column names: ' . $allHeaders[$header] . ' please fix and try again.');
      //  throw new Exception(__LINE__ . ' The header contains empty column names: ' . $allHeaders[$header] . ' please fix and try again.');
      }
      $newHeaders[$ymlMatchName] = $header;// index 0 ...
    }
    // update property and cache
    $this->BMImport->ExcelContent['header'] = $newHeaders;
    // cache, [header, names, options]
    $this->BMImport->BMImportXLSXhandler->set_cache_ExcelContent();

    $this->cache_ExcelContent_header($newHeaders);

    $this->identify_importable_headers();
  }

  /**
   * # identify_importable_headers
   *
   * @return void
   */
  public function identify_importable_headers(): void {
    $standardizedExcelHeaders = $this->BMImport->ExcelContent['header'];
    bm_d('', $standardizedExcelHeaders, 'excelContent headers','dsm');

    $ymlNames = $this->BMImport->BMImportYMLHandler->getYMLNames();
    bm_d('', $ymlNames, 'YMLNames','dsm');

    // default importable true for all headers where there is a yml column_name.
    $importableNames=['names'=>[],'selected'=>[],'ignored'=>[]];
    foreach ($standardizedExcelHeaders as $stdHeader=>$orgHeader) {
      if(in_array($stdHeader, $ymlNames)) {
        $importableNames['names'][] = $stdHeader;
        $importableNames['selected'][] = $stdHeader;
      }
    }

    bm_d('',$importableNames, 'These are the headers we can actually import','dsm');
    $this->set_cache_importableNames($importableNames);

  }

  /**
   * #### act_on__select_columns_to_ignore
   *
   * In `form_panel_mandatory_fields`,
   * * the user can check column names to be ignored.
   * * from $form extract checked columns
   * * and store to `set_cache_importableNames`
   * * creates `BMImport->importableNames`
   *
   * @param $input
   *
   * @return void
   */
  function act_on__select_columns_to_ignore($input): void {
    bm_d(BMImport::BM_D_TRACE,traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));

    // get the checked 'columns' to ignore,
    // and put them into cache with the names.
    $columnsToIgnore = $input['select_Excel_columns_to_ignore'] ?? [];
    if (!empty($columnsToIgnore)) {
      $importableNames = $this->get_cached_importableNames();
      if ($importableNames) {
        $importableNames['selected'] = $columnsToIgnore;
        $importableNames['ignoreNames'] = [];
        foreach ($columnsToIgnore as $nr) {
          if ($nr !== '0' && empty($nr)) {
            continue;
          }
          $name = $importableNames['names'][(int) $nr];
          if ($name !== NULL) {
            $importableNames['ignoreNames'][$name] = $name;
          }
        }
        $this->set_cache_importableNames($importableNames);
      }
    }

  }

  /**
   * #### get_cached_headers
   * Return from cache BMImport{ExcelContent][header]}
   *
   * @return mixed|null
   */
  public function get_cached_headers(): mixed {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if (isset($cache->data['ExcelContent']['header'])) {
      return $cache->data['ExcelContent']['header'];
    }
    return NULL;
  }

  /**
   * #### cache_ExcelContent_header(headers[])
   * header BMImport{ExcelContent][header]}
   *
   * @param $headers
   *
   * @return void
   */
  public function cache_ExcelContent_header($headers): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    $cache['ExcelContent']['header'] = $headers;
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }

  /**
   * set_cache_importableNames
   * #### list of Excel columns which can be ignored is cached here
   *
   * The form displays a panel where the user can select Excel columns to ignore.
   * The list of Excel columns which can be ignored is cached here and re-used
   * in the buildForm.
   *
   *
   *  ```
   *  argument:
   *  $importableNames [
   *  'names'=>[], the column names
   *  'selected'=>[0="0"], the column names selected as index
   *  'ignoreNames'=>[$name=>$name,$name=>$name] the columns names selected as string
   * ```
   *
   *   ```
   *   Uses cache:
   *   BMImport{['importableNames']['names']}
   *   BMImport{['importableNames']['selected']}
   *   BMImport{['importableNames']['ignoreNames']}
   *   ```
   *
   * @parm array
   *
   *
   */
  public function set_cache_importableNames($importableNames): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    else {
      $cache = [];
    }
    // the column names
    $cache['importableNames'] = $importableNames;

    if (!isset($cache['importableNames']['names'])) {
      $cache['importableNames']['names'] = [];
    }
    // the selected to ignore nrs
    if (!isset($cache['importableNames']['selected'])) {
      $cache['importableNames']['selected'] = [];
    }
    // the selected to ignore names
    if (!isset($cache['importableNames']['ignoreNames'])) {
      $cache['importableNames']['ignoreNames'] = [];
    }
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }

  /**
   * #### get_cached_importableNames
   * * return the names selected to be imported
   *
   * @return array
   */
  public function get_cached_importableNames(): array {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    else {
      $cache = [];
    }

    $importableNames = [];
    $importableNames['names'] = $cache['importableNames']['names'] ?? [];
    // the selected to ignore nrs
    $importableNames['selected'] = $cache['importableNames']['selected'] ?? [];
    // the selected to ignore names
    $importableNames['ignoreNames'] = $cache['importableNames']['ignoreNames'] ?? [];

    return $importableNames;
  }



}
