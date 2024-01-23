<?php

namespace Drupal\kontainer\Plugin\media\Source;

use Drupal\media\MediaTypeInterface;
use Drupal\media\Plugin\media\Source\Image;

/**
 * Media source for Image Kontainer DAM assets.
 *
 * @MediaSource(
 *   id = "kontainer_image",
 *   label = @Translation("Image (Kontainer)"),
 *   description = @Translation("Image assets from Kontainer DAM"),
 *   allowed_field_types = {"image"},
 *   default_thumbnail_filename = "generic.png",
 *   thumbnail_alt_metadata_attribute = "thumbnail_alt_value"
 * )
 */
class KontainerImageAsset extends Image implements KontainerMediaSourceInterface {

  /**
   * {@inheritdoc}
   */
  public function createSourceField(MediaTypeInterface $type) {
    return parent::createSourceField($type)->set('settings', ['file_extensions' => 'psd arw cin cr2 crw dcr dng mos mrw nef orf raf raw rw2 sr2 x3f art bmp cgm cur cut dcm dcx dib emf ept exr fax fig fpx gif heic heif htm html ico jbg jng jpeg jpg k25 kdc mif mng mtv mvg otb pbm pcd pcx pdb pfa pfb pfm pgm pic pict pix png rla rle sct sfw sgi srf svg tga tif tiff tim ttf wbmp webp wmf wmz wpg xbm xcf xpm ai epi eps ps ps2 ps3']);
  }

}
