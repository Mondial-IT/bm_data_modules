<?php
namespace Drupal\bm_import\BMImportHandlers;

use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\node\NodeInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class BMImportImagesHandler
 *
 * This class is responsible for managing the import and processing of images into nodes within a Drupal environment. It provides methods to handle various image operations such as importing images from URLs, managing existing images, and creating new file entities.
 *
 * **Key Responsibilities:**
 * - Handling existing image URIs and creating file entities if necessary.
 * - Importing images in a specific `u_w_h` format, which involves extracting and processing image URLs.
 * - Downloading images from URLs and attaching them to specific fields within nodes.
 * - Managing file entities to ensure they are saved and referenced correctly in nodes.
 *
 * **Methods Overview:**
 * - `fid_from_existing_imageUri`: Returns the file ID (fid) of an image based on an existing URI or creates it if it doesn't exist.
 * - `import_u_w_h__listing_image__listing_gallery`: Handles importing images into specific fields (`listing_image` and `listing_gallery`) from a specially formatted string.
 * - `upload_and_set_image_fid`: Downloads an image from a URL or local path and sets it to a node field.
 * - `uri_load_image`: Loads image data from a given URI using an HTTP client.
 * - `prepare_value_for_imageField`: Prepares an array of values for an image field in a node, such as fid, alt text, and dimensions.
 *
 * **Usage Example:**
 *
 * ```
 *
 * $imageHandler = new BMImportImagesHandler();
 * $fid = $imageHandler->fid_from_existing_imageUri('public://example.jpg');
 * if ($fid) {
 *   $node = \Drupal::entityTypeManager()->getStorage('node')->load(1);
 *   $imageHandler->upload_and_set_image_fid($node, 'field_image', 'https://example.com/image.jpg', TRUE);
 * }
 *
 * ```
 *
 * **Important Notes:**
 * - The class ensures that image files are correctly managed, either by using existing entities or by creating new ones as needed.
 * - When importing images, special attention is given to handling different image sources, such as URLs and local paths, to maintain flexibility.
 * - The class includes utility methods for managing images within nodes and creating file entities.
 *
 * **Package:**
 * Drupal\bm_import\BMImport
 */
class BMImportImagesHandler{
  const BM_D_KEY = __CLASS__;

  const BM_D_KEY_DETAIL = __CLASS__ . 'detail';

  /**
   * **fid_from_existing_imageUri**
   *
   * Returns the file ID (fid) of an image based on an existing URI or creates it if it doesn't exist.
   *
   * @param string $imageUri
   *   The URI of the image.
   *
   * @return int
   *   Returns 0 in case of an error, otherwise returns the fid of the image.
   *
   * @throws EntityStorageException
   * @throws InvalidPluginDefinitionException
   * @throws PluginNotFoundException
   */
  public function fid_from_existing_imageUri(string $imageUri): int {
    $fid = 0;
    $existing_files = \Drupal::entityTypeManager()
      ->getStorage('file')
      ->loadByProperties(['uri' => $imageUri]);
    $existing_file = reset($existing_files);

    // If the file is not already managed, create a file entity for it.
    if (!$existing_file) {
      $file_entity = \Drupal\file\Entity\File::create([
        'uri' => $imageUri,
        'status' => FILE_STATUS_PERMANENT, // Make sure to mark the file as permanent.
      ]);
      $file_entity->save();
      $fid = $file_entity->id();
    }
    return $fid;
  }

  /**
   * **import_u_w_h__listing_image__listing_gallery**
   *
   * Special case for importing images and gallery from a specific PHOTOS format.
   *
   * u_w_h = Url, Width and Height
   *
   * This method processes a formatted string to extract image URLs and import them into the specified node's image fields.
   *
   * @param string $field_u_w_h
   *   The formatted string containing image URLs and dimensions.
   * @param NodeInterface $node
   *   The node to which the images will be attached.
   *
   * @return void
   */
  public function import_u_w_h__listing_image__listing_gallery(string $field_u_w_h, NodeInterface $node): void {
    $u_w_h_multiple = explode(',', $field_u_w_h);
    $n = 0;
    foreach ($u_w_h_multiple as $u_w_h_string) {
      $u_w_h_parts = explode(';', $u_w_h_string);
      $url = '';
      foreach ($u_w_h_parts as $part) {
        if (str_contains($part, 'u=')) {
          $url = str_replace('u=', '', $part);
          break;
        }
      }
      if (!$url) {
        continue;
      }

      // Handling local installation paths or URLs.
      if (str_contains($url, 'public://')) {
        $file_system = \Drupal::service('file_system');
        $uri = 'public://';
        $public_directory_path = $file_system->realpath($uri);
        $url = $public_directory_path . '/' . str_replace('public://', '', $url);
        if (!file_exists($url)) {
          Drupal::messenger()->addStatus('file not found:' . $url);
          return;
        }
      }
      elseif (!str_contains($url, 'http')) {
        $url = \Drupal::request()->getSchemeAndHttpHost() . $url;
      }

      if ($n == 0) {
        // Set first image as main image.
        $this->upload_and_set_image_fid(
          node: $node,
          field_name: 'field_listing_image',
          imageUrl: $url,
          reset: TRUE);
      }
      // Set rest of images to gallery.
      $this->upload_and_set_image_fid(
        node: $node,
        field_name: 'field_listing_gallery',
        imageUrl: $url,
        reset: $n++ == 0);
    }
  }

  /**
   * **upload_and_set_image_fid**
   *
   * Downloads an image from a URL or local path, creates a file entity, and updates a node's image field.
   *
   * @param NodeInterface $node
   *   The node for which the image field is updated.
   * @param string $field_name
   *   The name of the field where the image will be set.
   * @param string|null $imageUrl
   *   The URL or real path of the image to download and set.
   * @param bool $reset
   *   Whether to reset the field, removing existing images and adding the image at position 0.
   *
   * @return int|false
   *   Returns the fid if successful, or false if the operation fails.
   */
  function upload_and_set_image_fid(NodeInterface $node, string $field_name, ?string $imageUrl, bool $reset = TRUE): int|false {

    bm_d(self::BM_D_KEY, '<span class="bgcolor-blue fgcolor-white"> BMImportCreator - upload_and_set_image_fid - ' . $field_name . 'Reset:' . $reset . '</span>');
    bm_d(self::BM_D_KEY, $imageUrl, 'imageUrl');

    // Check if the image URL is a real path.
    $isRealPath = str_starts_with($imageUrl, '/home/docker_volume');
    $fieldDefinition = $node->getFieldDefinition($field_name);
    $fieldSettings = $fieldDefinition->getSettings();
    $directory = $fieldSettings['file_directory'];
    $scheme = $fieldSettings['uri_scheme'];
    $fullDirectory = $scheme . '://' . $directory;
    \Drupal::service('file_system')->prepareDirectory($fullDirectory, FileSystemInterface::CREATE_DIRECTORY);
    $images = [];

    if ($reset) {
      // Remove existing images.
      $images = $node->get($field_name)->getValue();
      foreach ($images as $delta => $image) {
        $fileId = $image['target_id'];
        $file = File::load($fileId);
        if ($file) {
          try {
            \Drupal::entityTypeManager()->getStorage('file')->delete([$file]);
          } catch (InvalidPluginDefinitionException|PluginNotFoundException|EntityStorageException $e) {
          }
        }
      }
      $node->set($field_name, []);
    }

    if ($isRealPath) {
      $imageData = file_get_contents($imageUrl);
      if (!$imageData) {
        Drupal::messenger()
          ->addMessage(__LINE__ . ' <span class="fgcolor-red">BMImportCreator - Image data path not retrieved from: "' . $imageUrl . '"</span>');
        return FALSE;
      }
    }
    else {
      $imageData = $this->uri_load_image($imageUrl);
      if (!$imageData) {
        Drupal::messenger()
          ->addMessage(__LINE__ . ' <span class="fgcolor-red">BMImportCreator - Image data uri not retrieved from: "' . $imageUrl . '"</span>');
        return FALSE;
      }
    }
    // Determine the image format.
    $imageFormat = 'png'; // Default to jpg if the format cannot be determined.
    $imageInfo = getimagesizefromstring($imageData);
    bm_d(self::BM_D_KEY, $imageInfo, 'imageInfo', 'dsm');
    if ($imageInfo && isset($imageInfo['mime'])) {
      $mime = explode('/', $imageInfo['mime']);
      if (count($mime) === 2) {
        $imageFormat = $mime[1]; // Get the format from the mime type.
      }
    }
    $width = $imageInfo[0] ?? 500;
    $height = $imageInfo[1] ?? 500;

    $filename = basename($imageUrl);
    // Append the determined image format to the filename.
    $filename = pathinfo($filename, PATHINFO_FILENAME) . '.' . $imageFormat;
    $fileUri = $fullDirectory . '/' . $filename;
    try {
      $file = file_save_data($imageData, $fileUri, FileSystemInterface::EXISTS_REPLACE);
      bm_d(self::BM_D_KEY_DETAIL, ' BMImportCreator - Image [' . $file->id() . '] saved to: ' . $fileUri . ' length:' . strlen($imageData));
    } catch (Exception $e) {
      Drupal::messenger()->addMessage(__LINE__ . ' BMImportCreator - Image could not be saved to: ' . $fileUri);
      return FALSE;
    }
    $images = $node->get($field_name)->getValue();
    array_unshift($images, [
      'target_id' => $file->id(),
      'alt' => 'Image description',
      'title' => 'Image title',
      'width' => $width,
      'height' => $height,
    ]);
    $node->set($field_name, $images);
    try {

      $node->save();
      bm_d('', ' <span class="bgcolor-lightgreen fgcolor-black"> SUCCESS - ' . $field_name . ' BMImportCreator - Image ' . $file->id() . ' added to the field successfully.</span>');
      return TRUE;
    } catch (EntityStorageException $e) {
      bm_d('', ' <span class="bgcolor-red">FAIL - BMImportCreator - Node save on adding Image failed</span>');
      Drupal::messenger()->addError(__LINE__ . ' BMImportCreator - Image was not added to the field.');
      return FALSE;
    }
  }

  /**
   * **uri_load_image**
   *
   * Gets image data from a URI using an HTTP client.
   *
   * @param string $imageUrl
   *   The URL of the image to retrieve.
   *
   * @return string|false
   *   Returns the image data as a string, or false if the operation fails.
   */
  private function uri_load_image(string $imageUrl): string|false {
    $client = new Client();
    try {
      $response = $client->get($imageUrl);
      if ($response->getStatusCode() == 200) {
        $imageData = $response->getBody()->getContents();
        if (str_contains($imageData, 'Cloudflare Access')) {
          Drupal::messenger()
            ->addStatus(__LINE__ . '<span class="bgcolor-red">BMImportCreator Let op Cloudflare wall zit er tussen. Image not loaded. ' . $imageUrl . '</span>', TRUE);
          Drupal::messenger()->addError('Let op Cloudflare zit er tussen. Image not loaded.');
          return FALSE;
        }
        return $imageData;
      }
    } catch (RequestException $e) {
      Drupal::messenger()->addError('Request to image URL "' . $imageUrl . '" failed: ' . $e->getMessage());
    }
    return FALSE;
  }

  /**
   * **prepare_value_for_imageField**
   *
   * Prepares the value array to be used in an image field.
   *
   * @param int $fid
   *   The file ID (fid) of the image.
   * @param string $alt
   *   The alt text for the image.
   * @param int $width
   *   The width of the image.
   *
   * @return array
   *   Returns an array of field values, including fid, alt, width, and height.
   */
  public function prepare_value_for_imageField(int $fid, string $alt, int $width = 1024): array {
    return [
      'target_id' => $fid,
      'alt' => 'Image description',
      'width' => $width,
      'height' => $width,
    ];
  }
}
