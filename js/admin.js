(function () {
  'use strict';

  // ------------------------------------------------------------------------
  // AdminValidation — mirrors js/app.js's AuthValidation (Customer Dashboard
  // phase) shape and behavior, but is a separate, deliberately small
  // implementation: js/app.js is 62KB, is not IIFE-wrapped (its identifiers
  // are global), and its top-level $(function(){...}) binds customer-only
  // handlers (booking modal, navbar auth, voucher UI, #contactForm) against
  // elements that don't exist on admin pages — loading it here would either
  // throw on missing elements or add 62KB of dead weight for nothing admin
  // pages use. Same field-level convention: .is-invalid + a sibling
  // .invalid-feedback found by the '<fieldId>Feedback' naming convention,
  // set on blur, cleared/re-checked live on input once a field is touched.
  // ------------------------------------------------------------------------
  var AdminValidation = (function () {
    function feedbackFor($input) {
      return $('#' + $input.attr('id') + 'Feedback');
    }

    function setInvalid($input, message) {
      $input.addClass('is-invalid').attr('aria-invalid', 'true')
        .attr('aria-describedby', $input.attr('id') + 'Feedback');
      feedbackFor($input).text(message);
    }

    function clearField($input) {
      $input.removeClass('is-invalid').removeAttr('aria-invalid').removeAttr('aria-describedby');
      feedbackFor($input).text('');
    }

    function clearForm($form) {
      $form.find('.form-control, .form-select').each(function () {
        clearField($(this));
      });
    }

    // Returns the first failing rule's message, or null if all rules pass.
    function firstError(value, rules) {
      for (var i = 0; i < rules.length; i++) {
        if (!rules[i].test(value)) return rules[i].message;
      }
      return null;
    }

    // Wires blur (initial check) then input (live correction, once touched).
    function attachRealTime($input, rules) {
      var touched = false;

      function check() {
        var error = firstError($input.val(), rules);
        if (error) {
          setInvalid($input, error);
        } else {
          clearField($input);
        }
        return !error;
      }

      $input.on('blur', function () {
        touched = true;
        check();
      });
      $input.on('input', function () {
        if (touched) check();
      });

      return check;
    }

    // Validates every field in fieldConfigs = [{ $input, rules }], marking
    // each valid/invalid and focusing the first invalid one. Returns true
    // only if every field passed.
    function validateAll(fieldConfigs) {
      var $firstInvalid = null;
      var allValid = true;
      fieldConfigs.forEach(function (cfg) {
        var error = firstError(cfg.$input.val(), cfg.rules);
        if (error) {
          setInvalid(cfg.$input, error);
          allValid = false;
          if (!$firstInvalid) $firstInvalid = cfg.$input;
        } else {
          clearField(cfg.$input);
        }
      });
      if ($firstInvalid) $firstInvalid.trigger('focus');
      return allValid;
    }

    var rules = {
      required: function (message) {
        return { test: function (v) { return String(v == null ? '' : v).trim().length > 0; }, message: message };
      },
      email: function (message) {
        return { test: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v).trim()); }, message: message };
      },
      minLength: function (min, message) {
        return { test: function (v) { return String(v).trim().length >= min; }, message: message };
      },
      numeric: function (message) {
        return { test: function (v) { return v !== '' && v != null && !isNaN(v); }, message: message };
      },
      min: function (min, message) {
        return { test: function (v) { return v !== '' && v != null && !isNaN(v) && parseFloat(v) >= min; }, message: message };
      }
    };

    return {
      attachRealTime: attachRealTime,
      validateAll: validateAll,
      clearField: clearField,
      clearForm: clearForm,
      rules: rules
    };
  })();

  // ------------------------------------------------------------------------
  // In-modal / in-page feedback. $alert must be the specific jQuery-wrapped
  // .js-modal-alert element for this action's context (a modal's own alert
  // region, or a page-level #pageAlert/#dashboardAlert for row-level
  // actions that have no modal). The element is never re-queried, matching
  // js/app.js's showAuthError() convention.
  // ------------------------------------------------------------------------
  function showAdminError($alert, message) {
    if (!$alert || !$alert.length) return;
    $alert.find('.js-modal-alert-text').text(message);
    $alert.removeClass('d-none alert-success').addClass('alert-danger');
    $alert.trigger('focus');
  }

  function showAdminSuccess($alert, message) {
    if (!$alert || !$alert.length) return;
    $alert.find('.js-modal-alert-text').text(message);
    $alert.removeClass('d-none alert-danger').addClass('alert-success');
    $alert.trigger('focus');
  }

  // Manual close (not Bootstrap's data-bs-dismiss="alert", which removes
  // the element from the DOM — these alert regions are permanent template
  // markup meant to be reused by later actions).
  $(document).on('click', '.js-modal-alert-close', function () {
    $(this).closest('.js-modal-alert').addClass('d-none');
  });

  // ------------------------------------------------------------------------
  // G2 (System Enhancements initiative, Step 4): #adminLogoutBtn
  // (includes/admin_sidebar.php, shared by all six admin pages) previously
  // had no JS handler at all — a bare <a href="logout.php">. This is the
  // first handler ever bound to it. Delegated on document (not gated on a
  // page-specific .length check like the blocks below) since the sidebar is
  // present on every admin page this file loads on. The plain href is left
  // untouched in the markup so logout still works if this handler somehow
  // fails to attach — e.preventDefault() only runs once execution reaches
  // this function.
  // ------------------------------------------------------------------------
  $(document).on('click', '#adminLogoutBtn', function (e) {
    e.preventDefault();
    var href = $(this).attr('href');
    confirmAction({
      title: 'Log out of the admin panel?',
      confirmLabel: 'Log Out',
      variant: 'warning'
    }).then(function (confirmed) {
      if (confirmed) window.location = href;
    });
  });

  // ------------------------------------------------------------------------
  // Admin Dashboard phase, Step 9: the four "Edit X" row-action modals
  // (editUserModal, editVehicleModal, editVoucherModal, editTransactionModal)
  // are all opened via bootstrap.Modal.getOrCreateInstance(...).show() from a
  // row button's click handler, not via a data-bs-toggle="modal" attribute —
  // Bootstrap only auto-restores focus to the trigger on hide when it was
  // the one that tracked the trigger via data-bs-toggle, so none of these
  // four previously returned focus to the row's Edit button on close.
  // Confirmed live (keyboard test, DevTools): after Escape, focus stayed on
  // the modal's now-hidden first input instead of returning anywhere
  // visible. confirmAction() above already had this correctly (see its own
  // $trigger/'hidden.bs.modal.adminConfirm' handler) — this mirrors that
  // same pattern for the four modals opened outside confirmAction().
  // ------------------------------------------------------------------------
  function restoreFocusOnHide($modal, $trigger) {
    $modal.one('hidden.bs.modal', function () {
      if ($trigger && $trigger.length && document.body.contains($trigger.get(0))) {
        $trigger.trigger('focus');
      }
    });
  }

  // ------------------------------------------------------------------------
  // confirmAction — moved to js/confirm.js (System Enhancements initiative,
  // Step 1) as window.PMSConfirm, so the customer shell can reuse it without
  // loading this file. window.confirmAction below is kept as a one-line
  // alias so every existing call site in this file is unchanged.
  // js/confirm.js must be loaded on this page before this point.
  // ------------------------------------------------------------------------

  // ------------------------------------------------------------------------
  // Page-specific wiring. Every block is gated on the presence of its own
  // elements so this single file is safe to load on all five admin pages.
  // ------------------------------------------------------------------------
  $(function () {

    // ---- Users (admin_users.php) -----------------------------------------
    if ($('#usersTable').length) {
      $('#usersTable').DataTable({ order: [[0, 'desc']] });
    }

    if ($('#editUserForm').length) {
      var $editUserModal = $('#editUserModal');
      var $editUserAlert = $editUserModal.find('.js-modal-alert');

      AdminValidation.attachRealTime($('#editUserName'), [
        AdminValidation.rules.required('Please enter the user\'s name.')
      ]);
      AdminValidation.attachRealTime($('#editUserEmail'), [
        AdminValidation.rules.required('Please enter the user\'s email address.'),
        AdminValidation.rules.email('Please enter a valid email address.')
      ]);

      $(document).on('click', '.edit-user', function () {
        var userId = $(this).data('id');
        var userName = $(this).data('name');
        var userEmail = $(this).data('email');

        AdminValidation.clearForm($('#editUserForm'));
        $editUserAlert.addClass('d-none');
        $('#editUserId').val(userId);
        $('#editUserName').val(userName);
        $('#editUserEmail').val(userEmail);

        restoreFocusOnHide($editUserModal, $(this));
        bootstrap.Modal.getOrCreateInstance($editUserModal.get(0)).show();
      });

      $('#editUserForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var isValid = AdminValidation.validateAll([
          { $input: $('#editUserName'), rules: [AdminValidation.rules.required('Please enter the user\'s name.')] },
          {
            $input: $('#editUserEmail'), rules: [
              AdminValidation.rules.required('Please enter the user\'s email address.'),
              AdminValidation.rules.email('Please enter a valid email address.')
            ]
          }
        ]);
        if (!isValid) return;

        var submitBtn = $form.find('button[type="submit"]');
        PMSMotion.setButtonLoading(submitBtn, true);
        $editUserAlert.addClass('d-none');

        $.ajax({
          url: 'admin_update_user.php',
          type: 'POST',
          data: $form.serialize(),
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              location.reload();
            } else {
              PMSMotion.setButtonLoading(submitBtn, false);
              showAdminError($editUserAlert, response.error || 'Failed to update user.');
            }
          },
          error: function () {
            PMSMotion.setButtonLoading(submitBtn, false);
            showAdminError($editUserAlert, 'An error occurred while updating the user.');
          }
        });
      });
    }

    if ($('.delete-user').length || $('#usersTable').length) {
      var $usersPageAlert = $('#pageAlert');

      $(document).on('click', '.delete-user', function () {
        var $btn = $(this);
        var userId = $btn.data('id');

        confirmAction({
          title: 'Delete user',
          body: 'Are you sure you want to delete this user? This action cannot be undone.',
          confirmLabel: 'Delete',
          variant: 'danger'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $usersPageAlert.addClass('d-none');

          $.ajax({
            url: 'admin_delete_user.php',
            type: 'POST',
            data: { user_id: userId },
            dataType: 'json',
            success: function (response) {
              if (response.success) {
                $btn.closest('tr').fadeOut(400, function () { $(this).remove(); });
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($usersPageAlert, response.error || 'Failed to delete user.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($usersPageAlert, 'An error occurred while deleting the user.');
            }
          });
        });
      });
    }

    // ---- Vehicles (admin_vehicles.php) ------------------------------------
    if ($('#vehiclesTable').length) {
      $('#vehiclesTable').DataTable({
        order: [[0, 'desc']],
        columnDefs: [
          { targets: [6, 7], orderable: false, searchable: false }
        ]
      });
    }

    if ($('#addVehicleForm').length) {
      AdminValidation.attachRealTime($('#addTitle'), [
        AdminValidation.rules.required('Please enter the car title.')
      ]);
      AdminValidation.attachRealTime($('#addPrice'), [
        AdminValidation.rules.required('Please enter a price per day.'),
        AdminValidation.rules.min(0, 'Price must be zero or greater.')
      ]);
      AdminValidation.attachRealTime($('#addUnits'), [
        AdminValidation.rules.required('Please enter the number of available units.'),
        AdminValidation.rules.min(0, 'Units must be zero or greater.')
      ]);
      AdminValidation.attachRealTime($('#addSeats'), [
        AdminValidation.rules.required('Please enter the number of seats.'),
        AdminValidation.rules.min(1, 'Seats must be at least 1.')
      ]);

      $('#addVehicleForm').on('submit', function (e) {
        var isValid = AdminValidation.validateAll([
          { $input: $('#addTitle'), rules: [AdminValidation.rules.required('Please enter the car title.')] },
          {
            $input: $('#addPrice'), rules: [
              AdminValidation.rules.required('Please enter a price per day.'),
              AdminValidation.rules.min(0, 'Price must be zero or greater.')
            ]
          },
          {
            $input: $('#addUnits'), rules: [
              AdminValidation.rules.required('Please enter the number of available units.'),
              AdminValidation.rules.min(0, 'Units must be zero or greater.')
            ]
          },
          {
            $input: $('#addSeats'), rules: [
              AdminValidation.rules.required('Please enter the number of seats.'),
              AdminValidation.rules.min(1, 'Seats must be at least 1.')
            ]
          }
        ]);
        if (!isValid) {
          e.preventDefault();
          return;
        }
        PMSMotion.setButtonLoading($(this).find('button[type="submit"]'), true);
      });
    }

    if ($('#editVehicleForm').length) {
      AdminValidation.attachRealTime($('#editTitle'), [
        AdminValidation.rules.required('Please enter the car title.')
      ]);
      AdminValidation.attachRealTime($('#editPrice'), [
        AdminValidation.rules.required('Please enter a price per day.'),
        AdminValidation.rules.min(0, 'Price must be zero or greater.')
      ]);
      AdminValidation.attachRealTime($('#editUnits'), [
        AdminValidation.rules.required('Please enter the number of available units.'),
        AdminValidation.rules.min(0, 'Units must be zero or greater.')
      ]);
      AdminValidation.attachRealTime($('#editSeats'), [
        AdminValidation.rules.required('Please enter the number of seats.'),
        AdminValidation.rules.min(1, 'Seats must be at least 1.')
      ]);

      $(document).on('click', '.editVehicleBtn', function () {
        AdminValidation.clearForm($('#editVehicleForm'));
        $('#editVehicleModal').find('.js-modal-alert').addClass('d-none');

        $('#editVehicleId').val($(this).data('id'));
        $('#editTitle').val($(this).data('title'));
        $('#editCategory').val($(this).data('category'));
        $('#editPrice').val($(this).data('price'));
        $('#editUnits').val($(this).data('units'));
        $('#editSeats').val($(this).data('seats'));
        $('#editFuel').val($(this).data('fuel'));
        $('#editTransmission').val($(this).data('transmission'));
        $('#editActive').prop('checked', $(this).data('active') == 1);

        restoreFocusOnHide($('#editVehicleModal'), $(this));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editVehicleModal')).show();
      });

      $('#editVehicleForm').on('submit', function (e) {
        var isValid = AdminValidation.validateAll([
          { $input: $('#editTitle'), rules: [AdminValidation.rules.required('Please enter the car title.')] },
          {
            $input: $('#editPrice'), rules: [
              AdminValidation.rules.required('Please enter a price per day.'),
              AdminValidation.rules.min(0, 'Price must be zero or greater.')
            ]
          },
          {
            $input: $('#editUnits'), rules: [
              AdminValidation.rules.required('Please enter the number of available units.'),
              AdminValidation.rules.min(0, 'Units must be zero or greater.')
            ]
          },
          {
            $input: $('#editSeats'), rules: [
              AdminValidation.rules.required('Please enter the number of seats.'),
              AdminValidation.rules.min(1, 'Seats must be at least 1.')
            ]
          }
        ]);
        if (!isValid) {
          e.preventDefault();
          return;
        }
        PMSMotion.setButtonLoading($(this).find('button[type="submit"]'), true);
      });
    }

    $(document).on('click', '.delete-vehicle', function (e) {
      e.preventDefault();
      var href = $(this).attr('href');
      var title = $(this).data('title');

      confirmAction({
        title: 'Delete vehicle',
        body: 'Delete ' + title + '? This will also permanently delete every booking made for this vehicle and every transaction on those bookings.',
        confirmLabel: 'Delete',
        variant: 'danger'
      }).then(function (confirmed) {
        if (confirmed) window.location.href = href;
      });
    });

    // ---- Vouchers (admin_vouchers.php) ------------------------------------
    if ($('#addVoucherForm').length) {
      var $addVoucherAlert = $('#addVoucherModal').find('.js-modal-alert');
      var $editVoucherAlert = $('#editVoucherModal').find('.js-modal-alert');
      var $vouchersPageAlert = $('#pageAlert');

      var voucherFieldRules = function (prefix) {
        return [
          { $input: $('#' + prefix + 'code'), rules: [AdminValidation.rules.required('Please enter a voucher code.')] },
          {
            $input: $('#' + prefix + 'discount_pct'), rules: [
              AdminValidation.rules.required('Please enter a discount percentage.'),
              AdminValidation.rules.min(0, 'Discount percentage must be zero or greater.')
            ]
          },
          {
            $input: $('#' + prefix + 'discount_amount'), rules: [
              AdminValidation.rules.required('Please enter a discount amount.'),
              AdminValidation.rules.min(0, 'Discount amount must be zero or greater.')
            ]
          },
          {
            $input: $('#' + prefix + 'usage_limit'), rules: [
              AdminValidation.rules.required('Please enter a usage limit.'),
              AdminValidation.rules.min(1, 'Usage limit must be at least 1.')
            ]
          }
        ];
      };

      voucherFieldRules('').forEach(function (cfg) { AdminValidation.attachRealTime(cfg.$input, cfg.rules); });
      voucherFieldRules('edit_').forEach(function (cfg) { AdminValidation.attachRealTime(cfg.$input, cfg.rules); });

      $('#addVoucherForm').on('submit', function (e) {
        e.preventDefault();
        if (!AdminValidation.validateAll(voucherFieldRules(''))) return;

        var formData = new FormData($('#addVoucherForm').get(0));
        formData.append('action', 'create');
        var $btn = $('#saveVoucher');
        PMSMotion.setButtonLoading($btn, true);
        $addVoucherAlert.addClass('d-none');

        $.ajax({
          url: 'admin_vouchers.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function (result) {
            if (result.success) {
              location.reload();
            } else {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($addVoucherAlert, result.message || 'Error creating voucher.');
            }
          },
          error: function () {
            PMSMotion.setButtonLoading($btn, false);
            showAdminError($addVoucherAlert, 'An error occurred while creating the voucher.');
          }
        });
      });

      $(document).on('click', '.edit-voucher', function () {
        var $button = $(this);
        AdminValidation.clearForm($('#editVoucherForm'));
        $editVoucherAlert.addClass('d-none');

        $('#edit_id').val($button.data('id'));
        $('#edit_code').val($button.data('code'));
        $('#edit_discount_pct').val($button.data('discount-pct'));
        $('#edit_discount_amount').val($button.data('discount-amount'));
        $('#edit_usage_limit').val($button.data('usage-limit'));
        restoreFocusOnHide($('#editVoucherModal'), $button);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editVoucherModal')).show();
      });

      $('#editVoucherForm').on('submit', function (e) {
        e.preventDefault();
        if (!AdminValidation.validateAll(voucherFieldRules('edit_'))) return;

        var formData = new FormData($('#editVoucherForm').get(0));
        formData.append('action', 'update');
        var $btn = $('#updateVoucher');
        PMSMotion.setButtonLoading($btn, true);
        $editVoucherAlert.addClass('d-none');

        $.ajax({
          url: 'admin_vouchers.php',
          type: 'POST',
          data: formData,
          processData: false,
          contentType: false,
          dataType: 'json',
          success: function (result) {
            if (result.success) {
              location.reload();
            } else {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($editVoucherAlert, result.message || 'Error updating voucher.');
            }
          },
          error: function () {
            PMSMotion.setButtonLoading($btn, false);
            showAdminError($editVoucherAlert, 'An error occurred while updating the voucher.');
          }
        });
      });

      $(document).on('click', '.delete-voucher', function () {
        var $btn = $(this);
        var id = $btn.data('id');

        confirmAction({
          title: 'Delete voucher',
          body: 'Are you sure you want to delete this voucher?',
          confirmLabel: 'Delete',
          variant: 'danger'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $vouchersPageAlert.addClass('d-none');

          $.ajax({
            url: 'admin_vouchers.php',
            type: 'POST',
            data: { action: 'delete', id: id },
            dataType: 'json',
            success: function (result) {
              if (result.success) {
                location.reload();
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($vouchersPageAlert, result.message || 'Error deleting voucher.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($vouchersPageAlert, 'An error occurred while deleting the voucher.');
            }
          });
        });
      });
    }

    // ---- Transactions (view-all-data.php) ---------------------------------
    if ($('#transactionsTable').length) {
      $('#transactionsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        language: {
          search: 'Search bookings:',
          emptyTable: 'No transactions found',
          zeroRecords: 'No matching bookings found'
        },
        columnDefs: [
          { targets: -1, orderable: false, searchable: false }
        ]
      });
      $('#transactionsTable_filter input').attr('aria-label', 'Search bookings');
    }

    if ($('#editTransactionForm').length) {
      var $editTransactionAlert = $('#editTransactionModal').find('.js-modal-alert');

      AdminValidation.attachRealTime($('#editPickupTime'), [
        AdminValidation.rules.required('Please enter a pickup time.')
      ]);
      AdminValidation.attachRealTime($('#editDropoffTime'), [
        AdminValidation.rules.required('Please enter a dropoff time.')
      ]);

      $(document).on('click', '.edit-transaction', function () {
        var id = $(this).data('id');
        var pickup = $(this).data('pickup');
        var dropoff = $(this).data('dropoff');

        AdminValidation.clearForm($('#editTransactionForm'));
        $editTransactionAlert.addClass('d-none');

        $('#editTransactionId').val(id);
        $('#editPickupTime').val(String(pickup).replace(' ', 'T').substr(0, 5));
        $('#editDropoffTime').val(String(dropoff).replace(' ', 'T').substr(0, 5));

        restoreFocusOnHide($('#editTransactionModal'), $(this));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('editTransactionModal')).show();
      });

      $('#editTransactionForm').on('submit', function (e) {
        e.preventDefault();
        var isValid = AdminValidation.validateAll([
          { $input: $('#editPickupTime'), rules: [AdminValidation.rules.required('Please enter a pickup time.')] },
          { $input: $('#editDropoffTime'), rules: [AdminValidation.rules.required('Please enter a dropoff time.')] }
        ]);
        if (!isValid) return;

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');

        // G8 (System Enhancements initiative, Step 4): confirm before
        // mutating a customer's live booking. The customer isn't notified of
        // this change by any existing mechanism — stated plainly rather than
        // silently, since it's genuinely true. Opens over #editTransactionModal
        // (a nested-modal case); verified empirically (same method as Step
        // 3's G3) that no defensive handling was needed.
        confirmAction({
          title: 'Update booking times?',
          body: "Update this booking's pickup and drop-off times? The customer is not notified of this change.",
          confirmLabel: 'Update',
          variant: 'warning'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $editTransactionAlert.addClass('d-none');

          $.ajax({
            url: 'update_booking_time.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (response) {
              if (response.success) {
                location.reload();
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($editTransactionAlert, response.error || 'Failed to update booking.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($editTransactionAlert, 'An error occurred while updating the booking.');
            }
          });
        });
      });
    }

    if ($('.delete-transaction').length || $('#transactionsTable').length) {
      var $transactionsPageAlert = $('#pageAlert');

      $(document).on('click', '.delete-transaction', function () {
        var $btn = $(this);
        var id = $btn.data('id');
        var row = $btn.closest('tr');

        confirmAction({
          title: 'Delete transaction',
          body: 'Are you sure you want to delete this transaction? This action cannot be undone and will also delete its associated payment record.',
          confirmLabel: 'Delete',
          variant: 'danger'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $transactionsPageAlert.addClass('d-none');

          $.ajax({
            url: 'delete_booking.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function (response) {
              if (response.success) {
                row.fadeOut(400, function () { $(this).remove(); });
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($transactionsPageAlert, response.error || 'Failed to delete transaction.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($transactionsPageAlert, 'An error occurred while deleting the transaction.');
            }
          });
        });
      });
    }

    // ---- Confirm booking — shared by view-all-data.php (row buttons) and
    // admin-dashboard.php (Recent Transactions card). Each page supplies its
    // own alert region: #pageAlert on view-all-data.php, #dashboardAlert on
    // admin-dashboard.php.
    if ($('.confirm-transaction').length || $('#transactionsTable').length || $('#messagesTable').length) {
      var $confirmPageAlert = $('#pageAlert').length ? $('#pageAlert') : $('#dashboardAlert');

      $(document).on('click', '.confirm-transaction', function () {
        var $btn = $(this);
        var id = $btn.data('id');

        confirmAction({
          title: 'Confirm booking',
          body: 'Confirm this booking?',
          confirmLabel: 'Confirm',
          variant: 'primary'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $confirmPageAlert.addClass('d-none');

          $.ajax({
            url: 'admin_confirm_booking.php',
            type: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function (response) {
              if (response.success) {
                location.reload();
              } else {
                PMSMotion.setButtonLoading($btn, false);
                showAdminError($confirmPageAlert, response.error || 'Failed to confirm booking.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($confirmPageAlert, 'An error occurred while confirming the booking.');
            }
          });
        });
      });
    }

    // ---- Dashboard (admin-dashboard.php) -----------------------------------
    if ($('#messagesTable').length) {
      $('#messagesTable').DataTable({ order: [[0, 'desc']] });
    }

    // ---- Settings (admin_settings.php) -------------------------------------
    if ($('#profileForm').length) {
      var $settingsAlert = $('#settingsAlert');

      // G7 (System Enhancements initiative, Step 4): admin_settings.php
      // server-renders the current email straight into #settingsEmail's
      // value attribute (no separate display element, unlike the client
      // side's #profileEmailDisplay) — so the page-load value, captured
      // once here, is the "currently saved" reference to diff a submission
      // against. Updated on every successful save so a second submission in
      // the same page session diffs against the latest saved value, not the
      // original page-load one.
      var lastSavedEmail = $('#settingsEmail').val();

      AdminValidation.attachRealTime($('#settingsName'), [
        AdminValidation.rules.required('Please enter your name.')
      ]);
      AdminValidation.attachRealTime($('#settingsEmail'), [
        AdminValidation.rules.required('Please enter your email address.'),
        AdminValidation.rules.email('Please enter a valid email address.')
      ]);

      function submitProfileForm($form, $btn) {
        PMSMotion.setButtonLoading($btn, true);
        $settingsAlert.addClass('d-none');

        $.ajax({
          url: 'admin_update_profile.php',
          type: 'POST',
          data: $form.serialize(),
          dataType: 'json',
          success: function (response) {
            PMSMotion.setButtonLoading($btn, false);
            if (response.success) {
              lastSavedEmail = $('#settingsEmail').val();
              showAdminSuccess($settingsAlert, 'Profile updated successfully.');
            } else {
              showAdminError($settingsAlert, response.error || 'Failed to update profile.');
            }
          },
          error: function () {
            PMSMotion.setButtonLoading($btn, false);
            showAdminError($settingsAlert, 'An error occurred while updating your profile.');
          }
        });
      }

      $('#profileForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var isValid = AdminValidation.validateAll([
          { $input: $('#settingsName'), rules: [AdminValidation.rules.required('Please enter your name.')] },
          {
            $input: $('#settingsEmail'), rules: [
              AdminValidation.rules.required('Please enter your email address.'),
              AdminValidation.rules.email('Please enter a valid email address.')
            ]
          }
        ]);
        if (!isValid) return;

        var $btn = $form.find('button[type="submit"]');
        var newEmail = $('#settingsEmail').val().trim();

        if (newEmail !== lastSavedEmail) {
          confirmAction({
            title: 'Change your admin email?',
            body: 'Change your admin email to ' + newEmail + '? This is the address you\'ll sign in with.',
            confirmLabel: 'Change Email',
            variant: 'primary'
          }).then(function (confirmed) {
            if (confirmed) submitProfileForm($form, $btn);
          });
        } else {
          submitProfileForm($form, $btn);
        }
      });
    }

    if ($('#passwordForm').length) {
      var $passwordAlert = $('#settingsAlert');

      AdminValidation.attachRealTime($('#currentPassword'), [
        AdminValidation.rules.required('Please enter your current password.')
      ]);
      AdminValidation.attachRealTime($('#newPassword'), [
        AdminValidation.rules.required('Please enter a new password.'),
        AdminValidation.rules.minLength(6, 'New password must be at least 6 characters.')
      ]);
      AdminValidation.attachRealTime($('#confirmNewPassword'), [
        AdminValidation.rules.required('Please confirm your new password.'),
        { test: function (v) { return v === $('#newPassword').val(); }, message: 'Passwords do not match.' }
      ]);

      $('#passwordForm').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var isValid = AdminValidation.validateAll([
          { $input: $('#currentPassword'), rules: [AdminValidation.rules.required('Please enter your current password.')] },
          {
            $input: $('#newPassword'), rules: [
              AdminValidation.rules.required('Please enter a new password.'),
              AdminValidation.rules.minLength(6, 'New password must be at least 6 characters.')
            ]
          },
          {
            $input: $('#confirmNewPassword'), rules: [
              AdminValidation.rules.required('Please confirm your new password.'),
              { test: function (v) { return v === $('#newPassword').val(); }, message: 'Passwords do not match.' }
            ]
          }
        ]);
        if (!isValid) return;

        var $btn = $form.find('button[type="submit"]');

        // G5 (System Enhancements initiative, Step 4): confirm a credential
        // change before submitting it.
        confirmAction({
          title: 'Change your admin password?',
          body: 'You\'ll use the new password the next time you sign in.',
          confirmLabel: 'Change Password',
          variant: 'primary'
        }).then(function (confirmed) {
          if (!confirmed) return;

          PMSMotion.setButtonLoading($btn, true);
          $passwordAlert.addClass('d-none');

          $.ajax({
            url: 'admin_update_profile.php',
            type: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            success: function (response) {
              PMSMotion.setButtonLoading($btn, false);
              if (response.success) {
                showAdminSuccess($passwordAlert, response.message || 'Password updated successfully.');
                AdminValidation.clearForm($form);
                $form.trigger('reset');
              } else {
                showAdminError($passwordAlert, response.error || 'Failed to update password.');
              }
            },
            error: function () {
              PMSMotion.setButtonLoading($btn, false);
              showAdminError($passwordAlert, 'An error occurred while updating your password.');
            }
          });
        });
      });
    }
  });

  window.AdminValidation = AdminValidation;
  window.showAdminError = showAdminError;
  window.showAdminSuccess = showAdminSuccess;
  window.confirmAction = window.PMSConfirm;
})();
