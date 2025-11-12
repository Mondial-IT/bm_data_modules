<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-16 at 11:20:36
 */

namespace bmDataModules\bm_import\src\Form\Panel;

use Drupal;
use bmDataModules\bm_import\src\BMImport\BMImport;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;

class Panel_preflight_checks extends FormBase{

  private BMImport $BMImport;

  private bool $preflight_check_ok;

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'panel_preflight_checks';
  }

  /**
   * @inheritDoc
   * @throws Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    bm_d('', instantiateColor('instantiated Panel_preflight_checks'));

    try {
      $this->BMImport = \Drupal::service('bm_import.manager')->getInstance();
    }catch(Exception $e){
      Drupal::messenger()->addError($e->getMessage());
    }
    $this->form_panel_preflight_checks($form);

    return $form;
  }

  /**
   * #  preflight checks panel
   * * runs get_preflight_panel_form_content__todo_before_import
   *
   * **display Ready to start importing or "There are things to do before …"**
   * * instructing the user what to add to node, fields, paragraphs, taxonomies
   *
   * * $this->preflight_check_ok True/False
   *
   * @throws Exception
   */
  private function form_panel_preflight_checks(&$form): void {
    bm_d('', traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));

    $filePath = $this->BMImport->get_filePath();
    if (!isset($filePath)) {
      return;
    }
    $this->preflight_check_ok = FALSE;

    // AJAX replace ID
    $title = 'Pre-flight panel';
    $helpText = 'These are the entities and fields which should be present on this system. When one is not green, the import can not start.';
    $form['panel_preflight-wrapper'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="preflight-panel-wrapper" class="panel">'. bm_main_panel_title_and_help($title, $helpText).
      'This panel list the fields and entities that should be present on this system. When one is not green, the import can not start.',
      '#suffix' => '</div>',
    ];


    // this iterates the 'todo_before_import' yml
    // where fields, nodes, paragraphs are listed
    // that should be present for successful import
    // these fields, etc. are then created.
    // When something not successful, it is listed as not ready
    // when successful, they are displayed in green to the user.
    $elements = $this->BMImport->get_preflight_panel_form_content__todo_before_import();
    //  $message = 'returned by get_form_element__todo_before_import__entity_types_and_field_defs: '.json_encode($elements['ready']));


    if (isset($elements['ready']) && $elements['ready'] === TRUE) {

      $form['panel_preflight-wrapper']['panel_preflight']['details'] = [
        '#type' => 'markup',
        '#markup' => '<details><summary class="bgcolor-lightgreen">The Preflight is OK</summary>',
        '#suffix' => '</details></div>',
      ];
      $form['panel_preflight-wrapper']['panel_preflight']['details']['data'] = $elements['form'];

      $this->preflight_check_ok = TRUE;

    }
    else {
      $form['panel_preflight-wrapper']['panel_preflight']['todo'] = [
        '#type' => 'markup',
        '#markup' => '<div class="panel-preflight panel">' . bm_main_panel_title_and_help($title, $helpText),
        '#suffix' => '</div>',
      ];
      $form['panel_preflight-wrapper']['panel_preflight']['todo']['missing_fields'] = $elements['form']['missing_fields'];
      $message = ' elements form: things to do';
      bm_d('', violetColor($message));

      $this->preflight_check_ok = FALSE;
    }

  }
  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild(true);
  }

}
