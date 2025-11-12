<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-16 at 11:14:36
 */

namespace Drupal\bm_import\Form\Panel;

use Drupal\bm_import\BMImport\BMImport;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;

class Panel_field_defaults extends FormBase {

  private bool $problems_solved;

  private BMImport $BMImport;

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'panel_field_defaults';
  }

  /**
   * @inheritDoc
   * @throws Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    bm_d('', instantiateColor('instantiated Panel_mandatory_fields'));

    $this->BMImport = \Drupal::service('bm_import.manager')->getInstance();

    $this->form_panel_field_defaults($form);

    return $form;
  }
  /**
   * #  Field Defaults panel
   * **display Ready to start importing or "There are things to do before …"**
   * * instructing the user what to add to node, fields, paragraphs, taxonomies
   *
   * * $this->preflight_check_ok True/False
   *
   * @throws Exception
   */
  private function form_panel_field_defaults(&$form): void {
    bm_d(BMImport::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));

    $this->problems_solved = FALSE;


    $form['problem_solving_panel'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="problem-solving-panel">',
      '#suffix' => '</div>',
    ];

    $elements = $this->BMImport->get_field_defaults_panel_form_content__fieldIssuesAndRepairs();

    //$message = __LINE__.' returned by get_form_element__todo_before_import__entity_types_and_field_defs: '.json_encode($elements));
    $title = 'Field defaults panel';
    $helpText = 'These are other available fields where you can set a default value to before import creates a listing. It is not used in updates..';

    if (isset($elements['ready']) && $elements['ready'] === TRUE) {

      $form['problem_solving_panel']['todo'] = [
        '#type' => 'markup',
        '#prefix' => '<div class="problem-solving-panel panel">' . bm_main_panel_title_and_help($title, $helpText),
        '#suffix' => '</div>',
      ];

      $form['problem_solving_panel']['todo']['noproblems'] = array_merge((array) $form['problem_solving_panel']['todo'], $elements['form']);

      $form['problem_solving_panel']['todo']['e'] = [
        '#type' => 'markup',
        '#markup' => '<h3>The field defaults panel is OK.' . time() . '</h3>',
      ];
      $this->problems_solved = TRUE;
    }
    else {

      $form['problem_solving_panel']['todo'] = [
        '#type' => 'markup',
        '#prefix' => '<div class="problem-solving-panel panel">' .
          bm_main_panel_title_and_help($title, $helpText) .
          '<p>These fields (MIGHT) need input but are not provided by the CSV file.
                     You can set values for these fields OR add the fields to your CSV and retry the import.
                    </p><details><summary>Fields</summary>
                    ',
        '#suffix' => '</details></div>',
      ];

      foreach ($elements['form'] as $key => $element) {
        $form['problem_solving_panel']['todo'][$key] = $element;
      }
      //        $form['problem_solving_panel']['todo']['noproblems'] = array_merge( $form['problem_solving_panel'],$elements['form']);
      $this->problems_solved = FALSE;
    }
  }
  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(true);
  }

}
