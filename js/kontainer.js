// @todo "Drupalize" it (behaviors, ...).
let activeEl = null;
(function (Drupal, once) {
  'use strict';
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
})(Drupal, once);
function receiveMessage(event) {
  if (event) {
    postBack(event.data);
  }
}
function postBack(data) {
  if (data != '' && data != null) {
    if (activeEl.getAttribute('data-kontainer-type') === 'cdn') {
      let json = JSON.parse(data);
      // In this phase importing of multiple items at once is not yet
      // supported. If the array consists of multiple items, only the first one
      // will be imported.
      if (Array.isArray(json) === true) {
        json = json[0];
        console.log(json);
      }
      let hiddenFields = activeEl.parentNode.querySelectorAll('input[type=hidden]');
      let url = json.url;
      if (!!json.thumbnailUrl) {
        url = json.thumbnailUrl;
      }
      activeEl.previousElementSibling.getElementsByTagName('input')[0].value = url;
      hiddenFields[0].value = json.type;
      hiddenFields[2].value = json.fileId;
      hiddenFields[3].value = json.urlBaseName;
      if (json.type === 'image') {
        hiddenFields[1].value = json.alt;
      }
    }
    else {
      let url = drupalSettings.kontainer.createMediaPath;
      let ajax = Drupal.ajax({
        url: Drupal.url(url),
        type: 'post',
        dataType: 'json',
        contentType: 'application/json',
        submit: data
      });
      ajax.execute().then(function (response) {
        activeEl.previousElementSibling.getElementsByTagName('input')[0].value = response.media_label + ' (' + response.media_id + ')';
        activeEl.nextElementSibling.value = response.kontainer_file_id;
      });
    }
  }
  self.close();
}

function receive(id) {
  document.getElementById('url').value = id;
}
