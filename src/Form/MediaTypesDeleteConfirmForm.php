<?php

namespace Drupal\kontainer\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\kontainer\Service\KontainerServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a confirmation form to confirm deletion of Kontainer media types.
 */
class MediaTypesDeleteConfirmForm extends ConfirmFormBase {

  /**
   * Service "entity_type.manager".
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Service "kontainer_service".
   *
   * @var \Drupal\kontainer\Service\KontainerServiceInterface
   */
  protected KontainerServiceInterface $kontainerService;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, KontainerServiceInterface $kontainerService) {
    $this->entityTypeManager = $entityTypeManager;
    $this->kontainerService = $kontainerService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('kontainer_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure, that you want to delete all of the Kontainer media types?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('system.modules_uninstall');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'kontainer_media_types_delete_form';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mediaTypeStorage = $this->entityTypeManager->getStorage('media_type');
    $mediaTypeStorage->delete($mediaTypeStorage->loadMultiple($this->kontainerService->getKontainerMediaTypeNames()));
    $this->messenger()->addStatus($this->t('All of the Kontainer media types have been uninstalled.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
