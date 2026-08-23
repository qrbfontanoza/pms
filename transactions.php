  
<?php
session_start();
require 'db.php';

// PHP's default timezone is UTC, but the DB/server clock (and this business) is Asia/Manila
// (UTC+8) — without this, time()/strtotime() below would be 8 hours off from created_at,
// rental_date/pickup_time, and $today, breaking the "within 24/48 hours" alert windows.
date_default_timezone_set('Asia/Manila');

$user = $_SESSION['user'] ?? null;

if ($user) {
  // Fetch user's bookings
  $stmt = $pdo->prepare("
    SELECT
      b.id,
      b.status,
      b.booking_ref,
      b.rental_date,
      b.return_date,
      b.pickup_time,
      b.dropoff_time,
      b.days,
      b.rate,
      b.discount,
      b.total_amount,
      b.contact_number,
      b.created_at,
      v.title AS vehicle_title,
      (SELECT t.transaction_ref FROM transactions t WHERE t.booking_id = b.id ORDER BY t.id DESC LIMIT 1) AS transaction_ref
    FROM bookings b
    LEFT JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.user_id = ?
    ORDER BY b.rental_date DESC
  ");
  $stmt->execute([$user['id']]);
  $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Separate into active and completed, driven by actual status (not just date).
  // Active: pending/confirmed bookings whose return date hasn't passed yet.
  // Completed/History: completed bookings, cancelled bookings (regardless of date —
  // a cancelled booking is never "active" again), and any pending/confirmed booking
  // whose return date has already passed.
  $today = date('Y-m-d');
  $active = [];
  $completed = [];

  foreach ($bookings as $b) {
    $status = strtolower($b['status'] ?? 'pending');
    if (($status === 'pending' || $status === 'confirmed') && $b['return_date'] >= $today) {
      $active[] = $b;
    } else {
      $completed[] = $b;
    }
  }

  // Dashboard summary metrics — derived from $active/$completed only, no new query.
  $totalSpent = array_sum(array_column(array_merge($active, $completed), 'total_amount'));
  // Query is ORDER BY rental_date DESC, so the last element of $active is the nearest upcoming rental.
  $nextRental = !empty($active) ? $active[count($active) - 1] : null;

  // Dashboard alerts (Phase 7 Step 5, Option B) — contextual, dismissible, derived entirely
  // from $active/$completed. No new query beyond adding b.created_at above, no new table.
  //
  // The bookings table has no updated_at/confirmed_at/cancelled_at column — only created_at
  // (row insert time). There is no way to know exactly when a status changed. As an approximation,
  // "recently confirmed"/"recently cancelled" below means "created within the last 48 hours AND
  // currently in that status" — i.e. it also fires for a booking that was created and confirmed/
  // cancelled in the same action (the common case here, since bookings aren't re-confirmed after
  // creation in this system), but it is NOT a true status-change timestamp. This is documented in
  // CHANGELOG.md.
  $alerts = [];
  $now = time();
  $recentWindowSeconds = 48 * 3600;

  foreach ($active as $a) {
    $rentalStart = strtotime($a['rental_date'] . ' ' . ($a['pickup_time'] ?: '00:00:00'));
    $hoursUntil = ($rentalStart - $now) / 3600;
    if ($hoursUntil >= 0 && $hoursUntil <= 24) {
      $isToday = $a['rental_date'] === $today;
      $alerts[] = [
        'type' => 'warning',
        'icon' => 'fa-calendar-check',
        'message' => 'Reminder: Your rental of ' . htmlspecialchars($a['vehicle_title'] ?? 'your vehicle') .
          ($isToday ? ' starts today!' : ' starts tomorrow!'),
      ];
    }
  }

  foreach (array_merge($active, $completed) as $b2) {
    $status2 = strtolower($b2['status'] ?? 'pending');
    if (empty($b2['created_at'])) {
      continue;
    }
    $createdAgo = $now - strtotime($b2['created_at']);
    if ($createdAgo < 0 || $createdAgo > $recentWindowSeconds) {
      continue;
    }
    if ($status2 === 'confirmed') {
      $alerts[] = [
        'type' => 'info',
        'icon' => 'fa-bell',
        'message' => 'Your booking for ' . htmlspecialchars($b2['vehicle_title'] ?? 'your vehicle') . ' has been confirmed!',
      ];
    } elseif ($status2 === 'cancelled' || $status2 === 'canceled') {
      $alerts[] = [
        'type' => 'warning',
        'icon' => 'fa-exclamation-triangle',
        'message' => 'Booking for ' . htmlspecialchars($b2['vehicle_title'] ?? 'your vehicle') . ' was cancelled.',
      ];
    }
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <script>
    // No-flash theme bootstrap (System Enhancements initiative, Step 8b).
    // Runs before the stylesheet so data-bs-theme is set before first
    // paint — must stay here in <head>, not move to an external file, or
    // the page would flash light before flipping dark. localStorage wins
    // if the user has manually chosen before (Decision D1); otherwise the
    // OS preference seeds it, matching this project's existing
    // prefers-reduced-motion respect (css/styles.css).
    (function () {
      var stored = localStorage.getItem('pms-theme');
      var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PMS Car Rental — Transactions</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>

<body>
  <?php include 'includes/client_navbar.php'; ?>

  <div style="height:80px"></div>

  <main class="container py-5">
    <div class="text-center mb-4" data-reveal>
      <h1 class="fw-bold display-5 font-poppins">My Dashboard</h1>
      <p class="text-body-secondary">View your current and past rentals at a glance</p>
    </div>

    <?php if (!$user): ?>
      <!-- Not logged in -->
      <div class="text-center my-5">
        <div class="display-6 mb-3 text-body-secondary">No transactions yet</div>
        <p class="mb-4">Sign up or log in to view your transaction history.</p>
        <button class="btn btn-outline-primary rounded-pill btn-login me-2">Log In</button>
        <button class="btn btn-primary rounded-pill btn-signup">Sign Up</button>
      </div>

    <?php elseif (empty($bookings)): ?>
      <!-- Logged in but no bookings -->
      <div class="text-center my-5">
        <div class="display-6 mb-3 text-body-secondary">No transactions yet</div>
        <p class="mb-4">You have not made any bookings yet.</p>
        <a href="vehicles.php" class="btn btn-primary rounded-pill">Book a Vehicle</a>
      </div>

    <?php else: ?>
      <!-- Summary Cards -->
      <section class="mb-5">
        <h2 class="h4 fw-semibold mb-3">Overview</h2>
        <div class="row g-3 g-lg-4" data-reveal-stagger>
          <div class="col-sm-6 col-xl-3" data-reveal>
            <div class="dashboard-stat-card p-3 bg-body rounded-3 shadow-sm text-center h-100">
              <i class="fas fa-car dashboard-stat-icon mb-2" aria-hidden="true"></i>
              <h3 class="h6 mb-1">Active Rentals</h3>
              <div class="h2 dashboard-stat-value mb-0"><?= count($active) ?></div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3" data-reveal>
            <div class="dashboard-stat-card p-3 bg-body rounded-3 shadow-sm text-center h-100">
              <i class="fas fa-check-circle dashboard-stat-icon mb-2" aria-hidden="true"></i>
              <h3 class="h6 mb-1">Completed Rentals</h3>
              <div class="h2 dashboard-stat-value mb-0"><?= count($completed) ?></div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3" data-reveal>
            <div class="dashboard-stat-card p-3 bg-body rounded-3 shadow-sm text-center h-100">
              <i class="fas fa-money-bill-wave dashboard-stat-icon mb-2" aria-hidden="true"></i>
              <h3 class="h6 mb-1">Total Spent</h3>
              <div class="h2 dashboard-stat-value mb-0">₱<?= number_format($totalSpent, 2) ?></div>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3" data-reveal>
            <div class="dashboard-stat-card p-3 bg-body rounded-3 shadow-sm text-center h-100">
              <i class="fas fa-calendar-alt dashboard-stat-icon mb-2" aria-hidden="true"></i>
              <h3 class="h6 mb-1">Next Rental</h3>
              <div class="h4 dashboard-stat-value mb-0">
                <?= $nextRental ? htmlspecialchars(date('M j, Y', strtotime($nextRental['rental_date']))) : 'None scheduled' ?>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- NOTIFICATIONS / BOOKING STATUS ALERTS -->
      <?php if (!empty($alerts)): ?>
        <section class="mb-5" data-reveal>
          <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show" role="alert">
              <i class="fas <?= $alert['icon'] ?> me-2" aria-hidden="true"></i><?= $alert['message'] ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <!-- ACTIVE RENTALS -->
      <?php if (!empty($active)): ?>
        <section class="mb-5" data-reveal>
        <h2 class="h4 fw-semibold mb-3 text-primary"><i class="fas fa-car" aria-hidden="true"></i> Active & Upcoming Rentals</h2>
        <div id="bookingActionAlert"></div>
        <div class="list-group">
          <?php foreach ($active as $a): ?>
            <?php
              // Time-window label only (Upcoming vs Active) — date-string comparison avoids
              // same-day return_date being misread as "past" due to time-of-day.
              if ($today < $a['rental_date']) {
                $time_status = 'Upcoming';
                // text-body required: white badge text on Bootstrap's bg-info
                // (#0dcaf0) measures ~1.96:1 contrast, far below WCAG AA's 4.5:1 —
                // #212529-on-#0dcaf0 measures ~7.9:1.
                $time_badge_class = 'bg-info text-body';
              } else {
                $time_status = 'Active';
                $time_badge_class = 'bg-success';
              }

              // Booking status badge — $active only ever contains pending/confirmed (see bucketing above).
              $booking_status = strtolower($a['status'] ?? 'pending');
              $booking_badge_class = $booking_status === 'confirmed' ? 'bg-primary' : 'bg-warning text-body';
              $booking_label = ucfirst($booking_status);
            ?>
            <div class="list-group-item">
              <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-start gap-2">
                <div class="text-truncate">
                  <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h3 class="h6 mb-0 text-truncate"><?= htmlspecialchars($a['vehicle_title'] ?? 'Unknown Vehicle') ?></h3>
                    <span class="badge <?= $time_badge_class ?>" role="status"><?= $time_status ?></span>
                    <span class="badge <?= $booking_badge_class ?>" role="status"><?= $booking_label ?></span>
                  </div>
                  <small class="text-body-secondary">
                    <?= htmlspecialchars($a['rental_date']) ?> → <?= htmlspecialchars($a['return_date']) ?><br>
                    <?= htmlspecialchars($a['pickup_time'] ?: '-') ?> - <?= htmlspecialchars($a['dropoff_time'] ?: '-') ?>
                  </small>
                </div>
                <div class="text-md-end">
                  <div class="fw-bold text-success">₱<?= number_format($a['total_amount'], 2) ?></div>
                  <small class="text-body-secondary">Ref: <?= htmlspecialchars($a['booking_ref']) ?></small>
                </div>
              </div>
              <div class="mt-2 d-grid d-md-flex gap-2">
                <?php if ($time_status === 'Active'): ?>
                  <button type="button" class="btn btn-outline-warning py-3" onclick="showReturnEarlyModal('<?= $a['id'] ?>')" aria-label="Return <?= htmlspecialchars($a['vehicle_title']) ?> early">
                    Return Early
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-outline-danger py-3" onclick="showCancelModal('<?= $a['id'] ?>', '<?= htmlspecialchars($a['vehicle_title']) ?>', '<?= $a['rental_date'] ?>')" aria-label="Cancel booking for <?= htmlspecialchars($a['vehicle_title']) ?>">
                    Cancel Booking
                  </button>
                <?php endif; ?>
                <?php if ($booking_status === 'confirmed'): ?>
                  <a href="receipt.php?ref=<?= urlencode($a['booking_ref']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary py-3" aria-label="View booking confirmation for booking <?= htmlspecialchars($a['booking_ref']) ?>">
                    <i class="fas fa-file-invoice me-1" aria-hidden="true"></i> View Booking Confirmation
                  </a>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        </section>
      <?php endif; ?>

      <!-- COMPLETED RENTALS -->
      <?php if (!empty($completed)): ?>
        <section class="mb-5" data-reveal>
        <h2 class="h4 fw-semibold mb-3 text-secondary"><i class="fas fa-check-circle" aria-hidden="true"></i> Completed Rentals</h2>
        <div class="list-group">
          <?php foreach ($completed as $c): ?>
            <?php
              $c_status = strtolower($c['status'] ?? 'pending');
              switch ($c_status) {
                case 'completed':
                  $c_badge_class = 'bg-success';
                  break;
                case 'confirmed':
                  $c_badge_class = 'bg-primary';
                  break;
                case 'cancelled':
                case 'canceled':
                  $c_badge_class = 'bg-danger';
                  break;
                default:
                  $c_badge_class = 'bg-warning text-body';
                  break;
              }
              $c_label = ucfirst($c_status);
            ?>
            <div class="list-group-item">
              <div class="d-flex flex-column flex-md-row justify-content-md-between align-items-md-start gap-2">
                <div class="text-truncate">
                  <h3 class="h6 mb-1 text-truncate"><?= htmlspecialchars($c['vehicle_title'] ?? 'Unknown Vehicle') ?> <span class="badge <?= $c_badge_class ?> ms-2" role="status"><?= $c_label ?></span></h3>
                  <small class="text-body-secondary">
                    <?= htmlspecialchars($c['rental_date']) ?> → <?= htmlspecialchars($c['return_date']) ?><br>
                    <?= htmlspecialchars($c['pickup_time'] ?: '-') ?> - <?= htmlspecialchars($c['dropoff_time'] ?: '-') ?>
                  </small>
                </div>
                <div class="text-md-end">
                  <div class="fw-bold text-body">₱<?= number_format($c['total_amount'], 2) ?></div>
                </div>
              </div>
              <?php if ($c_status === 'completed'): ?>
              <div class="mt-2 d-grid d-md-flex">
                <a href="receipt.php?ref=<?= urlencode($c['booking_ref']) ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary py-3" aria-label="View receipt for booking <?= htmlspecialchars($c['booking_ref']) ?>">
                  <i class="fas fa-receipt me-1" aria-hidden="true"></i> View Receipt
                </a>
              </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <?php if ($user): ?>
      <!-- PROFILE SECTION -->
      <section class="mb-5" id="profile" data-reveal>
        <h2 class="h4 fw-semibold mb-3"><i class="fas fa-user" aria-hidden="true"></i> My Profile</h2>
        <div class="row g-3 g-lg-4">
          <div class="col-lg-6">
            <div class="bg-body rounded-3 shadow-sm p-4 h-100">
              <h3 class="h6 fw-semibold mb-3">Profile Information</h3>
              <div id="profileInfoAlert" class="alert d-none" role="alert" tabindex="-1"></div>
              <div id="profilePictureAlert" class="alert d-none" role="alert" tabindex="-1"></div>

              <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-start gap-3 mb-4">
                <div id="profilePictureDisplay" style="width:96px;height:96px;"></div>
                <form id="profilePictureForm" class="flex-grow-1 w-100" novalidate>
                  <label for="profilePictureInput" class="form-label">Profile Picture</label>
                  <div class="d-flex flex-column flex-sm-row gap-2">
                    <input type="file" class="form-control" id="profilePictureInput" name="profile_picture" accept="image/jpeg,image/png" aria-describedby="profilePictureInputHelp">
                    <button type="submit" id="profilePictureSaveBtn" class="btn btn-outline-primary rounded-pill px-4 py-3 text-nowrap" aria-label="Upload profile picture">Upload</button>
                  </div>
                  <div class="form-text" id="profilePictureInputHelp">JPG or PNG, up to 3MB</div>
                </form>
              </div>

              <dl class="row mb-4">
                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Name</dt>
                <dd class="col-7 col-sm-8" id="profileNameDisplay">—</dd>
                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Email</dt>
                <dd class="col-7 col-sm-8 text-break" id="profileEmailDisplay">—</dd>
                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Member Since</dt>
                <dd class="col-7 col-sm-8" id="profileMemberSinceDisplay">—</dd>
                <dt class="col-5 col-sm-4 text-body-secondary fw-normal">Role</dt>
                <dd class="col-7 col-sm-8" id="profileRoleDisplay">—</dd>
              </dl>
              <form id="profileInfoForm" novalidate>
                <div class="mb-3">
                  <label for="profileNameInput" class="form-label">Name</label>
                  <input type="text" class="form-control" id="profileNameInput" required autocomplete="name" aria-describedby="profileNameInputFeedback">
                  <div class="invalid-feedback" id="profileNameInputFeedback"></div>
                </div>
                <div class="mb-3">
                  <label for="profileEmailInput" class="form-label">Email</label>
                  <input type="email" class="form-control" id="profileEmailInput" required autocomplete="email" aria-describedby="profileEmailInputFeedback">
                  <div class="invalid-feedback" id="profileEmailInputFeedback"></div>
                </div>
                <div class="d-grid d-md-flex">
                  <button type="submit" id="profileInfoSaveBtn" class="btn btn-primary rounded-pill px-4 py-3" aria-label="Save profile information changes">Save Changes</button>
                </div>
              </form>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="bg-body rounded-3 shadow-sm p-4 h-100">
              <h3 class="h6 fw-semibold mb-3">Change Password</h3>
              <div id="changePasswordAlert" class="alert d-none" role="alert" tabindex="-1"></div>
              <form id="changePasswordForm" novalidate>
                <div class="mb-3">
                  <label for="currentPasswordInput" class="form-label">Current Password</label>
                  <input type="password" class="form-control" id="currentPasswordInput" required autocomplete="current-password" aria-describedby="currentPasswordInputFeedback">
                  <div class="invalid-feedback" id="currentPasswordInputFeedback"></div>
                </div>
                <div class="mb-3">
                  <label for="newPasswordInput" class="form-label">New Password</label>
                  <input type="password" class="form-control" id="newPasswordInput" required autocomplete="new-password" aria-describedby="newPasswordInputFeedback newPasswordInputHelp">
                  <div class="invalid-feedback" id="newPasswordInputFeedback"></div>
                  <div class="form-text" id="newPasswordInputHelp">Minimum 6 characters</div>
                </div>
                <div class="mb-3">
                  <label for="confirmNewPasswordInput" class="form-label">Confirm New Password</label>
                  <input type="password" class="form-control" id="confirmNewPasswordInput" required autocomplete="new-password" aria-describedby="confirmNewPasswordInputFeedback">
                  <div class="invalid-feedback" id="confirmNewPasswordInputFeedback"></div>
                </div>
                <div class="d-grid d-md-flex">
                  <button type="submit" id="changePasswordSaveBtn" class="btn btn-primary rounded-pill px-4 py-3" aria-label="Save new password">Save Changes</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <!-- Return Early Modal -->
  <div class="modal fade" id="returnEarlyModal" tabindex="-1" aria-labelledby="returnEarlyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="returnEarlyModalLabel">Return Vehicle Early</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="returnEarlyForm">
            <input type="hidden" id="booking_id">
            <p>Are you sure you want to return this vehicle early? Your rental period will end today.</p>
            <p class="text-body-secondary small">Note: No refunds will be provided for unused rental days.</p>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-warning" onclick="confirmReturnEarly(this)">Confirm Return</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Cancel Booking Modal -->
  <div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-labelledby="cancelBookingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="cancelBookingModalLabel">Cancel Booking</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="cancelBookingForm">
            <input type="hidden" id="cancel_booking_id">
            <div id="cancelBookingDetails" class="mb-3"></div>
            <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
            <div class="alert alert-info">
              <small>
                <i class="fas fa-info-circle"></i> Cancellation Policy:
                <ul class="mb-0">
                  <li>Full refund if cancelled at least 24 hours before rental date</li>
                  <li>50% refund if cancelled less than 24 hours before rental date</li>
                  <li>No refund for same-day cancellations</li>
                </ul>
              </small>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Keep Booking</button>
          <button type="button" class="btn btn-danger" onclick="confirmCancellation(this)">Confirm Cancellation</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Return Receipt Modal -->
  <div class="modal fade" id="returnReceiptModal" tabindex="-1" aria-labelledby="returnReceiptModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="returnReceiptModalLabel">Return Completed</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="returnReceiptContent">
          <!-- Content will be populated dynamically -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
  <script>

    // "My Profile" navbar link lands here as transactions.php#profile —
    // scroll it into view, offsetting for the fixed navbar (see the
    // height:80px spacer above), and skip the offset native browser jump
    // that would otherwise leave the section partially hidden underneath it.
    if (window.location.hash === '#profile') {
      const profileEl = document.getElementById('profile');
      if (profileEl) {
        setTimeout(function () {
          const y = profileEl.getBoundingClientRect().top + window.pageYOffset - 90;
          window.scrollTo({ top: y, behavior: 'smooth' });
        }, 300);
      }
    }

    function showBookingAlert(type, message) {
      const container = document.getElementById('bookingActionAlert');
      if (!container) return;
      container.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
          ${message}
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      `;
    }

    function showReturnEarlyModal(bookingId) {
      document.getElementById('booking_id').value = bookingId;
      new bootstrap.Modal(document.getElementById('returnEarlyModal')).show();
    }

    function showCancelModal(bookingId, vehicleTitle, rentalDate) {
      document.getElementById('cancel_booking_id').value = bookingId;
      document.getElementById('cancelBookingDetails').innerHTML = `
        <strong>Vehicle:</strong> ${vehicleTitle}<br>
        <strong>Rental Date:</strong> ${rentalDate}
      `;
      new bootstrap.Modal(document.getElementById('cancelBookingModal')).show();
    }

    async function confirmCancellation(btn) {
      const bookingId = document.getElementById('cancel_booking_id').value;
      const $btn = $(btn);

      PMSMotion.setButtonLoading($btn, true);
      try {
        const response = await fetch('cancel_booking.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            booking_id: bookingId
          })
        });

        const data = await response.json();

        if (data.success) {
          const modalEl = document.getElementById('cancelBookingModal');
          bootstrap.Modal.getInstance(modalEl)?.hide();
          showBookingAlert('success', data.message || 'Booking cancelled successfully!');
          setTimeout(() => window.location.reload(), 3000);
        } else {
          showBookingAlert('danger', data.error || 'Failed to cancel booking');
        }
      } catch (error) {
        console.error('Error:', error);
        showBookingAlert('danger', 'Failed to process cancellation');
      } finally {
        PMSMotion.setButtonLoading($btn, false);
      }
    }

    async function confirmReturnEarly(btn) {
      const bookingId = document.getElementById('booking_id').value;
      const $btn = $(btn);

      PMSMotion.setButtonLoading($btn, true);
      try {
        const response = await fetch('return_early.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            booking_id: bookingId
          })
        });

        const data = await response.json();
        
        if (data.success) {
          // Close the return early modal
          bootstrap.Modal.getInstance(document.getElementById('returnEarlyModal')).hide();

          // Show success alert with receipt
          const receiptModal = new bootstrap.Modal(document.getElementById('returnReceiptModal'));
          document.getElementById('returnReceiptContent').innerHTML = `
            <div class="alert alert-success mb-4">
              <i class="fas fa-check-circle me-2"></i> Vehicle returned successfully!
            </div>
            <div class="card">
              <div class="card-body">
                <h6 class="card-title">Return Receipt</h6>
                <div class="row g-3">
                  <div class="col-md-6">
                    <p><strong>Vehicle:</strong><br>${data.booking_details.vehicle_name}</p>
                    <p><strong>Booking Reference:</strong><br>${data.booking_details.booking_ref}</p>
                  </div>
                  <div class="col-md-6">
                    <p><strong>Original Return Date:</strong><br>${data.booking_details.return_date}</p>
                    ${data.booking_details.actual_return_date ? 
                      `<p><strong>Actual Return Date:</strong><br>${data.booking_details.actual_return_date}</p>` : ''}
                  </div>
                </div>
                ${data.booking_details.early_return ? `
                  <div class="alert alert-info mt-3">
                    <small><i class="fas fa-info-circle"></i> This vehicle was returned earlier than scheduled. 
                    Note that no refunds are provided for unused rental days.</small>
                  </div>
                ` : ''}
              </div>
            </div>
          `;
          receiptModal.show();

          // Reload page after 3 seconds
          setTimeout(() => window.location.reload(), 3000);
        } else {
          showBookingAlert('danger', data.error || 'Failed to return vehicle');
        }
      } catch (error) {
        console.error('Error:', error);
        showBookingAlert('danger', 'Failed to process return');
      } finally {
        PMSMotion.setButtonLoading($btn, false);
      }
    }
  </script>
</body>
</html>
