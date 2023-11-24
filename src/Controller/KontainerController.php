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
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response with media data.
   */
  public function createMedia(Request $request): JsonResponse {
    try {
      $assetData = $this->decodeAssetValues($request);
      $mediaValues = $this->kontainerService->createEntities($assetData);
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
      'kontainer_file_id' => $assetData['fileId'] ?? NULL,
    ]);
  }

  /**
   * Returns the Kontainer media usage data (formatted).
   *
   * If the "kontainerFileId" query parameter is present, only the data for that
   * file id will be returned.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming HTTP request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response with formatted usage data.
   */
  public function sendUsage(Request $request): JsonResponse {
    $usageData = $this->state()->get('kontainer_usage') ?? [];
    if ($kontainerFileId = $request->get('kontainerFileId')) {
      $usageData = $usageData[$kontainerFileId] ?? [];
    }
    return new JsonResponse($this->kontainerService->formatUsageData($usageData));
  }

  /**
   * Decodes the JSON object from the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return array
   *   Array with the asset data.
   */
  private function decodeAssetValues(Request $request): array {
    $assetData = Json::decode($request->getContent());
    if (empty($assetData)) {
      return [];
    }
    // In this phase importing of multiple items at once is not yet
    // supported. If the array consists of multiple items, only the first one
    // will be imported.
    return $assetData[0] ?? $assetData;
  }

}
