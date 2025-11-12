<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2025
   Created on 2025-3-12 at 4:49:21
 */

namespace Drupal\bm_config_sync\Handler;

class BMExportConfigHandler {
  public function __construct() {

  }

  public function save_content_type_configuration_to_file($id):void{
    \Drupal::messenger()->addMessage('Success '.$id);

  }
  public function save_taxonomy_configuration_to_file($id):void{
\Drupal::messenger()->addMessage('Success '.$id);
  }
  public function save_paragraph_configuration_to_file($id):void{
    \Drupal::messenger()->addMessage('Success '.$id);

  }
  public function save_field_configuration_to_file($id):void{
    \Drupal::messenger()->addMessage('Success '.$id);

  }
}
