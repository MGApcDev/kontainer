<?php

namespace Drupal\kontainer;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of cdn image conversions.
 */
class CdnImageConversionListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['template_id'] = $this->t('Template id');
    $header['format'] = $this->t('Format');
    $header['dimensions'] = $this->t('Dimensions (width)');
    $header['image_style'] = $this->t('Image style');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\kontainer\CdnImageConversionInterface $entity */
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['tempalte_id'] = $entity->get('template_id');
    $row['format'] = $entity->get('format');
    $row['dimensions'] = $entity->get('dimensions');
    $row['image_style to'] = $entity->get('image_style');
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

}
