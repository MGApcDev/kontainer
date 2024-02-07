<?php

namespace Drupal\kontainer\Plugin\Field\FieldFormatter;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin for Kontainer responsive CDN formatter.
 *
 * @FieldFormatter(
 *   id = "kontainer_responsive_cdn",
 *   label = @Translation("Kontainer Responsive CDN"),
 *   field_types = {
 *     "kontainer_cdn"
 *   }
 * )
 */
class KontainerResponsiveCdnFormatter extends FormatterBase {

  /**
   * The responsive image style entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $responsiveImageStyleStorage;

  /**
   * The image style entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $imageStyleStorage;

  /**
   * The image conversion entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $imageConversionStorage;

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

  /**
   * Service "current_user".
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Service "link_generator".
   *
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
   */
  protected LinkGeneratorInterface $linkGenerator;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entityTypeManager, KontainerServiceInterface $kontainerService, LinkGeneratorInterface $linkGenerator, AccountProxyInterface $currentUser) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->responsiveImageStyleStorage = $entityTypeManager->getStorage('responsive_image_style');
    $this->imageStyleStorage = $entityTypeManager->getStorage('image_style');
    $this->imageConversionStorage = $entityTypeManager->getStorage('cdn_image_conversion');
    $this->kontainerService = $kontainerService;
    $this->linkGenerator = $linkGenerator;
    $this->currentUser = $currentUser;
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
      $container->get('entity_type.manager'),
      $container->get('kontainer_service'),
      $container->get('link_generator'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'responsive_image_style' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $responsiveImageOptions = [];
    $responsiveImageStyles = $this->responsiveImageStyleStorage->loadMultiple();
    uasort($responsiveImageStyles, '\Drupal\responsive_image\Entity\ResponsiveImageStyle::sort');
    if (!empty($responsiveImageStyles)) {
      foreach ($responsiveImageStyles as $machineName => $responsiveImageStyle) {
        if ($responsiveImageStyle->hasImageStyleMappings()) {
          $responsiveImageOptions[$machineName] = $responsiveImageStyle->label();
        }
      }
    }
    $elements['responsive_image_style'] = [
      '#title' => $this->t('Responsive image style'),
      '#type' => 'select',
      '#default_value' => $this->getSetting('responsive_image_style') ?: NULL,
      '#required' => TRUE,
      '#options' => $responsiveImageOptions,
      '#description' => [
        '#markup' => $this->linkGenerator->generate($this->t('Configure Responsive Image Styles'), new Url('entity.responsive_image_style.collection')),
        '#access' => $this->currentUser->hasPermission('administer responsive image styles'),
      ],
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $responsiveImageStyle = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    if ($responsiveImageStyle) {
      $summary[] = $this->t('Responsive image style: @responsive_image_style', ['@responsive_image_style' => $responsiveImageStyle->label()]);
    }
    else {
      $summary[] = $this->t('Select a responsive image style.');
    }
    return array_merge($summary, parent::settingsSummary());
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    // Collect cache tags to be added for each item in the field.
    $responsiveImageStyle = $this->responsiveImageStyleStorage->load($this->getSetting('responsive_image_style'));
    $imageStylesToLoad = [];
    $cacheTags = [];
    if ($responsiveImageStyle) {
      $cacheTags = Cache::mergeTags($cacheTags, $responsiveImageStyle->getCacheTags());
      $imageStylesToLoad = $responsiveImageStyle->getImageStyleIds();
      $fallbackImageStyle = $responsiveImageStyle->getFallbackImageStyle();
      if (!in_array($fallbackImageStyle, $imageStylesToLoad)) {
        $imageStylesToLoad[] = $fallbackImageStyle;
      }
      $imageStyles = $this->imageStyleStorage->loadMultiple($imageStylesToLoad);
      foreach ($imageStyles as $imageStyle) {
        if ($imageStyle instanceof ImageStyleInterface) {
          $cacheTags = Cache::mergeTags($cacheTags, $imageStyle->getCacheTags());
        }
      }
    }
    foreach ($items as $delta => $item) {
      switch ($item->media_type) {
        case KontainerServiceInterface::KONTAINER_IMAGE_TYPE:
          try {
            /** @var \Drupal\kontainer\CdnImageConversionInterface | NULL $defaultCDNTemplate */
            $defaultCdnTemplate = $this->imageConversionStorage->load($this->kontainerService->getDefaultCdnConversionTemplateId());
            if (!$responsiveImageStyle || !$defaultCdnTemplate) {
              $imageConversionName = $this->kontainerService->getDefaultCdnConversionTemplateId();
              if (!$imageConversionName) {
                $uri = $item->uri;
              }
              else {
                $uri = $this->kontainerService->generateCdnFormattedUrl($item->base_uri, $imageConversionName);
              }
              $elements[$delta] = [
                '#theme' => 'image',
                '#uri' => $uri ?? $item->uri,
                '#alt' => !empty($item->kontainer_file_alt) ? $item->kontainer_file_alt : $this->t('kontainer_image'),
              ];
              if (!empty($item->kontainer_file_name)) {
                $elements[$delta]['#title'] = $item->kontainer_file_name;
              }
              break;
            }
            $formattedItems = [];
            foreach ($this->getAssociatedImageConversionNames($imageStylesToLoad, $defaultCdnTemplate) as $imageStyleName => $imageConversion) {
              $cacheTags = Cache::mergeTags($cacheTags, $imageConversion->getCachetags());
              $url = $this->kontainerService->generateCdnFormattedUrl($item->base_uri, $imageConversion->id());
              $width = $imageConversion->get('dimensions');
              $extension = $imageConversion->get('format');
              $formattedItems[$imageStyleName] = [
                'url' => $url,
                'width' => $width ?? '',
                'extension' => $extension ?? '',
              ];
            }
          }
          catch (\Exception $e) {
            $this->kontainerService->logException($e);
          }
          $elements[$delta] = [
            '#theme' => 'responsive_kontainer_cdn_image',
            '#items' => $formattedItems,
            '#alt' => !empty($item->kontainer_file_alt) ? $item->kontainer_file_alt : $this->t('kontainer_image'),
            '#responsive_image_style_id' => $responsiveImageStyle ? $responsiveImageStyle->id() : '',
            '#cache' => [
              'tags' => $cacheTags,
            ],
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
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $styleId = $this->getSetting('responsive_image_style');
    /** @var \Drupal\responsive_image\ResponsiveImageStyleInterface $style */
    if ($styleId && $style = $this->responsiveImageStyleStorage->load($styleId)) {
      $dependencies[$style->getConfigDependencyKey()][] = $style->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * Fetches associated Kontainer Image Conversions for each Drupal Image type.
   *
   * @param array $imageStyles
   *   Array of Image style ids.
   * @param \Drupal\Core\Entity\EntityInterface $defaultCdnTemplate
   *   Fallback Cdn conversion template.
   *
   * @return array
   *   Array of CdnImageConversions for linked image styles or empty arrays.
   */
  private function getAssociatedImageConversionNames(array $imageStyles, EntityInterface $defaultCdnTemplate): array {
    $output = [];
    foreach ($imageStyles as $imageStyle) {
      $results = $this->imageConversionStorage->loadByProperties(['image_style' => $imageStyle]);
      if (!empty($results)) {
        $output[$imageStyle] = $results[array_key_first($results)];
      }
      else {
        $output[$imageStyle] = $defaultCdnTemplate;
      }
    }
    return $output;
  }

}
