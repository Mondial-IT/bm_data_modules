<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-16 at 11:46:21
 */

namespace Drupal\bm_import\Form\Panel;

use Drupal\bm_import\BMImport\BMImport;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Exception;

class Panel_excel_upload extends FormBase{

  private BMImport $BMImport;

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
   return 'panel_excel_upload';
  }

  /**
   * @inheritDoc
   * @throws Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    bm_d('', instantiateColor('Form Panel_excel_upload'));

//    $this->BMImport = new BMImport();

    $this->form_panel_excel_upload($form, $form_state);

    return $form;
  }


  /**
   * # form_panel_excel_upload, note the ExcelUploadAndSheetSelectForm is already present in the page.
   * * sets its fid, in cache, use the BMXLSX class to retrieve the data
   *
   */
  private function form_panel_excel_upload(&$form): void {
    bm_d('', traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));


    $form['panel_excel_upload'] = [
      '#markup' => '<div id="panel-excel-upload" class="panel gap-20">' .
        bm_main_panel_title_and_help('Excel upload', 'The uploaded Excel to import').
        'Fid: '.bm_xlsx_get_last_uploaded_fid().':'.'File path: '.bm_xlsx_get_last_uploaded_file_path()
    ];

  }
  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(true);
  }

}
