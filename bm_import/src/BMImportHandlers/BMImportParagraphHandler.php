<?php
/*
 * Copyright (c) 2024. Mondial-IT BV
 */
/** @noinspection PhpPossiblePolymorphicInvocationInspection */

namespace Drupal\bm_import\BMImportHandlers;

use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Exception;

/**
 * # Class BMImportParagraphHandler
 *
 * This class is responsible for managing paragraph entities within a Drupal environment.
 * It provides methods for creating paragraph types, adding paragraphs to nodes,
 * setting paragraph field values, and checking the existence of fields on paragraph types.
 * The class also includes utilities for handling entity reference fields and
 * managing multiple field values within paragraphs.
 *
 * ## Key Responsibilities:
 * - Creating and managing paragraph types.
 * - Adding paragraphs to node entities.
 * - Setting and appending field values on paragraphs, including handling reference fields.
 * - Checking the existence of specific fields on paragraph types.
 *
 * ## Methods Overview:
 * - `check_paragraphTypeExists`: Checks if a specific paragraph type exists.
 * - `check_fieldExistsOnParagraphType`: Checks if a specific field exists on a paragraph type.
 * - `addParagraphToNodeFieldIfNotExists`: checks paragraph presence and adds if missing
 * - `addParagraph`: Adds a new paragraph to a node's field.
 * - `set_paragraph_fieldValues`: Sets values for a paragraph's field, with support for various actions such as appending and replacing values.
 * - `createParagraphType`: Creates a new paragraph type if it does not already exist.
 *
 * ## Usage Example:
 * ```
 * $paragraphHandler = new BMImportParagraphHandler();
 * $paragraphHandler->createParagraphType('custom_paragraph', 'Custom Paragraph');
 *
 * if ($paragraphHandler->check_paragraphTypeExists('custom_paragraph')) {
 *     $node = Node::load(1);
 *     $paragraphHandler->addParagraph($node, 'field_custom_paragraph');
 * }
 * ```
 *
 * ## Important Notes:
 * - The class handles paragraph entities primarily within the context of nodes, facilitating the
 *   addition and management of paragraphs on content types.
 * - Specific actions when setting field values (`append`, `replaceAll`, `set id`) should be used with caution,
 *   particularly when dealing with reference fields.
 * - The class includes utility methods for checking if fields exist on paragraph types, which is useful
 *   for ensuring that the necessary fields are configured before attempting to set values.
 *
 * @package Drupal\bm_import\BMImport
 */
class BMImportParagraphHandler {
  const ExceptionBMImportParagraphHandler = 99999;
  const EXCEPTION_PARAGRAPH_CREATION_FAILED = 9997;
  const EXCEPTION_PARAGRAPH_NOT_FOUND = 9996;
  const EXCEPTION_PARAGRAPH_SAVE_FAILED = 9995;
  /**
   * @var int|mixed|string
   */
  private mixed $paragraphId;
  private bool $verbose;

  public function version(): string {
    return '0.1';
  }

  public function __construct($verbose=false) {
    $this->verbose=$verbose;
    bm_d('','<div class="bgcolor-blue fgcolor-white">Instantiating BMImportParagraphHandler</div>','','screen');
  }
  /**
   * @param $paragraphType
   *
   * @return bool
   */
  public  function check_paragraphTypeExists($paragraphType): bool {
      $paragraphTypeEntity = ParagraphsType::load($paragraphType);
      return !empty($paragraphTypeEntity);
    }
  /**
   * @param $id
   * @param $label
   * @param $description
   * @param $translatable
   * @param $icon
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  public function createParagraphType($id, $label, $description, $translatable, $icon): void {

    if (!$this->isValidMachineName($id)) {
      throw new Exception("The paragraph ID '$id' is not a valid machine-readable name. It must contain only lowercase letters, numbers, and underscores.");
    }

    if (!ParagraphsType::load($id)) {
      $paragraph_type = ParagraphsType::create([
        'id' => $id,
        'label' => $label,
        'description' => $description,
      ]);
      $paragraph_type->set('translatable', $translatable);
      $paragraph_type->set('icon_default', $icon);
      // Save the paragraph type
      $paragraph_type->save();

    }
  }
  /**
   * Return the available contenttypes.
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function get_types(): array {
    $contentTypes = \Drupal::entityTypeManager()
      ->getStorage('paragraphs_type')
      ->loadMultiple();

    // Prepare options for the select dropdown.
    $options = [];
    foreach ($contentTypes as $machineName => $contentType) {
      $options[$machineName] = $contentType->label();
    }
    return $options;

  }
  /**
   * Deletes a paragraph type by its machine name.
   *
   * @param string $paragraph_type_id
   *   The machine name of the paragraph type to delete.
   *
   * @return bool
   *   Returns TRUE if the paragraph type was deleted, FALSE otherwise.
   */
  public function delete_paragraph_type(string $paragraph_type_id): bool {
    // Load the paragraph type entity.
    $paragraph_type = ParagraphsType::load($paragraph_type_id);

    // Check if the paragraph type exists.
    if ($paragraph_type) {
      try {
        $paragraph_type->delete();
        \Drupal::messenger()->addStatus(t('The paragraph type %type has been deleted.', ['%type' => $paragraph_type_id]));
        return TRUE;
      }
      catch (EntityStorageException $e) {
        \Drupal::messenger()->addError(t('An error occurred while deleting the paragraph type %type: @message', [
          '%type' => $paragraph_type_id,
          '@message' => $e->getMessage(),
        ]));
      }
    }
    else {
      \Drupal::messenger()->addWarning(t('The paragraph type %type does not exist.', ['%type' => $paragraph_type_id]));
    }

    return FALSE;
  }
  /**
   * Deletes all paragraph entities of a specific type.
   *
   * @param string $paragraph_type_id
   *   The machine name of the paragraph type.
   *
   * @throws EntityStorageException
   */
  function delete_all_paragraphs_of_type(string $paragraph_type_id): void {
    // Load all paragraphs of the given type.
    try {
      $paragraphs = \Drupal::entityTypeManager()
        ->getStorage('paragraph')
        ->loadByProperties(['type' => $paragraph_type_id]);
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {

    }
    foreach ($paragraphs as $paragraph) {
      $paragraph->delete();
    }
    \Drupal::messenger()->addStatus(t('All paragraphs of type %type have been deleted.', ['%type' => $paragraph_type_id]));
  }
  /**
   * Deletes all paragraphs from a paragraph reference field in a node.
   *
   * @param \Drupal\node\Entity\Node $node
   *   The node from which to remove paragraphs.
   * @param string $field_name
   *   The machine name of the paragraph reference field.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteParagraphsFromNodeField($nid, string $field_name): void {
    $node = Node::load($nid);
    if(!$node)return;
    // Check if the node has the field and if it contains paragraphs.
    if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
      // Get all paragraph entities referenced by the field.
      $paragraphs = $node->get($field_name)->referencedEntities();

      // Loop through and delete each paragraph entity.
      foreach ($paragraphs as $paragraph){
        if ($paragraph instanceof \Drupal\paragraphs\Entity\Paragraph) {
          Drupal::messenger()->addMessage('deleting paragraph: '.$paragraph->id());
          $paragraph->delete();
        }
      }

      // Remove the references from the node.
      $node->set($field_name, []);
      $node->save();

      \Drupal::messenger()->addMessage("All paragraphs removed from the field {$field_name} and deleted from the database.");
    }
    else {
      \Drupal::messenger()->addWarning("No paragraphs found in the field {$field_name} or the field does not exist.");
    }
  }
  /**
   * @param $paragraphType
   * @param $fieldName
   *
   * @return bool
   */
  public function check_fieldExistsOnParagraphType($paragraphType, $fieldName): bool {
  //  Drupal::messenger()->addMessage(__LINE__.' check_fieldExistsOnParagraphType paragraphType:'.$paragraphType);
  //  Drupal::messenger()->addMessage(__LINE__.'check_fieldExistsOnParagraphType fieldName:'.$fieldName);

    $field_storage = FieldConfig::loadByName('paragraph', $paragraphType, $fieldName);

    return $field_storage !== null;
  }
  /**
   * # addParagraphToNodeFieldIfNotExists
   *
   * @param \Drupal\node\NodeInterface $node
   * @param string                     $nodeFieldName the field to attach the paragraph to
   * @param string                     $paragraphType the machine name of a paragraph type
   * @param bool                       $deleteExisting when true, replaces what is there.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|false
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addParagraphToNodeFieldIfNotExists(NodeInterface $node, string $nodeFieldName, string $paragraphType, bool $deleteExisting = FALSE): Paragraph|false {
    // Drupal::messenger()->addStatus(__FUNCTION__.'nodeFieldName: '.$nodeFieldName.' paragraphType:'.$paragraphType);
    // NODE FIELD EXISTS ?
    if (!$node->hasField($nodeFieldName)) {
      Drupal::messenger()
        ->addError(__LINE__ . '<span class="bgcolor-yellow"> Exception - node field does not exist .' . $nodeFieldName . '</span>');
      Drupal::messenger()->addError(__LINE__ . ' Exception - node field does not exist .' . $nodeFieldName);
      return FALSE;
    }

    // note appendItem is the only way that works to add items to a node reference paragraph..
    $field = $node->get($nodeFieldName);
    $existing_paragraphs = $field->referencedEntities();
    foreach ($existing_paragraphs as $existing_paragraph) {
      /* @var $existing_paragraph Paragraph */
      if ($deleteExisting) {
        Drupal::messenger()->addStatus(__LINE__ . ' ' . __FUNCTION__ . ' delete existing' . $existing_paragraph->id());
        $existing_paragraph->delete();
      }

      //    dsm($existing_paragraph->getType(),__LINE__.'  TYPE '.$existing_paragraph->id());

      if ($existing_paragraph->getType() === $paragraphType) {
        if ($this->verbose) {
          Drupal::messenger()
            ->addStatus(__LINE__ . ' ' . __FUNCTION__ . ' found and return Pid' . $existing_paragraph->id());
        }
        return $existing_paragraph;
      }
    }

    // dsm(['create node paragaraph'],__LINE__.'creating the node paragraph ');

    $paragraphCreated = $this->addParagraph($node, $nodeFieldName, $paragraphType);
    if ($this->verbose) {
      Drupal::messenger()
        ->addStatus(__LINE__ . ' ' . __FUNCTION__ . ' created and return Pid' . $paragraphCreated->id());
    }
    return $paragraphCreated;

  }
  /**
   * ## addParagraph($node, $fieldName, $mNameParaType)
   * * Creates a paragraph and adds it to the node.
   * * The paragraph type is instantiated and the reference node field is set to it.
   * * The paragraph and the node are saved
   * * Returns the paragraph or null
   *
   * @param \Drupal\node\NodeInterface $node
   * @param string                     $node_field_name the field where the paragraph is attached to
   * @param string                     $paragraphType the machine name of a paragraph type
   *
   * @return \Drupal\paragraphs\Entity\Paragraph|null
   */
  private function addParagraph(NodeInterface $node,string $node_field_name, string $paragraphType): Paragraph|int|null {
    //self::remove_orphaned_paragraphs();
    try {
      $paragraph = Paragraph::create(['type' => $paragraphType]);
//      $this->paragraph->set('field_from_bank_account', $this->bankAccount);
//      $this->paragraph->set('field_notes', $this->notes);
//      $this->paragraph->set('field_amount', $this->amount);

//      $this->paragraph->set('field_payment_date',  time());
      $paragraph->save();

      $this->paragraphId= $paragraph->id()??0;
    }
    catch(\Exception $e){
      Drupal::logger(__CLASS__)->error('Error on paragraph create: ' . $e->getMessage());
      return null;
    }
    try{
      //if ($node->hasField($field_name)) {
      $field = $node->get($node_field_name);
      if ($field) {
        $field->appendItem($paragraph);
        $node->save();
        return $paragraph;
      }
      //}
    }catch(\Exception $e){
      Drupal::logger(__CLASS__)->error('Error on addParagraph, node append and save: ' . $e->getMessage());
      return null;
    }

    return $paragraph;
  }
  /** # lookup a specific paragraph in listing
   * #### by paragraph type, assuming there is only 1
   *
   * @param $listing          Node
   * @param $listingFieldName string
   * @param $paragraphType    string
   *
   * @return Paragraph|null
   */
  public function find_firstParagraph_byType(Node $listing, string $listingFieldName, string $paragraphType): ?Paragraph {
    /** @noinspection PhpPossiblePolymorphicInvocationInspection */
    $paragraphs = $listing->get($listingFieldName)->referencedEntities();
    $paragraph = NULL;

    if (!empty($paragraphs)) {
      foreach ($paragraphs as $specific_paragraph) {
        if ($specific_paragraph instanceof Paragraph && $specific_paragraph->getType() == $paragraphType) {
          // The paragraph is of the correct type, work with it
          $paragraph = $specific_paragraph;
          break;
        }
      }
    }
    //dsm($paragraph->getEntityTypeId());
    return $paragraph;
  }
  /**
   * # set paragraph field value
   * Pass it an action code:
   * * currentDateTime : sets current date time to field
   * * full_html : sets value to field and adds format=>full_html
   * * append id : appends ['target_id' => $id]
   * * append : appends value to field or sets value to field (not multiple)
   * * set id: [target_id=>value]
   * * upload image: makes value int
   *
   * ## description
   * * detect when it is a reference field and adds items
   * * detect if value is a value or an array of target_id's
   * * using replaceAll to replace all or append to existing set.
   *
   * ## note set with multiple target_ids doesn't work! use append_item!
   *
   * @param int          $pid
   * @param string       $fieldName
   * @param string|array $value
   * @param array        $actions [currentDateTime, full_html, append id, append, set id, upload image]
   * @param bool         $replaceAll
   *
   * @return void
   * @throws \Exception
   */
  public function set_paragraph_fieldValues(int $pid, string $fieldName, string|array $value,array $actions, bool $replaceAll = TRUE): void {
    Drupal::logger('BMImportParagraphHandler')->debug(__LINE__ . " set_paragraph_fieldValues($fieldName)");

    if(in_array($fieldName,['SBI','field_para_business_companyname'])){
      bm_d('',$fieldName,'BMImportParagraphHandler SKIPPED ','log');
    }

    $paragraph = Paragraph::load($pid);

    foreach ($actions as $action) {
      if (trim($action ?? '')) {

        $value = match ($action) {
          'currentDateTime' => strtotime(date('Y-m-d')),
          'full_html' => ['value' => $value, 'format' => 'full_html'],
          'append id' => $this->appendId($paragraph, $fieldName, $value),
          'append'=>$this->append($paragraph, $fieldName, $value),
          'set id' => ['target_id'=>$value],
          'upload_image' => (int) $value, // must be a fid, created before
          default => throw new Exception(
            __LINE__ . ' Exception - Unexpected action for set paragraph field value action::' . $action,
            self::ExceptionBMImportParagraphHandler),
        };
      }
    }

    if (!$paragraph->hasField($fieldName)) {
      Drupal::messenger()->addmessage(__LINE__.' BMImportParagraphHandler - Exception - set paragraph field does not exist  ' . $fieldName);
      Drupal::messenger()->addError(__LINE__.' BMImportParagraphHandler - - set paragraph field does not exist  ' . $fieldName);
      throw new Exception(
        'Paragraph set field failed, paragraph does not have field '.$fieldName,
        self::ExceptionBMImportParagraphHandler
      );
    }

    //    dsm($value, __LINE__ . ' SETTING IT WITH paragraph->set(' . $fieldName . ') pid:'.$pid);
    $replaceAll = FALSE;
    try {
      $field = $paragraph->get($fieldName);
      $field_definition = $field->getFieldDefinition();
      $field_type = $field_definition->getType();
      $is_reference_field = FALSE;
      if ($field instanceof FieldItemListInterface) {
        // Check if it's a reference field
        $is_reference_field = $field_type === 'entity_reference';
        // Check cardinality
        $cardinality = $field_definition->getFieldStorageDefinition()->getCardinality();
        $allows_multiple = (($cardinality === FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) || ($cardinality > 1));
      }

      if ($is_reference_field) {
        $presentItems = [];
        $toRemove = [];
        $currentItems = $paragraph->get($fieldName)->getValue();

        foreach ($currentItems as $delta => $item) {
          if (array_key_exists('target_id', $item)) {
            if ($replaceAll) {
              $toRemove[] = $delta;
            }
            else {
              $presentItems[] = $item['target_id'];
            }
            //break;
          }
        }
        foreach ($value as $val) {
          if(!isset($val['target_id'])){
       //     bm_d('',$value,'wat is hier aan de hand?','dsm');
            continue;
          }
          $id = $val['target_id'];
          if (!in_array($id, $presentItems)) {
            $paragraph->get($fieldName)->appendItem($val);
          }
        }
      }
      else {
        // check for date fields and convert values to be compatible
        if ($field_definition && $field_type === 'datetime') {

          if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            // Check if the value is a date string in 'yyyy-mm-dd' format.

            $dateObject = DrupalDateTime::createFromFormat('Y-m-d', $value);
            // Set the value as a formatted string for the datetime field.
            $paragraph->set($fieldName, $dateObject->format('Y-m-d'));

          }elseif (is_string($value) && preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
            // Convert 'mm-dd-yyyy' to 'yyyy-mm-dd' format.
            $dateObject = DrupalDateTime::createFromFormat('m-d-Y', $value);

            // Set the value as a formatted string for the datetime field.
            $paragraph->set($fieldName, $dateObject->format('Y-m-d'));

          } elseif (is_numeric($value)) {
            // If the value is a timestamp, convert it to 'Y-m-d' format.
            $dateObject = DrupalDateTime::createFromTimestamp($value);

            // Set the value as a formatted string for the datetime field.
            $paragraph->set($fieldName, $dateObject->format('Y-m-d'));
          }
          else {
            // If the value is neither a valid date string nor a timestamp, log an error.
            \Drupal::logger('bm_rinkel')->error('Invalid date format for field: ' . $fieldName);
          }
        } else {
          $paragraph->set($fieldName, $value);
        }
      }
      $result = $paragraph->save();
    } catch (Exception $e) {
      Drupal::messenger()->addmessage('Exception - save paragraph failed ' . $e->getMessage());
      Drupal::messenger()->addError('Exception - save paragraph failed ' . $e->getMessage());
      throw new Exception('Paragraph save failed',self::ExceptionBMImportParagraphHandler );
    }

    //dsm($paragraph->get($fieldName), '<span class="bgcolor-red">GET AFTER SAVE PARA ' . $pid.' result: </span>');

  }
  /**
   * # create a paragraph on a paragraph field
   * #### appends a paragraph. Note you have to remove unwanted paragraphs separately
   *
   * @param Paragraph $paragraph      the paragraph entity
   * @param string    $paragraphField the field on this paragraph to receive the new paragraph
   * @param string    $paragraphType  the paragraph type to create
   *
   * @return bool true success, false fail (exception)
   * @throws \Exception EXCEPTION_PARAGRAPH_SAVE_FAILED
   */
  public function create_paragraph_paragraph(Paragraph $paragraph, string $paragraphField, string $paragraphType): bool {
    $newParagraph = Paragraph::create([
      'type' => $paragraphType,
    ]);

    if (!$newParagraph) {
      throw new Exception('Paragraph creation failed', self::EXCEPTION_PARAGRAPH_CREATION_FAILED);
    }
    // append newParagraph id to paragraph field
    $paragraph->get($paragraphField)->appendItem($paragraph);
    // else
    // $selectedParagraph->save();
    try {
      $paragraph->save();
    } catch (\Exception $e) {
      Drupal::messenger()
        ->addError('Error processing listing import set paragraph on paragraph field: ' . $e->getMessage());
      throw new Exception('create_paragraph_paragraph - Paragraph save failed', self::EXCEPTION_PARAGRAPH_SAVE_FAILED);
      //return false;
    }
    return true;
  }
  /**
   * #### appendId
   * * append a target_id item to a field
   * * for terms and paragraphs, where a new term is added or a new paragraph
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $entity
   * @param                                     $fieldName
   * @param                                     $id
   *
   * @return mixed
   */
  private function appendId(Paragraph $entity, $fieldName, $id): mixed {
    $existing = $entity->get($fieldName)->getValue();
    $already_exists = NULL;
    foreach ($existing as $exists) {
      if ($exists['target_id'] == $id) {
        $already_exists = TRUE;
        break;
      }
    }
    if (!$already_exists) {
      $existing[] = ['target_id' => $id];
    }
    return $existing;
  }
  /**
   * #### append
   * Append value into next delta of the field
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $entity
   * @param                                     $fieldName
   * @param                                     $values
   *
   * @return mixed
   */
  private function append(Paragraph $entity, $fieldName, $values): mixed {
    $existing = $entity->get($fieldName)->getValue();
    $existing[] = $values;
    return $existing;
  }
  private function isValidMachineName($id): bool {
    // Regular expression to match valid machine-readable names
    return (bool) preg_match('/^[a-z0-9_]+$/', $id);
  }
}
