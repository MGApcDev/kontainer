(function (Drupal, once) {
  'use strict';
  Drupal.behaviors.copy_to_clipboard = {
    attach: function (context) {
      const usageUrlButton = once('copy_file_usage_url', '#kontainer-file-usage-url-button', context);
      usageUrlButton.forEach((el) => {
        el.addEventListener('click', function (event) {
          event.preventDefault();
          const usageUrlField = document.getElementById('kontainer-file-usage-url');
          usageUrlField.select();
          // For mobile devices.
          usageUrlField.setSelectionRange(0, 99999);
          try {
            // Works only on a secure origin (https, localhost).
            navigator.clipboard.writeText(usageUrlField.value).then(function () {
              console.log(Drupal.t('The URL was successfully copied to the clipboard.'));
            });
          } catch (error) {
            console.error(Drupal.t('Could not copy text, because you are not on a secure origin.'), error);
          }
        });
      });
    }
  };
})(Drupal, once);
