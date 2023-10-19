<?php

namespace Drupal\kontainer\Service;

/**
 * Kontainer service interface.
 */
interface KontainerServiceInterface {

  /**
   * Machine name of the Kontainer image media type.
   *
   * @var string
   */
  const KONTAINER_IMAGE_TYPE = 'image';

  /**
   * Machine name of the Kontainer image media type.
   *
   * @var string
   */
  const KONTAINER_VIDEO_TYPE = 'video';

  /**
   * Machine name of the Kontainer image media type.
   *
   * @var string
   */
  const KONTAINER_DOCUMENT_TYPE = 'document';

  /**
   * Machine name of the Kontainer image media type.
   *
   * @var string
   */
  const KONTAINER_FILE_TYPE = 'file';

  /**
   * The mapping of Kontainer media types (keys) to Drupal media types (values).
   *
   * @var array
   */
  const MEDIA_TYPES_MAPPING = [
    self::KONTAINER_IMAGE_TYPE => 'kontainer_image',
    self::KONTAINER_VIDEO_TYPE => 'kontainer_video',
    self::KONTAINER_DOCUMENT_TYPE => 'kontainer_document',
    self::KONTAINER_FILE_TYPE => 'kontainer_file',
  ];

  /**
   * Media storage Kontainer media source.
   *
   * @var string
   */
  const KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE = 'media_storage';

  /**
   * CDN URL Kontainer media source.
   *
   * @var string
   */
  const KONTAINER_MEDIA_SOURCE_CDN_URL = 'cdn';

  /**
   * Creates the file and media entities from the Kontainer file.
   *
   * @param array $assetData
   *   Kontainer asset data.
   *
   * @return bool|int
   *   The id of the created/existing media entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if the entity can't be saved.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   * @throws \Exception
   */
  public function createEntities(array $assetData): array;

  /**
   * Generates the formatter CDN URL (applies the image conversion to it).
   *
   * @param string $urlBaseName
   *   Kontainer asset URL base name.
   * @param string $imageConversion
   *   The image conversion machine name.
   *
   * @return string|null
   *   The formatter CDN url with the correct download template and format. NULL
   *   if the image conversion doesn't exit.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *    Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *    Thrown if the storage handler couldn't be loaded.
   * @throws \Exception
   */
  public function generateCdnFormattedUrl(string $urlBaseName, string $imageConversion): ?string;

  /**
   * Returns media types, that have a dependency on a specific module.
   *
   * @param string $moduleName
   *   The name of the module to check for dependencies.
   *
   * @return array
   *   An array of media type machine names (keys) and labels (values), that
   *   depend on the specified module.
   */
  public function getMediaTypesWithDependency(string $moduleName): array;

  /**
   * Generates a render array with the link to Kontainer image styles.
   *
   * @return array
   *   Render array.
   */
  public function getCdnImageConversionsRenderLink(): array;

  /**
   * Gets an array of image styles suitable for using as select list options.
   *
   * @param bool $includeEmpty
   *   If TRUE a '- None -' option will be inserted in the options array.
   *   Defaults to TRUE.
   *
   * @return string[]
   *   Array of conversions. Both key and value are set to conversion name.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function getCdnImageConversionsOptions(bool $includeEmpty = TRUE): array;

  /**
   * Returns field formatter settings for a field instance.
   *
   * @param string $entityFormDisplayId
   *   Entity form display id.
   * @param string $fieldName
   *   Field name.
   * @param string|null $settingName
   *   Field formatter setting name.
   *
   * @return array|string
   *   Array of settings or specific settings if $settingsName is provided.
   *   NULL if no settings is present.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @note Currently not used, may be used in future implementations.
   */
  public function getFieldFormatterSettings(string $entityFormDisplayId, string $fieldName, string $settingName = NULL);

  /**
   * Formats the exception message and logs it to the Kontainer channel.
   *
   * @param \Exception $e
   *   The exception to be logged.
   */
  public function logException(\Exception $e): void;

}
