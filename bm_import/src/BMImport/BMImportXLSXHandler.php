<?php

namespace bmDataModules\bm_import\src\BMImport;

use bmDataModules\bm_import\src\BMImport\BMImport;
use Drupal;
use Drupal\bm_xlsx\BMXLSX\BMXLSX;

/**
 * ## Managed properties
 * *
 * ## Cached properties
 * * ExcelContent
 * * ExcelContent[names]  : list of YML names
 * * ExcelContent[default_values] : the selected Excel table rows indexes
 */
class BMImportXLSXHandler {

  private BMImport $BMImport;

  public function __construct(BMImport $BMImport) {
    $this->BMImport = $BMImport;
  }

  /**
   * #### get_cached_ExcelContent
   * return BMImport{ExcelContent}
   *
   * @return array
   */
  public function get_cached_ExcelContent(): array {
    // we need it later to do problem finding
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache) {
      $cache = $cache->data;
    }
    // contains row and header, later separated
    if (isset($cache['ExcelContent'])) {
      return $cache['ExcelContent'];
    }
    return ['ExcelContent'];
  }


  /**
   * #### set_cache_ExcelContent
   * * store Excel content to cache
   *
   * @return void
   */
  public function set_cache_ExcelContent(): void {
    // we need it later to do problem finding
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache) {
      $data = $cache->data;
    }
    // [header, names, options], will later be separated
    $data['ExcelContent'] = $this->BMImport->ExcelContent;
    Drupal::cache('bluemarloc')->set('BMImport', $data);
  }

  /**
   * #### set_cache_ExcelContent_rowNums_as_DefaultValue
   * cache for later the selected rows (row nums)
   *
   * @param $rowNums
   *
   * @return void
   */
  public function set_cache_ExcelContent_rowNums_as_DefaultValue($rowNums): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache) {
      $cache = $cache->data;
    }
    // contains row and header, later separated
    $cache['ExcelContent']['default_value'] = array_values($rowNums);
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }

//  /**
//
//   * @throws Exception
//   */
//  public function executeTodoWithHeaders(): array {
//    $this->BMImport->BMImport_todo_with_headers->todo_with_headers($this->BMImport->ExcelContent['header']);
//    return $this->BMImport->ExcelContent;
//  }


  /**
   * Return BMImport->ExcelContent
   * * load from cache when not in property

   * @return array|null
   */
  public function &get_excelContent(): ?array {
    $BMImport= $this->BMImport;
    if (!empty($BMImport->ExcelContent)) {
      return $BMImport->ExcelContent;
    }
    // sets this->csvContent as well
    $this->get_cached_ExcelContent();
    return $BMImport->ExcelContent;
  }

  public function save_as_excel($data, $uri){
    return BMXLSX::save_as_excel($data, $uri);
  }

}
