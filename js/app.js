$(function () {
  // Navbar active highlighting
  var path = window.location.pathname.split('/').pop();
  if (!path) path = 'index.html';
  $('.nav-link').removeClass('active').removeAttr('aria-current');
  $('.nav-link').each(function () {
    var href = $(this).attr('href');
    if (href === path) {
      $(this).addClass('active').attr('aria-current', 'page');
    }
  });
});


  // Datepicker and timepicker
  if (typeof $.fn.datepicker === 'function') {
    $("#rentalDate, #returnDate").datepicker({
      dateFormat: "yy-mm-dd",
      minDate: 0
    });
  }


  if (typeof $.fn.timepicker === 'function') {
    $("#pickupTime, #dropoffTime").timepicker({
      timeFormat: 'HH:mm',
      interval: 30,
      minTime: '00:00',
      maxTime: '23:30',
      dynamic: false,
      dropdown: true,
      scrollbar: true
    });
  }
  


  $(function() {
  let selectedVehicleId = null;
  let previewData = null;


  // Open modal with vehicle ID
  $(document).on('click', '.btn-book', function() {
    selectedVehicleId = $(this).data('vehicle-id');
    $('#vehicle_id').val(selectedVehicleId);
    $('#bookingForm')[0].reset();
    $('#bookingPreview').empty();
    $('#receiptArea').addClass('d-none');
    $('#btnConfirm').prop('disabled', true);
    $('#bookingAlert').addClass('d-none');
    $('#bookingModal').modal('show');
  });


  // Refresh booking pricing preview. Extracted from the old #btnPreview click
  // handler (System Enhancements initiative, Step 2 — see
  // docs/SYSTEM_ENHANCEMENTS_IMPLEMENTATION_PLAN.md Step 2). #btnPreview no
  // longer exists; js/booking-validation.js now calls this function directly
  // on every date/voucher change, which is what actually drove the
  // "automatic update" the button-removal request described — that update
  // was always this same fetch, just triggered by a programmatic .click()
  // instead of a direct call. Loading state is shown on #btnConfirm (the
  // only button left in this flow): it stays disabled until this succeeds,
  // so a spinner there communicates "recalculating, not ready yet" the same
  // way the old #btnPreview spinner did — same PMSMotion helper, new target.
  // Exposed on window so booking-validation.js (a separate script) can call
  // it; the #amount_paid fallback-input branch from the old handler is
  // dropped because #amountPaidSection is now always present in vehicles.php
  // markup (no longer conditionally rendered), so that branch never ran.
  window.refreshBookingPreview = async function refreshBookingPreview() {
    const $btnConfirm = $('#btnConfirm');
    const vehicle_id = $('#vehicle_id').val();
    const rental_date = $('#rental_date').val();
    const return_date = $('#return_date').val();
    const pickup_time = $('#pickup_time').val();
    const dropoff_time = $('#dropoff_time').val();
    const contact_number = $('#contact_number').val();
    const voucher_code = $('#voucherSelect').val();
    const age = parseInt($('#age').val(), 10);

    if (!vehicle_id || !rental_date || !return_date) {
      showAlert('Please fill in required fields.', 'danger');
      $btnConfirm.prop('disabled', true);
      return false;
    }

    const payload = {
      is_preview: true,
      vehicle_id,
      rental_date,
      return_date,
      pickup_time,
      dropoff_time,
      contact_number,
      age,
      voucher_code
    };

    PMSMotion.setButtonLoading($btnConfirm, true);
    let success = false;
    try {
      const res = await fetch('reserve_preview.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.error) {
        showAlert(data.error || 'Preview failed', 'danger');
        return false;
      }

      const { days = 1, rate = 0, subtotal = 0, discount = 0, total = 0 } = data;

      // Vehicle name/thumbnail are reused from #bookingModal's data() — set
      // when #btnReserveFromDetails opens this modal from the
      // already-populated #vehicleDetailsModal — no new fetch needed.
      const vehicleNameRaw = $('#bookingModal').data('vehicleName') || '';
      const vehicleName = $('<div>').text(vehicleNameRaw).html();
      const vehicleThumbnail = $('#bookingModal').data('vehicleThumbnail');
      const vehicleRowHtml = vehicleThumbnail
        ? `<div class="d-flex align-items-center gap-2 mb-3">
             <img src="assets/${vehicleThumbnail}" alt="${vehicleName}" class="booking-preview-thumb rounded-3">
             <span class="fw-semibold">${vehicleName}</span>
           </div>`
        : '';

      $('#bookingPreview').html(`
        ${vehicleRowHtml}
        <div class="border rounded p-3 bg-body-tertiary">
          <h6 class="mb-2">Booking Summary</h6>
          <p class="mb-1">Days: <strong>${days}</strong></p>
          <p class="mb-1">Rate per day: ₱${Number(rate).toLocaleString()}</p>
          <p class="mb-1 text-danger">Discount: ₱${Number(discount).toLocaleString()}</p>
          <hr>
          <h5>Total: ₱${Number(total).toLocaleString()}</h5>
        </div>
      `).addClass('is-visible');

      $('#amount_paid').prop('required', true);
      showAlert('', 'success'); // hide previous alerts
      success = true;
      return true;
    } catch (err) {
      console.error('Preview error:', err);
      showAlert('Error generating preview.', 'danger');
      return false;
    } finally {
      // setButtonLoading(false) unconditionally re-enables the button, so the
      // final disabled state must be applied after it, not before.
      PMSMotion.setButtonLoading($btnConfirm, false);
      $btnConfirm.prop('disabled', !success);
    }
  };


// Add preview button click handler
$(document).on('click', '#previewBtn', async function(e) {
    e.preventDefault();
   
    const previewData = {
        is_preview: true,
        vehicle_id: $('#vehicle_id').val(),
        rental_date: $('#rental_date').val(),
        return_date: $('#return_date').val(),
        pickup_time: $('#pickup_time').val(),
        dropoff_time: $('#dropoff_time').val(),
        contact_number: $('#contact_number').val(),
        age: $('#age').val(),
        voucher_code: $('#voucher_code').val()
    };


    try {
        const response = await fetch('reserve.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(previewData)
        });
       
        const result = await response.json();
        if (result.success) {
            // Update the preview section
            $('#preview_total').text(result.total.toFixed(2));
            $('#preview_subtotal').text(result.subtotal.toFixed(2));
            $('#preview_discount').text(result.discount.toFixed(2));
           
            // Show the preview section and amount input
            $('.preview-section').removeClass('d-none');
            $('#amount_paid_section').removeClass('d-none');
        } else {
            alert(result.error || 'Preview failed');
        }
    } catch (error) {
        console.error('Preview error:', error);
        alert('Failed to get preview');
    }
});




  // === Confirm Booking ===
$('#btnConfirm').on('click', async function () {
  const $btn = $(this);
  const vehicle_id = $('#vehicle_id').val();
  const rental_date = $('#rental_date').val();
  const return_date = $('#return_date').val();
  const pickup_time = $('#pickup_time').val();
  const dropoff_time = $('#dropoff_time').val();
  const contact_number = $('#contact_number').val();
  const age = parseInt($('#age').val());
  const voucher_code = $('#voucherSelect').val();
  const amount_paid = parseFloat($('#amount_paid').val()) || 0;


  // Basic front-end check
  if (!vehicle_id || !rental_date || !return_date || !contact_number || !age) {
    alert('Please complete all required fields.');
    return;
  }
  if (age < 18) {
    alert('You must be at least 18 years old to rent a car.');
    return;
  }


  // G3 (System Enhancements initiative, Step 3): confirm before submitting a
  // financially-binding booking. Opens while #bookingModal is still open —
  // Bootstrap 5.3 supports this stacked-modal case directly (confirmed live);
  // no hide/reshow of #bookingModal is needed. #amount_paid was already read
  // above, so the live total can be shown rather than a generic message.
  const confirmed = await window.PMSConfirm({
    title: 'Confirm booking?',
    body: `Confirm this booking for ₱${amount_paid.toLocaleString()}? Your booking will be submitted for approval and the voucher you selected will be used.`,
    confirmLabel: 'Confirm Booking',
    variant: 'success'
  });
  if (!confirmed) return;


  PMSMotion.setButtonLoading($btn, true);
  let isNavigatingAway = false;
  try {
    const res = await fetch('reserve.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        vehicle_id,
        rental_date,
        return_date,
        pickup_time,
        dropoff_time,
        voucher_code,
        amount_paid,
        contact_number,
        age
      })
    });


    const data = await res.json();


    if (data.success) {
      // Redirect to receipt page using booking_id or booking_ref
      isNavigatingAway = true;
      const bookingId = data.booking_id || null;
      const ref = data.booking_ref || null;
      if (bookingId) {
        window.location.href = 'receipt.php?id=' + encodeURIComponent(bookingId);
      } else if (ref) {
        window.location.href = 'receipt.php?ref=' + encodeURIComponent(ref);
      } else {
        // fallback: hide modal and reload
        $('#bookingModal').modal('hide');
        location.reload();
      }
    } else {
      console.error('Booking failed:', data);
      alert(data.error || 'Booking failed.');
    }
  } catch (err) {
    console.error('Network error:', err);
    alert('Something went wrong while submitting your booking.');
  } finally {
    if (!isNavigatingAway) PMSMotion.setButtonLoading($btn, false);
  }
});




  // Confirm booking
  $('#bookingForm').on('submit', function(e) {
    e.preventDefault();


    const vehicle_id = $('#vehicle_id').val();
    const rental_date = $('#rental_date').val();
    const return_date = $('#return_date').val();
    const pickup_time = $('#pickup_time').val();
    const dropoff_time = $('#dropoff_time').val();
    const contact_number = $('#contact_number').val();
    const voucher_code = $('#voucherSelect').val().trim();
    const age = parseInt($('#age').val(), 10);


    if (age < 18) {
      showAlert('You must be at least 18 years old to rent a car.', 'danger');
      return;
    }


    $.ajax({
      url: 'reserve.php',
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({
        vehicle_id,
        rental_date,
        return_date,
        pickup_time,
        dropoff_time,
        contact_number,
        voucher_code,
        amount_paid: previewData ? previewData.total : 0
      }),
      success: function(res) {
        // Redirect to receipt page using booking_id or booking_ref
        const bookingId = res.booking_id || null;
        const ref = res.booking_ref || null;
        if (bookingId) {
          window.location.href = 'receipt.php?id=' + encodeURIComponent(bookingId);
        } else if (ref) {
          window.location.href = 'receipt.php?ref=' + encodeURIComponent(ref);
        } else {
          // Fallback: show inline receipt area
          $('#bookingForm').addClass('d-none');
          $('#receiptArea').removeClass('d-none');
          $('#receiptContent').html(`
            <p><strong>Booking Ref:</strong> ${res.booking_ref}</p>
            <p><strong>Total Paid:</strong> ₱${res.total.toLocaleString()}</p>
            <p><strong>Change:</strong> ₱${res.change.toLocaleString()}</p>
            <p class="text-body-secondary mt-3">You will receive an email confirmation shortly.</p>
          `);
        }
      },
      error: function(xhr) {
        const res = xhr.responseJSON || {};
        showAlert(res.error || 'Booking failed. Please try again.', 'danger');
      }
    });
  });


  function showAlert(msg, type) {
    $('#bookingAlert').removeClass('d-none alert-danger alert-success')
                      .addClass(`alert-${type}`)
                      .text(msg);
  }
});




  // Simple accordion fallback for non-Bootstrap behavior (if needed)
  $('.accordion-button').on('click', function () {
    var target = $(this).data('bs-target') || $(this).attr('data-bs-target');
    if (!target) return;
    $(target).collapse('toggle');
  });


  // Navbar state
  function renderNavbarAuth() {
    // const user = JSON.parse(localStorage.getItem('pmsUser') || 'null');
    const user = null; // replace by me.php
    const $area = $('#navbarAuthArea');
    $area.empty();
    if (user) {
      // Welcome + Logout dropdown
      $area.append(`
        <div class="dropdown">
          <button class="btn btn-outline-primary rounded-pill dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            Welcome, ${user.name.split(' ')[0]}!
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item text-danger" href="#" id="logoutBtn">Logout</a></li>
          </ul>
        </div>
      `);
    } else {
      // Login/Signup buttons
      $area.append(`
        <button class="btn btn-outline-primary rounded-pill btn-login me-2">Log In</button>
        <button class="btn btn-primary rounded-pill btn-signup">Sign Up</button>
      `);
    }
  }


  renderNavbarAuth();


  // Show modals
  $(document).on('click', '.btn-login', function () {
    $('#loginModal').modal('show');
  });
  $(document).on('click', '.btn-signup', function () {
    $('#signupModal').modal('show');
  });


  // "Login as Admin" button
  $('#loginAsAdminBtn').on('click', function () {
    window.location.href = 'admin-login.php';
  });


  // Booking form (demo only)
  $('#bookingForm').on('submit', function (e) {
    e.preventDefault();
    $('#bookingAlert').removeClass('d-none');
    setTimeout(() => $('#bookingAlert').addClass('d-none'), 2000);
    this.reset();
  });


  // Show validation feedback
  function showValidation(elementId, message, type = 'danger') {
    $(`#${elementId}Error`).removeClass('d-none').text(message);
  }


  function hideValidation(elementId) {
    $(`#${elementId}Error`).addClass('d-none').text('');
  }


  // Show floating alert
  function showFloatingAlert(message, type = 'danger') {
    const alert = $(`
      <div class="alert alert-${type} alert-floating shadow-sm">
        ${message}
        <button type="button" class="btn-close ms-3" data-bs-dismiss="alert"></button>
      </div>
    `);
   
    $('body').append(alert);
    setTimeout(() => alert.remove(), 5000);
  }


  // Only allow modal if logged in
  $('#openBookingMultiModal').on('click', async function () {
    const response = await fetch('check_login.php');
    const data = await response.json();
   
    if (!data.loggedIn) {
      showFloatingAlert('Please log in to make a booking', 'warning');
      $('#loginModal').modal('show');
      return;
    }
   
    $('#bookingMultiModal').modal('show');
    loadVouchers(); // Load available vouchers when modal opens
  });  // Step navigation
  function showStep(step) {
    $('.booking-step').addClass('d-none').hide();
    $('#bookingStep' + step).removeClass('d-none').fadeIn(200);
    // Focus first input for accessibility
    $('#bookingStep' + step).find('input,select,button').filter(':visible').first().focus();
  }


  function resetBookingModal() {
  const form = document.getElementById('bookingForm');
  if (form) form.reset(); // Prevents undefined error


  $('#bookingAlert').addClass('d-none').text('');
  $('#bookingPreview').empty();
  $('#btnConfirm').addClass('d-none');
  $('#receiptArea').addClass('d-none');
}


  // Step 1 → Step 2
  $('#bookingStep1Next').on('click', function () {
    // Validate fields
    let valid = true;
    $('#bookingStep1 input').each(function () {
      if (!$(this).val()) valid = false;
    });
    const age = parseInt($('#driverAge').val(), 10);
    if (isNaN(age) || age < 18) {
      $('#bookingStep1Alert').removeClass('d-none').text('You must be 18 or older to rent a vehicle. Please edit your information.');
      $('#driverAge').focus();
      return;
    } else {
      $('#bookingStep1Alert').addClass('d-none').text('');
    }
    if (!valid) {
      $('#bookingStep1Alert').removeClass('d-none').text('Please fill out all fields.');
      return;
    }
    // Prepare summary from booking form (index.html)
    let bookingData = getBookingFormData();
    let days = bookingData.days;
    let rate = bookingData.rate;
    let total = days * rate;
    $('#bookingSummary').html(`
      <div><strong>Car Type:</strong> ${bookingData.carType}</div>
      <div><strong>Rate per Day:</strong> ₱${rate}</div>
      <div><strong>Rental Period:</strong> ${bookingData.rentalDate} to ${bookingData.returnDate} (${days} days)</div>
      <div><strong>Total Cost:</strong> <span class="text-success fw-bold">₱${total}</span></div>
    `);
    showStep(2);
  });


  // Load available vouchers
  async function loadVouchers() {
    try {
      const response = await fetch('get_vouchers.php');
      const vouchers = await response.json();
     
      const $select = $('#voucherSelect').empty();
      $select.append('<option value="">Select a voucher (optional)</option>');
     
      vouchers.forEach(voucher => {
        $select.append(`<option value="${voucher.code}">
          ${voucher.code} - ${voucher.discount_pct}% off
        </option>`);
      });
    } catch (err) {
      console.error('Error loading vouchers:', err);
    }
  }


  // Step 2: Payment method fields
  $('#paymentMethod').on('change', function () {
    const method = $(this).val();
    $('#paymentFields').empty();
   
    switch(method) {
      case 'credit_card':
        $('#paymentFields').html(`
          <div class="mb-3">
            <label class="form-label">Card Number</label>
            <input type="text" class="form-control" name="card_number" required>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Expiry Date</label>
              <input type="text" class="form-control" name="card_expiry" placeholder="MM/YY" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">CVV</label>
              <input type="text" class="form-control" name="card_cvv" required>
            </div>
          </div>
        `);
        break;
       
      case 'gcash':
        $('#paymentFields').html(`
          <div class="mb-3">
            <label class="form-label">GCash Number</label>
            <input type="text" class="form-control" name="gcash_number" required>
          </div>
        `);
        break;
    }
  });


  // Handle voucher application
  $('#voucherCode').on('change', async function() {
    const code = $(this).val();
    if (!code) return;
   
    const total = parseFloat($('#totalAmount').data('original')) || 0;
   
    try {
      const response = await fetch('apply_voucher.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        credentials: 'include', // this sends PHP session cookies
        body: JSON.stringify({
          voucher_code: code,
          total_amount: total
        })
      });
     
      const result = await response.json();
     
      if (result.success) {
        $('#discountAmount').text(`-₱${result.discount.toFixed(2)}`);
        $('#finalTotal').text(`₱${result.final_total.toFixed(2)}`);
        $('#totalAmount').val(result.final_total);
        showAlert('Voucher applied successfully!', 'success');
      } else {
        showAlert(result.message, 'danger');
        $(this).val(''); // Clear invalid voucher
      }
    } catch (err) {
      console.error(err);
      showAlert('Error applying voucher', 'danger');
    }
  });  // Step 2 → Step 1 (Back)
  $('#bookingStep2Back').on('click', function () {
    showStep(1);
  });




  // Step 2 → Step 3 (Confirm Payment)
  /*
  $('#bookingStep2Confirm').on('click', function () {
    // Validate payment
    const method = $('#paymentMethod').val();
    if (!method) {
      $('#paymentMethod').focus();
      return;
    }
    let valid = true;
    $('#paymentFields input').each(function () {
      if (!$(this).val()) valid = false;
    });
    if (!valid) {
      $('#paymentFields input').first().focus();
      return;
    }
    // Generate receipt
    let bookingData = getBookingFormData();
    let driverName = $('#driverName').val();
    let transactionId = 'TX' + Math.floor(Math.random() * 90000 + 10000);
    let days = bookingData.days;
    let rate = bookingData.rate;
    let total = days * rate;
    $('#receiptSummary').html(`
      <div><strong>Transaction ID:</strong> ${transactionId}</div>
      <div><strong>Driver Name:</strong> ${driverName}</div>
      <div><strong>Car Type:</strong> ${bookingData.carType}</div>
      <div><strong>Rental Period:</strong> ${bookingData.rentalDate} to ${bookingData.returnDate} (${days} days)</div>
      <div><strong>Pick-up Time:</strong> ${bookingData.pickupTime}</div>
      <div><strong>Drop-off Time:</strong> ${bookingData.dropoffTime}</div>
      <div><strong>Total Cost:</strong> <span class="text-success fw-bold">₱${total}</span></div>
      <div><strong>Payment Method:</strong> ${method}</div>
    `);
    // Store booking in localStorage for transactions.html
    // let bookings = JSON.parse(localStorage.getItem('pmsBookings') || '[]');
    bookings.push({
      id: transactionId,
      driver: driverName,
      carType: bookingData.carType,
      rentalDate: bookingData.rentalDate,
      returnDate: bookingData.returnDate,
      pickupTime: bookingData.pickupTime,
      dropoffTime: bookingData.dropoffTime,
      days: days,
      rate: rate,
      total: total,
      paymentMethod: method,
      timestamp: new Date().toISOString()
    });
    // localStorage.setItem('pmsBookings', JSON.stringify(bookings));
    showStep(3);
  });
  */


  // Example: confirm booking button click
  $('#bookingStep2Confirm').on('click', async function () {
  const vehicle_id = $('#vehicle_id').val();
  const rental_date = $('#rental_date').val();
  const return_date = $('#return_date').val();
  const pickup_time = $('#pickup_time').val();
  const dropoff_time = $('#dropoff_time').val();
  const contact_number = $('#contact_number').val();
  const age = parseInt($('#age').val(), 10);
  const voucher_code = $('#voucherSelect').val();
  const amount_paid = parseFloat($('#amount_paid').val());


  // client-side validation
  if (!vehicle_id || !rental_date || !return_date || !contact_number || !age || isNaN(amount_paid)) {
    alert('Please fill all required fields (vehicle, dates, contact, age, amount).');
    console.log('Missing client fields:', { vehicle_id, rental_date, return_date, contact_number, age, amount_paid });
    return;
  }
  if (age < 18) {
    alert('You must be at least 18 years old to rent.');
    return;
  }


  // Correct payload construction
const payload = {
  is_preview: true,
  vehicle_id,
  rental_date,
  return_date,
  pickup_time,
  dropoff_time,
  contact_number,
  age,
  voucher_code
};
console.log('Reserve payload:', payload);


  // preview call (optional but recommended)
  try {
    const prevRes = await fetch('reserve_preview.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ vehicle_id, rental_date, return_date, voucher_code })
    });
    const preview = await prevRes.json();
    if (preview.error) { alert(preview.error); return; }


    // show preview summary to user
    // UI Implementation Plan, Phase 12 (Final UI Review) — BUGS.md item 2.
    // These five interpolations read `data`, which is never declared in this
    // handler's scope — the response was captured as `preview` on the line
    // above. Every one of them threw a ReferenceError the moment this block
    // ran. Renamed to `preview` (the variable that actually holds the parsed
    // reserve_preview.php response); no other change. NOTE: this handler is
    // bound to #bookingStep2Confirm, part of the multi-step booking wizard
    // that has no markup anywhere in the project (BUGS.md, Incomplete
    // Implementations) — it is unreachable today, so this is a correctness
    // fix to dead code, not a user-visible behaviour change. The wizard's
    // wholesale removal is tracked separately; see that BUGS.md entry.
    $("#bookingPreview").html(`
      <div class="border p-3 rounded mb-3">
        <p><strong>Days:</strong> ${preview.days}</p>
        <p><strong>Rate per day:</strong> ₱${preview.rate.toLocaleString()}</p>
        <p><strong>Subtotal:</strong> ₱${preview.subtotal.toLocaleString()}</p>
        <p><strong>Discount:</strong> ₱${preview.discount.toLocaleString()}</p>
        <p class="fw-bold text-success">Total: ₱${preview.total.toLocaleString()}</p>
      </div>


      <div class="mb-3">
        <label for="amount_paid" class="form-label">Enter Amount Paid</label>
        <input type="number" id="amount_paid" class="form-control" placeholder="Enter amount (₱)" required>
        <div class="form-text">Please input the amount you are paying.</div>
      </div>
    `);




  } catch (err) {
    console.error('Preview error', err);
    alert('Could not get price preview.');
    return;
  }


  // send final booking
  try {
    const res = await fetch('reserve.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        vehicle_id: $('#vehicle_id').val(),
        rental_date: $('#rental_date').val(),
        return_date: $('#return_date').val(),
        pickup_time: $('#pickup_time').val(),
        dropoff_time: $('#dropoff_time').val(),
        contact_number: $('#contact_number').val(),
        age: $('#age').val(),
        voucher_code: $('#voucherSelect').val(),
        amount_paid: parseFloat($('#amount_paid').val()) || 0
      })
    });


    const data = await res.json();
    console.log('Reserve response:', data);


    if (!res.ok) {
      // server returned non-200 — show helpful info
      alert((data && (data.error || JSON.stringify(data))) || 'Booking failed');
      return;
    }


    if (data.success) {
      // Redirect to receipt page using booking_id or booking_ref
      const bookingId = data.booking_id || null;
      const ref = data.booking_ref || null;
      if (bookingId) {
        window.location.href = 'receipt.php?id=' + encodeURIComponent(bookingId);
      } else if (ref) {
        window.location.href = 'receipt.php?ref=' + encodeURIComponent(ref);
      } else {
        // Fallback: show success then redirect to transactions
        $("#bookingAlert")
          .removeClass("d-none alert-danger")
          .addClass("alert-success")
          .text("Booking confirmed successfully!");
        setTimeout(() => window.location.href = 'transactions.php', 2500);
      }
    } else {
      alert(data.error || 'Booking failed.');
    }


  } catch (err) {
    console.error('Reserve submit error', err);
    alert('Server error while making booking.');
  }
});






  // Step 3 → Close
  $('#bookingStep3Close').on('click', function () {
    $('#bookingMultiModal').modal('hide');
    resetBookingModal();
  });


  // Utility: Get booking form data from index.html
  function getBookingFormData() {
    // Try to get from booking form (index.html)
    let carType = $('#carType').val() || 'Sedan';
    let rentalDate = $('#rentalDate').val() || '2025-10-14';
    let returnDate = $('#returnDate').val() || '2025-10-15';
    let pickupTime = $('#pickupTime').val() || '09:00';
    let dropoffTime = $('#dropoffTime').val() || '17:00';
    let rate = 1200; // Default rate
    // You can map carType to rate if needed
    if (carType === 'SUV') rate = 2500;
    if (carType === 'Minivan') rate = 1800;
    if (carType === 'Van') rate = 3500;
    if (carType === 'Scooter') rate = 600;
    // Calculate days
    let days = 1;
    try {
      let d1 = new Date(rentalDate);
      let d2 = new Date(returnDate);
      days = Math.max(1, Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24)));
    } catch (e) { days = 1; }
    return { carType, rentalDate, returnDate, pickupTime, dropoffTime, rate, days };
  }


  // Accessibility: Focus trap inside modal
  $('#bookingMultiModal').on('shown.bs.modal', function () {
    $(this).attr('tabindex', '-1').focus();
  });


  // Keyboard navigation for steps
  $('.booking-step').on('keydown', function (e) {
    if (e.key === 'Enter') {
      if ($(this).is('#bookingStep1')) $('#bookingStep1Next').click();
      if ($(this).is('#bookingStep2')) $('#bookingStep2Confirm').click();
    }
  });


  $('#bookingFormModern input, #bookingFormModern select').on('focus', function () {
    var step = $(this).closest('.form-group').index() + 1;
    $('.booking-step-indicator .step-circle').removeClass('active');
    $('.booking-step-indicator .step-circle').eq(step - 1).addClass('active');
  });


  // Animate on scroll for feature cards
  $(window).on('scroll', function () {
    $('.feature-card').each(function () {
      if ($(this).offset().top < $(window).scrollTop() + $(window).height() - 100) {
        $(this).addClass('animate__fadeInUp');
      }
    });
  });


  // Example: quick search input with fade on key
  $('.hero-immersive input[type="text"]').on('input', function () {
    $(this).addClass('animate__pulse');
    setTimeout(() => $(this).removeClass('animate__pulse'), 250);
  });


;


// ===================== AUTHENTICATION (SERVER-BASED) =====================


// Ask PHP who is currently logged in
async function getMe() {
  try {
    const res = await fetch('me.php');
    const data = await res.json();
    return data;
  } catch (err) {
    console.error('Error checking user session:', err);
    return { logged_in: false };
  }
}


// First letter of the first word + first letter of the last word (e.g.
// "QA Tester" -> "QT"); single-word names fall back to just that letter.
// Shared by the navbar dropdown, the profile section, and (via a PHP port
// of this same rule) admin_users.php, so the three stay consistent.
function getInitials(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}


// Renders either an <img> (profile_picture_path set) or an initials-circle
// fallback (unset) — used everywhere a customer's picture is displayed, so
// there's never a broken-image icon. sizePx/fontSizePx are set per call site
// since the same markup is reused at very different sizes (navbar vs. profile).
function getAvatarHtml(name, profilePicturePath, sizePx, fontSizePx) {
  const size = sizePx || 36;
  const fontSize = fontSizePx || Math.round(size * 0.4);
  if (profilePicturePath) {
    return `<img src="${profilePicturePath}" alt="" class="rounded-circle" style="width:${size}px;height:${size}px;object-fit:cover;">`;
  }
  return `<span class="avatar-circle text-white" style="width:${size}px;height:${size}px;font-size:${fontSize}px;">${getInitials(name)}</span>`;
}


// Register a new user
async function registerUser(name, email, password, privacyConsent) {
  const res = await fetch('register.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, password, privacy_consent: privacyConsent })
  });
  return await res.json();
}


// Log in existing user
async function loginUser(email, password) {
  const res = await fetch('login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password })
  });
  return await res.json();
}


// Log out current user
// UI Implementation Plan, Phase 12 (Final UI Review) — BUGS.md item 33.
// `await fetch(...)` resolves as soon as response *headers* arrive, not once
// the server has finished tearing the session down, so the reload below could
// race logout.php and land back on a still-partially-authenticated page.
// Reading the body to completion (via .text(), which resolves only after the
// response is fully received) makes the await mean what this code always
// assumed it meant. The reload runs either way — a failed logout request must
// not leave the user stuck on a page that still looks logged in — so the
// fetch is guarded rather than allowed to throw past the reload.
async function logoutUser() {
  try {
    const res = await fetch('logout.php');
    await res.text(); // drain the body: guarantees the request completed
  } catch (err) {
    console.error('Logout request failed', err);
  }
  window.location.reload(); // refresh the page after logout
}


// Request a password-reset code (forgot_password.php always returns
// {success:true} regardless of whether the email is registered, by design —
// it never leaks account existence).
async function requestPasswordReset(email) {
  const res = await fetch('forgot_password.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email })
  });
  return await res.json();
}


// Consume a reset code and set a new password
async function resetPassword(email, code, newPassword) {
  const res = await fetch('reset_password.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, code, new_password: newPassword })
  });
  return await res.json();
}


// ------------------------------------------------------------------------
// FRONT-END HANDLERS (connect forms/buttons to PHP)
// ------------------------------------------------------------------------


// ------------------------------------------------------------------------
// AUTH FIELD VALIDATION (real-time, per-field)
//
// Fills the ".is-invalid never set" gap documented in Motion Design Phase 8:
// previously every auth error surfaced only through the top-of-form alert
// (#loginError/#signupError) via showAuthError() below. This module adds
// per-field .is-invalid/.invalid-feedback state on blur (first check) and
// input (live correction once a field has been marked invalid).
//
// This is additive, not a replacement: showAuthError() is unchanged and
// remains the correct handler for errors no client-side rule can predict
// (wrong password, duplicate email, expired reset code, etc). Submit
// handlers call AuthValidation.clearForm() before showAuthError() so a
// server-rejection alert never appears alongside stale per-field state.
// ------------------------------------------------------------------------
const AuthValidation = (function () {
  // Looks up the field's .invalid-feedback element by the project's fixed
  // naming convention (<fieldId>Feedback), not by parsing aria-describedby
  // directly — aria-describedby can hold multiple space-separated ids (e.g.
  // signupPassword also points at its helper text), and treating that whole
  // string as a single jQuery/CSS selector silently matches nothing.
  function feedbackFor($input) {
    return $('#' + $input.attr('id') + 'Feedback');
  }

  function setInvalid($input, message) {
    $input.addClass('is-invalid').removeClass('is-valid').attr('aria-invalid', 'true');
    feedbackFor($input).text(message);
  }

  function setValid($input) {
    $input.removeClass('is-invalid').addClass('is-valid').attr('aria-invalid', 'false');
  }

  function clearField($input) {
    $input.removeClass('is-invalid is-valid').removeAttr('aria-invalid');
    feedbackFor($input).text('');
  }

  function clearForm($form) {
    // .form-check-input added for #signupPrivacyConsent (System Enhancements
    // initiative, Step 7) — a checkbox isn't .form-control, so it was never
    // cleared between submits before this.
    $form.find('.form-control, .form-check-input').each(function () {
      clearField($(this));
    });
  }

  // Returns the first failing rule's message, or null if all rules pass.
  // rules: [{ test: (value) => boolean, message: string }, ...], checked in order.
  function firstError(value, rules) {
    for (const rule of rules) {
      if (!rule.test(value)) return rule.message;
    }
    return null;
  }

  // Wires blur (initial check) then input (live correction, once touched)
  // to a single field. Returns a check() function for manual/submit-time use.
  function attachRealTime($input, rules, options) {
    options = options || {};
    let touched = false;

    function check() {
      const error = firstError($input.val(), rules);
      if (error) {
        setInvalid($input, error);
      } else if (options.showValid) {
        setValid($input);
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

  // Validates every field in fieldConfigs = [{ $input, rules, showValid }],
  // marking each as valid/invalid and focusing the first invalid one.
  // Returns true only if every field passed. Intended for on-submit,
  // pre-fetch validation (Steps 2-4 wire this into each submit handler).
  function validateAll(fieldConfigs) {
    let $firstInvalid = null;
    let allValid = true;
    fieldConfigs.forEach(function (cfg) {
      const error = firstError(cfg.$input.val(), cfg.rules);
      if (error) {
        setInvalid(cfg.$input, error);
        allValid = false;
        if (!$firstInvalid) $firstInvalid = cfg.$input;
      } else if (cfg.showValid) {
        setValid(cfg.$input);
      } else {
        clearField(cfg.$input);
      }
    });
    if ($firstInvalid) $firstInvalid.trigger('focus');
    return allValid;
  }

  // Reusable rule builders shared by every auth form.
  const rules = {
    required: function (message) {
      return { test: (v) => v.trim().length > 0, message: message };
    },
    email: function (message) {
      return { test: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()), message: message };
    },
    minLength: function (min, message) {
      return { test: (v) => v.length >= min, message: message };
    },
    matches: function (getOtherValue, message) {
      return { test: (v) => v === getOtherValue(), message: message };
    },
    pattern: function (regex, message) {
      return { test: (v) => regex.test(v.trim()), message: message };
    },
    // Every other rule tests the string firstError()/validateAll() pass in
    // ($input.val()), but a checkbox's .val() is "on" regardless of checked
    // state — it carries no information about whether the box is ticked.
    // Rather than change the shared value-passing pipeline for one field,
    // this rule builder takes $input directly and closes over it, checking
    // the real .checked property instead of the value it's handed.
    checked: function ($input, message) {
      return { test: () => $input.is(':checked'), message: message };
    },
  };

  return { attachRealTime, validateAll, clearField, clearForm, rules };
})();


// Shows an auth-form error: sets the message, shakes the alert, and moves
// focus to it. $alert must be the specific jQuery-wrapped element for this
// submit, captured by the caller — never re-queried here, so a delayed
// class-removal can't land on a different modal's alert after the user
// reopens one. Callers clear per-field AuthValidation state first (see
// submit handlers below) so this alert never appears alongside stale
// .is-invalid fields from an earlier validation pass.
function showAuthError($alert, message) {
  $alert.text(message).removeClass('d-none');
  $alert.addClass('animate__animated animate__shakeX');
  setTimeout(function () {
    $alert.removeClass('animate__animated animate__shakeX');
  }, 500);
  $alert.trigger('focus');
}


$(function () {
  // Login form fields — real-time validation. Signup/forgot-password fields
  // are wired in their own steps (Step 3, Step 4).
  AuthValidation.attachRealTime($('#loginEmail'), [
    AuthValidation.rules.required('Please enter your email address.'),
    AuthValidation.rules.email('Please enter a valid email address.'),
  ]);
  AuthValidation.attachRealTime($('#loginPassword'), [
    AuthValidation.rules.required('Please enter your password.'),
  ]);


  // "Forgot your password?" link — dismisses the login modal (via its own
  // data-bs-dismiss) and opens #forgotPasswordModal if it exists. That modal
  // doesn't exist yet (Step 4 builds it); guarded with .length so clicking
  // this link today is a safe no-op instead of throwing on a null target —
  // Bootstrap's declarative data-bs-target would throw on a missing element,
  // which is why this is wired manually rather than via data-bs-toggle.
  $(document).on('click', '.btn-forgot-password', function () {
    const $forgotModal = $('#forgotPasswordModal');
    if ($forgotModal.length) {
      $forgotModal.modal('show');
    }
  });


  // Signup form fields — real-time validation, matching register.php:24's
  // actual server rule (name/email required, password min 6 chars).
  AuthValidation.attachRealTime($('#signupName'), [
    AuthValidation.rules.required('Please enter your name.'),
  ]);
  AuthValidation.attachRealTime($('#signupEmail'), [
    AuthValidation.rules.required('Please enter your email address.'),
    AuthValidation.rules.email('Please enter a valid email address.'),
  ]);
  AuthValidation.attachRealTime($('#signupPassword'), [
    AuthValidation.rules.required('Please enter a password.'),
    AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
  ]);
  const checkConfirmPassword = AuthValidation.attachRealTime($('#signupConfirmPassword'), [
    AuthValidation.rules.required('Please confirm your password.'),
    AuthValidation.rules.matches(() => $('#signupPassword').val(), 'Passwords do not match.'),
  ], { showValid: true });

  // Cross-validation: if the user edits #signupPassword after already typing
  // a (previously matching) confirmation, re-check the confirm field so a
  // now-stale "valid" state doesn't linger. Only re-checks if confirm has
  // content — an empty, never-touched confirm field shouldn't be forced
  // into an error state just because the password field changed.
  $('#signupPassword').on('input', function () {
    if ($('#signupConfirmPassword').val()) {
      checkConfirmPassword();
    }
  });


  // Password visibility toggle — shared by #signupPassword and
  // #signupConfirmPassword. Only flips type/icon/aria-label; never touches
  // AuthValidation state, so an existing .is-invalid/.is-valid on the field
  // is preserved across a toggle.
  $(document).on('click', '.btn-toggle-password', function () {
    const $btn = $(this);
    const $input = $($btn.data('target'));
    const $icon = $btn.find('i');
    const showing = $input.attr('type') === 'text';
    $input.attr('type', showing ? 'password' : 'text');
    $icon.toggleClass('fa-eye fa-eye-slash');
    $btn.attr('aria-label', showing ? 'Show password' : 'Hide password');
  });


  // Register form
  $('#signupForm').on('submit', async function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));
    const $btn = $(this).find('button[type="submit"]');
    const $alert = $('#signupError');
    $alert.addClass('d-none').text('');


    // Full-form validation gate, ahead of the fetch. Supersedes the old
    // manual "!name || !email || !password || !confirm" and "password !==
    // confirm" checks (same rules, now per-field with .is-invalid + focus on
    // the first bad field) — both removed rather than kept as a fallback,
    // since this gate always runs first and would leave them unreachable
    // (same standard applied to the login handler in Step 2).
    const isValid = AuthValidation.validateAll([
      {
        $input: $('#signupName'),
        rules: [AuthValidation.rules.required('Please enter your name.')],
      },
      {
        $input: $('#signupEmail'),
        rules: [
          AuthValidation.rules.required('Please enter your email address.'),
          AuthValidation.rules.email('Please enter a valid email address.'),
        ],
      },
      {
        $input: $('#signupPassword'),
        rules: [
          AuthValidation.rules.required('Please enter a password.'),
          AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
        ],
      },
      {
        $input: $('#signupConfirmPassword'),
        rules: [
          AuthValidation.rules.required('Please confirm your password.'),
          AuthValidation.rules.matches(() => $('#signupPassword').val(), 'Passwords do not match.'),
        ],
        showValid: true,
      },
      {
        // System Enhancements initiative, Step 7: required privacy-consent
        // checkbox, unticked by default. This is a client-side convenience
        // gate only — register.php enforces the same requirement server-side
        // regardless of what this check does, since a client-only check is
        // not sufficient for a compliance requirement.
        $input: $('#signupPrivacyConsent'),
        rules: [AuthValidation.rules.checked($('#signupPrivacyConsent'), 'Please agree to the Privacy Policy to continue.')],
      },
    ]);
    if (!isValid) return;

    const name = $('#signupName').val().trim();
    const email = $('#signupEmail').val().trim();
    const password = $('#signupPassword').val().trim();
    const privacyConsent = $('#signupPrivacyConsent').is(':checked');


    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await registerUser(name, email, password, privacyConsent);
      if (res.success) {
        $('#signupModal').modal('hide');
        $('#loginModal').modal('show');
      } else {
        showAuthError($alert, res.error || 'Registration failed.');
      }
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Login form
  $('#loginForm').on('submit', async function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));
    const $btn = $(this).find('button[type="submit"]');
    const $alert = $('#loginError');
    $alert.addClass('d-none').text('');


    // Full-form validation gate, ahead of the fetch. This supersedes the
    // old manual "!email || !password" check (same rule, now per-field with
    // .is-invalid + focus on the first bad field) — keeping both would leave
    // the manual check unreachable dead code, since this always runs first.
    const isValid = AuthValidation.validateAll([
      {
        $input: $('#loginEmail'),
        rules: [
          AuthValidation.rules.required('Please enter your email address.'),
          AuthValidation.rules.email('Please enter a valid email address.'),
        ],
      },
      {
        $input: $('#loginPassword'),
        rules: [AuthValidation.rules.required('Please enter your password.')],
      },
    ]);
    if (!isValid) return;

    const email = $('#loginEmail').val().trim();
    const password = $('#loginPassword').val().trim();


    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await loginUser(email, password);
      if (res.success) {
        $('#loginModal').modal('hide');
        window.location.reload();
      } else {
        showAuthError($alert, res.error || 'Login failed.');
      }
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // ----------------------------------------------------------------------
  // Forgot Password modal — 3-step flow: email -> code -> new password.
  // Email/code are carried between steps in these two plain variables, not
  // the DOM, per the plan; both are cleared on hidden.bs.modal below so the
  // modal always reopens fresh at Step 1.
  // ----------------------------------------------------------------------
  let forgotEmail = '';
  let forgotCode = '';

  // Shows one step, hides the other two, and focuses its first input.
  // initModalFocus() (js/motion.js) only fires on shown.bs.modal, which
  // doesn't refire for an internal step change within an already-open
  // modal — this is the step-transition equivalent of that.
  function showForgotStep(stepNumber) {
    $('#forgotStep1, #forgotStep2, #forgotStep3').addClass('d-none');
    const $step = $('#forgotStep' + stepNumber);
    $step.removeClass('d-none');
    $step.find('input').first().trigger('focus');
  }

  AuthValidation.attachRealTime($('#forgotEmail'), [
    AuthValidation.rules.required('Please enter your email address.'),
    AuthValidation.rules.email('Please enter a valid email address.'),
  ]);
  AuthValidation.attachRealTime($('#forgotCode'), [
    AuthValidation.rules.required('Please enter the 6-digit code.'),
    AuthValidation.rules.pattern(/^\d{6}$/, 'Please enter the 6-digit code exactly as sent.'),
  ]);
  AuthValidation.attachRealTime($('#forgotNewPassword'), [
    AuthValidation.rules.required('Please enter a password.'),
    AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
  ]);
  const checkForgotConfirmPassword = AuthValidation.attachRealTime($('#forgotConfirmPassword'), [
    AuthValidation.rules.required('Please confirm your password.'),
    AuthValidation.rules.matches(() => $('#forgotNewPassword').val(), 'Passwords do not match.'),
  ], { showValid: true });

  // Same cross-validation pattern as signup (Step 3): editing the new
  // password after confirm was already filled re-checks confirm.
  $('#forgotNewPassword').on('input', function () {
    if ($('#forgotConfirmPassword').val()) {
      checkForgotConfirmPassword();
    }
  });


  // Step 1 -> Step 2: request the code
  $('#forgotStep1Form').on('submit', async function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));
    const $btn = $(this).find('button[type="submit"]');
    const $alert = $('#forgotError');
    $alert.addClass('d-none').text('');

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#forgotEmail'),
        rules: [
          AuthValidation.rules.required('Please enter your email address.'),
          AuthValidation.rules.email('Please enter a valid email address.'),
        ],
      },
    ]);
    if (!isValid) return;

    const email = $('#forgotEmail').val().trim();

    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await requestPasswordReset(email);
      if (res.success) {
        forgotEmail = email;
        $('#forgotStep2Instruction').text('We\'ve sent a 6-digit code to ' + forgotEmail + '.');
        showForgotStep(2);
      } else {
        showAuthError($alert, res.error || 'Something went wrong. Please try again.');
      }
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Step 2 -> Step 3: client-side only — no endpoint verifies the code in
  // isolation. A wrong code surfaces as a showAuthError() on Step 3's own
  // submit, not here (per the plan: this is the documented design, not a
  // shortcut).
  $('#forgotStep2Form').on('submit', function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#forgotCode'),
        rules: [
          AuthValidation.rules.required('Please enter the 6-digit code.'),
          AuthValidation.rules.pattern(/^\d{6}$/, 'Please enter the 6-digit code exactly as sent.'),
        ],
      },
    ]);
    if (!isValid) return;

    forgotCode = $('#forgotCode').val().trim();
    showForgotStep(3);
  });


  // Resend code — reuses the same endpoint/email from Step 1
  $('#btnResendCode').on('click', async function () {
    const $btn = $(this);
    const $status = $('#forgotResendStatus');
    PMSMotion.setButtonLoading($btn, true);
    try {
      await requestPasswordReset(forgotEmail);
      $status.text('Code resent! Check your email.');
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Back to Step 1 — preserves the already-entered email (#forgotEmail's
  // value is never cleared by this transition) so the user can correct a
  // typo without restarting the whole flow from the login modal.
  $('#btnForgotBack').on('click', function () {
    showForgotStep(1);
  });


  // Step 3 -> success: reuses the email (Step 1) and code (Step 2) carried
  // in the closure variables above, plus the new password entered here.
  $('#forgotStep3Form').on('submit', async function (e) {
    e.preventDefault();
    AuthValidation.clearForm($(this));
    const $btn = $(this).find('button[type="submit"]');
    const $alert = $('#forgotError');
    $alert.addClass('d-none').text('');

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#forgotNewPassword'),
        rules: [
          AuthValidation.rules.required('Please enter a password.'),
          AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
        ],
      },
      {
        $input: $('#forgotConfirmPassword'),
        rules: [
          AuthValidation.rules.required('Please confirm your password.'),
          AuthValidation.rules.matches(() => $('#forgotNewPassword').val(), 'Passwords do not match.'),
        ],
        showValid: true,
      },
    ]);
    if (!isValid) return;

    const newPassword = $('#forgotNewPassword').val().trim();

    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await resetPassword(forgotEmail, forgotCode, newPassword);
      if (res.success) {
        $('#forgotPasswordModal').modal('hide');
        $('#loginModal').modal('show');
      } else {
        showAuthError($alert, res.error || 'Password reset failed.');
      }
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Reset to Step 1 whenever the modal closes, by any path (backdrop click,
  // Escape, either "Log in" link, or a successful reset) — so it always
  // reopens fresh, never mid-flow with stale data.
  $('#forgotPasswordModal').on('hidden.bs.modal', function () {
    forgotEmail = '';
    forgotCode = '';
    $('#forgotStep1Form, #forgotStep2Form, #forgotStep3Form').each(function () {
      AuthValidation.clearForm($(this));
      this.reset();
    });
    $('#forgotError').addClass('d-none').text('');
    $('#forgotResendStatus').text('Didn\'t receive a code?');
    $('#forgotNewPassword, #forgotConfirmPassword').attr('type', 'password');
    $('.btn-toggle-password[data-target="#forgotNewPassword"] i, .btn-toggle-password[data-target="#forgotConfirmPassword"] i')
      .removeClass('fa-eye-slash').addClass('fa-eye');
    showForgotStep(1);
  });


  // Logout button
  $(document).on('click', '#logoutBtn', async function (e) {
    e.preventDefault();
    // G1 (System Enhancements initiative, Step 3): confirm before ending the
    // session — session-ending actions get a confirmation per this project's
    // convention (see docs/SYSTEM_ENHANCEMENTS_ANALYSIS.md §3.3).
    const confirmed = await window.PMSConfirm({
      title: 'Log out?',
      body: "You'll need to sign in again to view your bookings.",
      confirmLabel: 'Log Out',
      variant: 'warning'
    });
    if (!confirmed) return;
    await logoutUser();
  });


  // Navbar dynamic display
  async function renderNavbarAuth() {
    const $area = $('#navbarAuthArea');
    $area.empty();


    const me = await getMe();
    if (me.logged_in) {
      $area.append(`
        <div class="dropdown">
          <button class="btn btn-outline-primary rounded-pill dropdown-toggle d-inline-flex align-items-center gap-2" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <span id="navbarAvatar">${getAvatarHtml(me.user.name, me.user.profile_picture_path, 28, 12)}</span>
            <span id="navbarWelcomeText">Welcome, ${me.user.name.split(' ')[0]}!</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
            <li><a class="dropdown-item" href="transactions.php#profile">My Profile</a></li>
            <li><a class="dropdown-item text-danger" href="#" id="logoutBtn">Logout</a></li>
          </ul>
        </div>
      `);
    } else {
      $area.append(`
        <button class="btn btn-outline-primary rounded-pill btn-login me-2">Log In</button>
        <button class="btn btn-primary rounded-pill btn-signup">Sign Up</button>
      `);
    }
  }


  renderNavbarAuth();


  // ------------------------------------------------------------------------
  // Profile section (transactions.php) — AuthValidation's second consumer.
  // Selectors below are no-ops on pages without a #profile section.
  // ------------------------------------------------------------------------

  // MySQL datetime strings ("YYYY-MM-DD HH:MM:SS") aren't reliably parsed by
  // `new Date()` in all browsers (Safari rejects the space separator) —
  // swap in a "T" first.
  function formatMemberSince(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr.replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
  }

  // Inline adaptation of showAuthError() for non-modal use: same
  // shake/focus treatment, but also toggles alert-success/alert-danger
  // since this alert serves both outcomes, not just errors.
  function showInlineFeedback($alert, type, message) {
    $alert.removeClass('alert-success alert-danger d-none').addClass('alert-' + type).text(message);
    $alert.addClass('animate__animated animate__shakeX');
    setTimeout(function () {
      $alert.removeClass('animate__animated animate__shakeX');
    }, 500);
    $alert.trigger('focus');
  }

  async function populateProfileSection() {
    if (!$('#profile').length) return;
    const me = await getMe();
    if (!me.logged_in) return;

    $('#profileNameDisplay').text(me.user.name);
    $('#profileEmailDisplay').text(me.user.email);
    $('#profileRoleDisplay').text(me.user.role);
    $('#profileMemberSinceDisplay').text(formatMemberSince(me.user.created_at));
    $('#profileNameInput').val(me.user.name);
    $('#profileEmailInput').val(me.user.email);
    $('#profilePictureDisplay').html(getAvatarHtml(me.user.name, me.user.profile_picture_path, 96, 36));
  }

  populateProfileSection();

  // Live preview before upload — swaps the display circle for the locally
  // chosen file via FileReader; the actual picture isn't saved until Upload
  // is clicked and update_profile_picture.php responds successfully.
  $('#profilePictureInput').on('change', function () {
    const file = this.files && this.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (e) {
      $('#profilePictureDisplay').html(
        `<img src="${e.target.result}" alt="" class="rounded-circle" style="width:96px;height:96px;object-fit:cover;">`
      );
    };
    reader.readAsDataURL(file);
  });

  $('#profilePictureForm').on('submit', async function (e) {
    e.preventDefault();
    const $alert = $('#profilePictureAlert');
    $alert.addClass('d-none');

    const fileInput = document.getElementById('profilePictureInput');
    const file = fileInput.files && fileInput.files[0];
    if (!file) {
      showInlineFeedback($alert, 'danger', 'Please choose an image to upload.');
      return;
    }

    const $btn = $('#profilePictureSaveBtn');
    PMSMotion.setButtonLoading($btn, true);
    try {
      const formData = new FormData();
      formData.append('profile_picture', file);
      const res = await fetch('update_profile_picture.php', {
        method: 'POST',
        body: formData,
      });
      const data = await res.json();

      if (data.success) {
        showInlineFeedback($alert, 'success', 'Profile picture updated successfully.');
        const me = await getMe();
        $('#profilePictureDisplay').html(getAvatarHtml(me.user.name, me.user.profile_picture_path, 96, 36));
        $('#navbarAvatar').html(getAvatarHtml(me.user.name, me.user.profile_picture_path, 28, 12));
        fileInput.value = '';
      } else {
        showInlineFeedback($alert, 'danger', data.error || 'Failed to upload profile picture.');
        // Revert the live preview back to the actual saved picture/initials.
        populateProfileSection();
      }
    } catch (err) {
      console.error('Error:', err);
      showInlineFeedback($alert, 'danger', 'Failed to upload profile picture.');
      populateProfileSection();
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });

  AuthValidation.attachRealTime($('#profileNameInput'), [
    AuthValidation.rules.required('Please enter your name.'),
  ]);
  AuthValidation.attachRealTime($('#profileEmailInput'), [
    AuthValidation.rules.required('Please enter your email address.'),
    AuthValidation.rules.email('Please enter a valid email address.'),
  ]);

  $('#profileInfoForm').on('submit', async function (e) {
    e.preventDefault();
    const $alert = $('#profileInfoAlert');
    $alert.addClass('d-none');

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#profileNameInput'),
        rules: [AuthValidation.rules.required('Please enter your name.')],
      },
      {
        $input: $('#profileEmailInput'),
        rules: [
          AuthValidation.rules.required('Please enter your email address.'),
          AuthValidation.rules.email('Please enter a valid email address.'),
        ],
      },
    ]);
    if (!isValid) return;

    // G6 (System Enhancements initiative, Step 3): confirm only when the
    // email actually changed — a name-only edit submits with no modal.
    // #profileEmailDisplay reflects the last-saved email (set from
    // me.user.email on page load, updated only after a successful save —
    // see renderNavbarAuth() above), so it's a reliable "current value" to
    // diff against, independent of what the user may have typed and not
    // saved.
    const newEmail = $('#profileEmailInput').val().trim();
    const currentEmail = $('#profileEmailDisplay').text().trim();
    if (newEmail !== currentEmail) {
      const confirmed = await window.PMSConfirm({
        title: 'Change email?',
        body: `Change your email to ${newEmail}? This is the address you'll log in with and the address password resets are sent to.`,
        confirmLabel: 'Change Email',
        variant: 'primary'
      });
      if (!confirmed) return;
    }

    const $btn = $('#profileInfoSaveBtn');
    PMSMotion.setButtonLoading($btn, true);
    try {
      const name = $('#profileNameInput').val().trim();
      const email = $('#profileEmailInput').val().trim();
      const res = await fetch('update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email }),
      });
      const data = await res.json();

      if (data.success) {
        showInlineFeedback($alert, 'success', 'Profile updated successfully.');
        $('#profileNameDisplay').text(name);
        $('#profileEmailDisplay').text(email);
        $('#navbarWelcomeText').text('Welcome, ' + name.split(' ')[0] + '!');
      } else {
        showInlineFeedback($alert, 'danger', data.error || 'Failed to update profile.');
      }
    } catch (err) {
      console.error('Error:', err);
      showInlineFeedback($alert, 'danger', 'Failed to update profile.');
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });

  AuthValidation.attachRealTime($('#currentPasswordInput'), [
    AuthValidation.rules.required('Please enter your current password.'),
  ]);
  AuthValidation.attachRealTime($('#newPasswordInput'), [
    AuthValidation.rules.required('Please enter a new password.'),
    AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
  ]);
  const checkConfirmNewPassword = AuthValidation.attachRealTime($('#confirmNewPasswordInput'), [
    AuthValidation.rules.required('Please confirm your new password.'),
    AuthValidation.rules.matches(() => $('#newPasswordInput').val(), 'Passwords do not match.'),
  ]);
  // Re-check the confirm field as the new-password field changes, same
  // pattern the signup form uses for its confirm-password field.
  $('#newPasswordInput').on('input', function () {
    if ($('#confirmNewPasswordInput').val()) checkConfirmNewPassword();
  });

  $('#changePasswordForm').on('submit', async function (e) {
    e.preventDefault();
    const $alert = $('#changePasswordAlert');
    $alert.addClass('d-none');

    const isValid = AuthValidation.validateAll([
      {
        $input: $('#currentPasswordInput'),
        rules: [AuthValidation.rules.required('Please enter your current password.')],
      },
      {
        $input: $('#newPasswordInput'),
        rules: [
          AuthValidation.rules.required('Please enter a new password.'),
          AuthValidation.rules.minLength(6, 'Password must be at least 6 characters.'),
        ],
      },
      {
        $input: $('#confirmNewPasswordInput'),
        rules: [
          AuthValidation.rules.required('Please confirm your new password.'),
          AuthValidation.rules.matches(() => $('#newPasswordInput').val(), 'Passwords do not match.'),
        ],
      },
    ]);
    if (!isValid) return;

    // G4 (System Enhancements initiative, Step 3): confirm a credential
    // change before submitting it.
    const confirmed = await window.PMSConfirm({
      title: 'Change password?',
      body: "You'll use the new password the next time you log in.",
      confirmLabel: 'Change Password',
      variant: 'primary'
    });
    if (!confirmed) return;

    const $btn = $('#changePasswordSaveBtn');
    PMSMotion.setButtonLoading($btn, true);
    try {
      const res = await fetch('change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          current_password: $('#currentPasswordInput').val(),
          new_password: $('#newPasswordInput').val(),
        }),
      });
      const data = await res.json();

      // change_password.php's error shape is {error: "..."} (no
      // `success` key at all), unlike update_profile.php's
      // {success:false, error:"..."} — checking data.success alone
      // covers both since it's falsy/undefined in both failure shapes.
      if (data.success) {
        showInlineFeedback($alert, 'success', data.message || 'Password updated successfully.');
        const $form = $('#changePasswordForm');
        $form[0].reset();
        AuthValidation.clearForm($form);
      } else {
        showInlineFeedback($alert, 'danger', data.error || 'Failed to update password.');
      }
    } catch (err) {
      console.error('Error:', err);
      showInlineFeedback($alert, 'danger', 'Failed to update password.');
    } finally {
      PMSMotion.setButtonLoading($btn, false);
    }
  });


  // Modals (open login/signup)
  $(document).on('click', '.btn-login', function () {
    $('#loginModal').modal('show');
  });
  $(document).on('click', '.btn-signup', function () {
    $('#signupModal').modal('show');
  });
});



