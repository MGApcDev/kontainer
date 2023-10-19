<?php

namespace Drupal\kontainer\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\EntityReferenceAutocompleteWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Plugin implementation of the 'kontainer_widget' widget.
 *
 * @FieldWidget(
 *   id = "kontainer_media_reference",
 *   label = @Translation("Kontainer Entity Reference Widget"),
 *   field_types = {
 *     "kontainer_media_reference"
 *   }
 * )
 */
class KontainerReferenceItemWidget extends EntityReferenceAutocompleteWidget {

  /**
   * Service "config.factory".
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Service "router.route_provider".
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Service "config.factory".
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   Service "router.route_provider".
   * @param \Drupal\kontainer\Service\KontainerServiceInterface $kontainerService
   *   Service "kontainer_service".
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    ConfigFactoryInterface $configFactory,
    RouteProviderInterface $routeProvider,
    KontainerServiceInterface $kontainerService
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->configFactory = $configFactory;
    $this->routeProvider = $routeProvider;
    $this->kontainerService = $kontainerService;
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
      $container->get('config.factory'),
      $container->get('router.route_provider'),
      $container->get('kontainer_service')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    try {
      $createKontainerMediaPath = $this->routeProvider
        ->getRouteByName('kontainer.create_media')
        ->getPath();
      $element['target_id']['#attributes']['readonly'] = 'readonly';
      $element['kontainer_button'] = [
        '#type' => 'button',
        '#value' => $this->t('Kontainer select'),
        '#id' => Html::getUniqueId('open-kontainer'),
        '#attributes' => [
          'data-kontainer-selector' => 'open-kontainer',
          'data-kontainer-type' => 'media',
          'type' => 'button',
        ],
        '#attached' => [
          'library' => 'kontainer/kontainer-lib',
          'drupalSettings' => [
            'ajaxTrustedUrl' => [$createKontainerMediaPath => TRUE],
            'kontainer' => [
              'kontainerUrl' => $this->configFactory
                ->get('kontainer.settings')
                ->get('kontainer_url'),
              'createMediaPath' => ltrim($createKontainerMediaPath, '/'),
            ],
          ],
        ],
        '#weight' => 10,
      ];
      $element['kontainer_file_id'] = [
        '#type' => 'hidden',
        '#id' => Html::getUniqueId('kontainer-file-id'),
        '#weight' => 20,
        '#default_value' => $items[$delta]->getValue()['kontainer_file_id'] ?? NULL,
      ];
    }
    catch (RouteNotFoundException $e) {
      $this->kontainerService->logException($e);
    }

    return $element;
  }

}
