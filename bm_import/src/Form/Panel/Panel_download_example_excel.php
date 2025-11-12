<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-16 at 11:36:51
 */

namespace Drupal\bm_import\Form\Panel;

use Drupal\bm_import\BMImport\BMImport;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class Panel_download_example_excel extends FormBase{

  private BMImport $BMImport;

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'panel_download_example_excel';
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    bm_d('', instantiateColor('instantiated Panel_mandatory_fields'));

    $this->BMImport = \Drupal::service('bm_import.manager')->getInstance();

    $this->form_panel_download_example_excel($form);

    return $form;
  }
  /**
   * #### form_panel_buttons
   *
   * @param $form
   *
   * @return void
   */
  private function form_panel_download_example_excel(&$form): void {
    bm_d('', traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));

    $title = 'Buttons panel';
    $helpText = '';
    $form['panel_buttons']['bts'] = [
      '#markup' => '<div id="panel-buttons" class="panel gap-20">' .
        bm_main_panel_title_and_help($title, $helpText) . '<p>&nbsp;</p>',
    ];
    $form['panel_buttons']['submit-download-as-excel'] = [
      '#type' => 'submit',
      '#value' => 'Download headers example',
      '#description' => 'Download an example import spreadsheet',
      '#prefix' => '<span class="tippy">',
      '#suffix' => '<span data-tippy>Download an example spreadsheet</span></span>',
      '#ajax' => [
        'callback' => '::submit_AjaxCallback_download_example',
        'wrapper' => 'ajax-panel-list-results',
      ],
    ];

    $form['panel_buttons']['bte'] = ['#markup' => '</div>'];
  }
  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(true);
  }

  public function submit_ajaxCallback_download_example() {
    bm_d('', traceColor('trace: ' . __FUNCTION__ . ' ' . time()));

    $ajax_response = new AjaxResponse();

    $BMImport = \Drupal::service('bm_import.manager')->getInstance();
    $ymlNames = $BMImport->BMImportYMLHandler->get_availableHeaders_forExampleDownload();
    bm_d('', $ymlNames, 'yml names', 'dsm');

    $uri = 'public://BlueMarloc example_import.xlsx';
    $url = $BMImport->BMImportXLSXhandler->save_as_excel($ymlNames, $uri);

    // call the jquery function to make it a downloadable file and download it
    $isUrl = TRUE;// see bm_main.js
    $ajax_response->addCommand(new InvokeCommand(NULL, 'ajaxAutoDownload', [$url, 'import-example.xlsx', $isUrl]));
    bm_main_response_messages($ajax_response);

    return $ajax_response;
  }

}
