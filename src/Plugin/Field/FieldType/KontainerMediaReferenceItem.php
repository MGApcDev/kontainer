<?php

namespace Drupal\kontainer\Plugin\Field\FieldType;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\kontainer\Service\KontainerServiceInterface;

/**
 * Defines the 'Kontainer Media Reference' entity field type.
 *
 * @FieldType(
 *   id = "kontainer_media_reference",
 *   label = @Translation("Kontainer Media Reference"),
 *   description = @Translation("A field referencing Kontainer DAM media entities"),
 *   category = @Translation("Reference"),
 *   default_widget = "kontainer_media_reference",
 *   default_formatter = "entity_reference_entity_view",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 * )
 */
class KontainerMediaReferenceItem extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    return [
      'target_type' => 'media',
      'kontainer_file_id' => NULL,
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    try {
      $mediaEntityType = \Drupal::entityTypeManager()->getDefinition('media');
      $element['target_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Type of item to reference'),
        '#default_value' => $this->getSetting('target_type'),
        '#required' => TRUE,
        '#disabled' => TRUE,
        '#size' => 1,
        '#options' => [
          'Content' => [
            $mediaEntityType->id() => $mediaEntityType->getLabel(),
          ],
        ],
      ];
    }
    catch (PluginNotFoundException $e) {
      $this->kontainerService()->logException($e);
    }
    return $element ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::fieldSettingsForm($form, $form_state);
    $form['handler']['handler_settings']['target_bundles']['#options'] = $this->kontainerService()->getMediaTypesWithDependency('kontainer');
    unset($form['handler']['handler']['#options']['views']);
    unset($form['handler']['handler_settings']['auto_create']);
    unset($form['handler']['handler_settings']['auto_create_bundle']);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return [
      'kontainer_file_id' => DataDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('Kontainer file id'))
        ->setDescription(new TranslatableMarkup('The ID of the Kontainer file.')),
    ] + parent::propertyDefinitions($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = parent::schema($field_definition);
    $schema['columns']['kontainer_file_id'] = [
      'description' => 'The ID of the Kontainer file.',
      'type' => 'int',
      'unsigned' => TRUE,
    ];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function getPreconfiguredOptions() {
    return [];
  }

  /**
   * Retrieves the kontainer service.
   *
   * @return \Drupal\kontainer\Service\KontainerServiceInterface
   *   Service "kontainer_service".
   */
  private function kontainerService(): KontainerServiceInterface {
    return \Drupal::service('kontainer_service');
  }

}
