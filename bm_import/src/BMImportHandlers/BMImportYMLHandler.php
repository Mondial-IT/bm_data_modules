<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace Drupal\bm_import\BMImportHandlers;

use Drupal\bm_import\Form\SettingsForm;
use Drupal;
use Drupal\bm_debug\BMDebugInfo\BMDebugInfo;
use Drupal\bm_import\BMImport\BMImport;
use Symfony\Component\Yaml\Yaml;

/**
 * Constants used in bm_import.settings.yml
 */
class BMImportYMLHandler {

  /**
   * This is the Excel column name definition
   * for lookup of the definition for an Excel column
   */
  const COLUMN_NAME = 'column_name';

  const SQL = 'sql';

  const FIELD = 'field';

  const NAME = 'name';

  const TYPE = 'type';

  const TAXONOMY = 'taxonomy';

  const PARAGRAPH = 'paragraph';

  const FIELDS = 'fields';

  /**
   * Reason for locator queries
   */
  const REASON = 'reason';

  private BMImport $BMImport;


  const BM_D_DETAILS = __CLASS__ . '-details';

  const BM_D_KEY = __CLASS__;


  /** # bm_d
   */
  static public function bm_d_callback($key, $BMDbgInfo): BMDebugInfo|false {

    if ($key == self::BM_D_KEY) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImportSettings';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    if ($key == self::BM_D_DETAILS) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImportYMLHandler - details.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }

    return FALSE;
  }

  function __construct(BMImport $BMImport) {
    bm_d('', instantiateColor('Instantiating BMImportYMLHandler - the class with YML methods'));
    $this->BMImport = $BMImport;
    $this->load_system_settings_yml();
    bm_d('', $this->BMImport->system_settings_yml, 'This config/system.settings.yml has been loaded.', 'dsm');
  }


  /**
   * *load_cached_system_settings_yml*
   * load the cached system settings yml into property
   * @return void
   */
  private function load_cached_system_settings_yml(): void {

    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache) {
      $cache = $cache->data;
    }
    // contains row and header, later separated
    if (isset($cache['system_settings_yml'])) {
      $this->BMImport->system_settings_yml = $cache['system_settings_yml'];
      return;
    }
  }
  /**
   * #### load_system_settings_yml
   * load file system.settings.yml into BMImport->system_settings_yml property
   *
   * @return void
   */
  public function load_system_settings_yml(): void {
    $modulePath = Drupal::moduleHandler()->getModule('bm_import')->getPath();
    $this->BMImport->system_settings_yml = Yaml::parseFile($modulePath . '/config/system.settings.yml');
  }
  /**
   * #### get_system_settings_yml()
   * returns the system_settings_yml property
   *
   * @return array
   */
  public function get_system_settings_yml(): array {
    // from memory
    //if (empty($this->BMImport->system_settings_yml)) {
    // from cache
    //   $this->load_cached_system_settings_yml();
    //   if (empty($this->BMImport->system_settings_yml)) {
    // load from disk
    $this->load_system_settings_yml();
    //   }
    // }
    return $this->BMImport->system_settings_yml;
  }
  /**
   * #### get_availableHeaders_forExampleDownload
   * * helper utility to download the headers for the user
   * to identify the columns to use in the csv.
   * * @see settingsForm for the handling of the downloadable
   *
   *
   * @return string Names separated by ;
   */
  public function get_availableHeaders_forExampleDownload(): array {
    $this->BMImport->YMLNames = $this->get_cached_YMLNames();
    return$this->BMImport->YMLNames;
  }
  /**
   * _getColumnNames
   * make a list of all 'names' in the YAML
   *
   * @param       $array
   * @param array $names
   *
   * @return array
   */
  private function _getColumnNames($array, array &$names = []): array {
    foreach ($array as $key => $value) {
      if ($key === BMImportYMLHandler::COLUMN_NAME) {
        $names[] = $value;
        //       bm_d(self::BM_D_KEY,' name:' . $value);
      }
      elseif (is_array($value)) {
        $this->_getColumnNames($value, $names);
      }
    }
    return $names;
  }
  /**
   * #### getAllAvailableColumnNamesFromYML
   * get sorted unique column names from yml property
   * @return array
   */
  public function getAllAvailableColumnNamesFromYML(): array {
    $originalNames = $this->BMImport->system_settings_yml['todo_with_columns'];
    $names = array_unique($this->_getColumnNames($originalNames));
    sort($names);
    $this->BMImport->YMLNames=$names;
    return $names;
  }

  /**
   * #### getYMLNames
   * Get list of column name definitions from YML
   *
   * @return array
   */
  public function getYMLNames(): array {
    if(!empty($this->BMImport->YMLNames))return $this->BMImport->YMLNames;

    $this->getAllAvailableColumnNamesFromYML();
    $this->set_cache_YMLNames($this->BMImport->YMLNames);
    return $this->BMImport->YMLNames;
  }
  /**
   * #### setYMLNames
   * Store the list of column name definitions to property
   *
   * @param mixed $YMLNames
   */
  public function setYMLNames(mixed $YMLNames): void {
    $this->BMImport->YMLNames = $YMLNames;
  }
  /**
   * #### cache all names available in type.YAML
   * * for example file for user Excel download
   *
   * @param $YMLNames
   *
   * @return void
   */
  public function set_cache_YMLNames($YMLNames): void{
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $cache = $cache->data;
    }
    $cache['YMLNames'] = $YMLNames;
    Drupal::cache('bluemarloc')->set('BMImport', $cache);
  }

  /**
   * #### get_cached_YMLNames
   * * get list of YML column definition names
   *
   * Managed Properties:
   * * `BMImport->YMLNames` : is set from cache
   *
   * @return array
   */
  public function get_cached_YMLNames(): array {
    // check property
    if (empty($this->BMImport->YMLNames)) {
      // check cache
      $cache = Drupal::cache('bluemarloc')->get('BMImport');
      if ($cache !== FALSE) {
        $cache = $cache->data;
      }
      if (isset($cache['YMLNames'])) {
        $this->BMImport->YMLNames=$cache['YMLNames'];
        return $cache['YMLNames'];
      }
    }else{
      return $this->BMImport->YMLNames;
    }
    return [];
  }

}
