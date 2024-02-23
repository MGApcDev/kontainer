<?php

namespace Drupal\kontainer\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\UserSession;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kontainer authentication provider.
 */
class KontainerAuth implements AuthenticationProviderInterface {

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

  public function __construct(ConfigFactoryInterface $config_factory, RouteProviderInterface $routeProvider) {
    $this->configFactory = $config_factory;
    $this->routeProvider = $routeProvider;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    $kontainerUsagePath = $this->routeProvider
      ->getRouteByName('kontainer.usage')
      ->getPath();
    return $kontainerUsagePath === $request->getPathInfo() && $request->headers->get('Authorization');
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $kontainerSettings = $this->configFactory
      ->get('kontainer.settings');
    $configIntegrationId = $kontainerSettings->get('integration_id');
    $configIntegrationSecret = $kontainerSettings->get('integration_secret');
    if (empty($configIntegrationId) || empty($configIntegrationSecret)) {
      return NULL;
    }
    $token = $request->headers->get('Authorization');
    // The Symfony way
    // https://github.com/symfony/http-foundation/blob/6.3/ServerBag.php,
    // following this scheme https://datatracker.ietf.org/doc/html/rfc7617.
    if (0 === stripos($token, 'basic ')) {
      $exploded = explode(':', base64_decode(substr($token, 6)), 2);
      if (2 == count($exploded)) {
        [$requestIntegrationId, $requestIntegrationSecret] = $exploded;
        if (hash_equals($requestIntegrationId, $configIntegrationId) && hash_equals($requestIntegrationSecret, $configIntegrationSecret)) {
          // Use a fake role, for security reasons.
          return new UserSession(['roles' => ['kontainer_auth_role']]);
        }
      }
    }
    return NULL;
  }

}
