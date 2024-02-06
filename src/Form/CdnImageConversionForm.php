<?php

namespace Drupal\kontainer\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Cdn Image Conversion form.
 *
 * @property \Drupal\kontainer\CdnImageConversionInterface $entity
 */
class CdnImageConversionForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {

    $form = parent::form($form, $form_state);

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $this->entity->label(),
      '#description' => $this->t('Label for the cdn image conversion.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->entity->id(),
      '#machine_name' => [
        'exists' => '\Drupal\kontainer\Entity\CdnImageConversion::load',
      ],
      '#disabled' => !$this->entity->isNew(),
    ];

    $form['template_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Kontainer template id'),
      '#default_value' => $this->entity->get('template_id'),
      '#description' => $this->t('The Kontainer download template id.'),
      '#required' => TRUE,
    ];

    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Image format:'),
      '#options' => [
        'jpg' => $this->t('JPEG'),
        'png' => $this->t('PNG'),
        'webp' => $this->t('WEBP'),
        'bmp' => $this->t('BMP'),
      ],
      '#default_value' => $this->entity->get('format'),
      '#required' => TRUE,
    ];

    $form['dimensions'] = [
      '#type' => 'number',
      '#title' => $this->t('Dimensions'),
      '#default_value' => $this->entity->get('dimensions') ?? 0,
      '#description' => $this->t('The width of the Kontainer template.'),
      '#min' => 0,
      '#required' => TRUE,
    ];

    $image_styles = image_style_options();

    $form['image_style'] = [
      '#title' => $this->t('Drupal image style'),
      '#type' => 'select',
      '#default_value' => $this->entity->get('image_style'),
      '#options' => $image_styles,
      '#required' => TRUE,
      '#description' => $this->t('Select the Drupal image style you wish to map the Kontainer template to.'),
    ];

    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $this->entity->status(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $result = parent::save($form, $form_state);
    $message_args = ['%label' => $this->entity->label()];
    $message = $result == SAVED_NEW
      ? $this->t('Created new cdn image conversion %label.', $message_args)
      : $this->t('Updated cdn image conversion %label.', $message_args);
    $this->messenger()->addStatus($message);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
