<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace bmDataModules\bm_import\src\BMImportHandlers\unit;

use Drupal;
use bmDataModules\bm_import\src\BMImportHandlers\BMImportParagraphHandler;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\ParagraphsType;
use Exception;

class BMImportParagraphHandlerTest {

  public function __construct() {
    $this->handler = new BMImportParagraphHandler();
  }

  private BMImportParagraphHandler $handler;

  public function setUp(): void {
    $this->handler = new BMImportParagraphHandler();
  }

  /**
   * Tests checking if a paragraph type exists.
   *
   * @throws EntityStorageException
   * @throws Exception
   */
  public function test__check_paragraphTypeExists($paragraphType): array {
    //    $paragraphType = 'calldetailrecord_paragraphX';
    if ($this->handler->check_paragraphTypeExists($paragraphType)) {
      $message = 'Passed: check_paragraphTypeExists("calldetailrecord_paragraph")';
      Drupal::messenger()->addMessage($message);

      return [TRUE, $message];
    }
    else {
      throw new Exception('Test failed "' . $paragraphType . '" not found while expected to be present ..??.');
    }
  }

  /**
   * Tests creating a paragraph type.
   *
   * @throws Exception
   */
  public function test__createParagraphType($paragraph_type_id, $label, $description, $translatable, $icon): array {
    try {
      $this->handler->createParagraphType($paragraph_type_id, $label, $description, $translatable, $icon);
    } catch (Exception $e) {
      throw new Exception("Exception thrown by createParagraphType() " . $e->getMessage());
    }
    // test success
    $paragraph_type = ParagraphsType::load($paragraph_type_id);
    if (!empty($paragraph_type)) {
      $message = 'Passed: check_createParagraphType("test_paragraph_type")';
      Drupal::messenger()->addMessage($message);
      return [TRUE, $message];
    }
    else {
      throw new Exception('test failed "test_paragraph_type" does not exist');
    }
  }

  /**
   * # undo__createParagraphType
   * @throws Exception
   */
  public function undo__createParagraphType($paragraph_type_id, $label, $description, $translatable, $icon): array {
    if ($paragraph_type_id <> 'test_paragraph_type') {
      throw new Exception('I can only undo test_paragraph_type, manually delete the requested paragraph_type.');
    }
    else {
      $paragraph_type = ParagraphsType::load($paragraph_type_id);
      if ($paragraph_type) {
        try {
          $paragraph_type->delete();
          $message = 'Paragraph type deleted successfully.';
          Drupal::messenger()->addMessage($message);
          return [true, $message];
        } catch (Exception $e) {
            throw($e);
        }
      }else{
        throw new Exception("Paragraph type was not found.");
      }
    }
  }

  public function test__check_fieldExistsOnParagraphType($paragraphType, $fieldName): array {
    $result = $this->handler->check_fieldExistsOnParagraphType($paragraphType, $fieldName);
    return [$result,"check_fieldExistsOnParagraphType"];
  }

  public function test__find_firstParagraph_byType(string $nid, string $listingFieldName, string $paragraphType): array {

    $listing=Node::load($nid);
    if(!$listing){
      return [false,"Node not found"];
    }
    $result = $this->handler->find_firstParagraph_byType($listing, $listingFieldName, $paragraphType);
    if($result){
      return [True, 'find_firstParagraph_byType found a paragraph with ID: '.$result->id()];
    }
    return [false,"The method did not return a result."];
  }

  /**
   * @throws EntityStorageException
   */
  public function test__addParagraphToNodeFieldIfNotExists(string $nid, string $nodeFieldName, string $paragraphType, string $deleteExisting): array {

    $node = Node::load($nid);
    if (!$node) {
      return [FALSE, 'Node not found'];
    }
    $delete= in_array(strtolower($deleteExisting),["true","1"]);
    dsm($delete,'deleteExisting');
    $result = $this->handler->addParagraphToNodeFieldIfNotExists($node,  $nodeFieldName,  $paragraphType,  $delete);
    if($result){
      return [True, 'add_paragraph_to_node_field returned the paragraph id: '.$result->id()];
    }else{
      return [False, 'The method returned Null'];
    }
  }
  // addParagraph(NodeInterface $node,string $node_field_name, string $paragraphType)
  // set_paragraph_fieldValues(int $pid, string $fieldName, string|array $value,array $actions, bool $replaceAll = TRUE)
  // create_paragraph_paragraph(Paragraph $paragraph, string $paragraphField, string $paragraphType)
}
