<?php
namespace bmDataModules\bm_import\src\BMImport;

use Drupal\node\Entity\Node;
use Exception;

/**
 * ## class used by BMImport to maintain the list of "created elements" from yaml definitions.
 *
 * * In the YAML, paragraphs are constructed, which pids need to be inserted
 * into other fields.
 * * This class maintains a list of created element
 * to be used via the yaml definitions.
 */
class BMImportActiveResult {

  public Node $mainEntity;// where it starts with

  public int $mainEntityId;// nid

  public array $nodeIds;// created nodes

  public array $paragraphIds;// creates paragraphs

  public bool $verbose;

  public array $termIds;// created terms

  public array $awaits;// see set/get

  public function __construct() {
    $this->verbose = FALSE;// messages
    $this->awaits = [];
    $this->termIds = [];
    $this->nodeIds = [];
  }

  public function getMainEntity(): Node {
    return $this->mainEntity;
  }

  public function setMainEntity($entity): void {
    if ($entity) {
      $this->mainEntity = $entity;
      $this->mainEntityId = $entity->id();
    }
  }

  public function setCreatedNode($name, $nid, $yamlId): void {
    $this->nodeIds[] = ['nid' => $nid, 'name' => $name, 'yamlId' => $yamlId];
  }
  // __contact, field_para_reference, node_field

  /**
   * like javascript promisse,
   * a field awaits creation of another node (or other)
   * and when that is created it needs to be set in the field
   *
   * @param $id    string  id given in uaml of the thing to be created
   * @param $field string field to put the entity_id in
   * @param $type  string 'node_field, node_paragraph_field, paragraph_paragraph_field
   *
   * @return void
   */
  public function setAwait(string $id, string $field, string $type): void {
    bm_d('', ": Registered an await for $id for $field of type $type");
    $this->awaits[] = [
      'awaitingNid' => $this->mainEntityId,
      'awaitingId' => $id,
      'setToField' => $field,
      'ofType' => $type,
    ];
  }

  public function getAwaits(): array {
    return $this->awaits;
  }

  public function resolveAwaits(): void {
    //    dsm($this->nodeIds,'nodeIds');
    //    dsm( $this->awaits,' $this->awaits');

    $awaits = $this->awaits;

    if ($this->verbose) {
      ///  dsm($awaits, 'registered awaits');
    }
    if ($this->verbose) {
      ///  dsm($this->nodeIds, 'aregistered nodeids');
    }
    foreach ($awaits as $await) {
      $awaitNoMore = FALSE;
      if ($this->verbose) {
        ///   dsm($na, 'nA');
      }

      $awaitingNid = $await['awaitingNid'];
      $awaitingId = $await['awaitingId'];
      $setToField = $await['setToField'];
      $ofType = $await['ofType'];
      foreach ($this->nodeIds as $createInfo) {
        if ($this->verbose) {
          ///    dsm($nb, 'nB');
        }
        if ($createInfo['yamlId'] !== NULL && $createInfo['yamlId'] === $awaitingId) {
          $awaitNoMore = $createInfo['nid'];
          break;
        }
      }

      if ($awaitNoMore) {
        if ($this->verbose) {
          ///    dsm('awaitnomore');
        }
        if ($ofType === 'node_fields') {
          try {
            $node = Node::load($awaitingNid);
            $node->set($setToField, ['target_id' => $awaitNoMore]);

            $node->save();
          } catch (Exception) {
            ///    dsm($e);
            bm_d('', ' Exception resolve await <span class="bgcolor-red fgcolor-white">node_set(' . $setToField . ' value:' . $awaitNoMore . ') - FAILED</span>');
          }
          bm_d('', "<span class=\"bgcolor-lightgreen\">SUCCESS - Resolving await $awaitingNid waiting for $awaitingId found $awaitNoMore </span>");
        }
      }
      else {
        if ($this->verbose) {
          /// dsm('no await no-more');
        }
      }

    }
    if ($this->verbose) {
      /// dsm('DONE AWAITS');
    }
    bm_d('', '<span class="bgcolor-lightgreen">-- AWAITS RESOLVED  -- </span>');
  }

  public function storeParagraphId($pid, $id): void {
    $this->paragraphIds[$id] = $pid;
  }

  public function storeTermId($tid, $id): void {
    $this->paragraphIds[$id] = $tid;
  }

}
