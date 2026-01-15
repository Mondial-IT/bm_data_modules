<?php
/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:17
 */ /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace Drupal\bm_config_sync\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;

/**
 * Settings form for bm_config_sync.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['bm_config_sync.settings'];
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
    $form['#attached']['library'][] = 'bm_config_sync/bm_config_sync.admin_styles';

    $intro_data = Settings::get('bm_config_sync_intro', $this->getDefaultIntroData());



    $sentences_markup = '';
    foreach ($intro_data['sentences'] ?? [] as $sentence) {
      $sentences_markup .= '<p>' . Html::escape($sentence) . '</p>';
    }

    $gallery_markup = '';
    foreach ($intro_data['topics'] ?? [] as $topic) {
      $topic_id = Html::getId($topic['id'] ?? 'topic');
      $placeholder = $topic['placeholder'] ?? '';
      $title = $topic['title'] ?? $topic['caption'] ?? '';
      $topic_label = $topic['topic'] ?? $topic['caption'] ?? '';
      $description = $topic['description'] ?? '';
      $link = $this->buildTopicLink($topic);
      $media_markup = $this->buildTopicMedia($topic, $title ?: $placeholder ?: $topic_id);

      $gallery_markup .= '<article class="bm-config-sync-settings-card" data-topic="' . Html::escape($topic_id) . '">';
      $gallery_markup .= '<div class="bm-config-sync-settings-card__media">' . $media_markup . '</div>';
      $gallery_markup .= '<div class="bm-config-sync-settings-card__body">';
      if ($title) {
        $gallery_markup .= '<h3 class="bm-config-sync-settings-card__title">' . Html::escape($title) . '</h3>';
      }
      if ($topic_label) {
        $gallery_markup .= '<p class="bm-config-sync-settings-card__topic">' . Html::escape($topic_label) . '</p>';
      }
      if ($description) {
        $gallery_markup .= '<p class="bm-config-sync-settings-card__description">' . Html::escape($description) . '</p>';
      }
      if ($link) {
        $gallery_markup .= '
            <div class="bm-config-sync-settings-card__actions">
                <a class="bm-config-sync-settings-card__button" href="' . Html::escape($link) . '">' . $this->t('Open @title', ['@title' => $title ?: $this->t('feature')]) . '</a>
            </div>';
      }
      $gallery_markup .= '</div>';
      $gallery_markup .= '</article>';
    }

    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['bm-config-sync-settings-intro'],
      ],
    ];
    $form['intro']['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => Html::escape($intro_data['title'] ?? 'Config Sync settings'),
    ];
    $form['intro']['introduction'] = [
      '#type' => 'details',
      '#title' => $this->t('Introduction'),
      '#open' => FALSE,
      '#attributes' => [
        'class' => ['bm-config-sync-settings-intro__sentences'],
      ],
    ];
    $form['intro']['introduction']['content'] = [
      '#type' => 'markup',
      '#markup' => '<div class="bm-config-sync-settings-intro__sentences-content">' . $sentences_markup . '</div>',
    ];
    $form['intro']['gallery'] = [
      '#type' => 'markup',
      '#markup' => '<div class="bm-config-sync-settings-intro__gallery">' . $gallery_markup . '</div>',
    ];

    $help_url = Url::fromUserInput('/admin/help/bm_config_sync')->toString();
    $form['help_topic'] = [
      '#type' => 'markup',
      '#markup' => '<div class="bm-config-sync-settings-help-link"><a class="bm-config-sync-settings-card__button" href="' . Html::escape($help_url) . '">' . $this->t('Go to help topic') . '</a></div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

  /**
   * Provides fallback intro content when settings.php lacks overrides.
   */
  private function getDefaultIntroData(): array {
    return [
      'title' => 'Understand Blue Marloc Config Sync',
      'sentences' => [
        'Drupal stores content models and site settings as configuration files.',
        'Entity relationship diagrams show how nodes, paragraphs, and taxonomies connect.',
        'Use these tools when you need to understand dependencies before deployments.',
        'Exports generated here become YAML that keeps DDEV, QA, and production aligned.',
        'UUID repair utilities fix identifier mismatches that block safe imports.',
      ],
      'topics' => [
        [
          'id' => 'combined-erd',
          'title' => 'Combined ERD builder',
          'topic' => 'Architecture overview',
          'placeholder' => 'Screenshot',
          'caption' => 'Screenshot',
          'description' => 'Generates a high-level diagram that maps how bundles reference one another so new contributors grasp the information architecture quickly.',
          'route' => 'bm_config_sync.bm_config_sync_combined_erd_form',
        ],
        [
          'id' => 'individual-erd',
          'title' => 'Per-bundle ERD explorer',
          'topic' => 'Bundle drilldown',
          'placeholder' => 'Screenshot',
          'caption' => 'Screenshot',
          'description' => 'Shows every field on a specific entity, what it stores, and whether it points to taxonomy, media, or nested paragraphs.',
          'route' => 'bm_config_sync.bm_config_sync_individual_erd_form',
        ],
        [
          'id' => 'import-export',
          'title' => 'Import & export dashboard',
          'topic' => 'Config operations',
          'placeholder' => 'Screenshot',
          'caption' => 'Screenshot',
          'description' => 'Provides a guided wizard for moving curated config items in and out of this environment when you need both operations together.',
          'route' => 'bm_config_sync.bm_config_sync_import_export_form',
        ],
        [
          'id' => 'export-form',
          'title' => 'Targeted export form',
          'topic' => 'Focused exports',
          'placeholder' => 'Screenshot',
          'caption' => 'Screenshot',
          'description' => 'Lets you choose specific entity bundles and related dependencies before writing YAML to the sync directory.',
          'route' => 'bm_config_sync.bm_config_sync_export_form',
        ],
        [
          'id' => 'import-form',
          'title' => 'Targeted import form',
          'topic' => 'Focused imports',
          'placeholder' => 'Screenshot',
          'caption' => 'Screenshot',
          'description' => 'Applies curated YAML back into the site so schema changes or content-model tweaks arrive cleanly.',
          'route' => 'bm_config_sync.bm_config_sync_import_form',
        ],
        [
          'id' => 'uuid-sync',
          'title' => 'UUID synchronization tools',
          'topic' => 'Repair UUIDs',
          'placeholder' => 'Screenshot',
          'caption' => 'Screenshot',
          'description' => 'Compares UUID values inside the private bm_config_sync storage with the live site so you can fix mismatches before importing YAML.',
          'route' => 'bm_config_sync.bm_config_sync_uuid_form',
        ],
        [
          'id' => 'batch-export',
          'title' => 'Batch exporter console',
          'topic' => 'Streamlined batches',
          'placeholder' => 'Screenshot',
          'caption' => 'Screenshot',
          'description' => 'Streams curated configuration exports in batches, preparing clean YAML sets for deployments or automated documentation.',
          'route' => 'bm_config_sync.startBMConfigSyncBatchExporter',
        ],
        [
          'id' => 'json-api',
          'title' => 'JSON API utilities',
          'topic' => 'API endpoints',
          'placeholder' => 'Screenshot',
          'caption' => 'Screenshot',
          'description' => 'Lists machine endpoints that expose bundle definitions, ERDs, and field paths so QA tooling or documentation can stay in sync.',
          'route' => 'bm_config_sync.bm_config_sync_jsonapi_endpoints',
        ],
      ],
    ];
  }

  /**
   * Builds the navigation link for a topic.
   */
  private function buildTopicLink(array $topic): ?string {
    try {
      if (!empty($topic['route'])) {
        return Url::fromRoute($topic['route'])->toString();
      }
      if (!empty($topic['path'])) {
        return Url::fromUserInput($topic['path'])->toString();
      }
      if (!empty($topic['url'])) {
        return Url::fromUri($topic['url'])->toString();
      }
    }
    catch (\Exception $e) {
      // Ignore and fall back to no link.
    }
    return NULL;
  }

  /**
   * Builds image markup for a topic card with a safe fallback placeholder.
   */
  private function buildTopicMedia(array $topic, string $title): string {
    $caption = $topic['caption'] ?? $title;
    $image = trim((string) ($topic['image'] ?? ''));
    $safe_caption = Html::escape($caption ?: $title ?: $this->t('Feature preview'));

    if ($image !== '') {
      $safe_image = Html::escape(UrlHelper::stripDangerousProtocols($image));
      return '<img src="' . $safe_image . '" alt="' . $safe_caption . '" loading="lazy" decoding="async" />';
    }

    $placeholder_text = Html::escape($topic['placeholder'] ?? $caption ?: $title ?: $this->t('Preview'));
    $gradient_id = Html::getId('bm-config-sync-media-' . ($topic['id'] ?? $title ?? 'topic'));

    return '
      <svg class="bm-config-sync-settings-card__media-placeholder" viewBox="0 0 320 180" role="img" aria-label="' . $safe_caption . '">
        <defs>
          <linearGradient id="' . $gradient_id . '" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop stop-color="#f3f4f6" offset="0%"></stop>
            <stop stop-color="#e5e7eb" offset="100%"></stop>
          </linearGradient>
        </defs>
        <rect width="320" height="180" fill="url(#' . $gradient_id . ')" rx="16"></rect>
        <text x="50%" y="50%" text-anchor="middle" fill="#4b5563" font-size="16" font-family="Inter, system-ui, -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, sans-serif" dominant-baseline="middle">' . $placeholder_text . '</text>
      </svg>';
  }

}
