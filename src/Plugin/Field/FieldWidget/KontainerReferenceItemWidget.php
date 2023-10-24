<?php

namespace Drupal\kontainer\Plugin\Field\FieldWidget;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
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
   * Service "csrf_token".
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected CsrfTokenGenerator $csrfTokenGenerator;

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
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfTokenGenerator
   *   Service "csrf_token".
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    ConfigFactoryInterface $configFactory,
    RouteProviderInterface $routeProvider,
    KontainerServiceInterface $kontainerService,
    CsrfTokenGenerator $csrfTokenGenerator
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->configFactory = $configFactory;
    $this->routeProvider = $routeProvider;
    $this->kontainerService = $kontainerService;
    $this->csrfTokenGenerator = $csrfTokenGenerator;
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
      $container->get('kontainer_service'),
      $container->get('csrf_token')
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
      $createKontainerMediaPathTrimmed = ltrim($createKontainerMediaPath, '/');
      $fieldMachineName = $items->getName();
      $csrfToken = $this->csrfTokenGenerator->get($createKontainerMediaPathTrimmed);
      $element['target_id']['#attributes']['readonly'] = 'readonly';
      $element['target_id']['#attributes']['data-kontainer-selector'] = 'kontainer-reference-' . $fieldMachineName . '-' . $delta;
      $element['kontainer_button'] = [
        '#type' => 'button',
        '#value' => $this->t('Kontainer select'),
        '#attributes' => [
          'data-kontainer-selector' => 'open-kontainer',
          'data-kontainer-type' => 'media',
          'type' => 'button',
        ],
        '#attached' => [
          'library' => 'kontainer/kontainer-lib',
          'drupalSettings' => [
            'ajaxTrustedUrl' => [$createKontainerMediaPath . '?token=' . $csrfToken => TRUE],
            'kontainer' => [
              'kontainerUrl' => $this->configFactory
                ->get('kontainer.settings')
                ->get('kontainer_url'),
              'token' => $csrfToken,
              'createMediaPath' => $createKontainerMediaPathTrimmed,
            ],
          ],
        ],
        '#weight' => 10,
      ];
      $element['kontainer_file_id'] = [
        '#type' => 'hidden',
        '#attributes' => [
          'data-kontainer-selector' => 'kontainer-file-id-' . $fieldMachineName . '-' . $delta,
        ],
        '#weight' => 20,
        '#default_value' => $items[$delta]->getValue()['kontainer_file_id'] ?? NULL,
      ];
      $element['remove_button'] = [
        '#type' => 'button',
        '#value' => t('Remove'),
        '#name' => 'reference_remove_button' . $delta,
        '#attributes' => [
          'field-machine-name' => $fieldMachineName,
          'widget-delta' => $delta,
          'data-kontainer-selector' => 'kontainer-remove-button',
        ],
        '#ajax' => ['callback' => [$this, 'removeValue']],
        '#weight' => 30,
        '#limit_validation_errors' => [],
      ];
    }
    catch (RouteNotFoundException $e) {
      $this->kontainerService->logException($e);
    }

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
    $ajaxResponse->addCommand(new InvokeCommand("[data-kontainer-selector=\"kontainer-reference-$fieldMachineName-$delta\"]", 'val', ['']));
    $ajaxResponse->addCommand(new InvokeCommand("[data-kontainer-selector=\"kontainer-file-id-$fieldMachineName-$delta\"]", 'val', ['']));
    return $ajaxResponse;
  }

}
