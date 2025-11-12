<?php

namespace Drupal\bm_import\BMImport;

use Drupal;
use Drupal\bm_debug\BMDebugInfo\BMDebugInfo;
use Drupal\bm_import\BMImportHandlers\BMImportNodeHandler;
use Drupal\bm_import\BMImportHandlers\BMImportParagraphHandler;
use Drupal\bm_import\BMImportHandlers\BMImportVocabularyHandler;
use Drupal\bm_taxonomies\BMTaxonomies\BMTaxonomies;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Exception;

/**
 * # Class BMImportCreator
 * * This class is a wrapper for NodeHandler, ParagraphHandler and TaxonomyHandler
 *
 * This class is responsible for various tasks related to content import in a Drupal environment.
 * It provides methods for creating and managing nodes, paragraphs, taxonomy terms, vocabularies, and fields.
 * The class also handles special cases such as importing images, creating custom content types, and managing
 * interaction dates within imported content.
 *
 * ## Key Responsibilities:
 * - Creating and managing nodes, including the reuse of existing nodes based on specific conditions.
 * - Creating and managing paragraphs within nodes or other paragraphs, ensuring proper handling of existing content.
 * - Handling taxonomy terms, including creating terms if they do not exist and setting term field values.
 * - Special handling for image imports, including downloading images from URIs and setting them in nodes.
 * - Debugging support with verbose mode and integration with BMDebugInfo for tracking operations.
 *
 * ## Constants:
 * - EXCEPTION_NODE_CREATION_FAILED: Exception code for node creation failure.
 * - EXCEPTION_SET_NODE_FIELD_VALUE_FAILED: Exception code for setting node field value failure.
 * - EXCEPTION_PARAGRAPH_CREATION_FAILED: Exception code for paragraph creation failure.
 * - EXCEPTION_PARAGRAPH_NOT_FOUND: Exception code for paragraph not found.
 * - EXCEPTION_PARAGRAPH_SAVE_FAILED: Exception code for paragraph save failure.
 * - EXCEPTION_CREATE_VOCABULARY_FAILED: Exception code for vocabulary creation failure.
 * - EXCEPTION_ADD_TAXONOMY_TERM_FAILED: Exception code for adding a taxonomy term failure.
 * - EXCEPTION_SET_TAXONOMY_TERM_FIELD_VALUE_FAILED: Exception code for setting taxonomy term field value failure.
 * - BM_D_KEY: Debugging key for general operations in this class.
 * - BM_D_KEY_DETAIL: Debugging key for detailed operations in this class.
 *
 * ## Usage:
 * This class is typically used in the context of batch imports or content migration processes,
 * where complex content types and relationships need to be established programmatically.
 * The class is designed to handle errors gracefully and provide detailed debugging information when necessary.
 *
 * ## Example:
 * ```
 * $importCreator = new BMImportCreator(TRUE); // Enable verbose mode for debugging
 * $node = $importCreator->create_node('Example Title', 'listing', 'en');
 * if ($node) {
 *     $importCreator->set_node_fieldValues($node, 'field_example', 'Example Value');
 * }
 * ```
 *
 * @package Drupal\bm_import\BMImport
 */
class BMImportCreator {



  const EXCEPTION_CREATE_VOCABULARY_FAILED = 9994;



  const BM_D_KEY = __CLASS__;

  const BM_D_KEY_DETAIL = __CLASS__ . 'detail';


  private bool $verbose;

  private BMImportNodeHandler $nodeHandler;
  private BMImportParagraphHandler $paragraphHandler;
  private BMImportVocabularyHandler $vocabularyHandler;

  public function __construct(bool $verbose = FALSE) {
    bm_d('','<div class="bgcolor-blue fgcolor-white">Instantiating BMImportCreator</div>','','screen');

    $verbose = FALSE;
    $this->verbose = $verbose;
    $this->nodeHandler = new BMImportNodeHandler($verbose);
    $this->paragraphHandler = new BMImportParagraphHandler($verbose);
    $this->vocabularyHandler = new BMImportVocabularyHandler($verbose);

  }

  static public function bm_d_callback($key, $BMDbgInfo): BMDebugInfo|false {

    if ($key == self::BM_D_KEY) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImportCreator: Creates nodes, paragraphs, terms for BMImport.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'dsm';
      return $BMDbgInfo;
    }
    if ($key == self::BM_D_KEY_DETAIL) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImportCreator: Details';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    return FALSE;
  }
  /**
   * # create a vocabulary
   *
   * @param string $vocabularyName VID name
   * @param string|null   $vocabularyLabel the label to use for the vocabulary or null
   *
   * @return void
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function create_vocabulary(string $vocabularyName, ?string $vocabularyLabel = null): void {
    $this->vocabularyHandler->createVocabulary($vocabularyName, $vocabularyLabel??$vocabularyName);
  }
  /**
   * # set term field values
   *
   * @param Term  $term               the term
   * @param array $fieldNamesValues   ['fieldName'=>, 'value'=>, 'conversion'=>]
   *                                  conversion can be 'currentDateTime
   *
   * @return void
   * @throws '' EXCEPTION_ADD_TAXONOMY_TERM_FAILED
   * @throws '' EXCEPTION_SET_TAXONOMY_TERM_FIELD_VALUE_FAILED
   */
  public function set_term_fieldValues(Term $term, array $fieldNamesValues): void {
    $this->vocabularyHandler->set_term_fieldValues($term, $fieldNamesValues);
  }
  /**
   * # Get the term and create if not exist
   *
   * @param string $name_or_tid
   * @param string $vid term vocabulary
   * @param string $languageId
   *
   * @return null|Term
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function create_term_if_notExist(string $name_or_tid, string $vid, string $languageId = 'en'): Term|null {
    return $this->vocabularyHandler->create_term_if_notExist($name_or_tid, $vid, $languageId);
  }
  /**
   * # create node with title
   *
   * @param        $title      string the listing title
   * @param string $type
   * @param string $language_id
   * @param bool   $reUseExisting
   * @param int    $re_usedNid returns a nid in case it is re-using a node.
   *
   * @return Node|null
   * @throws Exception 9999 listing creation failed
   */
  public function create_node(string $title, string $type, string $language_id, bool $reUseExisting = FALSE, int &$re_usedNid = 0): ?Node {
    return $this->nodeHandler->create_node($title, $type, $language_id, $reUseExisting, $re_usedNid);
  }
  /**
   * # set_node_fieldValues
   * set id: [tid, ...]
   *
   * @throws Exception
   */
  public
  function set_node_fieldValues(Node $node, $fieldName, $values, array $actions = []): void {
    $this->nodeHandler->set_node_fieldValues($node, $fieldName, $values, $actions);
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
  public function addNodeParagraphIfNotExists(NodeInterface $node, string $nodeFieldName, string $paragraphType, bool $deleteExisting = FALSE): Paragraph|false {
    return $this->paragraphHandler->addParagraphToNodeFieldIfNotExists($node, $nodeFieldName, $paragraphType);
  }
  /**
   * # create a paragraph on a paragraph field
   * #### appends a paragraph. Note you have to remove unwanted paragraphs separately
   *
   * @param Paragraph $paragraph
   * @param string    $paragraphField
   * @param           $paragraphType string the paragraph type to create
   *
   * @return bool
   * @throws \Exception EXCEPTION_PARAGRAPH_CREATION_FAILED
   * @throws \Exception EXCEPTION_PARAGRAPH_SAVE_FAILED
   */
  public function create_paragraph_paragraph(Paragraph $paragraph, string $paragraphField, string $paragraphType): bool {
    return $this->paragraphHandler->create_paragraph_paragraph($paragraph, $paragraphField, $paragraphType);
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
    return $this->paragraphHandler->find_firstParagraph_byType($listing, $listingFieldName, $paragraphType);
  }
  /**
   * # getComplexFieldValue
   * * not implemented yet
   *
   * @param string $complexField
   *
   * @return mixed
   */
  public function getComplexFieldValue(string $complexField):mixed{


  }


}
