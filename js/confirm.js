(function () {
  'use strict';

  // ------------------------------------------------------------------------
  // PMSConfirm — Promise-based Bootstrap confirmation modal, shared by both
  // the customer (js/app.js) and admin (js/admin.js) shells. Originally
  // built as admin-only confirmAction() (Admin Dashboard phase, Step 6) to
  // replace every browser confirm() in admin code; extracted here unchanged
  // (System Enhancements initiative, Step 1) so the client side can reuse it
  // too, without either shell learning about the other's file. js/admin.js
  // keeps window.confirmAction as a one-line alias to this, so its five
  // existing call sites need no edit.
  //
  // Builds one shared modal lazily and reuses it. Resolves true if the
  // action was confirmed, false otherwise (Cancel, backdrop click, Esc).
  // Focus returns to the triggering element on close either way.
  // ------------------------------------------------------------------------
  function ensureConfirmModal() {
    var $existing = $('#adminConfirmModal');
    if ($existing.length) return $existing;

    var html =
      '<div class="modal fade" id="adminConfirmModal" tabindex="-1" role="alertdialog" ' +
      'aria-labelledby="adminConfirmModalTitle" aria-describedby="adminConfirmModalBody" aria-hidden="true">' +
      '  <div class="modal-dialog modal-sm">' +
      '    <div class="modal-content">' +
      '      <div class="modal-header">' +
      '        <h5 class="modal-title" id="adminConfirmModalTitle"></h5>' +
      '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
      '      </div>' +
      '      <div class="modal-body" id="adminConfirmModalBody"></div>' +
      '      <div class="modal-footer">' +
      '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
      '        <button type="button" class="btn btn-danger" id="adminConfirmModalConfirmBtn">Confirm</button>' +
      '      </div>' +
      '    </div>' +
      '  </div>' +
      '</div>';
    var $built = $(html).appendTo(document.body);
    // Bootstrap's Modal._showElement() unconditionally sets role="dialog" on
    // every show, clobbering the alertdialog role set above — reassert it
    // each time the modal is shown.
    $built.on('shown.bs.modal', function () {
      $built.attr('role', 'alertdialog');
    });
    return $built;
  }

  function confirmAction(options) {
    var opts = options || {};
    var $modal = ensureConfirmModal();
    $modal.find('#adminConfirmModalTitle').text(opts.title || 'Please confirm');
    $modal.find('#adminConfirmModalBody').text(opts.body || 'Are you sure?');

    var variant = opts.variant || 'danger';
    var $confirmBtn = $modal.find('#adminConfirmModalConfirmBtn');
    $confirmBtn.attr('class', 'btn btn-' + variant).text(opts.confirmLabel || 'Confirm');

    var $trigger = $(document.activeElement);
    var bsModal = bootstrap.Modal.getOrCreateInstance($modal.get(0));

    return new Promise(function (resolve) {
      var settled = false;
      function settle(value) {
        if (settled) return;
        settled = true;
        $confirmBtn.off('click.adminConfirm');
        $modal.off('hidden.bs.modal.adminConfirm');
        resolve(value);
      }

      $confirmBtn.on('click.adminConfirm', function () {
        settle(true);
        bsModal.hide();
      });
      $modal.on('hidden.bs.modal.adminConfirm', function () {
        settle(false);
        if ($trigger && $trigger.length && document.body.contains($trigger.get(0))) {
          $trigger.trigger('focus');
        }
      });

      bsModal.show();
    });
  }

  window.PMSConfirm = confirmAction;
})();
