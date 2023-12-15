<?php

namespace Drupal\kontainer\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigValueException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the KontainerLink constraint.
 */
class KontainerLinkConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Service "config.factory".
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

  public function __construct(ConfigFactoryInterface $configFactory, KontainerServiceInterface $kontainerService) {
    $this->configFactory = $configFactory;
    $this->kontainerService = $kontainerService;
  }

  /**
   * {@inheritdoc}
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
  public function validate($value, Constraint $constraint) {
    if (isset($value)) {
      try {
        /** @var \Drupal\Core\Url $url */
        $url = $value->getUrl();
        $kontainerUrl = $this->configFactory
          ->get('kontainer.settings')
          ->get('kontainer_url');
        if (!$kontainerUrl) {
          // If the Kontainer URL cannot be fetched, the constraint cannot check
          // further.
          throw new ConfigValueException('Kontainer URL configuration could not be fetched.');
        }
      }
      // If the URL is malformed, the constraint cannot check further.
      catch (\InvalidArgumentException | ConfigValueException $e) {
        $this->kontainerService->logException($e);
        return;
      }
      if (parse_url($url->getUri(), PHP_URL_HOST) !== parse_url($kontainerUrl, PHP_URL_HOST)) {
        $this->context->addViolation($constraint->message, [
          '@uri' => $value->uri,
          '@config_url' => $kontainerUrl,
        ]);
      }
    }
  }

}
