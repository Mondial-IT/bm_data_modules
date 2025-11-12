<?php
/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:17
 */ /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Drupal\bm_config_sync\Form;


use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;



/**
 * Configure bm_debug settings for this site.
 */
class SettingsForm extends ConfigFormBase {
  protected function getEditableConfigNames() {
    // TODO: Implement getEditableConfigNames() method.
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bm_config_sync_settings';
  }



  /**
   * {@inheritdoc}
   * @noinspection PhpMultipleClassDeclarationsInspection
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    //dsm($debugInfo);
    $form['intro']=[
      '#type'=>'markup',
      '#markup'=>'<h1>Config Sync settings</h1><hr>
        <p></p>'
    ];


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }



}
