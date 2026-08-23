// Format date to YYYY-MM-DD
function formatDate(date) {
  return date.toISOString().split('T')[0];
}

// Get tomorrow's date
function getTomorrow() {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  return tomorrow;
}

// Setup date restrictions and validation
function setupDateValidation() {
  // Get tomorrow's date (minimum rental date)
  const tomorrow = getTomorrow();
  const tomorrowStr = formatDate(tomorrow);
  
  // Set minimum dates for rental and return
  $('#rental_date').attr('min', tomorrowStr);
  $('#return_date').attr('min', tomorrowStr);
  
  // When rental date changes, update return date minimum
  $('#rental_date').on('change', function() {
    const selectedDate = $(this).val();
    $('#return_date').attr('min', selectedDate);
    
    // If return date is before new rental date, update it
    if($('#return_date').val() < selectedDate) {
      $('#return_date').val(selectedDate);
    }
    
    // Trigger price calculation if available
    calculateTotalPrice();
  });
  
  // When return date changes
  $('#return_date').on('change', function() {
    calculateTotalPrice();
  });
}

// Calculate total price based on dates and rate
function calculateTotalPrice() {
  const rental_date = $('#rental_date').val();
  const return_date = $('#return_date').val();
  const vehicle_id = $('#vehicle_id').val();
  const voucher_code = $('#voucherSelect').val();

  if(rental_date && return_date && vehicle_id) {
    // Trigger preview calculation directly — #btnPreview no longer exists
    // (System Enhancements initiative, Step 2). refreshBookingPreview() is
    // defined in js/app.js and exposed on window for this call.
    window.refreshBookingPreview();
  }
}

// Initialize validation when document is ready
$(function() {
  setupDateValidation();

  // When voucher selection changes
  $('#voucherSelect').on('change', function() {
    if($('#rental_date').val() && $('#return_date').val()) {
      calculateTotalPrice();
    }
  });
});