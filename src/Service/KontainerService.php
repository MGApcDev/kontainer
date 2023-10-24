<?php

namespace Drupal\kontainer\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\media\MediaTypeInterface;
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
   * Upload directory for files from Kontainer.
   *
   * @var string
   */
  protected string $uploadDirectory = 'public://Kontainer';

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Service "module_handler".
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Service "config.factory".
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Service "file_system".
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Service "entity_type.manager".
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Service "current_user".
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   Service "string_translation".
   * @param \Psr\Log\LoggerInterface $logger
   *   Kontainer channel logger instance.
   */
  public function __construct(
    ModuleHandlerInterface $moduleHandler,
    ConfigFactoryInterface $configFactory,
    FileSystemInterface $fileSystem,
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser,
    TranslationInterface $stringTranslation,
    LoggerInterface $logger
  ) {
    $this->moduleHandler = $moduleHandler;
    $this->configFactory = $configFactory;
    $this->fileSystem = $fileSystem;
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
    $this->stringTranslation = $stringTranslation;
    $this->logger = $logger;
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
    if (empty($assetUrl)) {
      throw new \Exception('No file url provided from Kontainer.');
    }
    if (empty(self::MEDIA_TYPES_MAPPING[$assetData['type']])) {
      throw new \Exception('Unsupported asset type.');
    }
    $this->checkAccess(self::MEDIA_TYPES_MAPPING[$assetData['type']]);
    $mediaType = $this->getMediaType($assetType);
    $sourceFieldName = $this->getMediaTypeSourceField($mediaType);
    $this->validateMediaTypeExtensions($assetType, $sourceFieldName, $assetExtension);
    $fileId = $this->createFile($assetUrl);
    return $this->createMedia($this->generateMediaValues($fileId, $assetType, $assetName, $sourceFieldName, $assetAlt));
  }

  /**
   * {@inheritDoc}
   */
  public function generateCdnFormattedUrl(string $urlBaseName, string $imageConversion): ?string {
    if (!$urlBaseName || !$imageConversion) {
      throw new \Exception('Cdn and/or image conversion id could not be fetched.');
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
      '#access' => $this->currentUser->hasPermission('administer image styles'),
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
      $options[''] = t('- None -');
    }
    foreach ($conversions as $name => $conversion) {
      $options[$name] = $conversion->label();
    }

    if (empty($options)) {
      $options[''] = t('No defined styles');
    }
    return $options;
  }

  /**
   * {@inheritDoc}
   */
  public function getFieldFormatterSettings(string $entityFormDisplayId, string $fieldName, string $settingName = NULL) {
    $formatterSettings = $this->entityTypeManager
      ->getStorage('entity_view_display')
      ->load($entityFormDisplayId)
      ->getRenderer($fieldName)
      ->getSettings();
    return $settingName ? ($formatterSettings[$settingName] ?? '') : ($formatterSettings ?? []);
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
  private function generateMediaValues(int $fileId, string $assetType, ?string $assetName, string $sourceFieldName, ?string $imageAltAttribute): array {
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
