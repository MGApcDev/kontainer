<?php

namespace Drupal\kontainer\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kontainer controller.
 */
class KontainerController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

  /**
   * Class constructor.
   *
   * @param \Drupal\kontainer\Service\KontainerServiceInterface $kontainerService
   *   Service "kontainer_service".
   */
  public function __construct(
    KontainerServiceInterface $kontainerService
  ) {
    $this->kontainerService = $kontainerService;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('kontainer_service')
    );
  }

  /**
   * Creates a file and a media entity from the JSON object.
   */
  public function createMedia(Request $request): JsonResponse {
    try {
      $assetsData = $this->decodeAssetValues($request);
      $mediaValues = $this->kontainerService->createEntities($assetsData);
    }
    catch (\Exception $e) {
      $this->kontainerService->logException($e);
      return new JsonResponse([
        'error_message' => $e->getMessage(),
      ]);
    }
    return new JsonResponse([
      'media_id' => $mediaValues['id'] ?? NULL,
      'media_label' => $mediaValues['label'] ?? NULL,
      'kontainer_file_id' => $assetsData['fileId'] ?? NULL,
    ]);
  }

  /**
   * Decodes the JSON object from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return array
   *   Array with the asset data or NULL.
   */
  private function decodeAssetValues(Request $request): array {
    $assetsData = Json::decode($request->getContent());
    if (empty($assetsData)) {
      return [];
    }
    // In this phase importing of multiple items at once is not yet
    // supported. If the array consists of multiple items, only the first one
    // will be imported.
    return $assetsData[0] ?? $assetsData;
  }

}
