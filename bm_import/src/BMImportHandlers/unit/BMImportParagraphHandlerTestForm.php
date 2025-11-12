<?php

namespace Drupal\bm_import\BMImportHandlers\unit;

use Drupal;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\Markup;
use Exception;

class BMImportParagraphHandlerTestForm extends FormBase {
  // title of a panel
  private string $title;
  // the function to call in the test, is appended with execute_ and undo_
  private string $callable;
  // id used in button name
  private string $short_id;
  // description on panel
  private string $description;

  public function getFormId(): string {
    return 'unit_test_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Add custom CSS styles for the form
    $form['custom_styles'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('<style>
        body{
            font-family:roboto, sans-serif;
        }
        #unit-test-form{
           display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
        }
        .panel .fieldset-legend {
          font-weight: bold;
          color: #333;
        }
        .form-actions {
          margin-top: 15px;
        }
        .form-item {
          margin-bottom: 10px;
        }
        .header-line {
          font-size: 0.85em;
          color: #888;
          margin-bottom: 10px;
        }
        .button-row {
          display: flex;
          justify-content: flex-start;
          gap: 10px;
          margin-top: 15px;
        }
        .button-row button {
          padding: 5px 10px;
        }
        .result{
            background:white;
            border:1px solid #ccc;
            padding:10px;
        }
      </style>'),
    ];

    // General input data for tests
    //
    $this->addGeneralTestDataFields($form, $form_state);

    // --------------------

    // panel title
    $this->title= 'test A: Check paragraph exists';
    // this is prepended with test__ and undo__ and executed on button click
    $this->callable = 'check_paragraphTypeExists';
    // unique identifier
    $this->short_id = 'A';
    //
    $this->description = 'This test checks if it can correctly detect paragraph type `calldetailrecord_paragraph` exists.';
    // boilerplate
    $this->panel_header(  $form);
    // your arguments to the callable function
    $this->panel_arguments__checkParagraphTypeExists($form[$this->short_id]['arguments'], $form_state);
    // boilerplate
    $this->panel_footer($form);

    // ------------------

    $this->title= 'test B: Check paragraph exists (when it not exists)';
    // this is prepended with test__ and undo__ and executed on button click
    $this->callable = 'check_paragraphTypeExists';
    $this->short_id = 'B';// appended to button name
    $this->description = 'This test checks if it can correctly detect paragraph type `calldetailrecord_paragraph_X` does not exists.';
    $this->panel_header($form);
    $this->panel_arguments__checkParagraphTypeNotExists( $form[$this->short_id]['arguments'], $form_state);
    $this->panel_footer( $form);

    $this->title= 'test C: Create Paragraph type';
    // this is prepended with test__ and undo__ and executed on button click
    $this->callable = 'createParagraphType';
    $this->short_id = 'C';// appended to button name
    $this->description = 'Test create paragraph type';
    $this->panel_header( $form);
    $this->panel_arguments__createParagraphType( $form[$this->short_id]['arguments'], $form_state);
    $this->panel_footer( $form);

    $this->title= 'test D: field exists in paragraph type';
    // this is prepended with test__ and undo__ and executed on button click
    $this->callable = 'check_fieldExistsOnParagraphType';
    $this->short_id = 'D';// appended to button name
    $this->description = 'Test the method "does the field exist in the paragraph type" <br >runs check_fieldExistsOnParagraphType($paragraphType, $fieldName)';
    $this->panel_header($form);
    $this->panel_arguments__check_fieldExistsOnParagraphType($form[$this->short_id]['arguments'], $form_state);
    $this->panel_footer( $form);




    $this->title= 'test E: find_firstParagraph_byType';
    // this is prepended with test__ and undo__ and executed on button click
    $this->callable = 'find_firstParagraph_byType';
    $this->short_id = 'E';// appended to button name
    $this->description = 'Test the method "panel_arguments__find_firstParagraph_byType" <br >'
                      .'runs panel_arguments__find_firstParagraph_byType($node, $nodeFieldName, $paragraphType)';
    $this->panel_header($form);
    $this->panel_arguments__find_firstParagraph_byType($form[$this->short_id]['arguments'], $form_state);
    $this->panel_footer( $form);


     $this->title= 'test F: Add paragraph to node field';
    $this->callable = 'addParagraphToNodeFieldIfNotExists';
    $this->short_id = 'F';// appended to button name
    $this->description = 'Test the method "Add paragraph to node field if not exists" <br >'
      .'calls addParagraphToNodeFieldIfNotExists(string $nid, string $nodeFieldName, string $paragraphType, bool $deleteExisting = FALSE)';
    $this->panel_header($form);
    $this->panel_arguments__addParagraphToNodeFieldIfNotExists($form[$this->short_id]['arguments'], $form_state);
    $this->panel_footer( $form);


    return $form;
  }

  private function addGeneralTestDataFields(array &$form, FormStateInterface $form_state): void {
    $form['test_data'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('General Test Data'),
      '#attributes' => ['class' => ['panel']],
    ];

    $form['test_data']['paragraph_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Paragraph Type'),
      '#default_value' => 'test_paragraph_type',
    ];
    $form['test_data']['node_type'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Node Type'),
      '#default_value' => 'test_content',
    ];

    $form['test_data']['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field Name'),
      '#default_value' => 'field_test_paragraph',
    ];
  }

  private function panel_arguments__checkParagraphTypeExists( array &$formArguments, FormStateInterface $form_state): void {
    $formArguments['paragraph_type_' . $this->short_id] = [
      '#type' => 'textfield',
      '#title' => $this->t('Paragraphtype to test'),
      '#default_value' => 'calldetailrecord_paragraph',
    ];
  }

  private function panel_arguments__checkParagraphTypeNotExists( array &$formArguments, FormStateInterface $form_state): void {
    $formArguments['paragraph_type_' . $this->short_id] = [
      '#type' => 'textfield',
      '#title' => $this->t('Paragraphtype to test'),
      '#default_value' => 'calldetailrecord_paragraph_X',
    ];
  }

  private function panel_arguments__createParagraphType( array &$formArguments, FormStateInterface $form_state): void {
    $short_id = $this->short_id;
    $formArguments['paragraph_type_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => $this->t('Paragraphtype'),
      '#default_value' => 'test_paragraph_type',
    ];
    $formArguments['label_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => 'Test paragraph',
    ];
    $formArguments['description_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => $this->t("Description"),
      '#default_value' => 'A paragraph used for testing.',
    ];
    $formArguments['translatable_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'Translatable',
      '#default_value' => 'True',
    ];
    $formArguments['icon_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => $this->t('Icon'),
      '#default_value' => 'icon.png',
    ];
  }

  private function panel_arguments__check_fieldExistsOnParagraphType(array &$formArguments, FormStateInterface $form_state): void {
    $short_id = $this->short_id;
    $formArguments['paragraphType_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'paragraphType',
      '#default_value' => 'test_paragraph_type',
    ];
    $formArguments['fieldName_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'fieldName',
      '#default_value' => 'test_paragraph_field',
    ];

  }

  private function panel_arguments__find_firstParagraph_byType(array &$formArguments, FormStateInterface $form_state): void {
    $short_id = $this->short_id;
    $formArguments['node_id_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'node ID',
      '#default_value' => '70',
    ];
    $formArguments['fieldName_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'node fieldName',
      '#default_value' => 'field_listing_datalink_paragraph',
    ];
    $formArguments['paragraphType_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'paragraphType',
      '#default_value' => 'datalink_paragraph',
    ];


  }

  private function panel_arguments__addParagraphToNodeFieldIfNotExists(array &$formArguments, FormStateInterface $form_state): void {
    $short_id = $this->short_id;
    // (string $nid, string $nodeFieldName, string $paragraphType, bool $deleteExisting = FALSE)
    $formArguments['node_id_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'node ID',
      '#default_value' => '70',
    ];
    $formArguments['fieldName_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'node fieldName',
      '#default_value' => 'field_listing_datalink_paragraph',
    ];
    $formArguments['paragraphType_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'paragraphType',
      '#default_value' => 'datalink_paragraph',
    ];
    $formArguments['deleteExisting_' . $short_id] = [
      '#type' => 'textfield',
      '#title' => 'deleteExisting',
      '#default_value' => 'TRUE',
    ];

  }

  // Boilerplate

  private function panel_header(&$form): void {
    // Retrieve cached result if available
    $cached_result = $this->getCachedTestResult($this->callable.'__'.$this->short_id);
    if ($cached_result) {
      $last_run = date('Y-m-d H:i:s', $cached_result['timestamp']);
      $result_status = $cached_result['result'];
    }
    else {
      $last_run = $this->t('Never');
      $result_status = $this->t('No result');
    }
    $execute_callable = 'test__' . $this->callable;
    $undo_callable = 'undo__' . $this->callable;

    $form[$this->short_id]=[
      '#type' => 'fieldset',
      '#title' => $this->title,
      '#attributes' => [
        'class' => ['panel'],
        ],
      '#callable'=>$this->callable,
      '#execute_callable' => $execute_callable,
      '#undo_callable' => $undo_callable,
      '#short_id' => $this->short_id
      ];
    $form[$this->short_id]['header'] = [
          'description' => [
            '#type' => 'markup',
            '#markup' => $this->t($this->description),
          ],
          'last_run' => [
            '#type' => 'markup',
            '#markup' => '<div class="header-line">' . $this->t('Test ID: @id | Last Run: @time | Result: @result', [
                '@id' => $this->callable,
                '@time' => $last_run,
                '@result' => $result_status,
              ]) . '</div>',
          ],
    ];
    $form[$this->short_id]['arguments']=[];
  }

  private function panel_footer(&$form): void {
    $short_id= $this->short_id;
    $callable= $form[$short_id]['#callable'];
    $wrapper_id = str_replace('_', '-', $callable);
    $result_wrapper_id='unit-test-result-'.$short_id;
    $execute_callable = $form[$short_id]['#execute_callable'];
    $undo_callable = $form[$short_id]['#undo_callable'];
    $arguments = [];
    foreach ($form[$short_id]['arguments'] as $key => $element) {
      $arguments[$key] = NULL;
    }
    $form[$short_id]['result'] = [
        '#type' => 'markup',
        '#markup' => '<div class="result" id="'. $result_wrapper_id . '"></div>',
      ];
    $form[$short_id]['button_row'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['button-row'], 'id' => 'execute-' . $wrapper_id],
        // Unique ID for the execute button],
        'execute' => [
          '#type' => 'button',
          '#value' => 'Execute ' . $short_id,
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => $result_wrapper_id,
          ],
          '#callable' => $execute_callable,
          '#arguments' => $arguments,
          '#test_id' => $callable,
          '#short_id' => $short_id,
        ],
        'undo' => [
          '#type' => 'button',
          '#value' => 'Undo ' . $short_id,
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => $result_wrapper_id,
          ],
          '#callable' => $undo_callable,
          '#arguments' => $arguments,
          '#test_id' => $callable,
          '#short_id' => $short_id,
        ],
      ];
  }

  private function getCachedTestResult($id) {
    $cache = Drupal::cache()->get('bm_import_test_result_' . $id);
    return $cache ? $cache->data : NULL;
  }

  private function cacheTestResult($id, $result): void {
    Drupal::cache()->set(
      'bm_import_test_result_' . $id,
      [
        'timestamp' => time(),
        'result' => $result,
      ],
      Cache::PERMANENT
    );
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $triggering_element = $form_state->getTriggeringElement();
    $callable = $triggering_element['#callable'];
    // retrieve these elements from the form input
    $argumentList = $triggering_element['#arguments'];
    $test_id = $triggering_element['#test_id'];
    $short_id = $triggering_element['#short_id'];
    // html id to display results
    $result_wrapper_id= 'unit-test-result-'.$short_id;
    $response = new AjaxResponse();

    // Prepare arguments by looking up current values in form_state
    $arguments = [];
    foreach ($argumentList as $argument => $default) {
      if ($default) {
        $value = $form_state->getValue($argument, $default);
      }
      else {
        $value = $form_state->getValue($argument, 'default value');
      }
      if (!$value) {
        Drupal::messenger()->addStatus($this->t('Check ' . $callable . ' value for ' . $argument));
      }
      $arguments[] = $value;
    }

    try {
      $html = '';
      $result = [FALSE, 'Error'];
      $handlerTest = new BMImportParagraphHandlerTest();
      //dsm($arguments, 'call_user_func_array([$handlerTest,' . $callable . '], arguments:');
      try {
        // result is array [true|false,"message"]
        $result = call_user_func_array([$handlerTest, $callable], $arguments);

      } catch (Exception $e) {
        // Update the panel with the JSON result
        $html .= '<p style="background-color:pink">' . $e->getMessage() . '</p>';
        Drupal::messenger()->addError($e->getMessage());
      }
      if (isset($result[0])) {
        dsm($result, 'result');
        $html .= '<p style="background-color:#bef8be">The test returned:  ' . $result[1] . '</p>';
        Drupal::messenger()->addMessage("The test returned:  " . $result[1]);

        $status = 'Success';
        Drupal::messenger()->addStatus($this->t('Test executed successfully.'));
      }
      else {
        $status = "Fail";
        $html .= '<p style="background-color:pink">The test returned:  ' . $result[1] . '</p>';
        Drupal::messenger()->addError($this->t('Test failed.'));
      }

      $this->cacheTestResult($test_id, $status);

    } catch (Exception $e) {
      $status = 'Failed';
      $this->cacheTestResult($test_id, $status);

      $html .= '<p style="background-color:pink">An error occurred during test execution: ' . $e->getMessage() . '</p>';
      Drupal::messenger()
        ->addError($this->t('An error occurred during test execution: @message', ['@message' => $e->getMessage()]));
    }

    $response->addCommand(new HtmlCommand('#' . $result_wrapper_id, $html));

    $this->response_messages($response);
    return $response;
  }

  private function response_messages($response): void {

    //bm_d(self::BM_D_KEY, __function__, 'trace', 'screen');
    $noMessages = TRUE;
    //    $messages = Drupal::messenger()->messagesByType('status');
    //    $messages = array_merge($messages, Drupal::messenger()->messagesByType('warning'));
    //    $messages = array_merge($messages, Drupal::messenger()->messagesByType('error'));

    $message_markup = '<div id="status-messages">';


    $message_markup .= '<div role = "contentinfo" aria-label = "Status message" class="messages messages--status">
      <h2 class="visually-hidden">Status message</h2>
      <ul class="messages__list" >';
    foreach (Drupal::messenger()->messagesByType('status') as $message) {
      //if(str_contains($message, 'Sfdump'))continue;
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-green" >' . $message . '</li>';
    }
    foreach (Drupal::messenger()->messagesByType('warning') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-orange" >' . $message . '</li>';
    }
    foreach (Drupal::messenger()->messagesByType('error') as $message) {
      $noMessages = FALSE;
      $message_markup .= '<li class="messages__item color-red" >' . $message . '</li>';
    }
    $message_markup .= '</ul></div>';

    $message_markup .= '</div>';
    Drupal::messenger()->deleteByType('status');
    Drupal::messenger()->deleteByType('error');
    Drupal::messenger()->deleteByType('warning');
    // ALTERNATIVE TO POPUP
    // send them and places them under table
    // $response->addCommand(new ReplaceCommand('#status-messages', $message_markup));

    // pops up a modal with the messages
    // The selector of the dialog.
    $selector = '#messages-modal';// js sees this in settings of behaviour call
    // The title of the dialog.
    $title = 'Messages';
    // The content that will be placed in the dialog, either a render array or an HTML string.
    $content = $message_markup;
    // (optional) Array of options to be passed to the dialog implementation. Any jQuery UI option can be used. See http://api.jqueryui.com/dialog.
    $dialog_options = [
      //'minHeight' => '700px',
      'width' => 700,
      'resizable' => TRUE,
    ];
    // (optional) Array of custom settings that will be passed to the Drupal behaviours on the content of the dialog.
    // When left empty, the settings will be populated automatically from the current request. See https://www.drupal.org/docs/8/api/javascript-api/javascript-api-overview.
    $settings = ['sizeAdjustModal' => $selector];
    if (!$noMessages) {
      $response->addCommand(new OpenModalDialogCommand($title, $content, $dialog_options, $settings));
    }
    //    $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand(null, 'throbber', [true,$message,true]));
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

}
