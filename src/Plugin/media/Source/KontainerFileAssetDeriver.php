<?php

namespace Drupal\kontainer\Plugin\media\Source;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver to create file media source plugins based on supported asset types.
 */
class KontainerFileAssetDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Service "string_translation".
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected TranslationInterface $stringTranslation;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   Service "string_translation".
   */
  public function __construct(TranslationInterface $stringTranslation) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [
      'document' => [
        'id' => 'document',
        'label' => $this->stringTranslation->translate('Document (Kontainer)'),
        'description' => $this->stringTranslation->translate("Document assets from Kontainer DAM"),
        'default_thumbnail_filename' => 'generic.png',
      ] + $base_plugin_definition,
      'file' => [
        'id' => 'file',
        'label' => $this->stringTranslation->translate('File (Kontainer)'),
        'description' => $this->stringTranslation->translate("File assets from Kontainer DAM"),
        'default_thumbnail_filename' => 'generic.png',
      ] + $base_plugin_definition,
    ];
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
