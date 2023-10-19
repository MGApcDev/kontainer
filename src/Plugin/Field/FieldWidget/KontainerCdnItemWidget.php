<?php

namespace Drupal\kontainer\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'kontainer_widget' widget.
 *
 * @FieldWidget(
 *   id = "kontainer_cdn",
 *   label = @Translation("Kontainer CDN Widget"),
 *   field_types = {
 *     "kontainer_cdn"
 *   }
 * )
 */
class KontainerCdnItemWidget extends LinkWidget {

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

  /**
   * Service "config.factory".
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Constructs a new ModerationStateWidget object.
   *
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   Field definition.
   * @param array $settings
   *   Field settings.
   * @param array $third_party_settings
   *   Third party settings.
   * @param \Drupal\kontainer\Service\KontainerServiceInterface $kontainerService
   *   Service "kontainer_service".
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Service "config.factory".
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    KontainerServiceInterface $kontainerService,
    ConfigFactoryInterface $configFactory
    ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->kontainerService = $kontainerService;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('kontainer_service'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    $element['uri']['#attributes']['readonly'] = 'readonly';
    $element['kontainer_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Kontainer select'),
      '#id' => Html::getUniqueId('open-kontainer'),
      '#attributes' => [
        'data-kontainer-selector' => 'open-kontainer',
        'data-kontainer-type' => 'cdn',
        'type' => 'button',
      ],
      '#attached' => [
        'library' => 'kontainer/kontainer-lib',
        'drupalSettings' => [
          'kontainer' => [
            'kontainerUrl' => $this->configFactory
              ->get('kontainer.settings')
              ->get('kontainer_url'),
          ],
        ],
      ],
      '#weight' => 10,
    ];
    $element['media_type'] = [
      '#type' => 'hidden',
      '#id' => Html::getUniqueId('kontainer-cdn-media-type'),
      '#weight' => 20,
      '#default_value' => $items[$delta]->getValue()['media_type'] ?? NULL,
    ];
    $element['alt'] = [
      '#type' => 'hidden',
      '#id' => Html::getUniqueId('kontainer-cdn-alt'),
      '#weight' => 30,
      '#default_value' => $items[$delta]->getValue()['alt'] ?? NULL,
    ];
    $element['kontainer_file_id'] = [
      '#type' => 'hidden',
      '#id' => Html::getUniqueId('kontainer-cdn-file-id'),
      '#weight' => 60,
      '#default_value' => $items[$delta]->getValue()['kontainer_file_id'] ?? NULL,
    ];
    $element['base_uri'] = [
      '#type' => 'hidden',
      '#id' => Html::getUniqueId('kontainer-cdn-base-uri'),
      '#weight' => 70,
      '#default_value' => $items[$delta]->getValue()['base_uri'] ?? NULL,
    ];
    unset($element['uri']['#description']);
    return $element;
  }

}
