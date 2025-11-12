<?php

namespace Drupal\bm_import\Test\src\unit;

use Drupal\bm_import\BMImportHandlers\BMImportParagraphHandler;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Exception;

class BMImportParagraphHandlerTest {

  private BMImportParagraphHandler $handler;

  public function setUp(): void {
    $this->handler = new BMImportParagraphHandler();
  }

  /**
   * Tests creating a paragraph type.
   *
   * @throws Exception
   */
  public function testCreateParagraphType(): void {
    $paragraphType = 'test_paragraph_type';
    $label = 'Test Paragraph';
    $description = 'A paragraph used for testing.';
    $translatable = TRUE;
    $icon = 'icon.png';

    $this->handler->createParagraphType($paragraphType, $label, $description, $translatable, $icon);
    if ($this->handler->check_paragraphTypeExists($paragraphType)) {
      \Drupal::messenger()->addMessage('Test testCreateParagraphType passed.');
    } else {
      throw new Exception('Paragraph type should have been created successfully.');
    }
  }

  /**
   * Tests checking if a paragraph type exists.
   *
   * @throws EntityStorageException
   */
  public function testCheckParagraphTypeExists(): void {
    $paragraphType = 'existing_paragraph';

    // Assume ParagraphsType::load() works here to verify existence.
    ParagraphsType::create(['id' => $paragraphType, 'label' => 'Existing Paragraph'])->save();
    if ($this->handler->check_paragraphTypeExists($paragraphType)) {
      \Drupal::messenger()->addMessage('Test testCheckParagraphTypeExists passed.');
    } else {
      throw new Exception('Paragraph type should exist.');
    }
  }

  /**
   * Tests adding a paragraph to a node field if it does not exist (single instance).
   *
   * @throws EntityStorageException
   * @throws Exception
   */
  public function testAddParagraphToNodeFieldIfNotExistsSingle(): void {
    $node = Node::create(['type' => 'test_content']);
    $fieldName = 'field_test_paragraph';
    $paragraphType = 'test_paragraph_type';

    $createdParagraph = $this->handler->addParagraphToNodeFieldIfNotExists($node, $fieldName, $paragraphType);
    if ($createdParagraph instanceof Paragraph) {
      \Drupal::messenger()->addMessage('Test testAddParagraphToNodeFieldIfNotExistsSingle passed.');
    } else {
      throw new Exception('Should be able to create a paragraph.');
    }
  }

  /**
   * Tests adding multiple paragraphs to a node field.
   *
   * @throws EntityStorageException
   * @throws Exception
   */
  public function testAddParagraphToNodeFieldIfNotExistsMultiple(): void {
    $node = Node::create(['type' => 'test_content']);
    $fieldName = 'field_test_multiple_paragraphs';
    $paragraphType = 'test_paragraph_type';

    $firstParagraph = $this->handler->addParagraphToNodeFieldIfNotExists($node, $fieldName, $paragraphType);
    $secondParagraph = $this->handler->addParagraphToNodeFieldIfNotExists($node, $fieldName, $paragraphType);

    if ($firstParagraph instanceof Paragraph && $secondParagraph instanceof Paragraph && $firstParagraph->id() !== $secondParagraph->id()) {
      \Drupal::messenger()->addMessage('Test testAddParagraphToNodeFieldIfNotExistsMultiple passed.');
    } else {
      throw new Exception('Should be able to create multiple paragraphs in the same field.');
    }
  }

  /**
   * Tests adding multiple paragraph types to a node field.
   *
   * @throws EntityStorageException
   * @throws Exception
   */
  public function testAddMultipleParagraphTypes(): void {
    $node = Node::create(['type' => 'test_content']);
    $fieldName = 'field_multi_paragraph_type';

    $typeOne = 'paragraph_type_one';
    $typeTwo = 'paragraph_type_two';

    $paraOne = $this->handler->addParagraphToNodeFieldIfNotExists($node, $fieldName, $typeOne);
    $paraTwo = $this->handler->addParagraphToNodeFieldIfNotExists($node, $fieldName, $typeTwo);

    if ($paraOne instanceof Paragraph && $paraTwo instanceof Paragraph) {
      \Drupal::messenger()->addMessage('Test testAddMultipleParagraphTypes passed.');
    } else {
      throw new Exception('Should be able to add multiple paragraph types.');
    }
  }

  /**
   * Tests setting paragraph field values.
   *
   * @throws EntityStorageException
   * @throws Exception
   */
  public function testSetParagraphFieldValues(): void {
    $paragraph = Paragraph::create(['type' => 'test_paragraph']);
    $paragraph->save();

    $fieldName = 'field_test_value';
    $value = 'Testing value';

    $this->handler->set_paragraph_fieldValues($paragraph->id(), $fieldName, $value, ['append']);

    $savedParagraph = Paragraph::load($paragraph->id());
    if ($savedParagraph->get($fieldName)->value === $value) {
      \Drupal::messenger()->addMessage('Test testSetParagraphFieldValues passed.');
    } else {
      throw new Exception('Value should be correctly set on the paragraph field.');
    }
  }

  /**
   * Tests creating a nested paragraph within a parent paragraph.
   *
   * @throws EntityStorageException
   * @throws Exception
   */
  public function testCreateNestedParagraph(): void {
    $parentParagraph = Paragraph::create(['type' => 'parent_paragraph']);
    $paragraphType = 'child_paragraph';

    $result = $this->handler->create_paragraph_paragraph($parentParagraph, 'field_child_paragraph', $paragraphType);
    if ($result) {
      \Drupal::messenger()->addMessage('Test testCreateNestedParagraph passed.');
    } else {
      throw new Exception('Nested paragraph should be created successfully.');
    }
  }
}
