<?php

namespace Drupal\kontainer\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_library\MediaLibraryUiBuilder;
use Drupal\media_library\Plugin\Field\FieldWidget\MediaLibraryWidget;

/**
 * Plugin implementation of the 'kontainer_media_library_widget' widget.
 *
 * @FieldWidget(
 *   id = "kontainer_media_library_widget",
 *   label = @Translation("Kontainer Media library"),
 *   description = @Translation("Allows you to select Kontainer items from the media library."),
 *   field_types = {
 *     "kontainer_media_reference"
 *   },
 *   multiple_values = TRUE,
 * )
 */
class KontainerMediaLibraryWidget extends MediaLibraryWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $element['open_button']['#media_library_state']->set('widget_id', $this->getPluginId());
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function openMediaLibrary(array $form, FormStateInterface $form_state) {
    $triggeringElement = $form_state->getTriggeringElement();
    $libraryUi = \Drupal::service('media_library.ui_builder')->buildUi($triggeringElement['#media_library_state']);
    $dialogOptions = MediaLibraryUiBuilder::dialogOptions();
    // Add widget_id to each Media type url available in dialog.
    // We need it in order to attach the Kontainer select button in the form.
    if (array_key_exists('menu', $libraryUi) && is_array($libraryUi['menu']) && array_key_exists('#links', $libraryUi['menu']) && is_array($libraryUi['menu']['#links'])) {
      if (array_key_exists('#media_library_state', $triggeringElement)) {
        /** @var \Drupal\media_library\MediaLibraryState $mediaLibrayState */
        $mediaLibraryState = $triggeringElement['#media_library_state'];
        foreach ($libraryUi['menu']['#links'] as $link) {
          $options = $link['url']->getOptions();
          if (array_key_exists('query', $options)) {
            $options['query']['widget_id'] = $mediaLibraryState->get('widget_id') ?? '';
            $link['url']->setOptions($options);
          }
        }
      }
    }
    return (new AjaxResponse())
      ->addCommand(new OpenModalDialogCommand($dialogOptions['title'], $libraryUi, $dialogOptions));
  }

}
