<?php

namespace bmDataModules\bm_import\src\BMImport;

use bmDataModules\bm_import\src\BMImport\BMImport;
use Drupal;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormStateInterface;
use Exception;

class BMImportBatch {

  protected BMImport $bmImport;
  private string $executeMethod;

  public function __construct() {
    $this->bmImport = new BMImport();
  }

  public function setMyBatch($rowNums, string $executeMethod): void {
    $batch_builder = new BatchBuilder();

    $batch_builder->setTitle(t('Processing your data'))
      ->setInitMessage(t('Initializing batch processing...'))
      ->setProgressMessage(t('Processed @current out of @total.'))
      ->setErrorMessage(t('An error occurred during batch processing.'));

    //$this->state = $this->bmImport->restoreState();
    // csvContent['options','header']
    // file_path
    // canvassTid
    // languageId

    // Define how many rows to process at once.
    $chunkSize = 2;
    $chunks = array_chunk($rowNums, $chunkSize);

    foreach ($chunks as $chunk) {
      // Add each chunk as a batch operation.
      $batch_builder->addOperation([$this, 'processChunk'], [$chunk]);
    }

    $batch_builder->setFinishCallback([$this, 'finishedCallback']);

    batch_set($batch_builder->toArray());

    Drupal::messenger()->addMessage('<div class="bgcolor-black fgcolor-yellow">'.__LINE__.' BMImportBatch initialized with '.count($rowNums).' rows todo</div>');
  }

  public function processChunk($chunkOfRowNums, &$context): void {
    Drupal::messenger()->addMessage('<div class="bgcolor-black fgcolor-yellow">'.__LINE__.' BMImportBatch processChunk '.json_encode($chunkOfRowNums).' rows todo</div>');

    try {
      $input=[];

      if (method_exists($this, $this->executeMethod)) {
        Drupal::messenger()->addMessage('<div class="bgcolor-black fgcolor-yellow">'.__LINE__.' BMImportBatch processChunk method exists</div>');

        //$this->{$this->executeMethod}($chunkOfRowNums, $input);  // Dynamically call the method
      } else {
        Drupal::messenger()->addMessage('<div class="bgcolor-black fgcolor-yellow">'.__LINE__.' BMImportBatch processChunk method not found</div>');
        throw new Exception('Invalid method: ' . $this->executeMethod);
      }

      // Process each row in the chunk.
      //$this->bmImport->importRows($chunkOfRowNums, $input);
    }catch(Exception $e){
      Drupal::messenger()->addMessage('<div class="bgcolor-black fgcolor-yellow">'.__LINE__.' BMImportBatch Exception '.$e->getMessage().'</div>');

    }
    // Update the batch progress.
    $context['results'][] = 'Processed ' . count($chunkOfRowNums) . ' rows.';
    $context['message'] = end($context['results']);
  }

  public function finishedCallback($success, $results, $operations): void {
    if ($success) {
      Drupal::messenger()->addMessage('All chunks processed successfully.');
    }
    else {
      // Handle any errors here.
      Drupal::messenger()->addMessage('An error occurred during processing.');
    }
  }



}
