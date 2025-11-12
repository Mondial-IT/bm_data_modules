<?php

namespace Drupal\bm_import\BMImportHandlers;

use Drupal;
use Drupal\bm_debug\BMDebugInfo\BMDebugInfo;
use Drupal\bm_taxonomies\BMTaxonomies\BMTaxonomies;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Exception;

/**
 * # Class BMImportVocabularyHandler
 *
 * This class handles the management of vocabularies and taxonomy terms within a Drupal environment.
 * It provides methods for creating vocabularies, adding and updating taxonomy terms, managing taxonomy
 * hierarchies, and creating custom fields on vocabularies. Additionally, the class supports checking the existence
 * of fields on vocabularies and includes utilities for debugging and logging.
 *
 * ## Key Responsibilities:
 * - Adding and updating taxonomy terms within specified vocabularies.
 * - Creating vocabularies if they do not already exist.
 * - Managing hierarchical relationships between taxonomy terms.
 * - Checking for the existence of fields on vocabularies.
 * - Facilitating the creation of custom fields on taxonomy terms.
 * - Providing debugging support through the `bm_d_callback` method.
 *
 * ## Methods Overview:
 * - `bm_d_callback`: Registers debug information with the BMDebugInfo system.
 * - `add_taxonomy_term`: Adds a new term to a vocabulary, creating the vocabulary if it doesn't exist.
 * - `add_update_taxonomy_hierarchy`: Creates or updates a hierarchy of taxonomy terms within a vocabulary.
 * - `createVocabulary`: Creates a new vocabulary.
 * - `getTermByName`: Retrieves a taxonomy term by its name and vocabulary ID.
 * - `getVocabularyByName`: Retrieves a vocabulary by its name.
 * - `saveTerm`: Saves a taxonomy term.
 * - `fieldExistsOnTaxonomy`: Checks if a field exists on a specific taxonomy vocabulary.
 * - `createFieldDefinition`: Creates a field definition for a taxonomy vocabulary.
 *
 * ## Usage Example:
 * ```
 * $vocabularyHandler = new BMImportVocabularyHandler();
 * $term = $vocabularyHandler->add_taxonomy_term('categories', 'New Category');
 *
 * if ($term) {
 *     Drupal::messenger()->addStatus('Term created: ' . $term->id());
 * } else {
 *     Drupal::messenger()->addError('Failed to create term.');
 * }
 * ```
 *
 * ## Important Notes:
 * - This class assumes that vocabularies and taxonomy terms are central to your data structure and may require
 *   additional validation or error handling in more complex applications.
 * - Hierarchical taxonomy management is supported, allowing for nested terms to be created or updated based on provided hierarchies.
 * - The class includes debugging capabilities which can be integrated with the BMDebugInfo system for tracking operations.
 *
 * @package Drupal\bm_import\BMImport
 */
class BMImportVocabularyHandler {
  const EXCEPTION_ADD_TAXONOMY_TERM_FAILED = 9993;
  const EXCEPTION_SET_TAXONOMY_TERM_FIELD_VALUE_FAILED = 9992;
  const BM_D_KEY = __CLASS__;
  const BM_D_KEY_DETAILS = __CLASS__.'details';

  static public function bm_d_callback($key, $BMDbgInfo): BMDebugInfo|false {

    if ($key == self::BM_D_KEY) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImport - general level';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    if ($key == self::BM_D_KEY_DETAILS) {
      $BMDbgInfo->module = 'bm_import';
      $BMDbgInfo->description = 'Debug BMImport - level details.';
      $BMDbgInfo->default = 0;
      $BMDbgInfo->screen_dsm_log = 'screen';
      return $BMDbgInfo;
    }
    return FALSE;
  }

  public function __construct() {
    bm_d('','<div class="bgcolor-blue fgcolor-white">Instantiating BMImportVocabularyHandler</div>');
  }

  // simple message text opleukertje
  private function inColor($text): string {
    return '<span class="fgcolor-navy">' . $text . '</span>';
  }
  public function version(): string {
    return '0.1';
  }
  /**
   * # get_contentTypes list vocabularies
   * Return the available contenttypes.
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function get_types(): array {
    $contentTypes = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_vocabulary')
      ->loadMultiple();

    // Prepare options for the select dropdown.
    $options = [];
    foreach ($contentTypes as $machineName => $contentType) {
      $options[$machineName] = $contentType->label();
    }
    return $options;

  }
  /**
   * # Create a new vocabulary.
   *
   * @param string $vocabularyName
   *   The name of the vocabulary.
   * @param string $vocabularyLabel
   * @param string $description
   *
   * @return int SAVED_NEW|SAVED_UPDATED
   *   The created vocabulary or null if it couldn't be created.
   * @throws EntityStorageException
   * @throws Exception
   */
  public function createVocabulary(string $vocabularyName, string $vocabularyLabel, string $description = 'BMImport'): int {
    if (!$this->isValidMachineName($vocabularyName)) {
      throw new Exception("The vocabulary ID '.$vocabularyName.' is not a valid machine-readable name. It must contain only lowercase letters, numbers, and underscores.");
    }

    if (!$vocabularyLabel) {
      $vocabularyLabel = $vocabularyName;
    }

    $vocabulary = Vocabulary::create([
      'vid' => $vocabularyName,
      'description' => $description,
      'name' => $vocabularyLabel
    ]);
    return $vocabulary->save();
  }
  /**
   * # getTermByName
   * * Get a taxonomy term by its name and vocabulary ID.
   *
   * @param string $vocabularyId
   *   The ID of the vocabulary.
   * @param string $termName
   *   The name of the term.
   *
   * @return TermInterface|null
   *   The found term or null if not found.
   */
  private function getTermByName(string $vocabularyId, string $termName) {
    // Your logic to retrieve a term by name and vocabulary ID goes here
    // Implement this method based on how terms are stored in your application
    // Return null if the term doesn't exist
  }
  /**
   * # Get a vocabulary by its name.
   *
   * @param string $vocabularyName
   *   The name of the vocabulary.
   *
   * @return Drupal\Core\Entity\EntityInterface
   *   The found vocabulary or null if not found.
   */
  private function getVocabularyByName(string $vocabularyName): ?Drupal\Core\Entity\EntityInterface {
    // Load the vocabulary by its machine name
    try {
      $vocabulary = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vocabularyName);
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      return null;
    }

    // Return the vocabulary entity if found, or null if not found
    return $vocabulary ?: null;
  }
  /**
   * @param $vocabularyId
   *
   * @return bool
   */
  public function check_vocabularyExists($vocabularyId): bool {
    return $this->getVocabularyByName($vocabularyId)<>NULL;
  }
  /**
   * Given a string representing the hierarchy items separated by .
   * traverse the hierarchy to find the bottom match
   * and return the tid.
   *
   * @param        $hierarchy_array [SBI, A, 1, 7, 20, 207]
   * @param string $vocabulary_id   int tid representing 207
   *
   * @return int
   */
  function get_bottom_tid_resembling_hierarchy($hierarchy_array,string $vocabulary_id): int {
    // Split the hierarchy string by dots
    $parts = $hierarchy_array;

    // Start with the top-level term
    $current_terms = \Drupal::entityQuery('taxonomy_term')
      ->condition('name', array_shift($parts))  // Start with the first part ("SBI")
      ->condition('vid', $vocabulary_id)
      ->execute();

    if (!$current_terms) {
      return 0; // Return NULL if the top-level term is not found
    }

    // Load the top-level term
    $current_term = Term::load(reset($current_terms));

    // Iterate through the remaining parts to drill down the hierarchy
    foreach ($parts as $part) {
      if (!$current_term) {
        return 0; // If at any level, the term is not found, return NULL
      }

      // Get the child terms of the current term
      $child_terms = \Drupal::entityQuery('taxonomy_term')
        ->condition('vid', $vocabulary_id)
        ->condition('parent', $current_term->id())
        ->condition('name', $part)
        ->execute();

      if (!$child_terms) {
        return 0; // If no matching child term is found, return NULL
      }

      // Move to the next level
      $current_term = Term::load(reset($child_terms));
    }

    return $current_term->id(); // Return the final term in the hierarchy
  }
  /**
   * # fieldExistsOnTaxonomy
   *
   * @param $vid
   * @param $fieldName
   *
   * @return bool
   */
  public function fieldExistsOnTaxonomy($vid, $fieldName): bool {
    $field_config = FieldConfig::loadByName('taxonomy_term', $vid, $fieldName);

    // Return true if the field configuration object exists, false otherwise.
    return $field_config !== null;
  }
  /**
   * # Get the term and create if not exist
   *
   * @param string $name_or_tid
   * @param string $vid term vocabulary
   * @param string $languageId
   *
   * @return \Drupal\taxonomy\Entity\Term|null
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function create_term_if_notExist(string $name_or_tid, string $vid, string $languageId = 'en'): Term|null {

    if(is_numeric($name_or_tid)){
      bm_d('', '<div class="bgcolor-green fgcolor-white"> Create term from id:<b>' . $name_or_tid . '</b> in Taxonomy:<b>' . $vid . '</b> language:' . $languageId . '</div>');  // tid given
      $term =Term::load($name_or_tid);
      if($term instanceof Term){
        return $term;
      }else{
        throw new exception("Create term in Taxonomy failed, a termId was given but it is not present in $vid");
      }
    }
    else {
      bm_d('', '<div class="bgcolor-green fgcolor-white"> Create term from name:<b>' . $name_or_tid . '</b> in Taxonomy:<b>' . $vid . '</b> language:' . $languageId . '</div>');
      $name= $name_or_tid;
      // note this needs the language id to function correctly.
      $term = BMTaxonomies::getTerm_using_nameAndVid($name, $vid, $languageId);
    }
    if (!$term) {
      $hasVid = Vocabulary::load($vid);
      if (!$hasVid) {
        Drupal::messenger()
          ->addError('Error processing listing import add taxonomy terms: name:' . $name . ' vid:' . $vid . ' VID NOT FOUND');
        return NULL;
      }
      $termProperties = [];
      $termProperties['vid'] = $vid;
      $termProperties['name'] = $name;
      $termProperties['langcode'] = $languageId;
      //      dsm($termProperties,__FUNCTION__.__LINE__.' term Properties');
      if (!empty($termProperties['name'])) {
        $term = Term::create($termProperties);
        $term->save();
      }
      else {
        Drupal::messenger()
          ->addError('Error processing listing import add taxonomy terms: name:' . $name . ' vid:' . $vid);
      }
      // todo save a default value
      //      if($languageId!=='en'){
      //        $term->save();
      //      }
    }

    if (!$term) {
      Drupal::messenger()
        ->addError('Error processing listing import add taxonomy terms: ' . $name . $vid);
      return NULL;
      // throw new Exception("Add term $name to $vid failed", self::EXCEPTION_ADD_TAXONOMY_TERM_FAILED);
    }

    return $term;
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
    foreach ($fieldNamesValues as $fieldInfo) {
      $value = $fieldInfo['value'];
      if (isset($fieldInfo['conversion'])) {
        if ($fieldInfo['conversion'] === 'currentDateTime') {
          $value = strtotime(date('Y-m-d'));
        }
      }
      if(!empty($value)) {
        $term->set($fieldInfo['fieldName'], $value);
      }else{
        bm_d('',$fieldInfo,' wat is er mis met deze [value]? 0 of null','dsm');
      }
    }
    try {
      $term->save();
    } catch (\Exception $e) {
      Drupal::messenger()->addError('Error processing listing import save term: ' . $e->getMessage());
      throw new Exception('Paragraph save failed', self::EXCEPTION_SET_TAXONOMY_TERM_FIELD_VALUE_FAILED);
    }
  }
  /**
   * ## add_update_taxonomy_hierarchy
   * Nederland, Groningen, Kropswolde
   * create this hierarchy or update parts when not complete.
   * and return the tids.
   *
   * @param string $vocabulary_id vid
   * @param array  $hierarchy [grandparent, parent, child] labels
   *
   * @return array of tids
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function add_update_taxonomy_hierarchy(string $vocabulary_id, array $hierarchy = []):array {
    $parent = 0; // Start with no parent for the top-level term

    $tids=[];
    foreach ($hierarchy as $term_name) {

      if(!isset($term_name)){
        bm_d('',$hierarchy, $term_name.' termName empty in add_update_taxonomy_hierarchy?','dsm');
        return [0,0,0];
      }
      /** @var Term[] $terms */
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
        'name' => $term_name,
        'vid' => $vocabulary_id,
        'parent' => $parent
      ]);

      if ($term = reset($terms)) {
        $tids[]=$term->id();
        // Term exists
       bm_d(self::BM_D_KEY,"Term '{$term_name}' exists.");
      } else {
        // Term does not exist, create it
        $term = Term::create([
          'name' => $term_name,
          'vid' => $vocabulary_id,
          'parent' => [$parent],
        ]);
        $term->save();
        $tids[]=$term->id();
        bm_d(self::BM_D_KEY, "Term '{$term_name}' created.");
      }

      // Set this term's id as the parent for the next level
      $parent = $term->id();
    }
    return $tids;
  }
  /**
   * # isValidMachineName
   * @param $id
   *
   * @return bool
   */
  private function isValidMachineName($id): bool {
    // Regular expression to match valid machine-readable names
    return (bool) preg_match('/^[a-z0-9_]+$/', $id);
  }
  /**
   * # Save a taxonomy term.
   *
   * @param TermInterface $term
   *   The term to be saved.
   */
  private function saveTerm(TermInterface $term) {
    // Your logic to save the term goes here
    // Implement this method based on how terms are saved in your application
  }

}
