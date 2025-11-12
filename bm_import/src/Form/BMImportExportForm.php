<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace bmDataModules\bm_import\src\Form;

use Drupal;
use bmDataModules\bm_import\src\BMImportHandlers\BMImportNodeHandler;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;

/**
 * Class BMTemplateForm for a Drupal settings form with AJAX table select functionality.
 */
class BMImportExportForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bm_import_export_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bm_template.settings'];
  }

  /**
   * Builds the settings form.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    // Attach the bm_tippy library to the form.
    $form['#attached']['library'][] = 'ziston/tippyjs';
    $form['#attached']['library'][] = 'ziston/bm_tippyjs';

    // Inject CSS styles directly into the page.
    $form['custom_styles'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('<style>
        .ajax-response { font-weight: bold; color: green; }
        .table-select-panel { margin: 10px; border: 1px solid #ccc; padding: 15px; }
        .button-container button { margin-right: 5px; }
      </style>'),
    ];

    // Detect the button clicked and call the action function.
    if ($form_state->getTriggeringElement()) {
      $button_clicked = $form_state->getTriggeringElement()['#name'];
      $this->execute_button_action($button_clicked, $form, $form_state);
    }

    $form['intro'] = [
      '#markup' => '<h1>BM IMPORT - CONFIGURATION EXPORT</h1>
                <p>Exporting configurations.</p>',
    ];

    // Panel with table select and buttons.
    $form['panels']['#prefix']=Markup::create('<div style="display: grid;grid-template-columns: repeat(3, 1fr);gap: 10px;">');
    $this->panel_table_contenttypes($form['panels'], $form_state);
    $this->panel_table_taxonomies($form['panels'], $form_state);
    $this->panel_table_vocabularies($form['panels'], $form_state);
    $form['panels']['#suffix']='</div>';

    return $form;
  }

  private function panel_table_contenttypes(array &$form, FormStateInterface $form_state): void {
    $form['table_select_panel1']['#prefix'] = '<div class="panel"><h2>Export content type configurations</h2><hr>';
    $form['table_select_panel1']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];
    $nodeHandler= new BMImportNodeHandler();
    $entitiesList=[];
    try {
      $entitiesList = $nodeHandler->get_contentTypes();
    }catch (\Exception $exception){
      Drupal::messenger()->addError($exception->getMessage());
    }

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'label' => $this->t('Label'),
    ];
    $rows=[];
    forEach($entitiesList as $entityMName=>$entityLabel){
      $rows[] = ['id' => 1, 'name' => $entityMName, 'label' => $entityLabel];
    }

    $form['table_select_panel1']['table_select'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // Response div for displaying processed rows.
    $form['table_select_panel1']['response'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response', 'class' => ['ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel1']['button1'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 1'),
      '#name' => 'button1',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];

    $form['table_select_panel1']['button2'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 2'),
      '#name' => 'button2',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];

    $form['table_select_panel1']['button3'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 3'),
      '#name' => 'button3',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];


  }

  private function panel_table_taxonomies(array &$form, FormStateInterface $form_state): void {
    $form['table_select_panel2']['#prefix'] = '<div class="panel"><h2>PANEL TITLE</h2><hr>';
    $form['table_select_panel2']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
    ];

    $rows = [
      1 => ['id' => 1, 'name' => 'Item 1', 'description' => 'Description 1'],
      2 => ['id' => 2, 'name' => 'Item 2', 'description' => 'Description 2'],
      3 => ['id' => 3, 'name' => 'Item 3', 'description' => 'Description 3'],
    ];

    $form['table_select_panel2']['table_select'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // Response div for displaying processed rows.
    $form['table_select_panel2']['response'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response', 'class' => ['ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel2']['button1'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 1'),
      '#name' => 'button1',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];

    $form['table_select_panel2']['button2'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 2'),
      '#name' => 'button2',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];

    $form['table_select_panel2']['button3'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 3'),
      '#name' => 'button3',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];


  }

  private function panel_table_vocabularies(array &$form, FormStateInterface $form_state): void {
    $form['table_select_panel3']['#prefix'] = '<div class="panel"><h2>PANEL TITLE</h2><hr>';
    $form['table_select_panel3']['#suffix'] = '</div>';

    $panel = [
      '#attributes' => ['class' => ['table-select-panel']],
    ];

    $header = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'description' => $this->t('Description'),
    ];

    $rows = [
      1 => ['id' => 1, 'name' => 'Item 1', 'description' => 'Description 1'],
      2 => ['id' => 2, 'name' => 'Item 2', 'description' => 'Description 2'],
      3 => ['id' => 3, 'name' => 'Item 3', 'description' => 'Description 3'],
    ];

    $form['table_select_panel3']['table_select'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $rows,
      '#empty' => $this->t('No items available.'),
    ];

    // Response div for displaying processed rows.
    $form['table_select_panel3']['response'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'ajax-response', 'class' => ['ajax-response']],
    ];

    // Separate AJAX buttons.
    $form['table_select_panel3']['button1'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 1'),
      '#name' => 'button1',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];

    $form['table_select_panel3']['button2'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 2'),
      '#name' => 'button2',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];

    $form['table_select_panel3']['button3'] = [
      '#type' => 'button',
      '#value' => $this->t('Button 3'),
      '#name' => 'button3',
      '#ajax' => [
        'callback' => '::ajaxProcessSelectedRows',
        'wrapper' => 'ajax-response',
      ],
    ];


  }

  /**
   * AJAX callback to process selected rows and display the results.
   */
  public function ajaxProcessSelectedRows(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    // Process selected rows and display response message.
    $selected_rows = array_filter($form_state->getValue('table_select'));
    $response_message = empty($selected_rows)
      ? $this->t('No rows selected.')
      : $this->t('Processed rows: @rows', ['@rows' => implode(', ', $selected_rows)]);

    // Update the response div.
    $response->addCommand(new HtmlCommand('#ajax-response', $response_message));

    // Display any messages in a modal popup.
    $this->response_messages($response);

    return $response;
  }

  /**
   * Executes the button action based on the clicked button.
   */
  private function execute_button_action($button_clicked, &$form, FormStateInterface $form_state) {
    $selected_rows = array_filter($form_state->getValue('table_select'));

    // Process the selected rows and provide different messages for each button.
    $response_message = '';

    switch ($button_clicked) {
      case 'button1':
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
      'button2':
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
      'button3':
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
