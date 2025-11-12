<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */


namespace bmDataModules\bm_import\test\src\unit;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use bmDataModules\bm_import\test\src\unit\BMImportParagraphHandlerTest;

class BMImportParagraphHandlerTestForm extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string
  {
    return 'bm_import_paragraph_handler_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    // Test data input fields.
    $form['test_data'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Test Data Input'),
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

    $form['run_tests'] = [
      '#type' => 'tableselect',
      '#header' => [
        'test_name' => $this->t('Test Name'),
        'last_run' => $this->t('Last Run'),
        'status' => $this->t('Status'),
      ],
      '#options' => [
        'testCreateParagraphType' => [
          'test_name' => $this->t('Create Paragraph Type'),
          'last_run' => '-',
          'status' => '-',
        ],
        'testCheckParagraphTypeExists' => [
          'test_name' => $this->t('Check Paragraph Type Exists'),
          'last_run' => '-',
          'status' => '-',
        ],
        'testAddParagraphToNodeFieldIfNotExistsSingle' => [
          'test_name' => $this->t('Add Paragraph To Node Field If Not Exists (Single)'),
          'last_run' => '-',
          'status' => '-',
        ],
        'testAddParagraphToNodeFieldIfNotExistsMultiple' => [
          'test_name' => $this->t('Add Paragraph To Node Field If Not Exists (Multiple)'),
          'last_run' => '-',
          'status' => '-',
        ],
        'testAddMultipleParagraphTypes' => [
          'test_name' => $this->t('Add Multiple Paragraph Types'),
          'last_run' => '-',
          'status' => '-',
        ],
        'testSetParagraphFieldValues' => [
          'test_name' => $this->t('Set Paragraph Field Values'),
          'last_run' => '-',
          'status' => '-',
        ],
        'testCreateNestedParagraph' => ['test_name' => $this->t('Create Nested Paragraph'),
          'last_run' => '-',
          'status' => '-',
        ],
      ],
      '#multiple' => TRUE,
      '#empty' => $this->t('No tests available.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run Selected Tests'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $selected_tests = array_filter($form_state->getValue('run_tests'));
    $handlerTest = new BMImportParagraphHandlerTest();

    foreach ($selected_tests as $test) {
      try {
        $handlerTest->setUp(); // Setup before each test
        $handlerTest->$test(); // Call the test method dynamically
        $status = $this->t('Success');
      } catch (Exception $e) {
        $status = $this->t('Failed: @message', ['@message' => $e->getMessage()]);
      }

      // Update the table row with the result (for demo purposes, updates are simulated).
      // Ideally, you would store these results in some persistent storage.
      $form['run_tests']['#options'][$test]['last_run'] = date('Y-m-d H:i:s');
      $form['run_tests']['#options'][$test]['status'] = $status;
    }

    \Drupal::messenger()->addMessage($this->t('Selected tests have been run. Please check the results.'));
  }
}
