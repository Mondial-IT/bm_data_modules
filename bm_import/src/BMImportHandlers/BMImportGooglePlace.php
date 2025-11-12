<?php

namespace Drupal\bm_import\BMImportHandlers;

use Drupal;
use Drupal\bm_google_places\GooglePlace\GooglePlace;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\node\NodeInterface;
/**
 * # Class BMImportGooglePlace
 * ## see BMGooglePlaces settingsForm
 *
 * This class facilitates the import of Google Place data into Drupal nodes.
 * It provides methods to handle CSV data imports, map Google Place fields to Drupal node fields,
 * and manage related taxonomy terms, images, and other node fields.
 *
 * ## Key Responsibilities:
 * - Importing Google Place data into Drupal nodes.
 * - Handling the creation and updating of nodes based on Google Place information.
 * - Mapping Google Place attributes such as address, location, and categories to Drupal node fields.
 * - Managing images associated with a Google Place, including setting them on the node.
 * - Supporting the import of place data from CSV files and converting this data into structured node entities.
 *
 * ## Methods Overview:
 * - `csvExport`: (Commented out) Intended for exporting Google Place data to CSV format.
 * - `importCSV`: Imports place data from a CSV string and processes it.
 * - `import`: Imports Google Place data into a specified Drupal node, or creates a new node if none is provided.
 *
 * ## Usage Example:
 * ```
 * $place = new GooglePlace(...); // Assuming a GooglePlace object is already created.
 * $node = Node::load(123); // Load an existing node or pass null to create a new one.
 * BMImportGooglePlace::import($node, $place, $placeData);
 * ```
 *
 * ## Important Notes:
 * - The `import` method performs the main task of mapping Google Place data to node fields. It handles various aspects
 *   such as address components, categories, latitude/longitude, and images.
 * - The class assumes certain field names and content types exist in Drupal, such as `field_listing_place_id`, `field_listing_category`, etc.
 * - Make sure that the Google Place data is well-structured and validated before passing it to the `import` method.
 *
 * ## Dependencies:
 * - The class depends on the `BMImportCreator` class for creating and managing nodes and fields.
 * - It also integrates with the `GooglePlace` class to handle Google Place data.
 *
 * @package Drupal\bm_import\BMImport
 */
class BMImportGooglePlace {

  private BMImportImagesHandler $BMImportImagesHandler;

  private function getBMImportImagesHandler(): BMImportImagesHandler {
    if (!isset($this->BMImportImagesHandler)) {
      $this->BMImportimagesHandler = new BMImportImagesHandler();
    }
    return $this->BMImportImagesHandler;
  }

  private function csvExport(){
//    $csv=[];
//    $totalRows='';
//    foreach($placeIds as $placeId){
//      $place = $this->getCachedPlace($placeId);
//      $csv = $place->csvExport();
//      $totalRows.=$csv['row'];
//    }
//    return $csv['header'].$totalRows;
  }

  public static function importCSV($CSVString): void {
    $placesCSV= explode(PHP_EOL,$CSVString);
    $placeData=[];
    dsm($placesCSV,__LINE__.'places CSV');
    $header= explode('|',$placesCSV[0]);
    $columnCount=count($header);
    array_shift($placeData);
    foreach($placesCSV as $rowNr=>$placeCSV){
      $rowValues= explode('|',$placeCSV);
      if(count($rowValues)<>$columnCount)continue;// skip something wrong
      foreach($rowValues as $colNr=>$rowValue) {
        $placeData[$rowNr][$header[$colNr]] = $rowValue;
      }
    }
    dsm($placeData,'CSV unpacked into array');
    foreach($placeData as $placeArray){

    }
  }

  /**
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   * @throws \Exception
   */
  public static function import(NodeInterface|null $node, GooglePlace $place, array $placeArray): void {
    $BMImportImagesHandler = new BMImportImagesHandler();

    // set the full data into permanent cache.
    $place->set_permanent_cache();

    $BMImportCreator = new BMImportCreator();
    //$title=$place->displayName['text'];

    if (!$node) {
      $node = $BMImportCreator->create_node(
        title: $place->displayName['text'],
        type: 'listing',
        language_id: 'en',
        reUseExisting: TRUE,
      );
      //$node=Node::load(7630);
      bm_d('', $node, 'created node', 'dsm');
    }

    $BMImportCreator->set_node_fieldValues($node, 'field_listing_place_id', $place->place_id);
    bm_d('', $node->get('field_listing_place_id')->getValue(), 'set place id:' . $place->place_id, 'dsm');

    // make a reference to the node in the place
    $place->set_isListing($node->id());
    //$this->googlePlaces->cacheUpdatePlace($place);

    if (isset($place->addressComponents)) {
      $streetNumber = '';
      $route = '';
      foreach ($place->addressComponents as $ac) {
        switch ($ac['types'][0]) {
          case 'street number':
            $streetNumber = $ac['longtext'];
            break;
          case 'route':
            $route = $ac['longText'];
            break;
          case 'locality':
            $BMImportCreator->set_node_fieldValues($node, 'field_listing_city', $ac['longText']);
            break;
          case 'administrative_area_level_2':
            //
            break;
          case 'administrative_area_level_1':
            $BMImportCreator->set_node_fieldValues($node, 'field_listing_province', $ac['longText']);
            break;
          case 'country':
            $BMImportCreator->set_node_fieldValues($node, 'field_listing_country', $ac['longText']);
            break;
          case 'postal_code':
            $BMImportCreator->set_node_fieldValues($node, 'field_listing_postcode', $ac['longText']);
            break;
          default:
            // skip
        }
      }
      $BMImportCreator->set_node_fieldValues($node, 'field_listing_street', $route . ' ' . $streetNumber);

    }
    bm_d('', $node->get('field_listing_street')->getValue(), 'set street', 'dsm');

    $BMImportCreator->set_node_fieldValues($node, 'field_listing_address', $place->shortFormattedAddress);
    $BMImportCreator->set_node_fieldValues($node, 'field_listing_phone', $place->internationalPhoneNumber);
    $BMImportCreator->set_node_fieldValues($node, 'field_listing_website', $place->websiteUri);
    bm_d('', $node->get('field_listing_phone')->getValue(), 'set phone', 'dsm');

    // category is types
    if (isset($place->types)) {
      $values = [];
      foreach ($place->types as $type) {
        $term = $BMImportCreator->create_term_if_notExist(
          name_or_tid: $type, vid: 'listing_category', languageId: 'en');
        $values[] = ['target_id' => $term->id()];
      }
      $BMImportCreator->set_node_fieldValues(node: $node, fieldName: 'field_listing_category', values: $values);
    }
    bm_d('', $node->get('field_listing_category')->getValue(), 'set category', 'dsm');

    // etl source
    $values = [];
    $term = $BMImportCreator->create_term_if_notExist(
      name_or_tid: 'googlePlaces', vid: 'listing_etl_sources', languageId: 'en');
    $values[] = ['target_id' => $term->id()];
    $BMImportCreator->set_node_fieldValues(node: $node, fieldName: 'field_listing_etl_sheetname', values: $values);
    bm_d('', $node->get('field_listing_etl_sheetname')->getValue(), 'set ETL name', 'dsm');

    // Lat en Long
    $lat = 0;
    $lng = 0;
    if (isset($place->location)) {
      $lat = $place->location['latitude'];
      $lng = $place->location['longitude'];
    }
    if (isset($place->geometry)) {
      $lat = $place->geometry->location->lat;
      $lng = $place->geometry->location->lng;
    }
    $values = [
      'name' => $place->shortFormattedAddress,
      'lat' => $lat,
      'lon' => $lng
    ];
    $BMImportCreator->set_node_fieldValues(node: $node, fieldName: 'field_listing_map', values: $values);
    bm_d('', $node->get('field_listing_map')->getValue(), 'set map lat lng', 'dsm');

    if (isset($place->photos)) {
      // $request_stack = \Drupal::service('request_stack');
      // $current_request = $request_stack->getCurrentRequest();
      // $domain = $current_request->getHost();
      // $base_url = $current_request->getSchemeAndHttpHost();


      $n = 0;
      foreach ($place->photos as $photo) {
        // bm_d("",$photo,'photo','dsm');
        if (!isset($photo->photoRealPath)) {
          continue;
        }
        if ($n == 0) {
          $BMImportImagesHandler->upload_and_set_image_fid(
            node: $node,
            field_name: 'field_listing_image',
            imageUrl: $photo->photoRealPath,
            reset: TRUE);
        }
        $BMImportImagesHandler->upload_and_set_image_fid(
          node: $node,
          field_name: 'field_listing_gallery',
          imageUrl: $photo->photoRealPath,
          reset: $n++ == 0);

        // bm_d("",'','upload images $imageUrl ');
      }
      bm_d('', $node->get('field_listing_gallery')->getValue(), 'set images', 'dsm');
    }


    Drupal::messenger()->addStatus('Created Listing ' . $place->displayName['text']);
  }

}
