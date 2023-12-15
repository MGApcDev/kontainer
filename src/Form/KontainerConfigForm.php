<?php

namespace Drupal\kontainer\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Kontainer module configuration form.
 */
class KontainerConfigForm extends ConfigFormBase {

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

  public function __construct(ConfigFactoryInterface $configFactory, KontainerServiceInterface $kontainerService) {
    parent::__construct($configFactory);
    $this->kontainerService = $kontainerService;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('kontainer_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'kontainer.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'kontainer_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('kontainer.settings');
    $form['kontainer_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Kontainer URL'),
      '#default_value' => $config->get('kontainer_url'),
      '#description' => $this->t('Enter the URL to your kontainer. Example: https://yourkontainer.kontainer.com'),
      '#required' => TRUE,
    ];

    $mediaSource = $config->get('kontainer_media_source') ?? KontainerServiceInterface::KONTAINER_MEDIA_SOURCE_CDN_URL;
    $form['kontainer_media_source'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred media source:'),
      '#options' => [
        KontainerServiceInterface::KONTAINER_MEDIA_SOURCE_MEDIA_STORAGE => $this->t('Media storage'),
        KontainerServiceInterface::KONTAINER_MEDIA_SOURCE_CDN_URL => $this->t('CDN'),
      ],
      '#default_value' => $mediaSource,
    ];
    if ($mediaSource === KontainerServiceInterface::KONTAINER_MEDIA_SOURCE_CDN_URL) {
      $form['kontainer_media_source']['#description'] = $this->kontainerService->getCdnImageConversionsRenderLink();
    }
    $form['usage_api_info'] = [
      '#markup' => '<h3>' . $this->t('File usage tracking') . '</h3><div>' .
      $this->t('In your Kontainer, insert this URL to create a Drupal integration:')
      . '</div>',
    ];
    $form['file_usage_url'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'readonly' => 'readonly',
      ],
      '#id' => 'kontainer-file-usage-url',
      '#default_value' => Url::fromRoute('kontainer.usage', [], ['absolute' => TRUE])->toString(),
    ];
    $form['file_usage_url_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Copy URL to clipboard'),
      '#id' => 'kontainer-file-usage-url-button',
      '#attached' => [
        'library' => 'kontainer/kontainer-copy-to-clipboard',
      ],
      '#limit_validation_errors' => [],
    ];
    $form['integration_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Integration ID'),
      '#default_value' => $config->get('integration_id'),
      '#description' => $this->t('Enter your integration ID for the usage API.'),
    ];
    $form['integration_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Integration secret'),
      '#default_value' => $config->get('integration_secret'),
      '#description' => $this->t('Enter your integration secret for the usage API.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!UrlHelper::isValid($form_state->getValue('kontainer_url'))) {
      $form_state->setErrorByName(
        'invalid_url',
        $this->t('The provided URL is not valid.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('kontainer.settings')
      ->set('kontainer_url', $form_state->getValue('kontainer_url'))
      ->set('kontainer_media_source', $form_state->getValue('kontainer_media_source'))
      ->set('integration_id', $form_state->getValue('integration_id'))
      ->set('integration_secret', $form_state->getValue('integration_secret'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
