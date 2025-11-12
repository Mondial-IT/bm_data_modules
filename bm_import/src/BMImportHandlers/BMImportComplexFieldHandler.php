<?php

namespace Drupal\bm_import\BMImportHandlers;

use Drupal;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Exception;

/**
 */
class BMImportComplexFieldHandler {

  const EXCEPTION_COMPLEX_FIELD_FAILED = 9999;

  private bool $verbose;

  /**
   * @var \Drupal\bm_import\BMImportHandlers\BMImportNodeHandler
   */
  private BMImportNodeHandler $nodeHandler;

  /**
   * @var \Drupal\bm_import\BMImportHandlers\BMImportVocabularyHandler
   */
  private BMImportVocabularyHandler $vocabularyHandler;

  /**
   * @var \Drupal\bm_import\BMImportHandlers\BMImportParagraphHandler
   */
  private BMImportParagraphHandler $paragraphHandler;

  public function __construct($verbose) {
    $this->verbose = $verbose;
    $this->nodeHandler = new BMImportNodeHandler($verbose);
    $this->vocabularyHandler = new BMImportVocabularyHandler($verbose);
    $this->paragraphHandler = new BMImportParagraphHandler($verbose);
  }

  /**
   * @param string $fieldPath
   *
   * @return void
   * @example N.listing.field_specific_paragraph->P.hotel_info_paragraph.field_para_rooms->value
   *
   * ## simple field without multiples
   *
   * * N: the current entity is a N(ode),T(erm),P(aragraph)
   * * listing:  its content type is listing
   * * field_specific_paragraph: name of the field
   * * entity.P: contains paragraph entities
   * * hotel_info_paragraph: type of paragraph to use in case of creation
   * * field_para_rooms: the field on the paragraph
   * * value: the value to get. When regular field the field .. todo
   *
   * ## dealing with multiples in the variables
   * @example N.listing.field_specific_paragraph.0->P_hotel_info_paragraph--field_para_type.n.T->value
   *
   * ## returns parsed
   * ```
   *    ['entity'=>'N','type'=>'listing','fieldName'=>'field_specific_paragraph','index'=>0,'multiple'=>false]
   *    ['entity'=>'P','type'=>'hotel_info_paragraph','fieldName'=>'field_para_rooms','index'=>0,'multiple'=>false]
   *    ['entity'=>'value','type'=>"",'fieldName'=>"",'index'=>"",'multiple'=>""]
   * ```
   */
  private function parseFieldPath(string $fieldPath): array {
    $path_fields = explode('->', $fieldPath);
    $parsed = [];

    foreach ($path_fields as $index => $entityFieldInfo) {
      dsm($entityFieldInfo, 'Processing part');

      $fieldParts = explode('.', $entityFieldInfo);
      $parsed[] = [
        'entity' => $fieldParts[0] ?? "",
        'type' => $fieldParts[1] ?? '',
        'bundle' => $fieldParts[2] ?? '',
        'fieldName' => $fieldParts[3] ?? '',
        'multiple' => $fieldParts[4] ?? '',
      ];
    }
    return $parsed;
  }

  /**
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Exception
   */
  private function exists($entity, $parsed) {
    if ($entity) {
      $current = $entity;
      $parent = 0;
      foreach ($parsed as $n => $fieldInfo) {
        // ['entity'=>'N','type'=>'listing','fieldName'=>'field_specific_paragraph','index'=>0,'multiple'=>false]
        // ['entity'=>'P','type'=>'hotel_info_paragraph','fieldName'=>'field_para_rooms','index'=>0,'multiple'=>false]
        // ['entity'=>'value','type'=>"",'fieldName'=>"",'index'=>"",'multiple'=>""]
        $parent = $fieldInfo;
        $child = $parsed[$n + 1];
        try {
          switch ($parent['entity']) {
            case 'N':
              switch ($child['entity']) {
                case 'N':
                  $current = $this->nodeHandler->addNodeToNodeFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
                case 'P':
                  $current = $this->nodeHandler->addParagraphToNodeFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
                case 'T':
                  $current = $this->nodeHandler->addTermToNodeFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
              }
              // check it exists and if not adds the paragraph
              break;
            case 'P':
              switch ($child['entity']) {
                case 'N':
                  $current = $this->paragraphHandler->addNodeToParagraphFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
                case 'P':
                  $current = $this->paragraphHandler->addParagraphToParagraphFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
                case 'T':
                  $current = $this->paragraphHandler->addTermToParagraphFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
              }
              break;
            case 'T':
              switch ($child['entity']) {
                case 'N':
                  $current = $this->vocabularyHandler->addNodeToVocabularyFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
                case 'P':
                  $current = $this->vocabularyHandler->addParagraphToVocabularyFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
                case 'T':
                  $current = $this->vocabularyHandler->addTermToVocabularyFieldIfNotExists(
                    entity: $entity,
                    fieldName: $parent['fieldName'],
                    bundle: $child['type']
                  );
                  break;
              }
              break;
          }
        }
        catch (Exception $e) {
          throw new Exception('Something wrong with entity creation ' . $e->getMessage(), $e->getCode(), $e);
        }
      }
    }
  }

}
