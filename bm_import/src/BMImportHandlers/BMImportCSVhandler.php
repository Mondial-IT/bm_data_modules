<?php

namespace bmDataModules\bm_import\src\BMImportHandlers;

use Drupal;
use bmDataModules\bm_import\src\BMImport\BMImport;
use Drupal\field\Entity\FieldConfig;
use Exception;

class BMImportCSVhandler {

  private BMImport $BMImport;

  public function __construct(BMImport $BMImport) {
    $this->BMImport = $BMImport;
  }

  /**
   * # get_cached_ExcelContent
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
   * # get_availableHeaders_forExampleDownload
   * * helper utility to download the headers for the user
   * to identify the columns to use in the csv.
   * * see settingsForm for the handling of the downloadable
   *
   * @return string
   */
  public function getCSVAvailableHeadersExport(): string {
    $names = $this->get_cached_ExcelContentNames();
    return implode(';', $names);
  }

  /**
   * # cache all names available in type.YAML
   * * for example file for user CSV download
   *
   * @param $names
   *
   * @return void
   */
  public function set_cache_ExcelContentNames($names): void {
    $this->BMImport->setYMLNames($names);
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    $cache['ExcelContent']['names'] = $names;
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }

  /**
   * # get_cached_YMLNames
   *
   * @return array
   */
  public function get_cached_ExcelContentNames(): array {
    if (empty($this->BMImport->getYMLNames())) {
      $cache = Drupal::cache('bluemarloc')->get('BMImport');
      if ($cache !== FALSE) {
        $cache = $cache->data;
      }
      if (isset($cache['ExcelContent']['names'])) {
        return $cache['ExcelContent']['names'];
      }
    }
    return [];
  }


  /**
   * # set_cache_ExcelContent
   * * store CSV content to cache
   *
   * @param $csvContent
   *
   * @return void
   */
  public function set_cache_ExcelContent(): void {
    // we need it later to do problem finding
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache) {
      $cache = $cache->data;
    }
    // contains row and header, later separated
    $cache['ExcelContent'] = $this->BMImport->ExcelContent;
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }


  /**
   * # set_cache_ExcelContent_rowNums_as_DefaultValue
   * cache for later the selected rows (row nums)
   *
   * @param $rowNums
   *
   * @return void
   */
  public function set_cache_ExcelContentDefaultValue($rowNums): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache) {
      $cache = $cache->data;
    }
    // contains row and header, later separated
    $cache['csvContent']['default_value'] = array_values($rowNums);
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }


  /**

   * @throws Exception
   */
  public function executeTodoWithHeaders(): array {
    $this->BMImport->todo_with_headers($this->BMImport->ExcelContent['header']);
    return $this->BMImport->ExcelContent;
  }


}
