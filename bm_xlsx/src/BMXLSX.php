<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:11
 */

namespace Drupal\bm_xlsx;

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

use Drupal\facets\Exception\Exception;
use Shuchkin\SimpleXLSX;

/**
 * Class handling .xlsx in php
 * * new BMXLSX($file_path)
 * * getRows($worksheetNr,$limit)
 * * getWorkSheets()
 */
class BMXLSX {

  public SimpleXLSX $xlsx;

  /**
   * @throws Exception
   */
  public function __construct($file_path) {
    if ($file_path && file_exists($file_path)) {
      $this->xlsx = SimpleXLSX::parse($file_path);
    } else {
     throw new Exception('xlsx file not found or not parsable.' . $file_path, 500);
    }
    return $this;
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
  public function getRowsKeyed( $workSheetIndex = 0, $limit = 100): ?array {
      if ($this->xlsx) {
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
    return NULL;
  }

  public function getRows( $workSheetIndex = 0, $limit = 100): ?array {
    if ($this->xlsx) {
      // Produce array keys from the array values of 1st array element
      return $this->xlsx->rows($workSheetIndex, $limit);
    }
    return NULL;
  }

  public function getRowHeader( $workSheetIndex = 0): ?array {
    if ($this->xlsx) {
      // Produce array keys from the array values of 1st array element
      return $this->xlsx->rows($workSheetIndex, 1);
    }
    return NULL;
  }

  public function getRowsWithoutHeader( $workSheetIndex = 0): ?array {
    if ($this->xlsx) {
      $rows=[];
      $n=0;
      foreach($this->xlsx->rows($workSheetIndex) as $r) {
        if($n++ > 0 )$rows[]=$r;
      }
      return $rows;
    }
    return NULL;
  }

  /**
   * @throws Exception
   */
  public function getSheetNames($file_path): ?array {
      if ($this->xlsx) {
        return $this->xlsx->sheetNames();
      }
      else {
        throw new Exception('.xlsx file not found ' . $file_path);
      }

  }

}
