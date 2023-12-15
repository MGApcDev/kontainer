<?php

namespace Drupal\kontainer\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\entity_usage\EntityUsageInterface;
use Drupal\kontainer\Plugin\media\Source\KontainerMediaSourceInterface;
use Drupal\media\MediaTypeInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;

/**
 * Kontainer service class.
 *
 * @package Drupal\kontainer\Service
 */
class KontainerService implements KontainerServiceInterface {

  /**
   * Service "module_handler".
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Service "config.factory".
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Service "file_system".
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * Service "entity_type.manager".
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Service "current_user".
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Service "string_translation".
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected TranslationInterface $stringTranslation;

  /**
   * Kontainer channel logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Service "entity_usage.usage".
   *
   * @var \Drupal\entity_usage\EntityUsageInterface
   */
  protected EntityUsageInterface $entityUsage;

  /**
   * Service "state".
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Service "entity_field.manager".
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Upload directory for files from Kontainer.
   *
   * @var string
   */
  protected string $uploadDirectory = 'public://Kontainer';

  public function __construct(ModuleHandlerInterface $moduleHandler, ConfigFactoryInterface $configFactory, FileSystemInterface $fileSystem, EntityTypeManagerInterface $entityTypeManager, AccountProxyInterface $currentUser, TranslationInterface $stringTranslation, LoggerInterface $logger, EntityUsageInterface $entityUsage, StateInterface $state, EntityFieldManagerInterface $entityFieldManager) {
    $this->moduleHandler = $moduleHandler;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->stringTranslation = $stringTranslation;
    $this->logger = $logger;
    $this->entityUsage = $entityUsage;
    $this->state = $state;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritDoc}
   */
  public function createEntities(array $assetData): array {
    $assetType = $assetData['type'] ?? NULL;
    $assetUrl = $assetData['thumbnailUrl'] ?? ($assetData['url'] ?? NULL);
    $assetExtension = $assetData['extension'] ?? NULL;
    $assetName = $assetData['fileName'] ?? NULL;
    $assetAlt = $assetData['alt'] ?? NULL;
    $assetKontainerFileId = $assetData['fileId'] ?? NULL;
    if (empty($assetUrl)) {
      throw new \Exception('No file url provided from Kontainer.');
    }
    if (empty(self::MEDIA_TYPES_MAPPING[$assetData['type']])) {
      throw new \Exception('Unsupported asset type.');
    }
    if (empty($assetKontainerFileId)) {
      throw new \Exception('No Kontainer file id provided from Kontainer.');
    }
    $this->checkAccess(self::MEDIA_TYPES_MAPPING[$assetData['type']]);
    $mediaType = $this->getMediaType($assetType);
    $sourceFieldName = $this->getMediaTypeSourceField($mediaType);
    $this->validateMediaTypeExtensions($assetType, $sourceFieldName, $assetExtension);
    $fileId = $this->createFile($assetUrl);
    return $this->createMedia($this->generateMediaValues($fileId, $assetKontainerFileId, $assetType, $assetName, $sourceFieldName, $assetAlt));
  }

  /**
   * {@inheritDoc}
   */
  public function generateCdnFormattedUrl(string $urlBaseName, string $imageConversion): ?string {
    if (!$urlBaseName || !$imageConversion) {
      throw new \Exception('CDN and/or image conversion id could not be fetched.');
    }
    $imageConversionLoaded = $this->entityTypeManager
      ->getStorage('cdn_image_conversion')
      ->load($imageConversion);
    if (!$imageConversionLoaded) {
      return NULL;
    }
    $templateId = $imageConversionLoaded->get('template_id');
    $format = $imageConversionLoaded->get('format');
    if (!$templateId || !$format) {
      throw new \Exception('Could not fetch format/template id from the image conversion.');
    }
    return $urlBaseName . '.' . $format . '?d=' . $templateId;
  }

  /**
   * {@inheritDoc}
   */
  public function getMediaTypesWithDependency(string $moduleName): array {
    if (!$this->moduleHandler->moduleExists($moduleName)) {
      $this->logger->error("Could not fetch media types with module dependencies. The module $moduleName is not enabled.");
      return [];
    }
    $mediaTypesWithDependency = [];
    $mediaTypeConfigurations = $this->configFactory->listAll('media.type');
    foreach ($mediaTypeConfigurations as $mediaTypeConfigName) {
      $config = $this->configFactory->get($mediaTypeConfigName);
      if (!empty($config)) {
        $dependencies = $config->get('dependencies');
        if (!empty($dependencies['module'][0]) && $dependencies['module'][0] === $moduleName) {
          $mediaTypesWithDependency[$config->get('id')] = $config->get('label');
        }
      }
      else {
        $this->logger->error("There are no media types with a dependency to $moduleName.");
      }
    }
    return $mediaTypesWithDependency;
  }

  /**
   * {@inheritDoc}
   */
  public function getCdnImageConversionsRenderLink(): array {
    $cdnImageStylesLink = Link::fromTextAndUrl(
      $this->stringTranslation->translate('Configure CDN Image Conversions'),
      Url::fromRoute('entity.cdn_image_conversion.collection')
    );
    return $cdnImageStylesLink->toRenderable() + [
      '#access' => $this->currentUser->hasPermission('administer cdn_image_conversion'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function getCdnImageConversionsOptions(bool $includeEmpty = TRUE): array {
    $conversions = $this->entityTypeManager
      ->getStorage('cdn_image_conversion')
      ->loadMultiple();
    $options = [];
    if ($includeEmpty && !empty($conversions)) {
      $options[''] = $this->stringTranslation->translate('- None -');
    }
    foreach ($conversions as $name => $conversion) {
      $options[$name] = $conversion->label();
    }

    if (empty($options)) {
      $options[''] = $this->stringTranslation->translate('No defined conversions');
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function logException(\Exception $e): void {
    $message = '%type: @message in %function (line %line of %file). Backtrace: @backtrace_string';
    $variables = Error::decodeException($e);
    $this->logger->error($message, $variables);
  }

  /**
   * {@inheritDoc}
   */
  public function getNestedTargets(ContentEntityInterface $entity, bool $cdn = FALSE): array {
    $entityTargets = $this->entityUsage->listTargets($entity, $entity->getRevisionId());
    $mediaTargets = [];
    if (!empty($entityTargets['paragraph'])) {
      foreach ($entityTargets['paragraph'] as $paragraphId => $paragraphUsageData) {
        /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
        $paragraph = $this->entityTypeManager
          ->getStorage('paragraph')
          ->load($paragraphId);
        if (!empty($paragraph)) {
          $mediaTargets += $this->getNestedTargets($paragraph, $cdn);
        }
      }
    }
    if ($cdn) {
      $this->getCdnTargets($entity, $mediaTargets);
    }
    else {
      if (!empty($entityTargets['media'])) {
        $mediaTargets += $entityTargets['media'];
      }
    }
    return $mediaTargets;
  }

  /**
   * {@inheritDoc}
   */
  public function getMasterSourceIds(array $directSources): array {
    $masterSourceIds = [];
    foreach ($directSources as $sourceType => $sourceIds) {
      if ($sourceType === 'node') {
        $masterSourceIds += array_keys($sourceIds);
      }
      if ($sourceType === 'paragraph') {
        $typeStorage = $this->entityTypeManager->getStorage($sourceType);
        foreach ($sourceIds as $sourceId => $sourceIdRevisions) {
          /** @var \Drupal\paragraphs\ParagraphInterface $sourceEntity */
          $sourceEntity = $typeStorage->load($sourceId);
          if ($sourceEntity) {
            $host = $this->getParagraphHost($sourceEntity);
            if ($host) {
              $masterSourceIds[] = $host->id();
            }
          }

        }
      }
    }
    return array_unique($masterSourceIds, SORT_NUMERIC);
  }

  /**
   * {@inheritDoc}
   */
  public function trackMediaStorageUsage(array $mediaTargets, NodeInterface $node): void {
    $this->deleteSourceUsage($node->id(), self::KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE);
    $kontainerUsage = $this->state->get('kontainer_usage') ?? [];
    if ($mediaIds = array_keys($mediaTargets)) {
      $nodeId = $node->id();
      /** @var \Drupal\media\MediaInterface $media */
      foreach ($this->entityTypeManager->getStorage('media')->loadMultiple($mediaIds) as $media) {
        if (!isset($media)) {
          continue;
        }
        $sourcePlugin = $media->getSource();
        if (!$sourcePlugin instanceof KontainerMediaSourceInterface) {
          continue;
        }
        $kontainerFileId = $media->get('field_kontainer_file_id')->getString();
        if (!$kontainerFileId) {
          continue;
        }
        $mediaId = $media->id();
        $kontainerUsage[$kontainerFileId][$nodeId][self::KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE][$mediaId] = [
          'nodeId' => $nodeId,
          'nodeUrl' => $node->toUrl('canonical', ['absolute' => TRUE])
            ->toString(),
          'nodeTitle' => $node->getTitle(),
          'mediaId' => $mediaId,
          'mediaUrl' => $media->toUrl('canonical', ['absolute' => TRUE])
            ->toString(),
          'kontainerFileId' => $kontainerFileId,
          'mediaSourceType' => self::KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE,
        ];
      }
    }
    $this->state->set('kontainer_usage', $kontainerUsage);
  }

  /**
   * {@inheritDoc}
   */
  public function trackCdnUsage(array $cdnTargets, NodeInterface $node): void {
    $this->deleteSourceUsage($node->id(), self::KONTAINER_MEDIA_SOURCE_CDN_URL);
    if (!empty($cdnTargets)) {
      $kontainerUsage = $this->state->get('kontainer_usage') ?? [];
      $nodeId = $node->id();
      foreach ($cdnTargets as $kontainerFileId) {
        $kontainerUsage[$kontainerFileId][$nodeId][self::KONTAINER_MEDIA_SOURCE_CDN_URL][] = [
          'nodeId' => $nodeId,
          'nodeUrl' => $node->toUrl('canonical', ['absolute' => TRUE])
            ->toString(),
          'nodeTitle' => $node->getTitle(),
          'kontainerFileId' => $kontainerFileId,
          'mediaSourceType' => self::KONTAINER_MEDIA_SOURCE_CDN_URL,
        ];
      }
      $this->state->set('kontainer_usage', $kontainerUsage);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function deleteSourceUsage(int $deletedSourceId, string $mediaSource): void {
    $kontainerUsage = $this->state->get('kontainer_usage');
    if (!empty($kontainerUsage)) {
      foreach ($kontainerUsage as $kontainerFileId => &$kontainerUsageData) {
        foreach ($kontainerUsageData as $sourceId => $targetsByMediaSource) {
          if ($deletedSourceId == $sourceId) {
            unset($kontainerUsageData[$sourceId][$mediaSource]);
          }
          if (empty($kontainerUsageData[$sourceId])) {
            unset($kontainerUsageData[$sourceId]);
          }
        }
        if (empty($kontainerUsageData)) {
          unset($kontainerUsage[$kontainerFileId]);
        }
      }
      $this->state->set('kontainer_usage', $kontainerUsage);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function deleteTargetUsage(array $sourceIds, int $targetId): void {
    if (!empty($sourceIds)) {
      $kontainerUsage = $this->state->get('kontainer_usage');
      if (!empty($kontainerUsage)) {
        foreach ($kontainerUsage as $kontainerFileId => &$kontainerUsageData) {
          foreach ($kontainerUsageData as $sourceId => &$targetsByMediaSource) {
            if (in_array($sourceId, $sourceIds) && isset($targetsByMediaSource[self::KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE][$targetId])) {
              unset($targetsByMediaSource[self::KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE][$targetId]);
            }
            if (empty($targetsByMediaSource[self::KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE])) {
              unset($targetsByMediaSource[self::KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE]);
            }
            if (empty($targetsByMediaSource)) {
              unset($kontainerUsageData[$sourceId]);
            }
          }
          if (empty($kontainerUsageData)) {
            unset($kontainerUsage[$kontainerFileId]);
          }
        }
        $this->state->set('kontainer_usage', $kontainerUsage);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function formatUsageData(array $kontainerUsage): array {
    $kontainerUsageFormatted = ['kontainerFiles' => []];
    foreach ($kontainerUsage as $kontainerFileId => $kontainerUsageData) {
      $usages = [];
      foreach ($kontainerUsageData as $targetsByMediaSource) {
        foreach ($targetsByMediaSource as $targetsData) {
          foreach ($targetsData as $targetData) {
            // NOTE: By CDN media source type, there will always be only one
            // entry per Kontainer file id per node (if there are multiple CDN
            // URL instances of the same Kontainer file on a node, usage data is
            // the same for all, hence only one is stored).
            $usages[] = $targetData;
          }
        }
      }
      $kontainerUsageFormatted['kontainerFiles'][] = [
        'kontainerFileId' => $kontainerFileId,
        'usages' => $usages,
      ];
    }
    return $kontainerUsageFormatted;
  }

  /**
   * Returns the top host of the paragraph.
   *
   * If NULL is returned, the paragraph is orphaned (at some level).
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity to check for its host entity in the last step of
   *   recursion.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The host node entity, NULL if orphaned (at any level), or not a node.
   */
  private function getParagraphHost(?ContentEntityInterface $entity): ?ContentEntityInterface {
    if ($entity instanceof ParagraphInterface) {
      return $this->getParagraphHost($entity->getParentEntity());
    }
    // This should be a node. If it's not a node, we could just call the
    // listSources function from EntityUsage service on it and start the
    // process of seeking source entities also for this entity. But if the site
    // has nested paragraphs in media, or some other entity types, that is just
    // a case of bad site building, and it should be avoided. If there is a
    // (custom) non-node entity, which references Kontainer media, that's out of
    // scope, currently only direct media references, media references in nested
    // paragraphs and what entity_usage provides for media is supported.
    return $entity instanceof NodeInterface ? $entity : NULL;
  }

  /**
   * Returns Kontainer CDN URL targets used on an entity.
   *
   * Checks if the entity has a field of type Kontainer CDN and adds the values
   * from the field(s) to the targets array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The (current) source entity.
   * @param array $mediaTargets
   *   Array with Kontainer targets.
   */
  private function getCdnTargets(ContentEntityInterface $entity, array &$mediaTargets) {
    $cdnFields = $this->entityFieldManager->getFieldMapByFieldType('kontainer_cdn');
    $entityFields = $entity->getFieldDefinitions();
    if (isset($cdnFields[$entity->getEntityTypeId()])) {
      foreach ($cdnFields[$entity->getEntityTypeId()] as $fieldMachineName => $field) {
        if (isset($entityFields[$fieldMachineName]) && !$entity->get($fieldMachineName)->isEmpty()) {
          foreach ($entity->get($fieldMachineName)->getValue() as $fieldEntries) {
            if (isset($fieldEntries['kontainer_file_id'])) {
              $mediaTargets[$fieldEntries['kontainer_file_id']] = $fieldEntries['kontainer_file_id'];
            }
          }
        }
      }
    }
  }

  /**
   * Maps the Kontainer media type to the Drupal media type and loads it.
   *
   * @param string $assetType
   *   Kontainer asset type.
   *
   * @return null|\Drupal\media\MediaTypeInterface
   *   Media type entity object or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  private function getMediaType(string $assetType): ?MediaTypeInterface {
    // Fallback to the file media type, if there is no asset type specified.
    if (empty($assetType)) {
      $mediaType = self::MEDIA_TYPES_MAPPING[self::KONTAINER_FILE_TYPE];
    }
    else {
      /** @var \Drupal\media\MediaTypeInterface $mediaType */
      $mediaType = $this->entityTypeManager
        ->getStorage('media_type')
        ->load(self::MEDIA_TYPES_MAPPING[$assetType]);
      if (empty($mediaType)) {
        throw new \Exception('Drupal media type could not be loaded.');
      }
    }
    return $mediaType;
  }

  /**
   * Fetches the source field machine name of a media type.
   *
   * @param \Drupal\media\MediaTypeInterface|null $mediaType
   *   Media type.
   *
   * @return string
   *   Media type source field machine name.
   *
   * @throws \Exception
   */
  private function getMediaTypeSourceField(?MediaTypeInterface $mediaType): string {
    $sourceFieldName = $mediaType->getSource()
      ->getConfiguration()['source_field'] ?? NULL;
    if (empty($sourceFieldName)) {
      throw new \Exception('Could not determine the source field of the media type.');
    }
    return $sourceFieldName;
  }

  /**
   * Validates if the file extensions is allowed in the media type source field.
   *
   * The "kontainer_file" media type has the "file_extensions" setting empty,
   * because it accepts all possible extensions. If the file extension is not
   * given, also an exception is thrown.
   *
   * @param string $assetType
   *   Kontainer asset type.
   * @param string $sourceFieldName
   *   Media type source field machine name.
   * @param string $assetExtension
   *   Kontainer asset file extension.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  private function validateMediaTypeExtensions(string $assetType, string $sourceFieldName, string $assetExtension): void {
    /** @var \Drupal\field\Entity\FieldConfig $mediaTypeFieldConfig */
    $mediaTypeFieldConfig = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('media.' . self::MEDIA_TYPES_MAPPING[$assetType] . '.' . $sourceFieldName);
    $mediaTypeExtensions = $mediaTypeFieldConfig->getSetting('file_extensions');
    if (empty($assetExtension)) {
      throw new \Exception('No file extension provided from Kontainer.');
    }
    if (!empty($mediaTypeExtensions) && strpos($mediaTypeExtensions, $assetExtension) === FALSE) {
      throw new \Exception('The file extension is incompatible with the corresponding media type.');
    }
  }

  /**
   * Creates a Drupal file entity from the downloaded Kontainer file.
   *
   * @param string $assetUrl
   *   Kontainer asset url.
   *
   * @return int
   *   The id of the created file.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  private function createFile(string $assetUrl): int {
    if ($this->fileSystem->prepareDirectory($this->uploadDirectory, FileSystemInterface::CREATE_DIRECTORY)) {
      /** @var \Drupal\file\FileInterface $file */
      $file = system_retrieve_file($assetUrl, $this->uploadDirectory, TRUE);
      if (!$file) {
        throw new \Exception('Could not create the Drupal file entity.');
      }
      return $file->id();
    }
    else {
      throw new \Exception('Could not create the file directory.');
    }
  }

  /**
   * Generates the media type field values.
   *
   * @param int $fileId
   *   File entity id.
   * @param int $kontainerFileId
   *   Kontainer file id.
   * @param string $assetType
   *   Kontainer asset type.
   * @param null|string $assetName
   *   Kontainer asset name.
   * @param string $sourceFieldName
   *   Media type source field machine name.
   * @param null|string $imageAltAttribute
   *   Image alt attribute, if present.
   *
   * @return array
   *   Array with media type field values.
   */
  private function generateMediaValues(int $fileId, int $kontainerFileId, string $assetType, ?string $assetName, string $sourceFieldName, ?string $imageAltAttribute): array {
    $sourceFieldValues = [
      'target_id' => $fileId,
    ];
    if ($assetType === self::KONTAINER_IMAGE_TYPE) {
      $sourceFieldValues['alt'] = $imageAltAttribute ?? $this->stringTranslation->translate('Kontainer image');
      $sourceFieldValues['title'] = $assetName;
    }

    return [
      'bundle' => self::MEDIA_TYPES_MAPPING[$assetType],
      'name' => $assetName,
      'uid' => $this->currentUser->id(),
      'status' => TRUE,
      $sourceFieldName => $sourceFieldValues,
      'field_kontainer_file_id' => $kontainerFileId,
    ];
  }

  /**
   * Creates the media entity from the given values and returns the id of it.
   *
   * @param array $mediaValues
   *   Array with media type field values.
   *
   * @return array
   *   The id and the label of the media as array values.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function createMedia(array $mediaValues): array {
    /** @var \Drupal\media\MediaInterface $media */
    $media = $this->entityTypeManager
      ->getStorage('media')
      ->create($mediaValues);
    $media->save();
    return [
      'id' => $media->id(),
      'label' => $media->label(),
    ];
  }

  /**
   * Checks if the current user has the permission to create the media type.
   *
   * @param string $mediaType
   *   Media type machine name.
   *
   * @throws \Exception
   */
  private function checkAccess(string $mediaType): void {
    if (!$this->currentUser->hasPermission("create $mediaType media")) {
      throw new \Exception('User does not have the permission to create the Kontainer media of this type in Drupal.');
    }
  }

}
