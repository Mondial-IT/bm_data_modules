<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-12-16 at 11:20:36
 */

namespace Drupal\bm_import\Form\Panel;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

/**
 * Preflight checks panel form.
 *
 * Notes on upgrade:
 * - Converted namespace to PSR-4 compatible Drupal\bm_import\Form\Panel
 * - Uses dependency injection (messenger and bm_import.manager service)
 * - Avoids direct \Drupal::messenger() calls
 * - Keeps existing helper function calls (bm_d, bm_main_panel_title_and_help, etc.)
 *   assuming they remain available globally in the site.
 */
class Panel_preflight_checks extends FormBase {

  /**
   * The bm_import manager service (the service that exposes getInstance()).
   *
   * Type is left as mixed because the module provides a custom manager.
   * Consider providing an interface and typing this property accordingly.
   *
   * @var mixed
   */
  private $bmImportManager;

  /**
   * The BMImport instance returned from the manager's getInstance().
   *
   * @var mixed
   */
  private $BMImport;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  private MessengerInterface $messenger;

  /**
   * Preflight status flag.
   *
   * @var bool
   */
  private bool $preflight_check_ok = FALSE;

  /**
   * Constructor.
   *
   * @param mixed $bm_import_manager
   *   The bm_import manager service (service id: bm_import.manager).
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger service.
   */
  public function __construct($bm_import_manager, MessengerInterface $messenger) {
    $this->bmImportManager = $bm_import_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Keep the manager service injected so we don't call \Drupal::service() in build.
    return new static(
      $container->get('bm_import.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'panel_preflight_checks';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Debug helper (keeps existing project-specific debug usage).
    if (function_exists('bm_d') && function_exists('instantiateColor')) {
      bm_d('', instantiateColor('instantiated Panel_preflight_checks'));
    }

    try {
      // The manager service previously returned an object via getInstance().
      // Preserve that call to remain compatible with existing manager implementation.
      if (is_object($this->bmImportManager) && method_exists($this->bmImportManager, 'getInstance')) {
        $this->BMImport = $this->bmImportManager->getInstance();
      }
      else {
        // If the service directly is the instance, use it.
        $this->BMImport = $this->bmImportManager;
      }
    }
    catch (Exception $e) {
      // Use the injected messenger service instead of \Drupal::messenger().
      $this->messenger->addError($e->getMessage());
    }

    // Build the panel content.
    $this->form_panel_preflight_checks($form);

    return $form;
  }

  /**
   * Build the preflight checks panel content.
   *
   * @param array $form
   *   Form render array passed by reference (consistent with original code).
   *
   * @throws \Exception
   */
  private function form_panel_preflight_checks(array &$form): void {
    if (function_exists('bm_d') && function_exists('traceColor')) {
      bm_d('', traceColor(' trace: ' . __FUNCTION__ . ' ' . time()));
    }

    // If BMImport instance could not be obtained, fail early.
    if (!isset($this->BMImport)) {
      // Nothing to display; return silently as the original did.
      return;
    }

    $filePath = NULL;
    if (is_object($this->BMImport) && method_exists($this->BMImport, 'get_filePath')) {
      $filePath = $this->BMImport->get_filePath();
    }

    if (!isset($filePath)) {
      return;
    }

    $this->preflight_check_ok = FALSE;

    // Panel title and help text.
    $title = 'Pre-flight panel';
    $helpText = 'These are the entities and fields which should be present on this system. When one is not green, the import can not start.';

    // Build wrapper markup; this preserves existing helper function usage.
    $prefix = '';
    if (function_exists('bm_main_panel_title_and_help')) {
      $prefix = bm_main_panel_title_and_help($title, $helpText);
    }
    $form['panel_preflight-wrapper'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="preflight-panel-wrapper" class="panel">' . $prefix .
        'This panel list the fields and entities that should be present on this system. When one is not green, the import can not start.',
      '#suffix' => '</div>',
    ];

    // Get the elements from the BMImport preflight helper.
    $elements = [];
    if (is_object($this->BMImport) && method_exists($this->BMImport, 'get_preflight_panel_form_content__todo_before_import')) {
      $elements = $this->BMImport->get_preflight_panel_form_content__todo_before_import();
    }

    // If elements report ready === TRUE, show success details.
    if (isset($elements['ready']) && $elements['ready'] === TRUE) {
      $form['panel_preflight-wrapper']['panel_preflight']['details'] = [
        '#type' => 'markup',
        '#markup' => '<details><summary class="bgcolor-lightgreen">The Preflight is OK</summary>',
        '#suffix' => '</details></div>',
      ];
      // Safely attach returned form fragment if present.
      if (isset($elements['form'])) {
        $form['panel_preflight-wrapper']['panel_preflight']['details']['data'] = $elements['form'];
      }
      $this->preflight_check_ok = TRUE;
    }
    else {
      $form['panel_preflight-wrapper']['panel_preflight']['todo'] = [
        '#type' => 'markup',
        '#markup' => '<div class="panel-preflight panel">' . $prefix,
        '#suffix' => '</div>',
      ];
      // Attach missing fields if present in the returned structure.
      if (isset($elements['form']['missing_fields'])) {
        $form['panel_preflight-wrapper']['panel_preflight']['todo']['missing_fields'] = $elements['form']['missing_fields'];
      }
      $message = ' elements form: things to do';
      if (function_exists('bm_d') && function_exists('violetColor')) {
        bm_d('', violetColor($message));
      }
      $this->preflight_check_ok = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild(TRUE);
  }

}