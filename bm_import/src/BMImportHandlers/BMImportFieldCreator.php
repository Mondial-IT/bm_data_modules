<?php
/*
 * Copyright (c) 2024. Mondial-IT BV
 */
/** @noinspection PhpClassHasTooManyDeclaredMembersInspection */

namespace Drupal\bm_import\BMImportHandlers;

use Drupal\bm_import\BMImportHandlers\BMImportParagraphHandler;
use Drupal;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\Markup;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Exception;

/**
 * # Class BMImportFieldCreator
 *
 * This class is responsible for creating and managing field definitions in a Drupal environment.
 * It provides methods to create fields for nodes, paragraphs, and taxonomy terms, including the
 * necessary field storage configurations and field instances. The class also includes utility methods
 * for checking the existence of fields within specific content types or paragraph types.
 *
 * ## Key Responsibilities:
 * - Creating field storage configurations and field instances for nodes, paragraphs, and taxonomy terms.
 * - Handling specific settings for fields, such as cardinality and entity reference settings.
 * - Ensuring that necessary manual adjustments are highlighted after field creation.
 * - Providing utility methods to check if a specific field exists on a content type, paragraph type, or taxonomy term.
 *
 * ## Methods Overview:
 *
 * * create taxonomy reference, paragraph reference, node reference or a simple field on a Node
 *
 * `createTaxoFieldOnNode`:
 * `createParaFieldOnNode`:
 * `createNodeReferenceOnNode`:
 * `createSimpleFieldOnNode`:
 *
 * * create taxonomy reference, paragraph reference, node reference or a simple field on a Paragraph
 *
 * `createTaxoFieldOnParagraph`:
 * `createParaFieldOnParagraph`:
 * `createNodeReferenceOnParagraph`:
 * `createSimpleFieldOnParagraph`:
 *
 * * create taxonomy reference, paragraph reference, node reference or a simple field on a Taxonomy
 *
 * `createTaxoFieldOnTaxonomy`:
 * `createParaFieldOnTaxonomy`:
 * `createNodeReferenceOnTaxonomy`:
 * `createSimpleFieldOnVocabulary`:
 *
 * * check field existence
 *
 * `checkContentTypeHasFieldAndType`:
 *  checkContentTypeHasField`:
 *
 * ## Usage Example:
 * ```
 * $fieldCreator = new BMImportFieldCreator();
 * $paragraph = $fieldCreator()
 * ```
 *
 * ## Important Notes:
 * - The class generates fields that may require manual adjustments in the Drupal admin UI, particularly for
 *   entity reference fields. Warnings are issued to indicate where manual work is needed.
 * - Entity reference settings may not always be fully configured programmatically due to Drupal limitations,
 *   and additional configuration through the Drupal UI may be necessary.
 *
 * @package Drupal\bm_import\BMImport
 */
class BMImportFieldCreator {

  private function warningManualWorkToBeDoneOnNewFields(): void {
    $message = 'BMIMPORTFIELDCREATOR
                <br />*******************************************************************************************
                <br />NOTE1 ER IS NOG ISSUE MET TAXO EN REFERENCES DIE MOETEN OOK APART GEDETECTEERD WORDEN
                <br />NOTE2 fields are disabled from viewing and need to be enabled manually in the "node edit view"
                <br />NOTE paragraph selection is not set automatically, go to gui and edit field definition manually
                <br />NOTE content type selection is not set automatically, go to gui and edit field definition manually
                <br />***************************************************************************************
';
    $message = Markup::create($message);
    Drupal::messenger()->addWarning($message);
  }

  public function __construct() {


  }

  /**
   * @param $contentType
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $vocabulary_ids
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTaxoFieldOnNode($contentType, $fieldName, $description, $multiple, $vocabulary_ids): void {
    $this->createFieldStorage('node', $fieldName, 'entity_reference', $multiple, 'taxonomy_term', $vocabulary_ids);
    $this->createFieldInstance('node', $contentType, $fieldName, 'entity_reference', $description, 'taxonomy_term', $vocabulary_ids);
  }

  /**
   * @param $contentType
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $paragraphTypes
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createParaFieldOnNode($contentType, $fieldName, $description, $multiple, $paragraphTypes): void {
    bm_d('',$paragraphTypes,'createParaFieldOnNode');

    $paragraphHandler= new BMImportParagraphHandler();
    foreach ($paragraphTypes as $paragraphType) {
      if($paragraphHandler->check_paragraphTypeExists($paragraphType)){
        bm_d('',$paragraphType,'BMImportFieldCreator: createParaFieldOnNode exists');
      }else{
        throw new \Exception($paragraphType.' not found');
      }
    }

    $this->createFieldStorage(
      'node', // The entity type (node)
      $fieldName, // The machine name of the field
      'entity_reference_revisions', // The field type (entity_reference_revisions for paragraphs)
      $multiple, // Whether the field allows multiple values
      'paragraph', // The target type is 'paragraph'
      $paragraphTypes // The bundles (paragraph types) that can be referenced
    );

    $this->createFieldInstance(
      'node', // The entity type (node)
      $contentType, // The bundle (content type)
      $fieldName, // The machine name of the field
      'entity_reference_revisions', // The field type
      $description, // The field description
      'paragraph', // The target type is 'paragraph'
      $paragraphTypes // The bundles (paragraph types) that can be referenced
    );
  }

  /**
   * @param $contentType
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $nodeTypes
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createNodeReferenceOnNode($contentType, $fieldName, $description, $multiple, $nodeTypes): void {
    $this->createFieldStorage('node', $fieldName, 'entity_reference', $multiple, 'node', $nodeTypes);
    $this->createFieldInstance('node', $contentType, $fieldName, 'entity_reference', $description, 'node', $nodeTypes);
  }

  /**
   * @param      $contentType
   * @param      $fieldName
   * @param      $fieldType
   * @param      $description
   * @param      $multiple
   * @param null $default_value
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createSimpleFieldOnNode($contentType, $fieldName, $fieldType, $description, $multiple, $default_value=null): void {
    $this->createFieldStorage('node', $fieldName, $fieldType, $multiple);
    $this->createFieldInstance('node', $contentType, $fieldName, $fieldType, $description);
    if($default_value){
      $fieldInstance = FieldConfig::loadByName('node',$contentType, $fieldName);
      $fieldInstance?->set('default_value', [['value' => $default_value]]);
      $fieldInstance?->save();
    }
  }

  /**
   * @param $paragraphType
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $vocabulary_ids
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTaxoFieldOnParagraph($paragraphType, $fieldName, $description, $multiple, $vocabulary_ids): void {
    $this->createFieldStorage('paragraph', $fieldName, 'entity_reference', $multiple, 'taxonomy_term', $vocabulary_ids);
    $this->createFieldInstance('paragraph', $paragraphType, $fieldName, 'entity_reference', $description, 'taxonomy_term', $vocabulary_ids);
  }

  /**
   * @param $paragraphType
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $paragraphTypes
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createParaFieldOnParagraph($paragraphType, $fieldName, $description, $multiple, $paragraphTypes): void {

    $this->createFieldStorage('paragraph', $fieldName, 'entity_reference_revisions', $multiple, 'paragraph', $paragraphTypes);
    $this->createFieldInstance('paragraph', $paragraphType, $fieldName, 'entity_reference_revisions', $description, 'paragraph', $paragraphTypes);
  }

  /**
   * @param $paragraphType
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $nodeTypes
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createNodeReferenceOnParagraph($paragraphType, $fieldName, $description, $multiple, $nodeTypes): void {
    $this->createFieldStorage('paragraph', $fieldName, 'entity_reference', $multiple, 'node', $nodeTypes);
    $this->createFieldInstance('paragraph', $paragraphType, $fieldName, 'entity_reference', $description, 'node', $nodeTypes);
  }

  /**
   * @param $paragraphType
   * @param $fieldName
   * @param $fieldType
   * @param $description
   * @param $multiple
   * @param $default_value
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createSimpleFieldOnParagraph($paragraphType, $fieldName, $fieldType, $description, $multiple, $default_value): void {
    bm_d('',[$paragraphType, $fieldName, $fieldType, $description, $multiple],'createParaFieldOnParagraph');
    $this->createFieldStorage('paragraph', $fieldName, $fieldType, $multiple);
    $this->createFieldInstance('paragraph', $paragraphType, $fieldName, $fieldType, $description);
    if($default_value){
      $fieldInstance = FieldConfig::loadByName('paragraph',$paragraphType, $fieldName);
      $fieldInstance?->set('default_value', [['value' => $default_value]]);
      $fieldInstance?->save();
    }
  }

  /**
   * @param $vocabulary
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $vocabulary_ids
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createTaxoFieldOnTaxonomy($vocabulary, $fieldName, $description, $multiple, $vocabulary_ids): void {
    $this->createFieldStorage('taxonomy_term', $fieldName, 'entity_reference', $multiple, 'taxonomy_term', $vocabulary_ids);
    $this->createFieldInstance('taxonomy_term', $vocabulary, $fieldName, 'entity_reference', $description, 'taxonomy_term', $vocabulary_ids);
  }

  /**
   * @param $vocabulary
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $paragraphTypes
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createParaFieldOnTaxonomy($vocabulary, $fieldName, $description, $multiple, $paragraphTypes): void {
    $this->createFieldStorage('taxonomy_term', $fieldName, 'entity_reference_revisions', $multiple, 'paragraph', $paragraphTypes);
    $this->createFieldInstance('taxonomy_term', $vocabulary, $fieldName, 'entity_reference_revisions', $description, 'paragraph', $paragraphTypes);
  }

  /**
   * @param $vocabulary
   * @param $fieldName
   * @param $description
   * @param $multiple
   * @param $nodeTypes
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createNodeReferenceOnTaxonomy($vocabulary, $fieldName, $description, $multiple, $nodeTypes): void {
    $this->createFieldStorage('taxonomy_term', $fieldName, 'entity_reference', $multiple, 'node', $nodeTypes);
    $this->createFieldInstance('taxonomy_term', $vocabulary, $fieldName, 'entity_reference', $description, 'node', $nodeTypes);
  }

  /**
   * @param $vocabulary
   * @param $fieldName
   * @param $fieldType
   * @param $description
   * @param $multiple
   * @param $default_value
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function createSimpleFieldOnVocabulary($vocabulary, $fieldName, $fieldType, $description, $multiple, $default_value): void {
    $this->createFieldStorage('taxonomy_term', $fieldName, $fieldType, $multiple);
    $this->createFieldInstance('taxonomy_term', $vocabulary, $fieldName, $fieldType, $description);
    if($default_value){
      $fieldInstance = FieldConfig::loadByName('taxonomy_term',$vocabulary, $fieldName);
      $fieldInstance?->set('default_value', [['value' => $default_value]]);
      $fieldInstance?->save();
    }
  }

  /**
   * @param       $entity_type
   * @param       $fieldName
   * @param       $fieldType
   * @param       $multiple
   * @param null  $target_type
   * @param array $target_bundles
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createFieldStorage($entity_type, $fieldName, $fieldType, $multiple, $target_type = NULL, $target_bundles = []): void {
    // Debugging
    bm_d('', [
      $entity_type,
      $fieldName,
      $fieldType,
      $multiple,
      $target_type,
      $target_bundles
    ], ' createFieldStorage', 'dsm');

    // Check if the field storage already exists
    if (FieldStorageConfig::loadByName($entity_type, $fieldName)) {
      \Drupal::messenger()
        ->addMessage("Field storage for '$fieldName' on entity type '$entity_type' already exists. Skipping creation.");
      return;
    }

    $cardinality = $multiple ? FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED : 1;
    $settings = [];

    // Handle settings for taxonomy term references
    if ($fieldType === 'entity_reference' && $target_type === 'taxonomy_term') {
      $settings = [
        'target_type' => 'taxonomy_term', // Correctly set to 'taxonomy_term'
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => array_combine($target_bundles, $target_bundles), // Taxonomy vocabularies as bundles
        ],
      ];
    }

    // Create the field storage configuration
    $field_storage = FieldStorageConfig::create([
      'entity_type' => $entity_type, // 'node', 'paragraph', or 'taxonomy_term'
      'field_name' => $fieldName,
      'type' => $fieldType, // Typically 'entity_reference' for taxonomy references
      'cardinality' => $cardinality,
      'settings' => $settings,
    ]);

    $field_storage->save();
  }

  /**
   * @param $entity_type
   * @param $bundle
   * @param $fieldName
   * @param $fieldType
   * @param $description
   * @param $target_type
   * @param $target_bundles
   *
   * @return void
   */
  private function createFieldInstance($entity_type, $bundle, $fieldName, $fieldType, $description, $target_type = NULL, $target_bundles = []): void {
    // Debugging
    bm_d('', [$entity_type, $bundle, $fieldName, $fieldType, $description, $target_type, $target_bundles], ' createFieldInstance', 'dsm');

    $field_instance = FieldConfig::loadByName($entity_type, $bundle, $fieldName);
    if (!$field_instance) {
      $settings = [];
      if (!empty($target_type) && $fieldType === 'entity_reference' && $target_type === 'taxonomy_term') {
        $settings = [
          'handler' => 'default',
          'handler_settings' => [
            'target_bundles' => array_combine($target_bundles, $target_bundles),
          ],
        ];
      }

      $field_instance = FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => ucfirst(str_replace('_', ' ', $fieldName)),
        'description' => $description,
        'required' => FALSE,
        'settings' => $settings,
      ]);

      try {
        $field_instance->save();
      } catch (Exception $e) {
        \Drupal::messenger()->addError(__LINE__ . ' create failed again: ' . $e->getMessage());
      }
    }
  }

  /**
   * # checkContentTypeHasFieldAndType
   * Checks if a content type has a specific field of a certain type.
   *
   * @param string $contentType
   *   The machine name of the content type.
   * @param string $fieldName
   *   The machine name of the field to check.
   * @param string $fieldType
   *   The type of the field to check for.
   *
   * @return bool
   *   TRUE if the content type has the specified field of the specified type, FALSE otherwise.
   */
  public function checkContentTypeHasFieldAndType(string $contentType, string $fieldName, string $fieldType): bool {
    // Attempt to load the field configuration for the specified content type and field name.
    $field = FieldConfig::loadByName('node', $contentType, $fieldName);
    if (!$field) {
      return FALSE;
    }
    $type = $field->getFieldStorageDefinition()->getType();
    //dsm($fieldName, $type);
    if ($type === $fieldType) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * # checkContentTypeHasField
   * Check this installation for having the correct fields
   *
   * @param string $contentType
   * @param string $fieldName
   *
   * @return bool
   */
  function checkContentTypeHasField(string $contentType, string $fieldName): bool {
    // Attempt to load the field configuration for the specified content type and field name.
    $field = FieldConfig::loadByName('node', $contentType, $fieldName);
    if (!$field) {
      return FALSE;
    }
    return TRUE;
  }



}
