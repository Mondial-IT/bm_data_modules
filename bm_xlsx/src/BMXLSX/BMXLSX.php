<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace Drupal\bm_xlsx\BMXLSX;

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */


use Drupal;
use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use Shuchkin\SimpleXLSX;
use stdClass;
use Shuchkin\SimpleXLSXGen;
/**
 * Class handling .xlsx in php
 * * new BMXLSX($file_path)
 * * getRows($worksheetNr,$limit)
 * * getWorkSheets()
 */
class BMXLSX {
  public ?string $fileUri;
  public ?int $fid;
  public ?int $sheet;
  public ?int $limit;
  public  SimpleXLSX $xlsx;

  /**
   * # new BMXLSX($fid) or new BMXLSX($fileUri)
   * Create an excel instance from file path or fid.
   *
   * @param string|int $file_path_or_fid uri or file_id
   *
   * @throws Exception
   */
  public function __construct(string|int $file_path_or_fid=0) {
    // is set by ExcelUploadAndSheetSelectForm
    // get a stored path, fid, limit and selected sheet.
    $config = self::get_config_sheet_selection_and_limit();
    $this->sheet = $config->sheet??0;
    $this->limit= $config->limit??100;
    $this->fileUri = $config->file_path??'';
    $this->fid = $config->file_id??'0';

    // not in cache, given a path?
    if(empty($this->fileUri) && !$this->fid) {
      if(is_string($file_path_or_fid)) {
        $this->fileUri = $file_path_or_fid;
      }
    }
    // not in cache, given a fid?
    if(empty($this->fileUri && $this->fid)) {
      $file = File::load($this->fid);
      if($file) {
        $this->fileUri = $file->getFileUri();
      }
    }

    if($this->fileUri !==''){
      try {
        $xlsx = SimpleXLSX::parse($this->fileUri);
        if($xlsx !== false) {
          $this->xlsx =$xlsx;
        }else{
          Drupal::messenger()->addStatus('problem instantiating SimpleXLSX');

        }
      }catch(Exception $e){
        throw new Exception('xlsx file not found or not parsable.' . $this->fileUri, 500);
      }
    }
    // cache this so it can be retrieved in Excel users
    self::cache_config_sheet_selection_and_limit($this->sheet, $this->limit);
    return $this;
  }

  public function getFileUri(): ?string {
    return $this->fileUri;
  }
  /**
   * Parse and return data from the cached XLSX file.
   *
   * @return array|null
   *   The parsed data, or NULL if no valid file is cached.
   */
  public function getXLSX(): ?SimpleXLSX {

      if ($this->xlsx) {

        // Produce array keys from the array values of 1st array element
        if (0) {
          $header_values = $rows = [];
          foreach ($this->xlsx->rows(0, 10) as $k => $r) {
            if ($k === 0) {
              $header_values = $r;
              continue;
            }
            $rows[] = array_combine($header_values, $r);
          }
          dsm($rows, 'using 1st row keys');
        }
        if (0) {
          foreach ($this->xlsx->readRows(0, 10) as $r) {
            dsm($r, 'readRows2');
          }
        }
        if (0) {
          dsm($this->xlsx->sheetNames(), 'sheetNames');
          dsm($this->xlsx->sheetName($this->xlsx->activeSheet), 'activeSheet');
        }
        if (0) {
          dsm($this->xlsx->rowsEx($worksheetIndex = 0, $limit = 10), 'rowsEx');
        }
        //return $this->xlsx->rows(0, 10);
        return $this->xlsx;
      }
    return NULL;
  }

  /**
   * @throws Exception
   */
  public function getRowsKeyed( $workSheetIndex = null, $limit = null): ?array {

    if($workSheetIndex===null)$workSheetIndex=$this->sheet;
    if($limit===null)$limit=$this->limit;

    if (isset($this->xlsx)) {
        // Produce array keys from the array values of 1st array element
        $header_values = $rows = [];
        foreach ($this->xlsx->rows($workSheetIndex, $limit) as $k => $r) {
          if ($k === 0) {
            $header_values = $r;
            continue;
          }
          $rows[] = array_combine($header_values, $r);
        }
        return $rows;
      }
    return [];
  }

  public function getRows( $workSheetIndex = null, $limit = null): ?array {

    if($workSheetIndex===null)$workSheetIndex=$this->sheet;
    if($limit===null)$limit=$this->limit;

    if (isset($this->xlsx)) {
      // Produce array keys from the array values of 1st array element
      return $this->xlsx->rows($workSheetIndex, $limit);
    }
    return NULL;
  }

  public function getRowHeader( $workSheetIndex = null): ?array {
    if($workSheetIndex===null) $workSheetIndex=$this->sheet;

    if (isset($this->xlsx)) {
      $headerKeyed=[];
      $header = $this->xlsx->rows($workSheetIndex, 1);
      if(isset($header[0])) {
        foreach ($header[0] as $h) {
          $headerKeyed[$h] = $h;
        }
        // Produce array keys from the array values of 1st array element
        return $headerKeyed;
      }
      return [];
    }
    return [];
  }

  public function getRowsWithoutHeader( $workSheetIndex = null, $limit=null): ?array {
    if(!$workSheetIndex)$workSheetIndex=$this->sheet;
    if(!$limit)$limit=$this->limit;

    if ($this->xlsx) {
      $rows=[];
      $n=0;
      foreach($this->xlsx->rows($workSheetIndex,$limit) as $r) {
        if($n++ > 0 )$rows[]=$r;
      }
      return $rows;
    }
    return NULL;
  }

  /**
   * @throws Exception
   */
  public function getSheetNames(): ?array {
      if (isset($this->xlsx)) {
        return $this->xlsx->sheetNames();
      }
      return null;
  }
  /**
   * Retrieve css color and background color of a column of rows.
   * @param     $sheet
   * @param int $rowStart
   * @param int $rowEnd
   * @param int $columnIndex
   *
   * @return array|null
   */
  public function getColors($sheet, ?int $rowStart = null, ?int $rowEnd = null, ?int $columnIndex = null): ?array {

    if($rowStart===null)$rowStart=0;
    if($rowEnd===null)$rowEnd=1000000;
    if($columnIndex===null)$columnIndex=0;
    $rows=[];
    if(isset($this->xlsx)) {
      $rows = $this->xlsx->rowsEx($sheet);
    }
    $colors = [];

    for ($row = $rowStart; $row <= $rowEnd; $row++) {
      if (!isset($rows[$row][$columnIndex]['css'])) {
        continue;
      }

      $cssString = $rows[$row][$columnIndex]['css'];
      $styles = explode(';', $cssString);
      $filteredStyles = [];

      foreach ($styles as $style) {
        $style = trim($style);
        if (str_starts_with($style, 'color:') || str_starts_with($style, 'background-color:')) {
          $filteredStyles[] = $style;
        }
      }

      if (!empty($filteredStyles)) {
        $colors[$row] = implode('; ', $filteredStyles) . ';';
      }
    }

    return $colors;
  }
  /**
   * * cache $sheet and $limit into bm_xlsx.settings
   * * Retrieves fid and filepath from bm_main_get_last_uploaded_fid()
   *
   * @param string $sheet
   * @param $limit
   *
   * @return void
   */
  static public function cache_config_sheet_selection_and_limit(string $sheet, $limit): void {

    $config = Drupal::cache('bluemarloc')->get('bm_xlsx.settings');
    if($config) {
      $existing_config = $config->data;
    }
    $fid = bm_main_get_last_uploaded_fid();
    if($fid) {
    $file = File::load($fid);
    if($file){
      $existing_config['selected_fid'] = $file->id();
      $existing_config['selected_file_path'] = $file->getFileUri();
    }
    }
    $existing_config['selected_sheet'] = $sheet;
    $existing_config['selected_limit'] = $limit;

  //  Drupal::messenger()->addStatus('Excel configuration cached: '.json_encode($existing_config));
    // Set the value.
    Drupal::cache('bluemarloc')->set('bm_xlsx.settings', $existing_config);

  }
  /**
   * Returns ->sheet, ->limit, ->file_path, ->fid
   * @return stdClass
   */
  static public function get_config_sheet_selection_and_limit(): stdClass{
    $config = Drupal::cache('bluemarloc')->get('bm_xlsx.settings');
    $settings=new stdClass();
    if($config) {
      $existing_config = $config->data;
      $settings->fid =  $existing_config['selected_fid']?? NULL;
      $settings->file_path =  $existing_config['selected_file_path']?? NULL;
      $settings->sheet= $existing_config['selected_sheet']?? NULL;
      $settings->limit= $existing_config['selected_limit'] ?? NULL;
    }else{
      $settings->sheet= 0;
      $settings->limit= 10;
      $settings->fid =  0;
      $settings->file_path =  '';
    }
    return $settings;
  }

  public function get_current_sheet(){
    return $this->sheet;
  }
  public function get_current_limit(){
    return $this->limit;
  }

  /**
   * # create_XLSX
   *
   * @param array  $data
   * @param string $fileName
   *
   * @return string
   */
  static public function save_as_excel(array $data, string $uri) {
    // Generate .xlsx content using output buffering
//    $data = [
//      ['Name', 'Age', 'Email'], // Headers
//      ['John Doe', 30, 'john@example.com'],
//      ['Jane Smith', 25, 'jane@example.com'],
//    ];

    $xlsx = SimpleXLSXGen::fromArray($data);
    $xlsx->saveAs($uri);

    $file_url_generator = \Drupal::service('file_url_generator');
    return $file_url_generator->generateAbsoluteString($uri);
  }
}
