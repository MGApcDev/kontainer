<?php

namespace Drupal\kontainer\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Defines the 'Kontainer' entity field type.
 *
 * @FieldType(
 *   id = "kontainer_cdn",
 *   label = @Translation("Kontainer CDN"),
 *   description = @Translation("A field storing Kontainer CDN links"),
 *   category = @Translation("Reference"),
 *   default_widget = "kontainer_cdn",
 *   default_formatter = "kontainer_cdn",
 *   constraints = {
 *     "LinkType" = {},
 *     "LinkAccess" = {},
 *     "LinkExternalProtocols" = {},
 *     "KontainerLink" = {}
 *   }
 * )
 */
class KontainerCdnItem extends LinkItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'title' => DRUPAL_DISABLED,
      'link_type' => LinkItemInterface::LINK_EXTERNAL,
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);
    $element['link_type']['#options'] = [
      self::LINK_EXTERNAL => $element['link_type']['#options'][self::LINK_EXTERNAL],
    ];
    // Just in case, that this gets changed in the future in LinkItemInterface.
    $element['link_type']['#default_value'] = self::LINK_EXTERNAL;
    $element['title']['#options'] = [
      DRUPAL_DISABLED => $element['title']['#options'][DRUPAL_DISABLED],
    ];
    // Just in case, that this gets changed in the future in system.module.
    $element['title']['#default_value'] = DRUPAL_DISABLED;
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return [
      'media_type' => DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Media type'))
        ->setDescription(new TranslatableMarkup('Kontainer media type.')),
      'kontainer_file_name' => DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Kontainer file name'))
        ->setDescription(new TranslatableMarkup('The name of the Kontainer file.')),
      'kontainer_file_id' => DataDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('Width'))
        ->setDescription(new TranslatableMarkup('The ID of the Kontainer file.')),
      'base_uri' => DataDefinition::create('uri')
        ->setLabel(new TranslatableMarkup('Base URI'))
        ->setDescription(new TranslatableMarkup('Base URI of the media.')),
    ] + parent::propertyDefinitions($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);

    $schema['columns'] = [
      'media_type' => [
        'description' => 'The Kontainer media type.',
        'type' => 'varchar',
        'length' => 512,
      ],
      'kontainer_file_name' => [
        'description' => 'The name of the Kontainer file.',
        'type' => 'varchar',
        'length' => 512,
      ],
      'kontainer_file_id' => [
        'description' => 'The ID of the Kontainer file.',
        'type' => 'int',
        'unsigned' => TRUE,
      ],
      'base_uri'   => [
        'description' => 'Base URI of the media.',
        'type' => 'varchar',
        'length' => 2048,
      ],
    ] + $schema['columns'];
    return $schema;
  }

}
