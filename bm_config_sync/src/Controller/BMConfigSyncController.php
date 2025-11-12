<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2025
   Created on 2025-6-7 at 3:50:45
 */

namespace Drupal\bm_config_sync\Controller;

use Drupal\bm_config_sync\BMConfigSyncBatchExporter;
use Drupal\bm_config_sync\Handler\BMConfigSyncHandler;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

class BMConfigSyncController extends ControllerBase {

  private BMConfigSyncHandler $configSyncHandler;

  /**
   * @throws Exception
   */


  public function startBMConfigSyncBatchExporter(): \Drupal\Component\Render\MarkupInterface|string {
    // Initialize the exporter class
    $exporter = new BMConfigSyncBatchExporter();

    // Prepare the batch
    $batch = $exporter->batchPrepare();

    // Set the batch (this automatically generates a unique batch ID)
    batch_set($batch);

    // Process the batch (this will trigger the background process)
    $redirectResponse = batch_process();

    $targetUrl = $redirectResponse->getTargetUrl();
    $targetUrl = str_replace('http:', 'https:', $targetUrl);


    // Start an iframe that loads the batch process page
    $iframe_html = '<iframe src="' . $targetUrl . '" style="width:100%; height:400px; border: none;"></iframe>';

    return Markup::create($iframe_html);
  }

  /**
   * Return available endpoints
   *
   * @return JsonResponse
   */
  public function jsonapi_endpoints():JsonResponse {
    $data = [
      '/json-api/config/fields/{bundleName}'=>'List of fields available in the bundle',
      '/json-api/config/bundles'=>'Bundles present in this instance',
      '/json-api/config/combined-field-paths/{bundleName}'=>'List of full paths to properties',
      '/json-api/config/combined-erd/{bundleName}'=>'Detailed configuration data per field and bundle',
    ];
    $response = [
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'copyright' => 'Blue Marloc',
        ],
      ],
      'data' => $data,
      'meta' => [
        'count' => count($data),
      ],
    ];

    return new JsonResponse($response);
  }
  /**
   * /json-api/config/fields/{bundleName}
   * @param $bundleName
   *
   * @return JsonResponse
   */
  public function jsonapi_field_list($bundleName): JsonResponse {

    $this->configSyncHandler = new BMConfigSyncHandler();

    $data = $this->configSyncHandler->get_type_fields($bundleName ?? '');

    $response = [
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'copyright' => 'Blue Marloc',
        ],
      ],
      'data' => $data,
      'meta' => [
        'count' => count($data),
        'bundle' => $bundleName,
      ],
    ];

    return new JsonResponse($response);
  }

  /**
   * /json-api/config/combined-erd/{bundleName}
   * @param $bundleName
   *
   * @return JsonResponse
   */
  public function jsonapi_combined_erd($bundleName){

    $this->configSyncHandler = new BMConfigSyncHandler();

    $fields = $this->configSyncHandler->get_type_fields($bundleName ?? '');
    $this->configSyncHandler->populate_combinedERD_ERD_per_bundle($fields);
    // Retrieve created ERD. i.e. node.field_bm_id => [bundle=>, description=>, settings=>, type=>integer]
    $data = $this->configSyncHandler->getCombinedERD();

    $response = [
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'copyright' => 'Blue Marloc',
        ],
      ],
      'data' => $data,
      'meta' => [
        'count' => count($data),
        'bundle' => $bundleName,
      ],
    ];

    return new JsonResponse($response);

  }

  /**
   * `/json-api/config/combined-field-paths/{bundleName}`
   *
   * @param $bundleName
   *
   * @return JsonResponse
   */
  public function jsonapi_combined_field_paths($bundleName): JsonResponse {

    $this->configSyncHandler = new BMConfigSyncHandler();

    $fields = $this->configSyncHandler->get_type_fields($bundleName ?? '');
    $this->configSyncHandler->populate_combinedERD_ERD_per_bundle($fields);
    // Retrieve created ERD. i.e. node.field_bm_id => [bundle=>, description=>, settings=>, type=>integer]
    $data = $this->configSyncHandler->get_combined_field_paths();

    $response = [
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'copyright' => 'Blue Marloc',
        ],
      ],
      'data' => $data,
      'meta' => [
        'count' => count($data),
        'bundle' => $bundleName,
      ],
    ];

    return new JsonResponse($response);

  }
  /**
   * /json-api/config/bundles
   * @return JsonResponse
   */
  public function jsonapi_bundle_list(): JsonResponse {

    $this->configSyncHandler = new BMConfigSyncHandler();

    $data = $this->configSyncHandler->get_type_bundles();

    $response = [
      'jsonapi' => [
        'version' => '1.0',
        'meta' => [
          'copyright' => 'Blue Marloc',
        ],
      ],
      'data' => $data,
      'meta' => [
        'count' => count($data),
      ],
    ];

    return new JsonResponse($response);
  }


}
