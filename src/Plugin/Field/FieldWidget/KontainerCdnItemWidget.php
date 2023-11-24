<?php

namespace Drupal\kontainer\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'kontainer_cdn' widget.
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
   * Class constructor.
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
    $fieldMachineName = $items->getName();
    $element['uri']['#attributes']['readonly'] = 'readonly';
    $element['uri']['#attributes']['data-kontainer-selector'] = 'kontainer-cdn-' . $fieldMachineName . '-' . $delta;
    unset($element['uri']['#description']);
    $element['kontainer_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Kontainer select'),
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
      '#attributes' => [
        'data-kontainer-selector' => 'kontainer-media-type-' . $fieldMachineName . '-' . $delta,
      ],
      '#weight' => 20,
      '#default_value' => $items[$delta]->getValue()['media_type'] ?? NULL,
    ];
    $element['kontainer_file_name'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'data-kontainer-selector' => 'kontainer-file_name-' . $fieldMachineName . '-' . $delta,
      ],
      '#weight' => 30,
      '#default_value' => $items[$delta]->getValue()['kontainer_file_name'] ?? NULL,
    ];
    $element['kontainer_file_id'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'data-kontainer-selector' => 'kontainer-file-id-' . $fieldMachineName . '-' . $delta,
      ],
      '#weight' => 60,
      '#default_value' => $items[$delta]->getValue()['kontainer_file_id'] ?? NULL,
    ];
    $element['base_uri'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'data-kontainer-selector' => 'kontainer-base-uri-' . $fieldMachineName . '-' . $delta,
      ],
      '#weight' => 70,
      '#default_value' => $items[$delta]->getValue()['base_uri'] ?? NULL,
    ];
    $element['remove_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Remove'),
      '#name' => 'cdn_remove_button_' . $fieldMachineName . $delta,
      '#attributes' => [
        'field-machine-name' => $fieldMachineName,
        'widget-delta' => $delta,
        'data-kontainer-selector' => 'kontainer-remove-button',
      ],
      '#ajax' => ['callback' => [$this, 'removeValue']],
      '#weight' => 80,
      '#limit_validation_errors' => [],
    ];
    return $element;
  }

  /**
   * Submit callback to clear the widget field values.
   */
  public function removeValue(array &$form, FormStateInterface $form_state): AjaxResponse {
    $button = $form_state->getTriggeringElement();
    $delta = $button['#attributes']['widget-delta'];
    $fieldMachineName = $button['#attributes']['field-machine-name'];
    $ajaxResponse = new AjaxResponse();
    $ajaxResponse->addCommand(new InvokeCommand("[data-kontainer-selector=\"kontainer-cdn-$fieldMachineName-$delta\"]", 'val', ['']));
    $ajaxResponse->addCommand(new InvokeCommand("[data-kontainer-selector=\"kontainer-media-type-$fieldMachineName-$delta\"]", 'val', ['']));
    $ajaxResponse->addCommand(new InvokeCommand("[data-kontainer-selector=\"kontainer-file_name-$fieldMachineName-$delta\"]", 'val', ['']));
    $ajaxResponse->addCommand(new InvokeCommand("[data-kontainer-selector=\"kontainer-file-id-$fieldMachineName-$delta\"]", 'val', ['']));
    $ajaxResponse->addCommand(new InvokeCommand("[data-kontainer-selector=\"kontainer-base-uri-$fieldMachineName-$delta\"]", 'val', ['']));
    return $ajaxResponse;
  }

}
