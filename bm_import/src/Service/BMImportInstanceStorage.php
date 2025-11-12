<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-17 at 5:25:34
 */

namespace bmDataModules\bm_import\src\Service;

use bmDataModules\bm_import\src\BMImport\BMImport;

class BMImportInstanceStorage {
  protected ?BMImport $instance;

  public function setInstance(BMImport $instance): void {
    $this->instance = $instance;
  }

  public function getInstance(): BMImport {

    if (!isset($this->instance)|| $this->instance === null) {
      throw new \RuntimeException('BMImport instance is not set.');
    }
    return $this->instance;
  }
}
