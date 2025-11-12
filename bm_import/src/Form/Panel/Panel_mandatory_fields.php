<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-16 at 4:44:21
 */

namespace Drupal\bm_import\Form\Panel;

use Drupal;
use Drupal\bm_hierarchical_select\BMHierarchicalSelect\BMHierarchicalSelect;
use Drupal\bm_import\BMImport\BMImport;
use Drupal\bm_import\BMImportHandlers\BMImportVocabularyHandler;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;

class Panel_mandatory_fields extends FormBase {

  /**
   * @inheritDoc
   */
  public function getFormId(): string {
    return 'panel_mandatory_fields';
  }
  private BMImport $BMImport;

  /**
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    bm_d('',instantiateColor("instantiated Panel_mandatory_fields"));

    $this->BMImport = \Drupal::service('bm_import.manager')->getInstance();

    $this->form_panel_mandatory_fields($form);

    return $form;
    }
    /**
     * #  form_panel_mandatory_fields
     * * panel select Canvass, Category and languageId
     *
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     */
    private
    function form_panel_mandatory_fields(&$form): void {
      bm_d(BMImport::BM_D_TRACE, traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));

      $mandatoryColumnsProvided = $this->BMImport->get_has_which_mandatory_columns();
      bm_d(BMImport::BM_D_KEY, $mandatoryColumnsProvided, 'from import get_has_which_mandatory_columns()');

      $title = 'Mandatory fields panel';
      $helpText = 'These fields require a value before import can be successful.
                  The field can either be provided in the worksheet as column or can be entered here for the missing cells.';

      $form['panel_select'] = [
        '#markup' => '<div id="panel-select-canvass" class="panel">
        ' . bm_main_panel_title_and_help($title, $helpText) . '
        <p>These fields require a value, please enter a default.</p>',
        '#suffix' => '</div>',
      ];

      $this->panel_mandatory__canvass_select($form['panel_select']);
      //dsm($form['panel_select']);
      $category = $mandatoryColumnsProvided['category'];
      $this->panel_mandatory__category_select($form['panel_select'], $category);

      $this->panel_mandatory__language_select($form['panel_select']);

      $this->panel_mandatory__ignorable_columns($form['panel_select']);

    }

    /**
     * @sets
     *  $this->canvassChain
     *
     * @dependencies
     *
     *  `BMImport->get_filePath())`,
     *  `BMImportVocabularyHandler`,
     *  `this->canvassChain`
     *
     * @param $form
     *
     * @return void 'panel_mandatory' form elements to select a default canvass
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     */
    public
    function panel_mandatory__canvass_select(&$form): void {
      $markup = '';
      $defaultCanvass = 'General Canvass for imports';
      $canvass = NULL;

      // set the default canvass based on the Excel filename
      $filePath = $this->BMImport->get_filePath();
      if (!empty($filePath)) {
        $defaultCanvass = explode('/', $this->BMImport->get_filePath());
        $defaultCanvass = array_pop($defaultCanvass);
        $defaultCanvass = str_replace('.xlsx', '', $defaultCanvass);
      }

      // create canvass hierarchy for HS select
      if (empty($this->canvassChain)) {
        try {
          $vocabularyHandler = new BMImportVocabularyHandler();
          // add a default hierarchy
          $hierarchy = [
            'General Company for Imports',
            'General Campaign for Imports',
            $defaultCanvass,
          ];

          dsm($hierarchy, 'check for empty tids');
          if ($hierarchy[0] && $hierarchy[1] && $hierarchy[2]) {
            [
              $compTid,
              $campTid,
              $canvassTid,
            ] = $vocabularyHandler->add_update_taxonomy_hierarchy(
              'company_campaign_canvas_tags', $hierarchy
            );

            // and use it.
            $this->canvassChain = "$compTid>$campTid>$canvassTid";
          }
          else {
            $markup .= 'check "General Company for Imports"."General Campaign for Imports"."General Canvass for imports" exists';
          }
        } catch (Exception $e) {
          Drupal::messenger()
            ->addError(__FUNCTION__ . ' 694.Exception in adding company, campaign, canvass hierarchy terms. <br />' . $e->getMessage());
        }
      }

      // display hierarchical select to choose canvass in case where it is missing as column.
      if (class_exists('\Drupal\bm_hierarchical_select\BMHierarchicalSelect\BMHierarchicalSelect')) {


        // create a title display element
        $form['canvass']['canvass_title'] =
          [
            '#type' => 'markup',
            '#markup' => '<h4>default Canvass name</h4>It will use <b>dp_' . $defaultCanvass . '</b> as canvass name when no canvass id is found.',
          ];


        if (!$canvass) {
          $fefq_name_or_chain = 'construct__company_campaign_canvas_tags__campaign_1';

          if (!empty($this->canvassChain) && $this->canvassChain <> 'none') { // persist user entry in between imports
            $fefq_name_or_chain = $this->canvassChain;
          }

          // $fefq_name_or_chain= '5203>5202>106>178>143';
          $form['canvass']['canvass_title'] =
            [
              '#type' => 'markup',
              '#markup' => '<h4>Default canvass</h4>Select a default canvass to use for the listings where no canvass is given.',
            ];

          $form['canvass']['panel_select_canvass'] =
            // note drupal_form_selected_canvass_hs has the full chain
            BMHierarchicalSelect::get_HierarchicalSelect_AsSelectForm(
              'selected_canvass', $fefq_name_or_chain
            );
        }
      }
      else {
        // backward compatible
        $form['input_canvass_tid'] = [
          '#type' => 'textfield',
          '#title' => 'Input canvass tid',
        ];

        Drupal::messenger()
          ->addWarning('\Drupal\bm_hierarchical_select\BMHierarchicalSelect\BMHierarchicalSelect is missing');
      }
    }

    /**
     * @param mixed $form
     *
     * @param       $category
     *
     * @return void
     * @throws InvalidPluginDefinitionException
     * @throws PluginNotFoundException
     */
    public
    function panel_mandatory__category_select(mixed &$form, $category): void {

      if (!$category) {
        $form['category']['category_title'] = [
          '#type' => 'markup',
          '#markup' => '<h4>default Category</h4>Select the category these entries belong to.',
        ];

        if (class_exists('\Drupal\bm_hierarchical_select\BMHierarchicalSelect\BMHierarchicalSelect')) {
          $fefq_name_or_chain = 'listing_category';
          //bm_d('',$this->categoryChain,'categoryChain','dsm');
          if (!empty($this->categoryChain) && $this->categoryChain <> 'none') { // persist user choice between imports
            $fefq_name_or_chain = $this->categoryChain;
          }
          // note drupal_form_selected_category_hs has the full chain on submit
          $form['category']['panel_select_category'] =
            BMHierarchicalSelect::get_HierarchicalSelect_AsSelectForm(
              'selected_category',
              $fefq_name_or_chain,);
        }
        else {
          $form['input_category_tid'] = [
            '#type' => 'textfield',
            '#title' => 'Input category tid',
          ];
        }
      }
    }

    /**
     * @param mixed $form
     *
     * @return void
     */
    public
    function panel_mandatory__language_select(mixed &$form): void {
      $form['language']['panel_select_title'] = ['#markup' => '<h4>default Language</h4>'];
      $form['language']['panel_select_language'] = [
        '#type' => 'select',
        '#multiple' => FALSE,
        '#options' => ['en' => 'en', 'nl' => 'nl', 'fr' => 'fr'],
        '#default_value' => 'nl',
        '#title' => 'Language selection',
        '#description' => 'Select the language of these entries.',
      ];
    }

  /**
   * BMImport
   * BMImportXLSXHandler
   *
   * @param mixed $form
   *
   * @return void
   */
    public
    function panel_mandatory__ignorable_columns(mixed &$form): void {
      // list names to give a selection for user to ignore those columns
      $importableNames = $this->BMImport->BMImport_todo_with_headers->get_cached_importableNames();

      $form['select_Excel_columns_to_ignore'] = [
        '#type' => 'checkboxes',
        '#multiple' => TRUE,
        '#options' => $importableNames['names'],
        '#default_value' => $importableNames['selected'] ?? [],
        '#title' => 'Table columns',
        '#prefix' => '<div class="grid-3"><br /><b>Select fields to ignore</b>',
        '#suffix' => '</div>',
      ];
    }




  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $message = ' trace: Panel_mandatory_fields ' . __FUNCTION__;
    bm_d(BMImport::BM_D_TRACE, traceColor($message));
    $form_state->setRebuild(true);
  }

  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state): AjaxResponse {
    $message = ' trace: Panel_mandatory_fields ' . __FUNCTION__;
    bm_d(BMImport::BM_D_TRACE, traceColor($message));

    $ajaxResponse = new ajaxResponse();

    bm_main_response_messages(response:$ajaxResponse);

    return $ajaxResponse;
  }
}
