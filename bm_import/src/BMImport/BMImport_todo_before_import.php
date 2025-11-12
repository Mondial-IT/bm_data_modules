<?php

namespace Drupal\bm_import\BMImport;

use Drupal;
use Drupal\bm_import\BMImportHandlers\BMImportFieldCreator;
use Drupal\bm_import\BMImportHandlers\BMImportNodeHandler;
use Drupal\bm_import\BMImportHandlers\BMImportParagraphHandler;
use Drupal\bm_import\BMImportHandlers\BMImportVocabularyHandler;
use Drupal\bm_import\BMImportHandlers\BMImportYMLHandler;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Vocabulary;
use Exception;

// todo: jbx Replace with drush config export and import
/**
 *
 */
class BMImport_todo_before_import {

  /**
   * # BMImport
   *
   * @var BMImport
   */
  private BMImport $BMImport;

  /**
   * # BMImportCreator
   * * a class with the general 'create entity and field value' functions
   *
   * @var BMImportCreator
   */
  private BMImportCreator $BMImportCreator;

  /**
   * # BMImportFieldCreator
   * class which handles creating field types
   *
   * @var BMImportFieldCreator
   */
  private BMImportFieldCreator $BMImportFieldCreator;

  /**
   * # BMImportParagraphHandler
   * * Centralized methods working on creating and setting paragraph items
   *
   * @var BMImportParagraphHandler
   */
  private BMImportParagraphHandler $BMImportParagraphHandler;

  /**
   * # BMImportVocabularyHandler
   * * centralized methods working on creating and setting vocabulary items
   *
   * @var BMImportVocabularyHandler
   */
  private BMImportVocabularyHandler $BMImportVocabularyHandler;

  /**
   * # missingFields
   *
   * List of fields and types absent in the installation.
   * * keyed by type (contentType, Paragraph, taxonomy) of missing fields
   *
   * @var array|mixed
   */
  private mixed $missingFields;

  /**
   * # entitiesList
   * List of entities and fields which are checked from config yml and are present in installation.
   *
   * * After `checkInstallation_doFieldsExist` array keyed by type and found type instance
   * ```
   * [contentTypes][{type}]=true
   * [node_field][{fieldName}]=true
   * [taxonomy][{taxonomyName}]=true
   * [paragraph][{paragraphTypeName}]=true
   * [paragraph_field][{paragraphFieldName}]=true
   * ```
   *
   * @var array|mixed
   */
  private mixed $entitieslist;


  public function __construct(BMImport $BMImport) {
    bm_d('','<div class="bgcolor-blue fgcolor-white">Instantiating BMImport_todo_before_import</div>');
    $this->BMImport = $BMImport;
  }

  /**
   * # preflight_checks_panel__create_required_fields
   *
   * #### todo_before_import: run the todo_before_import actions
   *
   * * processes yaml section 'todo_before_import',
   * * it checks if the mentioned taxonomy, paragraphs, and fields are present.
   *  * create paragraph types and fields
   *  * creates vocabulary types and fields
   *  * creates node types and fields
   *
   * ## Prerequisite
   * * `BMImport` must be instantiated
   * * `BMImport->system_settings_yml` must have been initialized.
   *
   * Returns: returns a `form element` informing the user
   * Returns: a `"ready"` variable which is set when there are no missing parts
   *
   * Returns an array containing form elements and readiness status.
   *
   * @return array {
   *  * @type array '$form' The element displaying checked and created fields and entities.
   *  * @type bool '$ready' Indicates readiness; true if ready, false otherwise.
   * }
   * @throws EntityStorageException
   * @throws \Exception
   */
  public function preflight_checks_panel__create_required_fields(): array {

    // $this->BMImport->registerImportType($filePath);

    bm_d(BMImport::BM_D_TRACE, '<span class="fgcolor-lightblue">Trace: <b>BMImport_todo_before_import</b> Creating missing entities and fields using definition: <b>' . $this->BMImport->importType . '</b></span>');

    $readyForImport = TRUE;
    $form = ["bmImportOK" => ['#type' => "markup", "#markup" => "<h3>OK</h3>"]];
    $fieldOk = [];
    $notExist=[];

    // bm_d(self::BM_D_KEY,__FUNCTION__.' checking yml for presence of get_form_element__todo_before_import__entity_types_and_field_defs'.json_encode($this->importInformation));
    if (isset($this->BMImport->system_settings_yml['todo_before_import'])) {

      // FORM DISPLAY - CHECK IF ANY FIELDS FROM YAML ARE STILL MISSING FROM INSTANCE
      $notExist = $this->checkInstallation_doFieldsExist();

      //      $entityFieldsToCreate = [];

      // YAML SECTION Todo before import
      // ITERATE THE ENTITIES AND FIELDS WHICH MUST BE PRESENT
      // IT CREATES THE REQUESTED ENTITIES AND FIELDS.
      foreach ($this->BMImport->system_settings_yml['todo_before_import'] as $n => $ymlKey) {

        switch ($ymlKey['name']) {
          case 'create_vocabulary_type':
            $this->create_vocabulary_type_from_yml_section($ymlKey, $form, $n);
            break;
          case 'create_vocabulary_fields':
            $fieldOk = $this->create_vocabulary_fields_from_yml_section($ymlKey, $form, $n, $fieldOk);
            break;
          case 'create_paragraph_type':
            $this->create_paragraph_type_from_yml_section($ymlKey, $form, $n);
            break;
          case 'create_paragraph_fields':
            $fieldOk = $this->create_paragraph_fields_from_yml_section($ymlKey, $form, $n, $fieldOk);
            break;
          case 'create_node_type':
            $this->create_node_type_from_yml_section($ymlKey, $form, $n);
            break;

          // create a field on a node.
          case 'create_node_fields':
            $fieldOk = $this->create_node_fields_from_yml_section($ymlKey, $form, $n, $fieldOk);
            break;
          default:
            bm_d('', $this->BMImport->system_settings_yml,' BM in the yaml there is an unexpected value,  [' . $ymlKey['name'] . '] for the main keys in section: <b>todo_before_import</b> YML','dsm');
        }
      }
    }
    else {
      bm_d('',$this->BMImport->system_settings_yml,' " todo_before_import" is not present in YAML settings','dsm');
    }

    // FORM DISPLAY - CHECK IF ANY FIELDS FROM YAML ARE STILL MISSING FROM INSTANCE
    bm_d('','Retest fields exist','','screen');
    $notExist = $this->checkInstallation_doFieldsExist();

    $readyForImport = count($notExist) === 0;
    if (!$readyForImport) {
      $markup = '<thead><th>Entity</th><th>Field</th></thead>';
      foreach ($notExist as $entity => $fieldNames) {
        foreach ($fieldNames as $fieldName) {
          $markup .= "<tr><td>$entity</td><td>$fieldName</td></tr>";
        }
      }

      $form['missing_fields'] = [
        '#type' => 'markup',
        '#markup' => '
                  <h3>These are the things to do, before importing</h3>
                  <p>Refresh the page after fixing these.</p>
                  <h3>Missing fields.</h3><table>' . $markup . '</table>',

      ];
    }

    bm_d(BMImport::BM_D_TRACE, '<span class="fgcolor-lightblue">Trace-END: <b>BMImport_todo_before_import</b> - FINISHED</span>');

    return [
      'form' => $form,
      'ready' => $readyForImport,
    ];
  }

  /**
   * # checkInstallation_doFieldsExist
   * * it reads yml section `todo_with_columns`
   * and verifies the mentioned entity types and fields
   * exist. When they do not exist, they are created.
   *
   * * the form is used to display the state (green or orange)
   *
   * ## Caches
   * entitiesList, list of contenttypes and field names keyed by create_node_type
   * * `BMImport: entitiesList = $entitiesList`
   *
   * * get `todo_with_columns` yml section
   * ```
   * # CREATE NODE_TYPE
   * #  - name: create_node_type
   * #    label: Contact
   * #    content_type: contact
   * #    description: 'Contact detail information'
   * ```
   * ```
   * # CREATE NODE FIELDS
   * #  - name: create_node_fields
   * #    content_type: listing
   * #    fields:
   * #      - name: 'field_unencoded_title'
   * #        type: string_long
   * #        description: 'The title in unencoded form'
   * #        multiple: false
   * ```
   * ```
   * # CREATE VOCABULARY TYPE format
   * #   - name: _create_vocabulary_type
   * #     vocabulary: financial_advisor_products
   * #     label:
   * #     description: 'de categorieën van de financial advisors'
   * ```
   *
   *          - column_name: LAND
   *            field: field_para_address_country
   * ```
   *
   * @return array
   */
  private function checkInstallation_doFieldsExist(): array {

    $this->entitieslist = [];

    if (empty($this->BMImport->system_settings_yml['todo_with_columns'])) {
      return [];
    }

    $debug = '';
    bm_d($debug, '', "BMImport_todo_before_import.<br /> Checking if required entities and fields are present in the installation.");
    $contentType = '';
    $this->missingFields = [];
    foreach ($this->BMImport->system_settings_yml['todo_with_columns'] as $item) {
      // node_create, node_fields, node_paragraph_fields, node_taxonomy_fields
      foreach ($item as $key => $subItem) {
        //dsm($key);
        //dsm($subItem);
        switch ($key) {
          case 'node_create':
            $contentType = $subItem[BMImportYMLHandler::TYPE];
            $this->entitieslist['contentTypes'][$contentType] = TRUE;
            break;

          // 1st level fields
          case 'node_fields':
            $nodeFields = [];
            foreach ($subItem as $subSubItem) {
              $fieldName = $subSubItem[BMImportYMLHandler::FIELD] ?? '';

              // assume present until proven otherwise
              $this->entitieslist['node_field'][$fieldName] = TRUE;

              $nodeFields[$fieldName] = $this->getBMImportFieldCreator()
                ->checkContentTypeHasField($contentType, $fieldName);

              if (!$nodeFields[$fieldName] && strlen(trim($fieldName))) {
                $this->missingFields[$contentType][] = $fieldName;
                //
                $this->entitieslist['node_field'][$fieldName] = FALSE;
              }

              // check it uses a taxonomy and it exists
              $taxonomy = $subSubItem[BMImportYMLHandler::TAXONOMY] ?? '';
              if ($taxonomy) {
                $this->entitieslist['taxonomy'][$taxonomy] = TRUE;
                $exists = $this->getBMImportVocabularyHandler()->check_vocabularyExists($taxonomy);
                if (!$exists) {
                  $this->missingFields[$contentType . ':' . $fieldName][] = 'taxonomy:' . $taxonomy;
                  $this->entitieslist['taxonomy'][$taxonomy] = FALSE;
                }
              }
            }


            break;

          case 'node_paragraph_fields':
            // REQUIRED FORMAT
            // Example given, multiple different paragraphs on one node field reference
            //       - FIELD: field_listing_specifics_para
            //        PARAGRAPH:
            //          NAME: infrastructure_paragraph
            //          fields:
            //            # regular fields definitions
            //      - FIELD: field_listing_specifics_para
            //        PARAGRAPH:
            //          NAME: finadvisors_listing_paragraph
            //          fields:
            //            # regular fields definitions

            foreach($subItem as $paragraph) {

              if (!isset($paragraph[BMImportYMLHandler::PARAGRAPH][BMImportYMLHandler::NAME])) {
                $message = __LINE__ . ' BMImport <b>Not found</b>. Expected yaml structure "paragraph=>name" in section: <i>todo_with_columns</i> YML near:' . json_encode($paragraph);
                Drupal::messenger()->addError($message);
                bm_d($debug, $paragraph, $message, 'dsm');
                continue;
              }

              $paraName = $paragraph[BMImportYMLHandler::PARAGRAPH][BMImportYMLHandler::NAME];
              $this->entitieslist['paragraph'][$paraName] = TRUE;
              if (!$this->getBMImportParagraphHandler()->check_paragraphTypeExists($paraName)) {
                Drupal::messenger()->addError(__LINE__ . ' BMImport <b>Not found</b>. Paragraph type does not exist <b>' . $paraName . '</b> in section: <i>todo_with_columns</i> YML near:' . json_encode($paragraph));

                $this->missingFields[$paraName][] = 'paragraph type: ' . $paraName;
                $this->entitieslist['paragraph'][$paraName] = TRUE;
                continue;

              }


              $paraFields = [];

              if (!isset($paragraph[BMImportYMLHandler::PARAGRAPH][BMImportYMLHandler::FIELDS])) {
                Drupal::messenger()
                  ->addError(__LINE__ . ' BMImport <b>Not found</b>. expected yaml structure "paragraph=>fields" in section: <i>todo_with_columns</i> YML near:' . json_encode($paragraph));
                bm_d($debug, __LINE__ . ' NODE PARA FIELDS check subitem is it a string?');
                bm_d($debug, $subItem, 'subItem', 'dsm');
                continue;
              }
              // check fields
              // expects
              // field : field_listing_place_paragraph
              // paragraph:
              //  name: paragraph_address
              //  fields:
              //    column_name:
              //    field:
              //    ...

              foreach ($paragraph[BMImportYMLHandler::PARAGRAPH][BMImportYMLHandler::FIELDS] as $paraSubSubItem) {
                $fieldName = $paraSubSubItem['field'] ?? '';
                $this->entitieslist['paragraph_field'][$fieldName] = TRUE;

                $paraFields[$fieldName] = $this->getBMImportParagraphHandler()
                  ->check_fieldExistsOnParagraphType($paraName, $fieldName);
                if (!$paraFields[$fieldName] && strlen(trim($fieldName))) {
                  $this->missingFields[$paraName][] = $fieldName;
                  $this->entitieslist['paragraph_field'][$fieldName] = FALSE;

                }
                else {
                  // check it uses a taxonomy and it exists
                  $taxonomy =$paragraph[BMImportYMLHandler::TAXONOMY] ?? '';
                  if ($taxonomy) {
                    $this->entitieslist['taxonomy'][$taxonomy] = TRUE;
                    $exists = $this->getBMImportVocabularyHandler()->check_vocabularyExists($taxonomy);
                    if (!$exists) {
                      $this->entitieslist['taxonomy'][$taxonomy] = FALSE;

                      $this->missingFields[$paraName][] = 'taxonomy:' . $taxonomy;

                    }
                  }
                }
              }
              }
            break;
          default:
            bm_d(BMImport::BM_D_KEY, $subItem, 'this type is not yet supported for auto creation', 'dsm');
            break;
        }
      }

    }
    // interpret html from any created messages
    $this->BMImport->markup_messages_html_save();

    bm_d(BMImport::BM_D_KEY, $this->missingFields, __FUNCTION__.': these are the missing fields', 'dsm');
    bm_d(BMImport::BM_D_KEY, $this->entitieslist,  __FUNCTION__.': These are the entities mentioned in the YML', 'dsm');

    $this->set_cache_entitiesList($this->entitieslist);

    return $this->missingFields;
  }

  /**
   * # set_cache_entitiesList
   *
   * @param $entitiesList
   *
   * @return void
   */
  private function set_cache_entitiesList($entitiesList): void {
    $cache = Drupal::cache('bluemarloc')->get('BMImport');
    if ($cache !== FALSE) {
      $data = $cache->data;
    }
    $data['entitiesList'] = $entitiesList;
    Drupal::cache('bluemarloc')->set('BMImport', $data);
  }

  /**
   * # display_result_in_form
   * * every create step, has the same message, created here.
   *
   * @param $form
   * @param $n
   * @param $entityName
   * @param $fieldName
   * @param $success
   *
   * @return void
   */
  private function display_result_in_form(&$form, $n, $entityName, $fieldName, $success): void {
    if (!$success) {
      $message = "BMImport has created $entityName, $fieldName";
      Drupal::messenger()->addWarning($message);
      Drupal::logger('BMImport')->info($message);
      $form['bmimport_' . $n] = [
        '#type' => 'markup',
        '#markup' => '<div class="checked-warning"> ' . $entityName . ':' . $fieldName . ' - Has been created</div>',
      ];
    }
    else {
      $form['bmimport_' . $n] = [
        '#type' => 'markup',
        '#markup' => '<div class="checked-ok"> ' . $entityName . ':' . $fieldName . ' - Exists</div>',
      ];
    }
  }

  /**
   *
   * @param mixed      $ymlKey
   * @param array      $form
   * @param int|string $n
   *
   * @return void
   * @throws EntityStorageException
   */
  private function create_vocabulary_type_from_yml_section(mixed $ymlKey, array &$form, int|string $n): void {
    $vocabularyName = $ymlKey['vocabulary'];
    $description = $ymlKey['description'];
    $vid = Vocabulary::load($vocabularyName);
    if (!$vid) {
      $readyForImport = FALSE;
      $this->getBMImportVocabularyHandler()
        ->createVocabulary($vocabularyName, $vocabularyName, $description);

      $this->display_result_in_form($form, $n, 'VOCABULARY', $vocabularyName, FALSE);
    }
    else {
      $this->display_result_in_form($form, $n, "Vocabulary", $vocabularyName, TRUE);
    }

  }

  /**
   * @param mixed      $ymlKey
   * @param mixed      $form
   * @param int|string $n
   * @param            $fieldOk
   *
   * @return array
   * @throws EntityStorageException
   * @throws Exception
   */
  private function create_vocabulary_fields_from_yml_section(mixed $ymlKey, mixed &$form, int|string $n, $fieldOk): array {
    $description = $ymlKey['description'] ?? 'BMImport';
    $vocabularyName = $ymlKey['vocabulary'];
    $taxonomyLabel = $ymlKey['label'] ?? $vocabularyName;

    //   $this->YMLSection_create_vocabulary_type($ymlKey, $form, $n);

    foreach ($ymlKey['fields'] as $nn => $field) {
      $fieldName = $this->getVerifiedFieldName($field['name']);
      $fieldDescription = $field['description'] ?? $description;
      $multiple = $field['multiple'] ?? FALSE;

      // not used any more
      $references = $field['references'] ?? NULL;

      // identifies creating a taxonomy reference field on node
      $vocabulary_id = $field['vocabulary'] ?? FALSE;
      // identifies creating a paragraph reference field on node
      $paragraphType = $field['paragraphType'] ?? FALSE;
      // identifies creating a node reference field on node
      $fieldContentType = $field['contentType'] ?? FALSE;
      // when none of the above, identifies the regular field on a node type
      $fieldType = $field['type'] ?? FALSE;

      // f.x. field_term_color value: 'green' on a vocabulary
      $default_value = $field['value'] ?? NULL;

      if (!$this->getBMImportVocabularyHandler()
        ->fieldExistsOnTaxonomy($vocabularyName, $fieldName)) {

        if ($vocabulary_id) {
          $this->BMImportFieldCreator->createTaxoFieldOnTaxonomy($vocabularyName, $fieldName, $fieldDescription, $multiple, explode(',', $vocabulary_id));
        }
        elseif ($paragraphType) {
          $this->BMImportFieldCreator->createParaFieldOnTaxonomy($vocabularyName, $fieldName, $fieldDescription, $multiple, explode(',', $paragraphType));
        }
        elseif ($fieldContentType) {
          $this->BMImportFieldCreator->createNodeReferenceOnTaxonomy($vocabularyName, $fieldName, $fieldDescription, $multiple, explode(',', $fieldContentType));
        }
        else {
          $this->BMImportFieldCreator->createSimpleFieldOnVocabulary($vocabularyName, $fieldName, $fieldType, $fieldDescription, $multiple, $default_value);
        }

        $this->display_result_in_form($form, $n . '_' . $nn, $vocabularyName, $fieldName, FALSE);

      }
      else {
        $this->display_result_in_form($form, $n . '_' . $nn, $vocabularyName, $fieldName, TRUE);
        $fieldOk[] = $fieldName;
      }
    }
    return $fieldOk;
  }

  /**
   * @throws EntityStorageException
   */
  private function create_paragraph_type_from_yml_section($ymlKey, $form, $n): void {
    $paragraphName = $ymlKey['paragraph'];
    $paragraphLabel = $ymlKey['label'] ?? $paragraphName;
    $description = $ymlKey['description'] ?? 'BMImport';
    $translatable = $ymlKey['translatable'] ?? FALSE;
    $icon = $ymlKey['icon'] ?? FALSE;

    if (!$this->getBMImportParagraphHandler()->check_paragraphTypeExists($paragraphName)) {
      $this->getBMImportParagraphHandler()
        ->createParagraphType($paragraphName, $paragraphLabel, $description, $translatable, $icon);
      $this->display_result_in_form($form, $n, 'PARAGRAPH TYPE', $paragraphName, FALSE);
    }
    else {
      $this->display_result_in_form($form, $n, 'Paragraph type', $paragraphName, TRUE);
    }
  }

  /**
   * @param mixed      $ymlKey
   * @param mixed      $form
   * @param int|string $n
   * @param array      $fieldOk
   *
   * @return array
   * @throws EntityStorageException
   * @throws Exception
   */
  private function create_paragraph_fields_from_yml_section(mixed $ymlKey, mixed &$form, int|string $n, array $fieldOk): array {
    $description = $ymlKey['description'] ?? 'BMImport';
    $paragraphName = $ymlKey['paragraph'];
    $paragraphLabel = $ymlKey['label'] ?? $paragraphName;

    // make sure the paragraph type exists
    $this->create_paragraph_type_from_yml_section($ymlKey, $form, $n);

    foreach ($ymlKey['fields'] as $nn => $field) {
      $fieldName = $this->getVerifiedFieldName($field['name']);
      $fieldDescription = $field['description'] ?? $description;
      $multiple = $field['multiple'] ?? FALSE;

      // not used any more
      $references = $field['references'] ?? NULL;

      // identifies creating a taxonomy reference field on node
      $vocabulary_id = $field['vocabulary'] ?? FALSE;
      // identifies creating a paragraph reference field on node
      $paragraphType = $field['paragraphType'] ?? FALSE;
      // identifies creating a node reference field on node
      $fieldContentType = $field['contentType'] ?? FALSE;
      // when none of the above, identifies the regular field on a node type
      $fieldType = $field['type'] ?? FALSE;

      $default_value = $field['value'] ?? NULL;

      if (!$this->getBMImportParagraphHandler()
        ->check_fieldExistsOnParagraphType($paragraphName, $fieldName)) {
        $readyForImport = FALSE;


        if ($vocabulary_id) {
          $this->BMImportFieldCreator->createTaxoFieldOnParagraph($paragraphName, $fieldName, $fieldDescription, $multiple, explode(',', $vocabulary_id));
        }
        elseif ($paragraphType) {
          $this->BMImportFieldCreator->createParaFieldOnParagraph($paragraphName, $fieldName, $fieldDescription, $multiple, explode(',', $paragraphType));
        }
        elseif ($fieldContentType) {
          $this->BMImportFieldCreator->createNodeReferenceOnParagraph($paragraphName, $fieldName, $fieldDescription, $multiple, explode(',', $fieldContentType));
        }
        else {
          $this->BMImportFieldCreator->createSimpleFieldOnParagraph($paragraphName, $fieldName, $fieldType, $fieldDescription, $multiple, $default_value);
        }

        $this->display_result_in_form($form, $n . '_' . $nn, $paragraphName, $fieldName, FALSE);
      }
      else {
        $this->display_result_in_form($form, $n . '_' . $nn, $paragraphName, $fieldName, TRUE);
        $fieldOk[] = $fieldName;
      }

    }


    //            if (count($fieldOk)) {
    //              $markup = '';
    //              foreach ($fieldOk as $field) {
    //                $markup .= '<div class="checked-ok">Field -' . $field . ' in ' . $paragraphName . ' - Exists</div>';
    //              }
    //              $form['paraFieldsOk_' . $n] = [
    //                '#type' => 'markup',
    //                '#markup' => $markup,
    //              ];
    //            }
    return $fieldOk;
  }

  /**
   * @param mixed      $ymlKey
   * @param mixed      $form
   * @param int|string $n
   *
   * @return void
   * @throws EntityStorageException
   */
  private function create_node_type_from_yml_section(mixed $ymlKey, mixed &$form, int|string $n): void {
    $mname = $ymlKey['content_type'];
    $label = $ymlKey['label'] ?? $mname;
    $description = $ymlKey['description'] ?? 'BMImport';
    $help = $ymlKey['help'] ?? $description;
    // create content type if not present.
    if (!NodeType::load($mname)) {
      $this->GetBMImportNodeHandler()->createNodeType($mname, $label, $description, $help);
      $this->display_result_in_form($form, $n, 'CONTENTTYPE', $mname, FALSE);
    }
    else {
      $this->display_result_in_form($form, $n, 'Content type', $mname, TRUE);
    }

  }

  /**
   * @param mixed      $ymlKey
   * @param mixed      $form    the form displays the correct fields in green, and new in red.
   * @param int|string $n
   * @param mixed      $fieldOk list of fields which are found OK
   *
   * @return array fieldOk a list of fields which ar found and are OK
   * @throws EntityStorageException
   * @throws Exception
   */
  private function create_node_fields_from_yml_section(mixed $ymlKey, mixed &$form, int|string $n, mixed $fieldOk): array {

    if (!isset($ymlKey['content_type'])) {
      $message = 'YMLSection_create_node_fields key:[content_type] is missing.';
      throw new Exception($message);
    }

    $contentType = $ymlKey['content_type'];
    $label = $ymlKey['label'] ?? $contentType;
    $description = $ymlKey['description'] ?? 'BMImport';

    // just make sure the content type is present
    // $this->YMLSection_create_node_type($ymlKey, $form, $n);

    foreach ($ymlKey['fields'] as $nn => $field) {
      $fieldName = $this->getVerifiedFieldName($field['name']);
      $fieldDescription = $field['description'] ?? $description;
      $multiple = $field['multiple'] ?? FALSE;

      // not used any more
      $references = $field['references'] ?? NULL;

      // identifies creating a taxonomy reference field on node
      $vocabulary_id = $field['vocabulary'] ?? FALSE;
      // identifies creating a paragraph reference field on node
      $paragraphType = $field['paragraphType'] ?? FALSE;
      // identifies creating a node reference field on node
      $fieldContentType = $field['contentType'] ?? FALSE;
      // when none of the above, identifies the regular field on a node type
      $fieldType = $field['type'] ?? FALSE;

      $default_value = $field['value'] ?? NULL;

      if ($vocabulary_id) {
        if (!$this->getBMImportFieldCreator()
          ->checkContentTypeHasFieldAndType($contentType, $fieldName, 'entity_reference')) {
          bm_d('', $fieldName, "Create TAXO field on NODE");
          $this->BMImportFieldCreator->createTaxoFieldOnNode($contentType, $fieldName, $fieldDescription, $multiple, explode(',', $vocabulary_id));
          $readyForImport = FALSE;
          $this->display_result_in_form($form, $n . '_' . $nn, $contentType, $fieldName, FALSE);
        }
        else {
          $this->display_result_in_form($form, $n . '_' . $nn, $contentType, $fieldName, TRUE);
          $fieldOk[] = $fieldName;
        }
      }
      // paragraph ref on node
      elseif ($paragraphType) {
        if (!$this->getBMImportFieldCreator()
          ->checkContentTypeHasFieldAndType($contentType, $fieldName, 'entity_reference_revisions')) {
          bm_d('', $fieldName, 'Create PARA field on NODE');
          $this->BMImportFieldCreator->createParaFieldOnNode($contentType, $fieldName, $fieldDescription, $multiple, explode(',', $paragraphType));
          $readyForImport = FALSE;
          $this->display_result_in_form($form, $n . '_' . $nn, $contentType, $fieldName, FALSE);
        }
        else {
          $this->display_result_in_form($form, $n . '_' . $nn, $contentType, $fieldName, TRUE);
          $fieldOk[] = $fieldName;
        }
      }
      // Node ref on node
      elseif ($fieldContentType) {
        if (!$this->getBMImportFieldCreator()
          ->checkContentTypeHasFieldAndType($contentType, $fieldName, 'entity_reference')) {
          bm_d('', $fieldName, 'Create NODE field on NODE');
          $this->BMImportFieldCreator->createNodeReferenceOnNode($contentType, $fieldName, $fieldDescription, $multiple, explode(',', $fieldContentType));
          $readyForImport = FALSE;
          $this->display_result_in_form($form, $n . '_' . $nn, $contentType, $fieldName, FALSE);
        }
        else {
          $this->display_result_in_form($form, $n . '_' . $nn, $contentType, $fieldName, TRUE);
          $fieldOk[] = $fieldName;
        }
      }
      // regular fields
      else {
        if (!$this->getBMImportFieldCreator()
          ->checkContentTypeHasFieldAndType($contentType, $fieldName, $fieldType)) {
          bm_d('', $fieldName, 'Create REGULAR field on NODE');
          $this->BMImportFieldCreator->createSimpleFieldOnNode($contentType, $fieldName, $fieldType, $fieldDescription, $multiple, $default_value);
          $readyForImport = FALSE;
          $this->display_result_in_form($form, $n . '_' . $nn, $contentType, $fieldName, FALSE);
        }
        else {
          $this->display_result_in_form($form, $n . '_' . $nn, $contentType, $fieldName, TRUE);
          $fieldOk[] = $fieldName;
        }
      }


    }
    return $fieldOk;
  }

  /**
   * @return BMImportVocabularyHandler
   */
  private function getBMImportVocabularyHandler(): BMImportVocabularyHandler {
    if (!isset($this->BMImportVocabularyHandler)) {
      $this->BMImportVocabularyHandler = new BMImportVocabularyHandler();
    }
    return $this->BMImportVocabularyHandler;
  }

  private function getBMImportNodeHandler(): BMImportNodeHandler {
    if (!isset($this->BMImportNodeHandler)) {
      $this->BMImportNodeHandler = new BMImportNodeHandler();
    }
    return $this->BMImportNodeHandler;
  }

  /**
   * @return BMImportParagraphHandler
   */
  private function getBMImportParagraphHandler(): BMImportParagraphHandler {
    if (!isset($this->BMImportParagraphHandler)) {
      $this->BMImportParagraphHandler = new BMImportParagraphHandler();
    }
    return $this->BMImportParagraphHandler;
  }

  /**
   * @return BMImportCreator
   */
  private function getBMImportCreator(): BMImportCreator {
    if (!isset($this->BMImportCreator)) {
      $this->BMImportCreator = new BMImportCreator();
    }
    return $this->BMImportCreator;
  }

  /**
   * @return BMImportFieldCreator
   */
  private function getBMImportFieldCreator(): BMImportFieldCreator {
    if (!isset($this->BMImportFieldCreator)) {
      $this->BMImportFieldCreator = new BMImportFieldCreator();
    }
    return $this->BMImportFieldCreator;
  }

  /**
   * check YAML content name on correctness.
   *
   * @param string $fieldName
   *
   * @return string
   * @throws Exception
   */
  private function getVerifiedFieldName(string $fieldName): string {
    if (strlen($fieldName) > 32) {
      throw new Exception("The YAML field name '.$fieldName.' must be no more than 32 characters");
    }
    if (!$this->isValidMachineName($fieldName)) {
      throw new Exception("The YAML field name '$fieldName' is not a valid machine name");
    }
    return $fieldName;
  }

  private function isValidMachineName($id): bool {
    // Regular expression to match valid machine-readable names
    return (bool) preg_match('/^[a-z0-9_]+$/', $id);
  }

}
