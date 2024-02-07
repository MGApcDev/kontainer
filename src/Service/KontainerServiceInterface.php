<?php

namespace Drupal\kontainer\Service;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\node\NodeInterface;

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
   * Machine name of the Kontainer video media type.
   *
   * @var string
   */
  const KONTAINER_VIDEO_TYPE = 'video';

  /**
   * Machine name of the Kontainer document media type.
   *
   * @var string
   */
  const KONTAINER_DOCUMENT_TYPE = 'document';

  /**
   * Machine name of the Kontainer file media type.
   *
   * @var string
   */
  const KONTAINER_FILE_TYPE = 'file';

  /**
   * Machine name of the Drupal Kontainer CDN media type.
   *
   * @var string
   */
  const CDN_MEDIA_TYPE_NAME = 'kontainer_cdn';

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
   * @return array
   *   The id and the label of the media as array values.
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
   * Generates a render array with the link to Kontainer image conversions.
   *
   * @return array
   *   Render array.
   */
  public function getCdnImageConversionsRenderLink(): array;

  /**
   * Gets an array of CDN image conversions as select list options.
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
   * Formats the exception message and logs it to the Kontainer channel.
   *
   * @param \Exception $e
   *   The exception to be logged.
   */
  public function logException(\Exception $e): void;

  /**
   * Returns the Kontainer targets of an entity, also if nested in paragraphs.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The (current) source entity.
   *
   * @return array
   *   Array with Kontainer target ids, if there are any.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getNestedTargets(ContentEntityInterface $entity): array;

  /**
   * Gets all the source (node) ids, also for nested paragraphs.
   *
   * @param array $directSources
   *   Array with source data.
   *
   * @return array
   *   Array with source ids.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getMasterSourceIds(array $directSources): array;

  /**
   * Rebuilds the Kontainer usage for Kontainer media per node.
   *
   * Tracks usage for the source entity. It is called, when the source (node)
   * entity is being created or updated. Saves data to Drupal state.
   *
   * @param array $mediaTargets
   *   Array with media targets on the parent node.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function trackMediaUsage(array $mediaTargets, NodeInterface $node): void;

  /**
   * Removes usage for the source (node) entity, when it is being deleted.
   *
   * @param int $deletedSourceId
   *   The id of the node (source), that is being deleted.
   */
  public function deleteSourceUsage(int $deletedSourceId): void;

  /**
   * Removes usage for the target entities on source entities.
   *
   * @param array $sourceIds
   *   The ids of the sources (nodes), where the target (media) usage is
   *   present.
   * @param int $targetId
   *   The id of the target (media), that is being deleted.
   */
  public function deleteTargetUsage(array $sourceIds, int $targetId): void;

  /**
   * Formats the Kontainer usage, removes the in-between entity id array keys.
   *
   * @param array $kontainerUsage
   *   Kontainer usage from Drupal state.
   *
   * @return array
   *   Array without the in-between keys, that are needed for updating the
   *   Kontainer usage.
   */
  public function formatUsageData(array $kontainerUsage): array;

  /**
   * Checks if remote media source is enabled in Kontainer configuration.
   *
   * @return bool
   *   TRUE if media source is set to CDN, FALSE if it's set to Media Storage.
   */
  public function isRemoteMediaSource(): bool;

  /**
   * Returns the ID of default CDN Image conversion template.
   *
   * @return string
   *   ID of CdnImageConversion entity or empty if not set.
   */
  public function getDefaultCdnConversionTemplateId(): string;

  /**
   * Creates a Drupal file entity from the downloaded Kontainer file.
   *
   * @param string $assetUrl
   *   Kontainer asset url.
   * @param bool $uri
   *   If set to TRUE, file uri is returned, instead of the file id. Defaults to
   *   FALSE.
   *
   * @return int|string
   *   The id of the created file or the file uri.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public function createFile(string $assetUrl, bool $uri = FALSE);

  /**
   * Invalidates Media entities of type that have Kontainer CDN as source field.
   */
  public function invalidateKontainerCdnSourcedMediaEntities(): void;

  /**
   * Builds an array for Kontainer Select button form element.
   *
   * @param string $type
   *   Type of element.
   * @param array $attributes
   *   Array of element attributes.
   *
   * @return array
   *   Build array for the element.
   */
  public function buildKontainerSelectButtonArray(string $type, array $attributes): array;

  /**
   * Checks if there are any entities of Kontainer Media types left.
   *
   * @return bool
   *   TRUE if it can, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function canModuleBeUninstalled(): bool;

}
