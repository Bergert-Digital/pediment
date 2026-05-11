(function () {
  'use strict';

  function init() {
    document.querySelectorAll('form.starter-contact-form').forEach(bindForm);
  }

  function bindForm(form) {
    var restUrl    = form.getAttribute('data-rest-url');
    var nonce      = form.getAttribute('data-rest-nonce');
    var successMsg = form.getAttribute('data-success') || 'Thanks — we will be in touch.';
    var statusEl   = form.querySelector('.starter-contact-form__status');
    var submitBtn  = form.querySelector('.starter-contact-form__submit');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!restUrl) { return; }

      var payload = {
        name:     valueOf(form, 'name'),
        email:    valueOf(form, 'email'),
        phone:    valueOf(form, 'phone'),
        message:  valueOf(form, 'message'),
        hp_field: valueOf(form, 'hp_field'),
        _t:       valueOf(form, '_t'),
      };

      submitBtn.disabled = true;
      showStatus(statusEl, '', null);

      fetch(restUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': nonce || '',
        },
        body: JSON.stringify(payload),
      })
        .then(function (res) { return res.json().then(function (body) { return { res: res, body: body }; }); })
        .then(function (out) {
          submitBtn.disabled = false;
          if (out.res.ok && out.body && out.body.ok) {
            form.querySelectorAll('input,textarea,button').forEach(function (el) { el.disabled = true; });
            showStatus(statusEl, successMsg, 'success');
          } else {
            var msg = (out.body && out.body.message) ? out.body.message : 'Something went wrong. Please try again.';
            showStatus(statusEl, msg, 'error');
          }
        })
        .catch(function () {
          submitBtn.disabled = false;
          showStatus(statusEl, 'Network error. Please try again.', 'error');
        });
    });
  }

  function valueOf(form, name) {
    var el = form.querySelector('[name="' + name + '"]');
    return el ? el.value : '';
  }

  function showStatus(el, msg, state) {
    if (!el) { return; }
    if (!msg) { el.hidden = true; el.textContent = ''; el.removeAttribute('data-state'); return; }
    el.hidden = false;
    el.textContent = msg;
    if (state) { el.setAttribute('data-state', state); } else { el.removeAttribute('data-state'); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
