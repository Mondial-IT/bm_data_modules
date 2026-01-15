<?php
namespace Drupal\bm_config_sync\Form;

use Drupal;
use Drupal\bm_config_sync\Utility\BMConfigSyncPathHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class ConfigSyncUUIDForm.
 */
class ConfigSyncUUIDForm extends FormBase {
  const SQL_DUMP_PATH = DRUPAL_ROOT . '/../private/bmlistings/backup_migrate/latest-backup-for-config-sync.mysql'; // Adjust to your actual dump file path.

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bm_config_sync_uuid_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'core/drupal.ajax';
    set_time_limit(600);
    $form['description'] = [
      '#type' => 'markup',
      '#markup' => '<p>Select configurations to synchronize UUIDs.</p>
      <p>This compares the <i>bm_config_sync</i> private directory with the current installation UUIDs and displays where changes are needed.</p>',
    ];

    [$rows,$newRows] = $this->compareConfigs();

    $header = [
      'label' => $this->t('Config Identifier'),
      'current_uuid' => $this->t('Current UUID'),
      'target_uuid' => $this->t('Target UUID'),
      'update_count' => $this->t('Dry Run Updates'),
      'sql_count' => $this->t('Database References'),
      'description' => $this->t('Description'),
    ];

    $form['table_select'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No configuration differences found.'),
    ];
    $form['table_select_new'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $newRows,
      '#empty' => $this->t('No configuration differences found.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Synchronize UUIDs'),
      '#ajax' => [
        'callback' => '::syncUuidAjax',
        'wrapper' => 'ajax-response',
      ],
    ];

    $form['ajax_response'] = [
      '#type' => 'markup',
      '#markup' => '<div id="ajax-response"></div>',
    ];

    return $form;
  }

  /**
   * Compare configurations between the active configuration and target directory.
   */
  private function compareConfigs(): array {
    $rows = [];
    $newRows = [];
    $config_factory = \Drupal::configFactory();
    $directory_info = BMConfigSyncPathHelper::getDirectoryInfo();
    if ($directory_info === NULL) {
      Drupal::messenger()->addError($this->t('The UUID sync directory %directory could not be resolved. Verify that the private files system is configured and accessible.', ['%directory' => BMConfigSyncPathHelper::getConfiguredDirectory()]));
      return [$rows, $newRows];
    }

    if (!$this->assertDirectoryReady($directory_info['resolved'], $directory_info['configured'])) {
      return [$rows, $newRows];
    }

    foreach (scandir($directory_info['resolved']) as $file) {
      if (pathinfo($file, PATHINFO_EXTENSION) === 'yml') {
        $config_name = pathinfo($file, PATHINFO_FILENAME);

        $target_file = $directory_info['resolved'] . "/$file";
        $target_data = file_exists($target_file) ? Yaml::parseFile($target_file) : [];
        $target_uuid = $target_data['uuid'] ?? null;

        // Attempt to retrieve the active configuration.
        $current_config = $config_factory->get($config_name);
        if (!$current_config->isNew()) {
          $current_uuid = $current_config->get('uuid');

          // If UUID exists, handle normally; if null, label it as 'N/A'.
          if ($current_uuid !== $target_uuid && $target_uuid) {
            $update_count = $current_uuid ? $this->countUuidOccurrences($current_uuid) : 0;
            $sql_count = $current_uuid ? $this->grepSqlForUuid($current_uuid) : 0;

            $rows[$config_name] = [
              'label' => $config_name,
              'current_uuid' => $current_uuid ?? $this->t('N/A'),
              'target_uuid' => $target_uuid,
              'update_count' => $update_count,
              'sql_count' => $sql_count,
              'description' => $current_config->get('description') ?? $this->t('No description'),
            ];
          }
        } else {
          // Configuration does not exist in the current system.
          $newRows[$config_name] = [
            'label' => $config_name,
            'current_uuid' => $this->t('not-present'),
            'target_uuid' => $target_uuid,
            'update_count' => 0,
            'sql_count' => 0,
            'description' => $this->t('missing configuration.'),
          ];
        }
      }
    }

    return [$rows,$newRows];
  }


  /**
   * Count occurrences of a UUID in the active configuration (dry run).
   */
  private function countUuidOccurrences(string $uuid): int {
    $config_storage = \Drupal::service('config.storage');
    $count = 0;

    foreach ($config_storage->listAll() as $config_name) {
      $config_data = json_encode($config_storage->read($config_name));
      if (strpos($config_data, $uuid) !== false) {
        $count++;
      }
    }

    return $count;
  }

  /**
   * Use grep to count occurrences of a UUID in the SQL dump file.
   */
  private function grepSqlForUuid(string $uuid): int {
    return 0;
    $command = sprintf("grep -o '%s' %s | wc -l", escapeshellarg($uuid), escapeshellarg(self::SQL_DUMP_PATH));
    $output = shell_exec($command);
    return is_numeric(trim($output)) ? (int) trim($output) : 0;
  }

  /**
   * AJAX callback for UUID synchronization.
   */
  public function syncUuidAjax(array &$form, FormStateInterface $form_state) {
    $selected = array_filter($form_state->getValue('table_select'));

    if (empty($selected)) {
      return [
        '#type' => 'markup',
        '#markup' => '<div id="ajax-response">No configurations selected for synchronization.</div>',
      ];
    }

    foreach ($selected as $config_name) {
      $this->updateConfigUuid($config_name);
    }

    return [
      '#type' => 'markup',
      '#markup' => '<div id="ajax-response">UUIDs synchronized successfully!</div>',
    ];
  }

  /**
   * Update the UUID of a configuration item.
   */
  private function updateConfigUuid(string $config_name): void {
    $directory_info = BMConfigSyncPathHelper::getDirectoryInfo();
    if ($directory_info === NULL) {
      Drupal::messenger()->addError($this->t('The UUID sync directory %directory could not be resolved. Verify that the private files system is configured and accessible.', ['%directory' => BMConfigSyncPathHelper::getConfiguredDirectory()]));
      return;
    }

    $foreign_uuid_dir = $directory_info['resolved'];
    $config_factory = \Drupal::configFactory();

    $target_file = "$foreign_uuid_dir/$config_name.yml";
    if (!file_exists($target_file) || !is_readable($target_file)) {
      Drupal::messenger()->addError($this->t('The UUID source file %file is missing or cannot be read.', ['%file' => $target_file]));
      return;
    }

    $target_data = Yaml::parseFile($target_file);

    if (!empty($target_data['uuid'])) {
      $config = $config_factory->getEditable($config_name);
      $config->set('uuid', $target_data['uuid'])->save();
      Drupal::messenger()->addMessage($this->t('Updated local config UUID: ' . $config_name));
    }
  }

  /**
   * Ensures the UUID directory exists and is readable before use.
   */
  private function assertDirectoryReady(string $directory, ?string $display_directory = NULL): bool {
    $display_directory = $display_directory ?? $directory;
    if (!is_dir($directory)) {
      Drupal::messenger()->addError($this->t('The UUID sync directory %directory does not exist.', ['%directory' => $display_directory]));
      return FALSE;
    }

    if (!is_readable($directory)) {
      Drupal::messenger()->addError($this->t('The UUID sync directory %directory is not readable.', ['%directory' => $display_directory]));
      return FALSE;
    }

    if (!is_writable($directory)) {
      Drupal::messenger()->addWarning($this->t('The UUID sync directory %directory is not writable. Synchronization may fail.', ['%directory' => $display_directory]));
    }

    return TRUE;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

}
