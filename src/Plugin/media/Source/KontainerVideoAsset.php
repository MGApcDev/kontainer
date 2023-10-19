<?php

namespace Drupal\kontainer\Plugin\media\Source;

use Drupal\media\MediaTypeInterface;
use Drupal\media\Plugin\media\Source\VideoFile;

/**
 * Media source for Video Kontainer DAM assets.
 *
 * @MediaSource(
 *   id = "kontainer_video",
 *   label = @Translation("Video (Kontainer)"),
 *   description = @Translation("Video assets from Kontainer DAM"),
 *   allowed_field_types = {"file"},
 *   default_thumbnail_filename = "generic.png",
 * )
 */
class KontainerVideoAsset extends VideoFile {

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set('settings', ['file_extensions' => 'webm mkv avi wmv mp4 m4p mp4v mpg mpeg mp2 mpe mpv mxf 3gp mov mp3 wav']);
  }

}
