<?php

namespace Drupal\kontainer\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'kontainer_cdn' formatter.
 *
 * @FieldFormatter(
 *   id = "kontainer_cdn",
 *   label = @Translation("Kontainer CDN"),
 *   field_types = {
 *     "kontainer_cdn"
 *   }
 * )
 */
class KontainerCdnFormatter extends FormatterBase {

  /**
   * Service "current_user".
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

  /**
   * Kontainer channel logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, AccountProxyInterface $currentUser, KontainerServiceInterface $kontainerService, LoggerInterface $logger) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->currentUser = $currentUser;
    $this->kontainerService = $kontainerService;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('current_user'),
      $container->get('kontainer_service'),
      $container->get('logger.channel.kontainer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'image_conversion' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['image_conversion'] = [
      '#title' => $this->t('Image conversion'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('image_conversion') ?? '',
      '#empty_option' => $this->t('None (original image)'),
      '#options' => $this->fetchImageConversions(),
      '#description' => $this->kontainerService->getCdnImageConversionsRenderLink(),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $imageConversions = $this->fetchImageConversions();
    $imageConversionSettings = $this->getSetting('image_conversion');
    if (isset($imageConversions[$imageConversionSettings])) {
      $summary[] = $this->t('Image conversion: @conversion', ['@conversion' => $imageConversions[$imageConversionSettings]]);
    }
    else {
      $summary[] = $this->t('Original image');
    }
    return array_merge($summary, parent::settingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      switch ($item->media_type) {
        case KontainerServiceInterface::KONTAINER_IMAGE_TYPE:
          if ($imageConversionName = $this->getSettings()['image_conversion']) {
            try {
              $uri = $this->kontainerService->generateCdnFormattedUrl($item->base_uri, $imageConversionName);
            }
            catch (\Exception $e) {
              $this->kontainerService->logException($e);
            }
          }
          // The templates have different options for the resize setting for the
          // image output, so width and height aren't always specified, hence
          // they are not added as attributes to the image template.
          $elements[$delta] = [
            '#theme' => 'image',
            '#uri' => $uri ?? $item->uri,
            '#alt' => !empty($item->kontainer_file_alt) ? $item->kontainer_file_alt : $this->t('kontainer_image'),
          ];
          if (!empty($item->kontainer_file_name)) {
            $elements[$delta]['#title'] = $item->kontainer_file_name;
          }
          break;

        case KontainerServiceInterface::KONTAINER_VIDEO_TYPE:
          $elements[$delta] = [
            '#theme' => 'file_video',
            '#attributes' => ['controls' => TRUE],
            '#files' => [
              [
                'source_attributes' => new Attribute(['src' => $item->uri]),
              ],
            ],
          ];
          break;

        default:
          $elements[$delta] = [
            '#type' => 'link',
            '#url' => Url::fromUri($item->uri),
            '#title' => $item->kontainer_file_name ?? $this->t('Kontainer item'),
          ];
      }
    }
    return $elements;
  }

  /**
   * Tries to fetch the Image Conversions, logs error on Exception.
   *
   * @return array
   *   Array of conversions. Both key and value are set to conversion name.
   */
  private function fetchImageConversions(): array {
    try {
      return $this->kontainerService->getCdnImageConversionsOptions(FALSE);
    }
    catch (\Exception $e) {
      $this->kontainerService->logException($e);
      return [];
    }
  }

}
