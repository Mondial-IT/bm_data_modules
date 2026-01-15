<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace Drupal\bm_config_sync\Form;

use Drupal;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Render\Markup;

/**
 * Class BMTemplateForm for a Drupal settings form with AJAX table select functionality.
 */
class BMConfigSyncImportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bm_import_import_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bm_import.settings'];
  }

  /**
   * Builds the settings form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Attach supporting libraries.
    $form['#attached']['library'][] = 'bm_tooltip/tooltip';
    $form['#attached']['library'][] = 'bm_config_sync/admin_styles';
    $form['#attributes']['class'][] = 'bm-config-sync-form';

    // Detect the button clicked and call the action function.
    if ($form_state->getTriggeringElement()) {
      $button_clicked = $form_state->getTriggeringElement()['#name'];
      $this->execute_button_action($button_clicked, $form, $form_state);
    }

    $form['intro'] = [
      '#markup' => '<h1>BM IMPORT - CONFIGURATION</h1>
                <p>Importing configurations.</p>',
    ];

    // Panel with table select and buttons.
    $form['panels']['#prefix'] = Markup::create('<div class="bm-config-sync-panel-grid">');
    $this->panel_table_contenttypes($form['panels'], $form_state);
    $this->panel_table_taxonomies($form['panels'], $form_state);
    $this->panel_table_paragraphs($form['panels'], $form_state);
    $this->panel_table_fields($form['panels'], $form_state);
    $form['panels']['#suffix'] = '</div>';

    return $form;
  }

  private function panel_table_contenttypes(array &$form, FormStateInterface $form_state): void {
    $panel_title = $this->t('Node config import');
    $panel_help = $this->t('Import content type definition YAML from the private sync directory. The table lists every available node bundle export.');
    $form['table_select_panel1']['#prefix'] = '<div class="bm-config-sync-panel">'
      . $this->buildPanelHeading($panel_title, $panel_help)
      . '<hr>';
    $form['table_select_panel1']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];
    $nodeHandler = new BMImportConfigHandler();
    $entitiesList = [];
    try {
      $entitiesList = $nodeHandler->list_content_type_configurations();
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name')
    ];
    $rows = [];
    $n = 1;
    foreach ($entitiesList as $index => $filename) {
      $rows[$n] = ['id' => $n++, 'name' => $filename];
    }


    $form['table_select_panel1']['table_select_1'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // Response div for displaying processed rows.
    $form['table_select_panel1']['response-1'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-1', 'class' => ['bm-config-sync-ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel1']['node_import'] = [
      '#type' => 'button',
      '#value' => $this->t('Import'),
      '#name' => 'node_import',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];




  }

  private function panel_table_taxonomies(array &$form, FormStateInterface $form_state): void {
    $panel_title = $this->t('Vocabulary config import');
    $panel_help = $this->t('Import taxonomy vocabulary configuration files, including their field storage.');
    $form['table_select_panel2']['#prefix'] = '<div class="bm-config-sync-panel">'
      . $this->buildPanelHeading($panel_title, $panel_help)
      . '<hr>';
    $form['table_select_panel2']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $handler = new BMImportConfigHandler();
    $entitiesList = [];
    try {
      $entitiesList = $handler->list_taxonomy_type_configurations();
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name')
    ];
    $rows = [];
    $n = 1;
    foreach ($entitiesList as $index => $filename) {
      $rows[$n] = ['id' => $n++, 'name' => $filename];
    }


    $form['table_select_panel2']['table_select_2'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // Response div for displaying processed rows.
    $form['table_select_panel2']['response_2'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-2', 'class' => ['bm-config-sync-ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel2']['vocabulary_import'] = [
      '#type' => 'button',
      '#value' => $this->t('Import'),
      '#name' => 'vocabulary_import',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];





  }

  private function panel_table_paragraphs(array &$form, FormStateInterface $form_state): void {
    $panel_title = $this->t('Paragraph config import');
    $panel_help = $this->t('Import paragraph bundle definitions and field storage YAML.');
    $form['table_select_panel3']['#prefix'] = '<div class="bm-config-sync-panel">'
      . $this->buildPanelHeading($panel_title, $panel_help)
      . '<hr>';
    $form['table_select_panel3']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $handler = new BMImportConfigHandler();
    $entitiesList = [];
    try {
      $entitiesList = $handler->list_paragraph_type_configurations();
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name')
    ];
    $rows = [];
    $n = 1;
    foreach ($entitiesList as $index => $filename) {
      $rows[$n] = ['id' => $n++, 'name' => $filename];
    }


    $form['table_select_panel3']['table_select_3'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // ajax Response div for displaying processed rows.
    $form['table_select_panel3']['response_3'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-3', 'class' => ['bm-config-sync-ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel3']['paragraph_import'] = [
      '#type' => 'button',
      '#value' => $this->t('Import'),
      '#name' => 'paragraph_import',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];




  }
  private function panel_table_fields(array &$form, FormStateInterface $form_state): void {
    $panel_title = $this->t('Field config import');
    $panel_help = $this->t('Import individual field configuration YAML files that belong to existing bundles.');
    $form['table_select_panel4']['#prefix'] = '<div class="bm-config-sync-panel">'
      . $this->buildPanelHeading($panel_title, $panel_help)
      . '<hr>';
    $form['table_select_panel4']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $handler = new BMImportConfigHandler();
    $entitiesList = [];
    try {
      $entitiesList = $handler-> list_field_type_configurations(); // Method to list exported field YAML files
    } catch (\Exception $exception) {
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
    ];
    $rows = [];
    $n = 1;
    foreach ($entitiesList as $index => $filename) {
      $rows[$n] = ['id' => $n++, 'name' => $filename];
    }

    $form['table_select_panel4']['table_select_4'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // Response div for displaying processed rows.
    $form['table_select_panel4']['response-4'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response-4', 'class' => ['bm-config-sync-ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel4']['field_import'] = [
      '#type' => 'button',
      '#value' => $this->t('Import'),
      '#name' => 'field_import',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];
  }

  public function ajaxProcessSelectedRows(array &$form, FormStateInterface $form_state): AjaxResponse {
    $trigger = $form_state->getTriggeringElement()['#name'];
    $selected_rows = [];
    $selector = '#ajax-response';
    $response_messages = [];
    switch ($trigger) {
      case 'node_import':
        // Existing node import logic
        $selected_rows = array_filter($form_state->getValue('table_select_1'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel1']['table_select_1']['#options'][$index];
          $handler = new BMImportConfigHandler();
          $response_messages = $handler->import_configuration_from_file($row['name']);
        }
        $selector = '#ajax-response-1';
        break;
      case 'vocabulary_import':
        // Existing vocabulary import logic
        $selected_rows = array_filter($form_state->getValue('table_select_2'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel2']['table_select_2']['#options'][$index];
          $handler = new BMImportConfigHandler();
          $response_messages = $handler->import_configuration_from_file($row['name']);
        }
        $selector = '#ajax-response-2';
        break;
      case 'paragraph_import':
        // Existing paragraph import logic
        $selected_rows = array_filter($form_state->getValue('table_select_3'));
        foreach ($selected_rows as $index => $value) {
          $row = $form['panels']['table_select_panel3']['table_select_3']['#options'][$index];
          $handler = new BMImportConfigHandler();
          $response_messages = $handler->import_configuration_from_file($row['name']);
        }
        $selector = '#ajax-response-3';
        break;
      case 'field_import':
        // New field import logic
        $selected_rows = array_filter($form_state->getValue('table_select_4'));
        foreach ($selected_rows as $index) {
          $row = $form['panels']['table_select_panel4']['table_select_4']['#options'][$index];
          $handler = new BMImportConfigHandler();
          $response_messages = $handler->import_configuration_from_file($row['name']); // New handler method
        }
        $selector = '#ajax-response-4';
        break;
      default:
        break;
    }

    $response = new AjaxResponse();

    $result_message = empty($selected_rows)
      ? $this->t('No rows selected.')
      : $this->t('Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);

    // Update the response div.
    $response_message = '<ul><li>' . implode('</li><li>', $response_messages) . '</li></ul>';
    $response->addCommand(new HtmlCommand($selector, $result_message . $response_message));

    // Display any messages in a modal popup.
    $this->response_messages($response);

    return $response;
  }

  /**
   * Executes the button action based on the clicked button.
   */
  private function execute_button_action($button_clicked, &$form, FormStateInterface $form_state): void {


    // Process the selected rows and provide different messages for each button.
    $response_message = '';

    switch ($button_clicked) {
      case 'node_import':
        if (!empty($selected_rows)) {
          $response_message = $this->t('Button 1 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);
        }
        else {
          $response_message = $this->t('No rows selected.');

        }
        // Update the form state with the response message.
        $form['table_select_panel1']['response']['#markup'] = $response_message;
        break;
      case
      'taxonomy_import':
        if (!empty($selected_rows)) {
          $response_message = $this->t('Button 2 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);
        }
        else {
          $response_message = $this->t('No rows selected.');
        }
        // Update the form state with the response message.
        $form['table_select_panel2']['response']['#markup'] = $response_message;
        break;
      case
      'paragraph_import':
        if (!empty($selected_rows)) {
          $response_message = $this->t('Button 3 clicked. Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);
        }
        else {
          $response_message = $this->t('No rows selected.');

        }
        // Update the form state with the response message.
        $form['table_select_panel3']['response']['#markup'] = $response_message;
        break;
      default:
        break;
    }
    // Clear the table select checkboxes after processing.
    $form_state->setRebuild();
  }


  /**
   * Builds a heading row with tooltip helper markup.
   */
  private function buildPanelHeading(string $title, string $description): string {
    $render_array = [
      '#type' => 'inline_template',
      '#template' => '<div class="bm-config-sync-panel__heading"><h2>{{ title }}</h2>{{ tooltip(description, "Info", options) }}</div>',
      '#context' => [
        'title' => $title,
        'description' => $description,
        'options' => [
          'theme' => 'dark',
          'position' => 'bottom',
          'edge' => TRUE,
          'tag' => 'button',
          'class' => 'bm-config-sync-panel__info',
          'attributes' => [
            'type' => 'button',
            'aria-label' => $this->t('Panel info: @title', ['@title' => $title]),
          ],
        ],
      ],
    ];

    return (string) \Drupal::service('renderer')->renderPlain($render_array);
  }


  /**
   * Prepares messages to display as a popup.
   *
   * @param AjaxResponse $response
   */
  protected
  function response_messages(AjaxResponse $response): void {
    $noMessages = TRUE;

    $message_markup = '<div id="status-messages">';
    $message_markup .= '<div role="contentinfo" aria-label="Status message" class="messages messages--status">
      <h2 class="visually-hidden">Status message</h2>
      <ul class="messages__list">';

    foreach (Drupal::messenger()->messagesByType('status') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-green">' . $message . '</li>';
    }
    foreach (Drupal::messenger()->messagesByType('warning') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-orange">' . $message . '</li>';
    }
    foreach (Drupal::messenger()->messagesByType('error') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-red">' . $message . '</li>';
    }

    $message_markup .= '</ul></div></div>';

    // Clear messages once processed.
    Drupal::messenger()->deleteByType('status');
    Drupal::messenger()->deleteByType('error');
    Drupal::messenger()->deleteByType('warning');

    // Only open the modal if there are messages to display.
    if (!$noMessages) {
      $dialog_options = ['width' => 700, 'resizable' => TRUE];
      $settings = ['sizeAdjustModal' => '#messages-modal'];
      $response->addCommand(new OpenModalDialogCommand('Messages', $message_markup, $dialog_options, $settings));
    }
  }

}
