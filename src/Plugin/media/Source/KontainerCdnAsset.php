<?php

namespace Drupal\kontainer\Plugin\media\Source;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Drupal\media\MediaInterface;
use Drupal\media\MediaSourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Media source for CDN Kontainer DAM assets.
 *
 * @MediaSource(
 *   id = "kontainer_cdn",
 *   label = @Translation("Kontainer CDN media"),
 *   description = @Translation("CDN assets from Kontainer DAM"),
 *   allowed_field_types = {"kontainer_cdn"},
 *   default_thumbnail_filename = "generic.png",
 *   thumbnail_alt_metadata_attribute = "thumbnail_alt_value"
 * )
 */
class KontainerCdnAsset extends MediaSourceBase implements KontainerMediaSourceInterface {

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, FieldTypePluginManagerInterface $field_type_manager, ConfigFactoryInterface $config_factory, KontainerServiceInterface $kontainerService) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager, $field_type_manager, $config_factory);
    $this->kontainerService = $kontainerService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('kontainer_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(MediaInterface $media, $attribute_name) {
    switch ($attribute_name) {
      case 'thumbnail_uri':
        try {
          $cdnField = $media->get($this->configuration['source_field']);
          $cdnValues = $cdnField->first()->getValue();
          if (isset($cdnValues['media_type']) && $cdnValues['media_type'] === KontainerServiceInterface::KONTAINER_IMAGE_TYPE && isset($cdnValues['uri'])) {
            return $this->kontainerService->createFile($cdnValues['uri'], TRUE);
          }
          return parent::getMetadata($media, 'thumbnail_uri');
        }
        catch (\Exception $e) {
          $this->kontainerService->logException($e);
          return parent::getMetadata($media, 'thumbnail_uri');
        }
      default:
        return parent::getMetadata($media, $attribute_name);
    }
  }

}
