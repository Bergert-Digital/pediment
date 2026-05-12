(function () {
  'use strict';

  function init() {
    bindImagePickers();
    bindSocialRepeater();
  }

  function bindImagePickers() {
    document.querySelectorAll('.starter-brand-image').forEach(function (root) {
      var idInput  = root.querySelector('.starter-brand-image__id');
      var preview  = root.querySelector('.starter-brand-image__preview');
      var pick     = root.querySelector('.starter-brand-image__pick');
      var clear    = root.querySelector('.starter-brand-image__clear');

      var frame = null;

      pick.addEventListener('click', function (e) {
        e.preventDefault();
        if (!frame) {
          frame = wp.media({
            title: 'Select image',
            button: { text: 'Use this image' },
            library: { type: 'image' },
            multiple: false,
          });
          frame.on('select', function () {
            var att = frame.state().get('selection').first().toJSON();
            idInput.value = String(att.id);
            preview.src = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
            preview.hidden = false;
            clear.hidden = false;
          });
        }
        frame.open();
      });

      clear.addEventListener('click', function (e) {
        e.preventDefault();
        idInput.value = '0';
        preview.hidden = true;
        clear.hidden = true;
      });
    });
  }

  function bindSocialRepeater() {
    document.querySelectorAll('.starter-brand-social').forEach(function (root) {
      var rows = root.querySelector('.starter-brand-social__rows');
      var addBtn = root.querySelector('.starter-brand-social__add');
      var template = root.querySelector('.starter-brand-social__template');

      addBtn.addEventListener('click', function (e) {
        e.preventDefault();
        var nextIndex = rows.querySelectorAll('.starter-brand-social__row').length;
        var fragment = template.content.cloneNode(true);
        fragment.querySelectorAll('input').forEach(function (input) {
          input.name = input.name.replace(/__INDEX__/g, String(nextIndex));
        });
        rows.appendChild(fragment);
      });

      rows.addEventListener('click', function (e) {
        if (e.target.classList.contains('starter-brand-social__remove')) {
          e.preventDefault();
          var row = e.target.closest('.starter-brand-social__row');
          if (row) {
            row.parentNode.removeChild(row);
          }
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
