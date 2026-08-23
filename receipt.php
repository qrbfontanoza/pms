<?php
session_start();
require 'db.php';

/**
 * UI Implementation Plan, Phase 12 (Final UI Review) — BUGS.md item 29.
 *
 * The two guard clauses below previously did `echo "<p>...</p>"; exit;` —
 * a bare fragment with no <head>, no viewport meta, no stylesheet and no
 * data-bs-theme attribute, so a mobile browser rendered it at its ~980px
 * desktop fallback width and it ignored the user's chosen theme entirely.
 * They are only reachable by hitting receipt.php with a missing/unknown
 * identifier (no normal application path does this), so this was never a
 * user-visible regression — but "renders an unstyled fragment" is a
 * markup defect regardless of how it is reached, and the fix is markup
 * only: no status code, no redirect, and no query/lookup behaviour
 * changes. Reuses the project's existing card/button/alert conventions
 * and the same no-flash theme bootstrap every other page carries, so the
 * error page is themed, responsive and navigable rather than a dead end.
 */
function receipt_error_page(string $title, string $message, int $status): void
{
    http_response_code($status);
    ?>
<!doctype html>
<html lang="en">
<head>
  <script>
    // Same no-flash theme bootstrap as the main receipt document below —
    // must stay inline in <head> so data-bs-theme is set before first paint.
    (function () {
      var stored = localStorage.getItem('pms-theme');
      var theme = stored || (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
      document.documentElement.setAttribute('data-bs-theme', theme);
    })();
  </script>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?> — PMS Car Rental</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body>
  <main class="container py-5">
    <div class="card shadow-sm mx-auto" style="max-width: 32rem;">
      <div class="card-body text-center p-4">
        <i class="fa-solid fa-circle-exclamation fa-2x text-body-secondary mb-3" aria-hidden="true"></i>
        <h1 class="h4 mb-2"><?= htmlspecialchars($title) ?></h1>
        <p class="text-body-secondary mb-4"><?= htmlspecialchars($message) ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
          <a href="transactions.php" class="btn btn-primary">View My Bookings</a>
          <a href="vehicles.php" class="btn btn-outline-secondary">Browse Vehicles</a>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
    <?php
    exit;
}

$user = $_SESSION['user'] ?? null;
if (!$user) {
  // Not logged in — redirect to login page
  header('Location: login.php');
  exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$ref = isset($_GET['ref']) ? trim($_GET['ref']) : null;

if (!$id && !$ref) {
  receipt_error_page(
    'Receipt unavailable',
    'This link is missing a booking reference, so there is nothing to show. Open the receipt from your bookings list instead.',
    400
  );
}

// Prepare query; allow lookup by id or booking_ref
if ($id) {
  $stmt = $pdo->prepare("SELECT b.*, v.title AS vehicle_title FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.id = ? AND b.user_id = ? LIMIT 1");
  $stmt->execute([$id, $user['id']]);
} else {
  $stmt = $pdo->prepare("SELECT b.*, v.title AS vehicle_title FROM bookings b LEFT JOIN vehicles v ON b.vehicle_id = v.id WHERE b.booking_ref = ? AND b.user_id = ? LIMIT 1");
  $stmt->execute([$ref, $user['id']]);
}

$booking = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$booking) {
  receipt_error_page(
    'Booking not found',
    "We couldn't find that booking, or it doesn't belong to your account.",
    404
  );
}
// Determine paid amount: prefer bookings.paid (new column). If not present, fall back to latest transaction.
$amount_paid = null;
$total_due = isset($booking['total_amount']) ? (float)$booking['total_amount'] : 0.0;
if (array_key_exists('paid', $booking) && $booking['paid'] !== null && $booking['paid'] !== '') {
  $amount_paid = (float)$booking['paid'];
} else {
  // fallback: look up most recent transaction for this booking
  $txnStmt = $pdo->prepare("SELECT * FROM transactions WHERE booking_id = ? ORDER BY paid_at DESC LIMIT 1");
  $txnStmt->execute([$booking['id']]);
  $txn = $txnStmt->fetch(PDO::FETCH_ASSOC);
  if ($txn && isset($txn['amount'])) {
    $amount_paid = (float)$txn['amount'];
  }
}

$change = null;
if ($amount_paid !== null) {
  $change = $amount_paid - $total_due;
}

// A completed booking gets the "Receipt" framing (a finished transaction); anything
// else (confirmed, pending) gets "Booking Confirmation" — the total/amount shown isn't
// necessarily final yet (early return, adjustments, or cancellation can still change it).
$is_completed = strtolower($booking['status'] ?? 'pending') === 'completed';
$doc_heading = $is_completed ? 'Booking Receipt' : 'Booking Confirmation';
$doc_note = $is_completed
  ? 'Please present this receipt and a valid ID at pickup.'
  : 'Please present this confirmation and a valid ID at pickup.';
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
  <title><?= htmlspecialchars($doc_heading) ?> — <?= htmlspecialchars($booking['booking_ref']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
  <style>
    body { padding-top: 60px; }
    .receipt-card { max-width: 900px; margin: 0 auto; }
  </style>
</head>
<body class="receipt-page"><!-- .receipt-page scopes css/styles.css's @media print block to this page only (BUGS.md item 26) -->
  <?php include 'includes/client_navbar.php'; ?>

  <main class="container py-5">
    <div class="receipt-card card shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h1 class="h4 mb-0"><?= htmlspecialchars($doc_heading) ?></h1>
            <small class="text-body-secondary">Reference: <?= htmlspecialchars($booking['booking_ref']) ?></small>
          </div>
          <div class="text-end">
            <h2 class="h5 mb-0">PMS Car Rental</h2>
            <small class="text-body-secondary">Contact: (02) 1234 5678</small>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <p class="mb-1"><strong>Vehicle</strong><br><?= htmlspecialchars($booking['vehicle_title'] ?? 'N/A') ?></p>
            <p class="mb-1"><strong>Rental Period</strong><br><?= htmlspecialchars($booking['rental_date']) ?> → <?= htmlspecialchars($booking['return_date']) ?></p>
            <p class="mb-1"><strong>Pickup Time</strong><br><?= htmlspecialchars($booking['pickup_time'] ?: 'N/A') ?></p>
          </div>
          <div class="col-md-6">
            <p class="mb-1"><strong>Days</strong><br><?= htmlspecialchars($booking['days']) ?></p>
            <p class="mb-1"><strong>Rate per Day</strong><br>₱<?= number_format($booking['rate'], 2) ?></p>
            <p class="mb-1"><strong>Total</strong><br>₱<?= number_format($booking['total_amount'], 2) ?></p>
          </div>
        </div>

        <hr>
        <div class="row">
          <div class="col-md-6">
            <p><strong>Contact Number</strong><br><?= htmlspecialchars($booking['contact_number'] ?? '-') ?></p>
            <p><strong>Driver Age</strong><br><?= htmlspecialchars($booking['age'] ?? '-') ?></p>
          </div>
          <div class="col-md-6 text-end">
            <p><strong>Amount Paid</strong><br>
            <?php if ($amount_paid !== null): ?>
              ₱<?= number_format($amount_paid, 2) ?>
            <?php else: ?>
              <span class="text-body-secondary">Not paid yet</span>
            <?php endif; ?>
            </p>

            <?php if ($change !== null): ?>
              <p class="mb-0"><strong>Change</strong><br>
                <?= $change >= 0 ? '₱' . number_format($change, 2) : '<span class="text-danger">-₱' . number_format(abs($change), 2) . ' (underpaid)</span>' ?>
              </p>
            <?php endif; ?>

            <p class="mb-0"><strong>Status</strong><br><?= htmlspecialchars(ucfirst($booking['status'] ?? 'pending')) ?></p>
          </div>
        </div>

        <div class="mt-4 text-center text-body-secondary">
          <small><?= htmlspecialchars($doc_note) ?></small>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <a href="vehicles.php" class="btn btn-outline-secondary">Exit</a>
        <a href="transactions.php" class="btn btn-link">View All Transactions</a>
      </div>
    </div>
  </main>

  <?php include 'includes/client_footer.php'; ?>

  <?php include 'includes/auth_modals.php'; ?>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
</body>
</html>
