<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2025
   Created on 2025-3-12 at 4:49:21
 */

namespace Drupal\bm_config_sync\Handler;

use Drupal;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\FileStorage;

class BMConfigSyncHandler {

  private array $combinedERD;

  private array $ERD;

  // keyed by entity name|field_name
  public array $fields;

  public array $bundles;


  public function __construct() {
    $this->combinedERD = [];
    $this->ERD=[];
    $this->bundles = [];
    $this->fields = [];
    $this->populate_all_fields_and_bundle_names();
  }

  public function exportNodeConfigurations($configName): string {
    $exportedYML = $this->configuration_item_exporter('node.type.' . $configName);
    $exportedYML .= $this->exportContentTypeDependencies($configName);
    return $exportedYML;
  }

  public function exportTaxonomyConfigurations($configName): string {
    $exportedYML = $this->configuration_item_exporter('taxonomy.vocabulary.' . $configName);
    $exportedYML .= $this->exportVocabularyDependencies($configName);
    return $exportedYML;
  }

  public function exportParagraphConfigurations($configName): string {
    $exportedYML = $this->configuration_item_exporter('paragraphs.paragraphs_type.' . $configName);
    $exportedYML .= $this->exportParagraphDependencies($configName);
    return $exportedYML;
  }

  /**
   * Export form and view display configurations for paragraph field references.
   */
  public function exportParagraphDependencies($paragraph_type): string {
    $exportedYML = '';
    try {
      // Export the field storage and field configurations for the paragraph type
      $fields = \Drupal::entityTypeManager()
        ->getStorage('field_storage_config')
        ->loadByProperties(['entity_type' => 'paragraph']);
      foreach ($fields as $field) {
        if ($field->get('bundle') == $paragraph_type) {
          $field_name = $field->getName();
          $exportedYML .= $this->configuration_item_exporter('field.storage.' . $field_name);
          $exportedYML .= $this->configuration_item_exporter('field.field.' . $field_name);
        }
      }

      // Export the form display and view display for the paragraph type
      $exportedYML .= $this->configuration_item_exporter('core.entity_form_display.paragraph.' . $paragraph_type . '.default');
      $exportedYML .= $this->configuration_item_exporter('core.entity_view_display.paragraph.' . $paragraph_type . '.default');
    } catch (Drupal\facets\Exception\Exception $e) {
      Drupal::messenger()->addError($e->getMessage());
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
    }
    return $exportedYML;
  }

  /**
   * Export all dependencies for the content type, including fields, form displays, and view displays.
   */
  public function exportContentTypeDependencies($content_type): string {
    $exportedYML = '';

    // Export the field storage and field configurations for the content type
    try {
      $fields = \Drupal::entityTypeManager()
        ->getStorage('field_storage_config')
        ->loadByProperties(['entity_type' => 'node']);
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      Drupal::messenger()->addError($e->getMessage());
      return '';
    }
    foreach ($fields as $field) {
      if ($field->get('bundle') == $content_type) {
        $field_name = $field->getName();
        $exportedYML .= $this->configuration_item_exporter('field.storage.' . $field_name);
        $exportedYML .= $this->configuration_item_exporter('field.field.' . $field_name);
      }
    }

    // Export the form display and view display for the content type
    $exportedYML .= $this->configuration_item_exporter('core.entity_form_display.node.' . $content_type . '.default');
    $exportedYML .= $this->configuration_item_exporter('core.entity_view_display.node.' . $content_type . '.default');

    return $exportedYML;
  }

  /**
   * Export all dependencies for the vocabulary, including field storage.
   */
  public function exportVocabularyDependencies($vocabulary): string {
    $exportedYML = '';
    try {
      // Load all field instances for the taxonomy term entity type
      $field_definitions = \Drupal::entityTypeManager()
        ->getStorage('field_config')
        ->loadByProperties(['entity_type' => 'taxonomy_term']);

      // Iterate through all field configurations
      foreach ($field_definitions as $field_definition) {
        // Check if the field is attached to the given vocabulary (bundle)
        if ($field_definition->get('bundle') == $vocabulary) {
          // Get the field name
          $field_name = $field_definition->getName();

          // Export the field storage and field instance configurations
          $exportedYML .= $this->configuration_item_exporter('field.storage.taxonomy_term.' . $field_name);
          $exportedYML .= $this->configuration_item_exporter('field.field.taxonomy_term.' . $vocabulary . '.' . $field_name);
        }
      }
    } catch (\Exception $e) {
      Drupal::messenger()->addError($e->getMessage());
    }
    return $exportedYML;
  }


  /**
   * # configuration_item_exporter
   * * use item name and export all children and dependencies
   *
   * @param $item_name
   *
   * @return string
   */
  public function configuration_item_exporter($item_name): string {
    // Get the active configuration storage.
    $activeStorage = \Drupal::service('config.storage');
    // Get the sync directory path from settings.php.
    $syncDirectory = \Drupal::service('settings')->get('config_sync_directory');
    // Ensure the sync directory is defined, exists, and is writable.
    if (!$syncDirectory || !is_dir($syncDirectory) || !is_writable($syncDirectory)) {
      Drupal::messenger()
        ->addError("The sync directory is either undefined, missing, or not writable: $syncDirectory");
      return '';
    }
    // Create a FileStorage instance for the sync directory.
    $syncStorage = new FileStorage($syncDirectory);
    // Get the dependencies of the specified item
    $dependencies = [];
    $config_entity = \Drupal::configFactory()->get($item_name);
    // check for empty (happens with core.base_field_override for example, these are optional.
    if (!$config_entity->getRawData()) {
      return '';
    };

    // Check if the config has dependencies (e.g., fields, displays)
    if (isset($config_entity->get('dependencies')['config'])) {
      $dependencies = array_merge($dependencies, $config_entity->get('dependencies')['config']);
    }

    // Add the vocabulary config itself to the list
    $dependencies[] = $item_name;
    $yaml_output = '';
    // Export the vocabulary and its dependencies
    foreach ($dependencies as $dependency) {
      // Read the configuration data
      $data = $activeStorage->read($dependency);

      // Write the configuration to the sync storage (overwriting the existing one)
      if (is_array($data)) {

        // add to the ERD builder array
        $this->populate_combinedERD_ERD_per_bundle($item_name, $data);


        $syncStorage->write($dependency, $data);

        // Read the written YAML file
        $file_path = $syncDirectory . '/' . $dependency . '.yml'; // Assuming YAML extension
        if (file_exists($file_path)) {
          // Read the YAML file and append its contents to the $yaml_output variable
          $yaml_contents = file_get_contents($file_path);
          $yaml_output .= "\n\n# " . $dependency . "\n\n" . $yaml_contents; // Add a separator for clarity between configurations
        }
        \Drupal::messenger()->addMessage('Exported configuration: ' . $dependency);
      }
      else {
        Drupal::messenger()->addError("The configuration '$dependency' data error ");
      }

    }

    return $yaml_output;
  }

  /**
   * **Returns a list of field configurations for the bundle name**
   * * (it does not return the children configurations.)
   * * key format: `bundleName|fieldName`
   * * data format: `bundle, machineName, field_name, label, description, value_target_id, target_type`.
   *
   * Where `value_target_id` contains `'value'` or `'target_id'` to inform of the type.
   *
   * Example:
   * ```
   * 'listing|field_bm_id'=>
   * [
   *  'bundle'=> 'node.listing',
   *  'machineName'=> 'node.field_bm_id',
   *  'field_name'=> 'field_bm_id',
   *  'label'=> 'field_bm_id',
   *  'description'=> null,
   *  'value_target_id'=> 'value',
   *  'target_type'=> null
   * ],
   * ...
   * * more fields
   *
   *```
   *
   * @param string $keyFilter
   *
   * @return array
   */
  public function get_type_fields(string $keyFilter): array {

    return array_filter($this->fields,
      function ($data, $key) use ($keyFilter) {
      // Check if the key starts with the bundleFilter
      $entity = explode('|', $key)[0];
      return $entity === $keyFilter;
    }, ARRAY_FILTER_USE_BOTH);
  }

  /**
   * **Returns a list of bundles**
   *
   * Where bundles are contentType names, paragraph names, taxonomy names
   * * format: bundleType.bundleName=>bundleName
   *
   * ```
   * 'node.listing'=> 'listing',
   * ...
   * 'paragraph.datalink_paragraph'=> 'datalink_paragraph',
   * ...
   * 'taxonomy_term.amenities'=> 'amenities',
   *```
   *
   * @param string $bundleFilter
   *
   * @return array
   */
  public
  function get_type_bundles(string $bundleFilter=''): array {
    return array_filter($this->bundles,
      function ($bundle, $key) use ($bundleFilter) {
      // Check if the key starts with the bundleFilter
      return str_starts_with($key, $bundleFilter);
    }, ARRAY_FILTER_USE_BOTH);
  }

  /**
   * Returns a list of field_paths of the bundle.
   * With a pattern of
   *
   * **`bundleName.bundleType.fieldName.bundleName.bundleType.fieldName`**
   *
   * ```
   * 'listing.node.field_bm_id',
   * 'listing.node.field_call_report',
   * 'listing.node.field_campaigns_migration_source',
   * 'listing.node.field_chain',
   * 'listing.node.field_listing_tags.listing_tags.taxonomy_term.field_term_color',
   * 'listing.node.field_listing_tags.listing_tags.taxonomy_term.field_term_icon',
   * 'listing.node.field_listing_address',
   * 'listing.node.field_listing_address_paragraph',
   * 'listing.node.field_para_reference_contacts.paragraph_address.paragraph.field_para_address_city',
   * 'listing.node.field_para_reference_contacts.paragraph_address.paragraph.field_para_address_country',
   * 'listing.node.field_para_reference_contacts.paragraph_address.paragraph.field_para_address_epsg_x',
   * 'listing.node.field_para_reference_contacts.paragraph_address.paragraph.field_para_address_epsg_y',
   * "listin
   * ```
   *
   * @param $bundleFilter
   *
   * @return array
   */
  public
  function get_combined_field_paths($bundleFilter=''): array {
    $combinedERD = $this->getCombinedERD();
    $field_paths=[];
    foreach($combinedERD as $ERD){
      $field_paths[]= $ERD['field_path'];
    }
    return $field_paths;
  }

  /**
   * **Retrieve all fields from the system and populate $fields and $bundles.**
   * Returns the cached version, unless there is a configuration change, then it will refresh the cache and return.
   *
   * @return void
   */
  public
  function populate_all_fields_and_bundle_names(): void {
    $this->fields = [];
    $allBundles = [];

    // from cache
    $cache = Drupal::cache('bluemarloc')->get('bm_config_sync');
    if($cache){
      $this->fields = $cache->data['fields']??false;
      $this->bundles = $cache->data['bundles']??false;
      if($this->fields){
        return;
      }
    }

    try {

      // Load all field storage configurations.
      $fieldStorages = \Drupal::entityTypeManager()->getStorage('field_storage_config')->loadMultiple();
      foreach ($fieldStorages as $fieldStorage) {
        $name = $fieldStorage->getName();
        $bundles = $fieldStorage->getBundles();
        $dependencies = $fieldStorage->getDependencies();
        $description = $fieldStorage->getDescription();
        $Oid = $fieldStorage->getOriginalId();// node.field_call_report
        $Eid = $fieldStorage->getEntityTypeId();// field_storage_config
        $type = $fieldStorage->getEntityType();
        $machineName = $fieldStorage->getLabel();
        $value_target_id = $fieldStorage->getMainPropertyName();
        $parent_target_type = $fieldStorage->getTargetEntityTypeId();// node
        $target_type = $fieldStorage->getSettings()['target_type'] ?? NULL;// taxonomy_term
        if (str_starts_with($machineName, 'node.field') ||
          str_starts_with($machineName, 'taxonomy_term.field') ||
          str_starts_with($machineName, 'paragraph.field')) {

          foreach ($bundles as $bundle) {
            $type = explode('.', $machineName);
            $allBundles[$type[0] . '.' . $bundle] = $bundle;


            /** @var \Drupal\field\Entity\FieldStorageConfig $fieldStorage */
            $this->fields[$bundle . '|' . $fieldStorage->getName()] = [

              'bundle' => $type[0] . '.' . $bundle,
              'machineName' => $machineName,
              'field_name' => $name,
              'label' => $name,
              'description' => $description,
              'value_target_id' => $value_target_id,
              'target_type' => $target_type,
            ];
          }
        }
      }
    } catch (\Exception $e) {
      Drupal::messenger()->addError($this->t('Failed to retrieve fields: @error', ['@error' => $e->getMessage()]));
    }

    $this->bundles = $allBundles;

    // invalidate when changed
    $cache_tags = [
      'config:entity_type.node',
      'config:entity_type.taxonomy_term',
      'config:entity_type.paragraph',
    ];
    Drupal::cache('bluemarloc')->set(
      'bm_config_sync',
      ['fields'=>$this->fields, 'bundles'=>$this->bundles],
      Cache::PERMANENT,
      $cache_tags);
  }

  /**
   * * Populates `$this->combinedERD` with the ERD of the Bundle and its children
   * * Retrieve with `getCombinedERD`
   * * Populates  `$this->ERD[$bundle]` with the ERD per Bundle.
   * * Retrieve with `getERD`
   *
   *
   * ```
   * id: node.listing.body
   * field_name: body
   * entity_type: node
   * bundle: listing
   * label: Body
   * field_type: text_with_summary
   * field_type: string_long
   * field_type: string
   * field_type: text_long
   * field_type: integer
   *    settings: [ min: null, max: null, prefix: '', suffix: '' ]
   *
   * MUST FOLLOW TARGET BUNDLES AS POSSIBLE ENDPOINTS
   * field_type: entity_reference
   *    settings: [
   *      handler=> 'default:taxonomy_term,
   *      handler_settings=>['target_bundles'=>[chain_name:chain_name] ] ]
   *
   * MUST FOLLOW TARGET BUNDLES AS POSSIBLE ENDPOINTS
   * field_type: entity_reference_revisions
   *  settings: [ 'handler'=>'default:paragraph', 'handler_settings'=>['target_bundles'=> [
   *  'paragraph_address'=>'paragraph_address', ... => ...
   *
   * field_type: timestamp
   *
   * field_type: image
   *    settings: ['handler'=>'default:file',file_directory: listings, file_extensions: 'png gif jpg jpeg webp',
   *    max_filesize: ''
   *
   * field_type: google_map_field
   *    settings:
   *
   * field_type: list_string
   * langcode
   * name
   * type
   * description
   * help?
   *
   * ```
   *
   * @param        $items
   * @param int    $depth
   * @param string $field_path
   *
   * @return void
   */
  public function populate_combinedERD_ERD_per_bundle($items, $depth=0, $field_path=''): void {
    if ($depth > 10) {
      Drupal::logger('bm_config_sync')->warning('ERD depth exceeded 10 while traversing @path.', ['@path' => $field_path]);
    }
    if ($depth > 20) {
      Drupal::logger('bm_config_sync')->error('ERD depth exceeded 20 while traversing @path; aborting recursion.', ['@path' => $field_path]);
      return;
    }

    $activeStorage = \Drupal::service('config.storage');
    // example listing|field_bm_id
    foreach ($items as $entity_and_field => $data) {
      $entity= explode('|',$entity_and_field)[0];
      // Fetching the field storage configuration
      $machineName = $data['machineName'];
      $field_storage_data = $activeStorage->read('field.storage.' . $machineName);

      // Fetching the field instance configuration (field applied to a bundle)
      $field_instance_data = $activeStorage->read('field.field.' . $data['bundle'] . '.' . $data['field_name']);

      // Build the ERD (Entity Relationship Diagram) with detailed field information

      // ERD from top to bottom all included.
      $this->combinedERD[$data['machineName']] = [
        'bundle' => $field_instance_data['bundle'] ?? '', // From field.instance configuration
        'id' => $field_storage_data['id'] ?? '',
        'label' => $field_instance_data['label'] ?? '',
        'description' => $field_instance_data['description'] ?? '',
        'field_path'=>$field_path.$entity.'.'.$field_storage_data['id'],// node.field.taxo.field. ...
        'entity_type' => $field_storage_data['entity_type'] ?? '',
        'depth'=>$depth,
 // id has field name as well       'field_name' => $field_storage_data['field_name'] ?? '', // Field applied to bundle
  // same as field_type      'type' => $field_storage_data['type'] ?? '',
        'field_type' => $field_instance_data['field_type'] ?? '', // Field type in instance
        'langcode' => $field_instance_data['langcode'] ?? '',
        'help' => $field_instance_data['help'] ?? '',
        'name' => $field_storage_data['name'] ?? '',
        'settings' => json_encode($field_instance_data['settings']) ?? '',
        'children' => '',
      ];

      // ERD per entity
      $this->ERD[$data['bundle']][$data['machineName']] = [
        'id' => $field_storage_data['id'] ?? '',
        'entity_type' => $field_storage_data['entity_type'] ?? '',
        'field_name' => $field_storage_data['field_name'] ?? '', // Field applied to bundle
        'label' => $field_instance_data['label'] ?? '',
        'description' => $field_instance_data['description'] ?? '',
        // same as field_type      'type' => $field_storage_data['type'] ?? '',
        'bundle' => $field_instance_data['bundle'] ?? '', // From field.instance configuration
        'field_type' => $field_instance_data['field_type'] ?? '', // Field type in instance
        'field_path'=>$entity.'.'.$field_storage_data['id'],// node.field.taxo.field. ...
        'langcode' => $field_instance_data['langcode'] ?? '',
        'help' => $field_instance_data['help'] ?? '',
        'name' => $field_storage_data['name'] ?? '',
        'settings' => json_encode($field_instance_data['settings']) ?? '',
        'children' => '',
        'depth'=>$depth,
      ];

      // assume node field first
      $this->ERD[$data['bundle']][$data['machineName']][ 'edit']=
        '<a href="/admin/structure/types/manage/'.$field_instance_data['bundle'].'/fields/'. $field_storage_data['entity_type'].'.'.$field_instance_data['bundle'].'.'.$field_instance_data['field_name'].'">Edit</a>';

      $settings = $field_instance_data['settings'];
      switch($this->combinedERD[$data['machineName']]['field_type']){
        // paragraph on lower level
        case 'entity_reference_revisions':
          // add edit link
          $this->ERD[$data['bundle']][$data['machineName']][ 'edit']=
            '<a href="/admin/structure/paragraph_type/manage/'.
              $field_instance_data['bundle'].
              '/fields/'.
              $field_storage_data['entity_type'].'.'.
              $field_instance_data['bundle'].'.'.
              $field_instance_data['field_name'].
            '">Edit</a>';

          // process children
          $paragraphs = $settings['handler_settings']['target_bundles'];
          foreach($paragraphs as $paragraph){
            $this->combinedERD[$data['machineName']]['children'] .=' '. $paragraph;
            $this->ERD[$data['bundle']][$data['machineName']]['children'].=' '. $paragraph;
            $fields = $this->get_type_fields($paragraph);
            $this->populate_combinedERD_ERD_per_bundle($fields, $depth+1, $this->combinedERD[$data['machineName']]['field_path'].'.');
          }
          break;
        // node and taxonomy on lower level
        case 'entity_reference':
          if($settings['handler']==='default:taxonomy_term') {
            $this->ERD[$data['bundle']][$data['machineName']][ 'edit']=
            '<a href= admin/structure/taxonomy/manage/'.$field_instance_data['bundle'].'/overview/fields/'.
              $field_storage_data['entity_type'].'.'.
              $field_instance_data['bundle'].'.'.
              $field_instance_data['field_name'].
            '">Edit</a>';

            $vocabularies = $settings['handler_settings']['target_bundles'];
            foreach ($vocabularies as $vocabulary) {
              $this->combinedERD[$data['machineName']]['children'] .=' '. $vocabulary;
              $this->ERD[$data['bundle']][$data['machineName']]['children'].=' '. $vocabulary;

              $fields  = $this->get_type_fields($vocabulary);
              $this->populate_combinedERD_ERD_per_bundle($fields, $depth+1, $this->combinedERD[$data['machineName']]['field_path'].'.');
            }
          }else{
            // node reference default:node
            // look in the list of connected content types
            if($settings['handler']==='default:node') {
              if(!isset($settings['handler_settings']['target_bundles'])){
                Drupal::messenger()->addStatus('Notice: This node references a node content type but no specific type has been set for it.');
                $this->ERD[$data['bundle']][$data['machineName']]['children']='not set? error';
                // no content type is selected, (which is odd but possible)
                break;
              }
              $contentTypes = $settings['handler_settings']['target_bundles'];
              foreach ($contentTypes as $contentType) {
                $this->combinedERD[$data['machineName']]['children'] .=' '. $contentType;
                $this->ERD[$data['bundle']][$data['machineName']]['children'] .=' '. $contentType;
                break;
              }
            }
          }
          break;
        default: break;
      }

      // Optionally, you can also fetch additional field properties from $field_instance_data if necessary
    }
  }

  public function getCombinedERD(): array {
    return $this->combinedERD;
  }

  public function getERDs(): array {
    return $this->ERD;
  }

  /**
   * **Return the list of fields available in the ERD**
   * * used by forms to create the column headers.
   * @return string[]
   */
  public function getERDColumnNames(): array {
    return [
      'bundle' => 'bundle',
      'id' => 'id',
      'label' => 'label',
      'description' => 'description',
      'field_path' => 'field_path',
      'entity_type' => 'entity_type',
      'depth' => 'depth',
      'field_name' => 'field_name',
      // same as field_type      'type' => $field_storage_data['type'] ?? '',
      'field_type' => 'field_type',
      'langcode' => 'langcode',
      'help' => 'help',
      'name' => 'help',
      'settings' => 'settings',
      'children' => 'children',
      'edit'=>'edit'
    ];
  }


}
