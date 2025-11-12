<?php

namespace Drupal\bm_import\BMImportHandlers;

use Drupal\bm_import\BMImportHandlers\BMImportParagraphHandler;
use Drupal\bm_import\BMImportHandlers\BMImportVocabularyHandler;
use Drupal;
use Drupal\bm_import\BMImportHandlers\BMImportCreator;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Exception;

/**
 * # Class BMImportNodeHandler
 *
 * This class is responsible for handling the creation and management of nodes
 * and paragraphs within a Drupal environment. It provides methods for creating
 * node types, adding nodes, attaching paragraphs to nodes, and managing titles
 * safely.
 *
 * Key Responsibilities:
 * - Creating and managing node types.
 * - Adding nodes to the system, including options for re-using existing nodes.
 * - Attaching paragraph entities to node fields.
 * - Validating input such as machine-readable names and encoding titles.
 *
 * Methods Overview:
 * - createNodeType: Creates a new content type (node type) with specified
 * parameters.
 * - create_node: Creates a new node of a specific type with a given title,
 * optionally re-using an existing node if it already exists.
 * - create_node_paragraph: Creates and attaches a paragraph entity to a
 * specified field of an existing node.
 * - title_encoding: Encodes a title to ensure it is HTML safe.
 * - isValidMachineName: Validates machine-readable names to ensure they meet
 * Drupal requirements.
 *
 * Usage Example:
 * ```
 * $nodeHandler = new BMImportNodeHandler();
 * $nodeHandler->createNodeType('custom_type', 'Custom Type', 'A custom node
 * type description');
 * $node = $nodeHandler->create_node('Example Title', 'custom_type', 'en',
 * FALSE); if ($node) {
 *   $nodeHandler->create_node_paragraph($node, 'field_custom_paragraph',
 *   'custom_paragraph_type');
 * }
 *```
 *
 * Important Notes:
 * - The class handles the creation and management of node and paragraph
 * entities primarily within the context of content types.
 * - Node reuse functionality should be carefully handled to avoid
 * unintentional overwriting of data.
 * - Paragraphs are added to specified fields, and the fields must be
 * configured appropriately in the content type.
 *
 * Package:
 * Drupal\bm_import\BMImport
 */
class BMImportNodeHandler {

  const EXCEPTION_NODE_CREATION_FAILED = 9999;

  const EXCEPTION_SET_NODE_FIELD_VALUE_FAILED = 9998;

  public function __construct() {
    bm_d('', '<div class="bgcolor-blue fgcolor-white">Instantiating BMImportNodeHandler</div>', '', 'screen');
  }
  /**
   * Creates a new node type (content type).
   *
   * @param string $mname
   *   The machine name for the new node type.
   * @param string $label
   *   The human-readable label for the node type.
   * @param string $description
   *   A description for the node type.
   * @param string $help
   *   Optional help text for the node type.
   *
   * @throws EntityStorageException
   * @throws Exception
   */
  public function createNodeType($mname, $label, $description, $help = ''): void {
    if (!$this->isValidMachineName($mname)) {
      throw new \InvalidArgumentException('createNodeType: Machine name ' . $mname . ' is not valid. It should be lowercase and only _ allowed.');
    }

    // Use Drupal's built-in logging to record the creation process.
    \Drupal::logger('my_module')
      ->notice("Creating node type: $mname, $label, $description");

    $node_type = NodeType::create([
      'type' => $mname,
      'name' => $label,
      'description' => $description,
      'help' => $help,
      'new_revision' => FALSE,
      'preview_mode' => 0,
      'display_submitted' => TRUE,
    ]);

    // Explicitly set default settings for clarity.
    $node_type->setThirdPartySetting('node', 'promote', FALSE);
    $node_type->setThirdPartySetting('node', 'sticky', FALSE);

    // Set available menus for the content type (uncomment and modify as needed).
    $node_type->setThirdPartySetting('menu_ui', 'available_menus', []);

    $node_type->save();
  }

  /**
   * # get_contentTypes
   * Return the available contenttypes.
   *
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function get_contentTypes(): array {
    $contentTypes = \Drupal::entityTypeManager()
      ->getStorage('node_type')
      ->loadMultiple();
    // Prepare options for the select dropdown.
    $options = [];
    foreach ($contentTypes as $machineName => $contentType) {
      $options[$machineName] = $contentType->label();
    }
    return $options;

  }

  /**
   * # createNode
   * Creates a listing or contact node with a specific title.
   *
   * @param string $title
   *   The title of the node.
   * @param string $type
   *   The content type of the node (e.g., listing, contact).
   * @param string $language_id
   *   The language code for the node.
   * @param bool   $reUseExisting
   *   Whether to reuse an existing node if one with the same title exists.
   * @param int    $re_usedNid
   *   Outputs the node ID if an existing node is reused.
   *
   * @return Node|null
   *   The created or reused node, or NULL if creation failed.
   *
   * @throws Exception
   *   Throws an exception if the listing creation fails.
   */
  public function create_node(string $title, string $type, string $language_id, bool $reUseExisting = FALSE, int &$re_usedNid = 0): ?Node {
    Drupal::logger('BMImport')
      ->debug(__LINE__ . " create_node($title,$type,$language_id)");

    // Encode the title to ensure it is HTML-safe.
    $cleanTitle = $this->title_encoding($title);

    // Query to check if a node with the same title already exists.
    $entity_type = 'node';
    $query = \Drupal::entityQuery($entity_type)
      ->condition('title', $cleanTitle)
      ->condition('type', $type);

    $entity_ids = $query->execute();

    // If a node with the same title exists and reUseExisting is true, reuse it.
    if (!empty($entity_ids)) {
      if ($reUseExisting) {
        $nid = reset($entity_ids);
        Drupal::messenger()->addWarning('RE-USING AN EXISTING NODE ' . $nid);
        $node = Node::load($nid);
        if ($node) {
          $re_usedNid = $node->id();
          return $node;
        }
      }
      // If reuse is not allowed, return NULL.
      return NULL;
    }

    try {
      if (empty($title)) {
        Drupal::messenger()
          ->addError('Error processing entity - title cannot be null');
        return NULL;
      }

      // Create a new node with the specified data.
      $data = [
        'type' => $type,
        'langcode' => $language_id,
        'title' => $cleanTitle,
        'field_unencoded_title' => $title,
      ];
      $node = Node::create($data);
      $node->save();
      Drupal::messenger()
        ->addStatus(__LINE__ . ' Created ' . $type . '  ' . $node->id() . ' ' . $node->getTitle());
    } catch (\Exception $e) {
      Drupal::messenger()
        ->addError('Error processing entity: ' . $e->getMessage());
      throw new Exception('Listing creation failed: ' . $e->getMessage(), BMImportCreator::EXCEPTION_NODE_CREATION_FAILED);
    }
    return $node;
  }

  /**
   * # set_node_fieldValues
   * Pass it an `action` code (see yml):
   * * `currentDateTime` : sets current date time to field
   * * `full_html` : sets value to field and adds format=>full_html
   * * `append id` : appends ['target_id' => $id]
   * * `append` : appends value to field or sets value to field (not multiple)
   * * `set id`: tids [target_id=>value]
   * * `set ids`: tids [target_id=>value[0], target_id=>value[1],
   * target_id=>value[n]]
   * * `upload image`: set (cast) the integer value (a FID)
   *
   * ## description
   * * detect when it is a reference field and adds items
   * * detect if value is a value or an array of target_id's
   * * using replaceAll to replace all or append to existing set.
   *
   * ## note set with multiple target_ids doesn't work! use append_item!
   *
   * @param Node   $node
   * @param string $fieldName
   * @param array  $values
   * @param array  $actions
   *
   * @throws Exception EXCEPTION_SET_NODE_FIELD_VALUE_FAILED
   */
  public
  function set_node_fieldValues(Node $node, string $fieldName, array $values, array $actions = []): void {
    Drupal::logger('BMImport')
      ->debug(__LINE__ . " set_node_fieldValues($fieldName,val:" . json_encode($values) . ',' . json_encode($actions) . ')');

    foreach ($actions as $action) {
      if (trim($action ?? '')) {
        switch ($action) {
          case 'currentDateTime':
            $values = strtotime(date('Y-m-d'));
            break;
          case 'full_html':
            $values = ['value' => $values, 'format' => 'full_html'];
            break;
          case 'append id' :
            $values = $this->appendId($node, $fieldName, $values);
            break;
          case 'append':
            $values = $this->append($node, $fieldName, $values);
            break;
          // set tid
          case 'set id':
            if (is_array($values)) {
              foreach ($values as $tid) {
                if (empty($tid)) {
                  bm_d('', $fieldName . ' Warning tid is empty?');
                  return;
                }// skip
                $values = ['target_id' => $tid];
                break;
              }
            }
            else {
              if (empty($tid)) {
                bm_d('', $fieldName . ' Warning tid is empty?');
                return;
              }
              else {
                $values = ['target_id' => $values];
              }
            }
            bm_d('', $values, 'set id result');
            break;
          // a list of tids transformed to target_ids
          case 'set ids':
            $ids = [];
            if (!is_array($values)) {
              if (is_numeric($values)) {
                $values = [$values];
              }
              else {
                throw new Exception('set Ids, Is not an array');
              }
            }

            foreach ($values as $tid) {
              $ids[] = ['target_id' => $tid];
            }
            $values = $ids;
            //        bm_d('',json_encode($values), 'set ids result');
            break;
          case 'upload_image':
            $values = (int) $values;  // must be a fid, created before
            break;
          default:
            throw new \Exception(__LINE__ . ' Exception - Unexpected action for set node field value action::' . $action);
        }
      }
    }

    try {
      $node->set($fieldName, $values);
      $node->save();
    } catch (Exception $e) {
      bm_d('', $e->getMessage(), ' Exception');
      // Values are not compatible with the field type, throw an exception.
      throw new Exception(
        __LINE__ . ' BMException in BMImportNodeHandler set_node_fieldValues fieldName:' . $fieldName . ' values:' . json_encode($values),
        self::EXCEPTION_SET_NODE_FIELD_VALUE_FAILED
      );
    }
    //bm_d("","success $fieldName");
    // Drupal::logger('BMImport')->debug(__LINE__ . " set_node_fieldValues($fieldName) end");
  }
  /**
   * # addParagraphToNodeFieldIfNotExists
   * Creates a paragraph entity and attaches it to a specified field of a node
   * (if it does not exist already)
   *
   * @param Node   $entity
   * @param string $fieldName
   * @param string $bundle
   *
   * @return Paragraph
   *   The created paragraph entity and set to the node field
   *
   * @throws \Exception EXCEPTION_NODE_CREATION_FAILED
   */
  public function addParagraphToNodeFieldIfNotExists(Node $entity, string $fieldName, string $bundle): Paragraph {
    $paragraphHandler = new BMImportParagraphHandler();
    return $paragraphHandler->addParagraphToNodeFieldIfNotExists($entity, $fieldName, $bundle);
  }
  /**
   * # addTermToNodeFieldIfNotExists
   *
   * @param Node   $entity
   * @param string $fieldName
   * @param string $bundle
   *
   * @return \Drupal\taxonomy\Entity\Term
   */
  public function addTermToNodeFieldIfNotExists(Node $entity, string $fieldName, string $bundle): Term {
    $vocabularyHandler = new BMImportVocabularyHandler();
    if (!$entity->hasField($fieldName)) {
      throw new Exception('Term field "' . $fieldName . '" does not exist', self::EXCEPTION_NODE_CREATION_FAILED);
    }
    $tid = $entity->get($fieldName)[0]['target_id'];
    $term = Term::load($tid);
    if ($term) {
      $entity->set($fieldName, ['target_id' => $tid]);
      return $term;
    }
    // create and add term

    throw new Exception('Paragraph does not exist for pid ' . $tid, self::EXCEPTION_NODE_CREATION_FAILED);
  }
  /**
   * # addNodeToNodeFieldIfNotExists
   *
   * @throws \Exception
   */
  public function addNodeToNodeFieldIfNotExists(Node $entity, string $fieldName, string $bundle): Node {
    $newNode = $this->create_node($fieldName, $bundle, 'en');
    $this->set_node_fieldValues($entity, $fieldName, $newNode->id(), ['append id']);
    return $newNode;
  }
  /**
   * # title_encoding
   *
   * Encodes the given title to be HTML safe.
   *
   * @param string $title
   *   The title to be encoded.
   *
   * @return string
   *   The HTML-encoded title.
   */
  public function title_encoding(string $title): string {
    return htmlspecialchars(trim($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }
  /**
   * # isValidMachineName
   * Validates if the provided machine name is valid.
   *
   * @param string $id
   *   The machine-readable name to validate.
   *
   * @return bool
   *   TRUE if the name is valid, FALSE otherwise.
   */
  private function isValidMachineName(string $id): bool {
    // Regular expression to match valid machine-readable names.
    return (bool) preg_match('/^[a-z0-9_]+$/', $id);
  }

  /**
   * # append a target_id item to a field
   * ## for terms and paragraphs, where a new term is added or a new paragraph
   *
   * @param Node $entity
   * @param      $fieldName
   * @param      $id
   *
   * @return mixed
   * @throws Exception
   */
  private function appendId(Node $entity, $fieldName, $id): mixed {
    if (empty($id)) {
      throw new Exception("BMImportNodeHandler appendId($fieldName, id), id can not be empty");
    }
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
   * # Append value into next delta of the field
   *
   * @param Node $entity
   * @param      $fieldName
   * @param      $values
   *
   * @return mixed
   */
  private function append(Node $entity, $fieldName, $values): mixed {
    $existing = $entity->get($fieldName)->getValue();
    $existing[] = $values;
    return $existing;
  }

}
