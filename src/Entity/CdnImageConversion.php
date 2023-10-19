<?php

namespace Drupal\kontainer\Entity;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\kontainer\CdnImageConversionInterface;

/**
 * Defines the cdn image conversion entity type.
 *
 * @ConfigEntityType(
 *   id = "cdn_image_conversion",
 *   label = @Translation("Cdn Image Conversion"),
 *   label_collection = @Translation("Cdn Image Conversions"),
 *   label_singular = @Translation("cdn image conversion"),
 *   label_plural = @Translation("cdn image conversions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count cdn image conversion",
 *     plural = "@count cdn image conversions",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\kontainer\CdnImageConversionListBuilder",
 *     "form" = {
 *       "add" = "Drupal\kontainer\Form\CdnImageConversionForm",
 *       "edit" = "Drupal\kontainer\Form\CdnImageConversionForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     }
 *   },
 *   config_prefix = "cdn_image_conversion",
 *   admin_permission = "administer cdn_image_conversion",
 *   links = {
 *     "collection" = "/admin/structure/cdn-image-conversion",
 *     "add-form" = "/admin/structure/cdn-image-conversion/add",
 *     "edit-form" = "/admin/structure/cdn-image-conversion/{cdn_image_conversion}",
 *     "delete-form" = "/admin/structure/cdn-image-conversion/{cdn_image_conversion}/delete"
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "template_id",
 *     "format"
 *   }
 * )
 * @todo Possible feature: fetch and create from templates via API.
 */
class CdnImageConversion extends ConfigEntityBase implements CdnImageConversionInterface {

  /**
   * The cdn image conversion ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The cdn image conversion label.
   *
   * @var string
   */
  protected $label;

  /**
   * The cdn image conversion status.
   *
   * @var bool
   */
  protected $status;

  /**
   * The Kontainer download template id.
   *
   * @var int
   */
  protected $template_id;

  /**
   * Image format.
   *
   * @var string
   */
  protected $format;

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    /** @var \Drupal\kontainer\CdnImageConversionInterface[] $entities */
    foreach ($entities as $conversion) {
      try {
        static::replaceConversionStyle($conversion);
      }
      catch (EntityStorageException | PluginNotFoundException | InvalidPluginDefinitionException $e) {
        /** @var \Drupal\kontainer\Service\KontainerServiceInterface $kontainerService */
        $kontainerService = \Drupal::service('kontainer_service');
        $kontainerService->logException($e);
      }
    }
  }

  /**
   * Update view display, if the image conversion is deleted.
   *
   *  Loop through all entity displays looking for formatters using the image
   *  conversion and set the conversion to the empty value (original image).
   *
   * @param \Drupal\kontainer\CdnImageConversionInterface $conversion
   *   The CDN image conversion.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the entity could not be saved.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   *
   * @note The last two exceptions can be thrown in loadMultiple(), but are not
   * described in the functions doc comment.
   */
  protected static function replaceConversionStyle(CdnImageConversionInterface $conversion) {
    foreach (EntityViewDisplay::loadMultiple() as $display) {
      foreach ($display->getComponents() as $name => $options) {
        if (
          isset($options['type']) && $options['type'] == 'kontainer_cdn' &&
          $options['settings']['image_conversion'] == $conversion->id()
        ) {
          $options['settings']['image_conversion'] = '';
          $display->setComponent($name, $options)->save();
        }
      }
    }
  }

}
