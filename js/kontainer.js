(function (Drupal, once, drupalSettings) {
  'use strict';
  let activeEl = null;
  Drupal.behaviors.kontainer = {
    attach: function (context) {
      if (window.addEventListener) {
        window.addEventListener('message', receiveMessage, false);
      }
      const kontainerButtonMedia = once('open_kontainer_once', '[data-kontainer-selector="open-kontainer"]', context);
      kontainerButtonMedia.forEach((el) => {
        el.addEventListener('click', function (event) {
          event.preventDefault();
          activeEl = this;
          let ifrm = document.createElement('IFRAME');
          ifrm.setAttribute('frameborder', '0');
          ifrm.setAttribute('src', drupalSettings.kontainer.kontainerUrl + '/login-cms-redirect?cmsMode=1');
          el.kontainer_window = window.open(ifrm.src, "_blank");
        });
      });
    }
  };
  function receiveMessage(event) {
    if (event) {
      postBack(event.data);
    }
  }
  function postBack(data) {
    if (data !== '' && data !== null) {
      if (activeEl.getAttribute('data-kontainer-type') === 'cdn') {
        let json = JSON.parse(data);
        // In this phase importing of multiple items at once is not yet
        // supported. If the array consists of multiple items, only the first one
        // will be imported.
        if (Array.isArray(json) === true) {
          json = json[0];
        }
        let hiddenFields = activeEl.parentNode.querySelectorAll('input[type=hidden]');
        let url = json.url;
        if (!!json.thumbnailUrl) {
          url = json.thumbnailUrl;
        }
        activeEl.previousElementSibling.getElementsByTagName('input')[0].value = url;
        hiddenFields[0].value = json.type;
        hiddenFields[1].value = json.fileName;
        hiddenFields[2].value = json.fileId;
        hiddenFields[3].value = json.urlBaseName;
      }
      else {
        let url = drupalSettings.kontainer.createMediaPath;
        let ajax = Drupal.ajax({
          url: Drupal.url(url) + '?token=' + drupalSettings.kontainer.token,
          type: 'post',
          dataType: 'json',
          contentType: 'application/json',
          submit: data,
          element: activeEl,
          progress: {
            type: 'throbber',
            message: Drupal.t('Downloading media from Kontainer...'),
          }
        });
        ajax.execute().then(function (response) {
          if (response.media_label !== null && response.media_id !== null && response.kontainer_file_id !== null) {
            activeEl.previousElementSibling.getElementsByTagName('input')[0].value = response.media_label + ' (' + response.media_id + ')';
            activeEl.nextElementSibling.value = response.kontainer_file_id;
          }
          else {
            console.error(Drupal.t('Could not fetch all the data from the response.'));
          }
        });
      }
    }
    self.close();
  }
})(Drupal, once, drupalSettings);
