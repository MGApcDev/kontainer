<?php

namespace Drupal\kontainer\Plugin\media\Source;

use Drupal\media\MediaTypeInterface;
use Drupal\media\Plugin\media\Source\File;

/**
 * Media source for DAM assets.
 *
 * @MediaSource(
 *   id = "kontainer",
 *   label = @Translation("Kontainer asset"),
 *   description = @Translation("Assets from Kontainer DAM"),
 *   allowed_field_types = {"file"},
 *   default_thumbnail_filename = "generic.png",
 *   deriver = "Drupal\kontainer\Plugin\media\Source\KontainerFileAssetDeriver",
 * )
 * @todo Add kontainer_file_id to file/media directly -> usage API.
 */
class KontainerFileAsset extends File implements KontainerMediaSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    switch ($this->pluginId) {
      case 'kontainer:document':
        return parent::createSourceField($type)->set('settings', ['file_extensions' => 'doc docx dotx txt rtf odt']);

      default:
        return parent::createSourceField($type)->set('settings', ['file_extensions' => '']);
    }
  }

}
