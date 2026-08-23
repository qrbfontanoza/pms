<?php
require 'db.php'; // connect to database
?>


<?php session_start();
 ?>


<?php
// Sort whitelist — never interpolate $_GET['sort'] directly into SQL
$allowedSorts = [
  'title_asc'  => 'title ASC',
  'title_desc' => 'title DESC',
  'price_asc'  => 'price_per_day ASC',
  'price_desc' => 'price_per_day DESC',
  'newest'     => 'id DESC',
];
$sortParam = $_GET['sort'] ?? 'title_asc';
$orderBy = $allowedSorts[$sortParam] ?? 'title ASC';
if (!isset($allowedSorts[$sortParam])) {
  $sortParam = 'title_asc';
}

// --- Filter parameters (Step 5) -------------------------------------------
// Homepage compatibility: the homepage search widget hands off a plain
// ?category=suv string; the sidebar form submits ?category[]=suv. Normalize
// the string form into a one-element array so both feed the same IN (?) logic.
$rawCategory = $_GET['category'] ?? [];
if (is_string($rawCategory)) {
  $rawCategory = $rawCategory !== '' ? [strtolower(trim($rawCategory))] : [];
} else {
  $rawCategory = array_map(fn($c) => strtolower(trim((string)$c)), (array)$rawCategory);
}
$filterCategories    = $rawCategory;
$filterFuels         = isset($_GET['fuel']) ? (array)$_GET['fuel'] : [];
$filterTransmissions = isset($_GET['transmission']) ? (array)$_GET['transmission'] : [];
$filterPriceMin      = isset($_GET['price_min']) ? (float)$_GET['price_min'] : 0;
$filterPriceMax      = isset($_GET['price_max']) ? (float)$_GET['price_max'] : 0;
$filterAvailableOnly = isset($_GET['available_only']);

// Seat buckets — corrected ranges (every seat count maps to exactly one
// bucket, no gaps/overlap): 2 / 4-6 / 7-8 / 9+.
$validSeatBuckets = ['2', '4-6', '7-8', '9+'];
$filterSeatBuckets = isset($_GET['seats']) ? array_values(array_intersect((array)$_GET['seats'], $validSeatBuckets)) : [];

// --- Sidebar static data (Step 4, corrected per Decisions 1 & 2) ----------
// Category counts and seat-bucket counts are computed against the full
// is_active=1 set, independent of any active filter (Decision 1: static,
// unfiltered counts — they must not shrink as filters are applied).
$allActiveVehicles = $pdo->query("SELECT category, seats FROM vehicles WHERE is_active = 1")->fetchAll();

$categoryCounts = [];
foreach ($allActiveVehicles as $v) {
  $cat = $v['category'];
  $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
}
ksort($categoryCounts);

// Price range and distinct fuel/transmission — small read-only lookups on
// the same $pdo connection (no second DB connection introduced).
$priceRange = $pdo->query("SELECT MIN(price_per_day) AS min_price, MAX(price_per_day) AS max_price FROM vehicles WHERE is_active = 1")->fetch();
$fuelOptions = $pdo->query("SELECT DISTINCT fuel FROM vehicles WHERE is_active = 1 ORDER BY fuel")->fetchAll(PDO::FETCH_COLUMN);
$transmissionOptions = $pdo->query("SELECT DISTINCT transmission FROM vehicles WHERE is_active = 1 ORDER BY transmission")->fetchAll(PDO::FETCH_COLUMN);

// Seats: corrected buckets (Decision 2), counts static/unfiltered (Decision 1).
$seatBuckets = [
  '2'   => ['label' => '2 Seats',   'count' => 0],
  '4-6' => ['label' => '4-6 Seats', 'count' => 0],
  '7-8' => ['label' => '7-8 Seats', 'count' => 0],
  '9+'  => ['label' => '9+ Seats',  'count' => 0],
];
foreach ($allActiveVehicles as $v) {
  $seats = (int)$v['seats'];
  if ($seats === 2) {
    $seatBuckets['2']['count']++;
  } elseif ($seats >= 4 && $seats <= 6) {
    $seatBuckets['4-6']['count']++;
  } elseif ($seats >= 7 && $seats <= 8) {
    $seatBuckets['7-8']['count']++;
  } elseif ($seats >= 9) {
    $seatBuckets['9+']['count']++;
  }
}

// --- Build the filtered vehicle query (Step 5, adapted in Step 6 for the
// LEFT JOIN alias) -----------------------------------------------------------
// Every filter value below is bound as a parameter — never string-interpolated.
// Columns are prefixed with v. now that vehicles is joined against the
// booked-count subquery (bc) below.
$where = ['v.is_active = 1'];
$params = [];

if (!empty($filterCategories)) {
  $placeholders = implode(',', array_fill(0, count($filterCategories), '?'));
  $where[] = "LOWER(v.category) IN ($placeholders)";
  $params = array_merge($params, $filterCategories);
}

if (!empty($filterFuels)) {
  $placeholders = implode(',', array_fill(0, count($filterFuels), '?'));
  $where[] = "v.fuel IN ($placeholders)";
  $params = array_merge($params, $filterFuels);
}

if (!empty($filterTransmissions)) {
  $placeholders = implode(',', array_fill(0, count($filterTransmissions), '?'));
  $where[] = "v.transmission IN ($placeholders)";
  $params = array_merge($params, $filterTransmissions);
}

if (!empty($filterSeatBuckets)) {
  $seatClauses = [];
  foreach ($filterSeatBuckets as $bucket) {
    switch ($bucket) {
      case '2':
        $seatClauses[] = 'v.seats = ?';
        $params[] = 2;
        break;
      case '4-6':
        $seatClauses[] = 'v.seats BETWEEN ? AND ?';
        $params[] = 4;
        $params[] = 6;
        break;
      case '7-8':
        $seatClauses[] = 'v.seats BETWEEN ? AND ?';
        $params[] = 7;
        $params[] = 8;
        break;
      case '9+':
        $seatClauses[] = 'v.seats >= ?';
        $params[] = 9;
        break;
    }
  }
  if (!empty($seatClauses)) {
    $where[] = '(' . implode(' OR ', $seatClauses) . ')';
  }
}

if ($filterPriceMin > 0) {
  $where[] = 'v.price_per_day >= ?';
  $params[] = $filterPriceMin;
}

if ($filterPriceMax > 0) {
  $where[] = 'v.price_per_day <= ?';
  $params[] = $filterPriceMax;
}

// Availability filter (Step 6): moved from a Step 5 PHP post-filter into the
// SQL WHERE clause, using the booked_count from the LEFT JOIN below. This
// must run *before* LIMIT/OFFSET — filtering after pagination would let a
// page with unavailable vehicles come back with fewer than $perPage rows
// even though more available vehicles exist on later pages, and would also
// make the total/page-count math wrong.
if ($filterAvailableOnly) {
  $where[] = '(v.units_total - COALESCE(bc.booked_count, 0)) > 0';
}

$whereSql = implode(' AND ', $where);

// Booked-count join: replaces the old per-vehicle N+1 query (one query per
// card for the Available/Unavailable badge) with a single LEFT JOIN against
// a pre-aggregated subquery. Same status filter as the old per-vehicle
// query, so "booked" means the same thing it always did.
$joinSql = "
  LEFT JOIN (
    SELECT vehicle_id, COUNT(*) AS booked_count
    FROM bookings
    WHERE status IN ('pending','confirmed','completed')
    GROUP BY vehicle_id
  ) bc ON v.id = bc.vehicle_id
";

// --- Pagination (Step 6) ----------------------------------------------------
$perPage = 9;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

// Count query — identical filters (incl. availability), no ORDER BY/LIMIT.
$countSql = "SELECT COUNT(*) FROM vehicles v $joinSql WHERE $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalVehicles = (int)$countStmt->fetchColumn();

$totalPages = max(1, (int)ceil($totalVehicles / $perPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $perPage;

// Data query — single LEFT JOIN, no per-vehicle queries.
$dataSql = "
  SELECT v.*, COALESCE(bc.booked_count, 0) AS booked_count
  FROM vehicles v
  $joinSql
  WHERE $whereSql
  ORDER BY $orderBy
  LIMIT ? OFFSET ?
";
$dataParams = array_merge($params, [$perPage, $offset]);
$stmt = $pdo->prepare($dataSql);
$stmt->execute($dataParams);
$vehicles = $stmt->fetchAll();

$vehicleCount = count($vehicles);

// Pagination URL helper — carries the current filter + sort state forward.
function paginationUrl($page, $params) {
  $params['page'] = $page;
  return 'vehicles.php?' . http_build_query($params);
}

$paginationParams = ['sort' => $sortParam];
if (!empty($filterCategories))    $paginationParams['category'] = $filterCategories;
if (!empty($filterFuels))         $paginationParams['fuel'] = $filterFuels;
if (!empty($filterTransmissions)) $paginationParams['transmission'] = $filterTransmissions;
if (!empty($filterSeatBuckets))   $paginationParams['seats'] = $filterSeatBuckets;
if ($filterPriceMin > 0)          $paginationParams['price_min'] = $filterPriceMin;
if ($filterPriceMax > 0)          $paginationParams['price_max'] = $filterPriceMax;
if ($filterAvailableOnly)         $paginationParams['available_only'] = 1;
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
  <title>PMS Car Rental — Vehicles</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <!-- Animate.css for entrance/scroll animations -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>


<body>
  <?php include 'includes/client_navbar.php'; ?>


  <!-- BOOKING MODAL -->
<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-poppins" id="bookingModalLabel">Book This Vehicle</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>


      <div class="modal-body">
        <!-- Alert -->
        <div id="bookingAlert" class="alert d-none" role="alert"></div>


        <form id="bookingForm">
          <input type="hidden" id="vehicle_id">


          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="rental_date">Rental Date</label>
              <input type="date" class="form-control" id="rental_date" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="return_date">Return Date</label>
              <input type="date" class="form-control" id="return_date" required>
            </div>


            <div class="col-md-6">
              <label class="form-label" for="pickup_time">Pick-up Time</label>
              <input type="time" class="form-control" id="pickup_time" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="dropoff_time">Drop-off Time</label>
              <input type="time" class="form-control" id="dropoff_time" required>
            </div>


            <div class="col-md-6">
              <label class="form-label" for="contact_number">Contact Number</label>
              <input type="tel" class="form-control" id="contact_number" required>
            </div>


            <div class="col-md-3">
              <label class="form-label" for="age">Age</label>
              <input type="number" class="form-control" id="age" required min="18">
            </div>

            <div class="col-md-6">
              <label class="form-label" for="license_file">Upload Driver's License</label>
                <input type="file" class="form-control" id="license_file" accept="image/jpeg,image/png" required aria-describedby="licenseFileHelp">
                <div class="form-text" id="licenseFileHelp">Required — please upload a valid driver's license (JPG or PNG only, max 5MB).</div>
            </div>


            <div class="col-md-6">
              <label class="form-label" for="voucherSelect">Apply Voucher</label>
              <select class="form-select" id="voucherSelect">
                <option value="">Select a voucher (optional)</option>
              </select>
              <div class="form-text">Select a voucher to get instant discount</div>
            </div>


          </div>


          <hr class="my-4">


          <!-- UI Implementation Plan, Phase 12 (Final UI Review): a
               commented-out earlier copy of the #bookingPreview markup
               (BUGS.md, Deprecated Code) was deleted here. It duplicated
               three of this block's element ids verbatim
               (bookingPreview, previewContent, amount_paid), which made
               every duplicate-ID audit of this file report three false
               positives, and its <label> had no `for`
               attribute — i.e. it would have re-introduced the exact defect
               Phase 10 fixed on the live copy below if it were ever
               uncommented. The live version immediately below supersedes it
               in full. -->
          <div id="bookingPreview" class="mb-3 js-booking-reveal">
            <div class="card">
              <div class="card-body">
                <h6 class="card-title">Booking Summary</h6>
                <div id="previewContent"></div>
                <!-- amount input lives in the always-visible #amountPaidSection below -->
              </div>
            </div>
          </div>


          <!-- Amount input: always present; #btnConfirm stays disabled until
               a successful preview (js/app.js's refreshBookingPreview())
               enables it, so this no longer needs to be hidden/revealed
               separately (System Enhancements initiative, Step 2). -->
          <div id="amountPaidSection" class="mb-3">
            <div class="card">
              <div class="card-body">
                <label class="form-label" for="amount_paid">Amount to Pay</label>
                <input type="number" id="amount_paid" class="form-control" placeholder="Enter amount" step="0.01">
                <div class="form-text">Please enter the payment amount</div>
              </div>
            </div>
          </div>


          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success rounded-pill" id="btnConfirm" disabled>Confirm Booking</button>
          </div>
        </form>


        <!-- Receipt -->
        <div id="receiptArea" class="d-none mt-4">
          <h6 class="mb-3 font-poppins">Booking Receipt</h6>
          <div class="card">
            <div class="card-body" id="receiptContent"></div>
          </div>
          <div class="text-center mt-3">
            <span class="fw-bold text-success">Booking Confirmed!</span><br>
            <small class="text-body-secondary">Please present this receipt, license, and 1 valid ID upon pickup.</small>
          </div>
        </div>


      </div>
    </div>
  </div>
</div>


  <!-- VEHICLE DETAILS MODAL -->
  <div class="modal fade" id="vehicleDetailsModal" tabindex="-1" aria-labelledby="vehicleDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-poppins" id="vehicleDetailsModalLabel">Vehicle Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="row g-4">
            <div class="col-12 col-md-5">
              <img id="detailsModalImage" src="" alt="" class="details-modal-img rounded-3 w-100 js-fade-on-load">
            </div>
            <div class="col-12 col-md-7">
              <h4 id="detailsModalTitle" class="mb-1"></h4>
              <span id="detailsModalCategory" class="badge bg-primary rounded-pill mb-2"></span>
              <div class="vehicle-pricebar mb-2">
                <div id="detailsModalPrice" class="price fs-4"></div>
              </div>
              <div class="mb-3">
                <span id="detailsModalAvailability" class="badge"></span>
              </div>
              <div class="vehicle-specs">
                <div><span id="detailsModalSeats"></span></div>
                <div><span id="detailsModalFuel"></span></div>
                <div><span id="detailsModalTransmission"></span></div>
              </div>
            </div>
          </div>

          <hr class="my-4">

          <div class="rental-info-block">
            <div class="section-eyebrow">Rental Information</div>
            <ul class="text-body-secondary small mb-0 ps-3">
              <li>Minimum age: 18 years old</li>
              <li>Valid driver's license required</li>
              <li>Pick-up/drop-off times apply as scheduled</li>
            </ul>
          </div>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary rounded-pill" id="btnReserveFromDetails">Reserve Now</button>
        </div>
      </div>
    </div>
  </div>




  <main class="container py-5 navbar-offset">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page">Vehicles</li>
      </ol>
    </nav>
    <div class="text-center mb-4">
      <div class="section-eyebrow">VEHICLES</div>
      <h1 class="h2 fw-bold">Browse Our Vehicles</h1>
      <p class="text-body-secondary">Find the perfect vehicle for your next trip</p>
    </div>
    <button class="btn btn-outline-primary d-lg-none mb-3" type="button"
      data-bs-toggle="offcanvas" data-bs-target="#filterSidebar" aria-controls="filterSidebar">
      <i class="fas fa-sliders-h me-2"></i>Filters
      <span class="badge bg-primary rounded-pill ms-2 d-none" id="activeFilterCount">0</span>
    </button>

    <div class="row">
      <!-- Sidebar filter panel (Step 4) -->
      <div class="col-lg-3">
        <div class="offcanvas-lg offcanvas-start filter-sidebar" tabindex="-1" id="filterSidebar" aria-labelledby="filterSidebarLabel">
          <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="filterSidebarLabel">Filters</h5>
            <button type="button" class="btn-close d-lg-none" data-bs-dismiss="offcanvas"
              data-bs-target="#filterSidebar" aria-label="Close"></button>
          </div>
          <div class="offcanvas-body p-3">
            <form method="GET" action="vehicles.php" id="filterForm">
              <div class="filter-group">
                <div class="filter-group-title">Category</div>
                <?php foreach ($categoryCounts as $cat => $count): $catKey = strtolower($cat); ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="category[]" value="<?= htmlspecialchars($catKey) ?>" id="filter_cat_<?= htmlspecialchars($catKey) ?>" <?= in_array($catKey, $filterCategories, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_cat_<?= htmlspecialchars($catKey) ?>"><?= htmlspecialchars(ucfirst($cat)) ?> (<?= (int)$count ?>)</label>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Price Range</div>
                <div class="row g-2">
                  <div class="col-6">
                    <label class="form-label small" for="filter_price_min">Min ₱</label>
                    <input type="number" class="form-control form-control-sm" id="filter_price_min" name="price_min" min="0" value="<?= isset($_GET['price_min']) ? (int)$_GET['price_min'] : (int)($priceRange['min_price'] ?? 0) ?>">
                  </div>
                  <div class="col-6">
                    <label class="form-label small" for="filter_price_max">Max ₱</label>
                    <input type="number" class="form-control form-control-sm" id="filter_price_max" name="price_max" min="0" value="<?= isset($_GET['price_max']) ? (int)$_GET['price_max'] : (int)($priceRange['max_price'] ?? 0) ?>">
                  </div>
                </div>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Seats</div>
                <?php foreach ($seatBuckets as $key => $bucket): $seatId = 'filter_seats_' . str_replace(['+', '-'], ['plus', 'to'], $key); ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="seats[]" value="<?= htmlspecialchars($key) ?>" id="<?= $seatId ?>" <?= in_array($key, $filterSeatBuckets, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="<?= $seatId ?>"><?= htmlspecialchars($bucket['label']) ?> (<?= (int)$bucket['count'] ?>)</label>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Fuel Type</div>
                <?php foreach ($fuelOptions as $fuel): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="fuel[]" value="<?= htmlspecialchars($fuel) ?>" id="filter_fuel_<?= htmlspecialchars(strtolower($fuel)) ?>" <?= in_array($fuel, $filterFuels, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_fuel_<?= htmlspecialchars(strtolower($fuel)) ?>"><?= htmlspecialchars($fuel) ?></label>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Transmission</div>
                <?php foreach ($transmissionOptions as $trans): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="transmission[]" value="<?= htmlspecialchars($trans) ?>" id="filter_trans_<?= htmlspecialchars(strtolower($trans)) ?>" <?= in_array($trans, $filterTransmissions, true) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_trans_<?= htmlspecialchars(strtolower($trans)) ?>"><?= htmlspecialchars($trans) ?></label>
                </div>
                <?php endforeach; ?>
              </div>

              <div class="filter-group">
                <div class="filter-group-title">Availability</div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="available_only" value="1" id="filter_available_only" <?= $filterAvailableOnly ? 'checked' : '' ?>>
                  <label class="form-check-label" for="filter_available_only">Show available only</label>
                </div>
              </div>

              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary w-100 rounded-pill">Apply Filters</button>
                <a href="vehicles.php" class="btn btn-link text-decoration-none">Clear All</a>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Results + grid (Step 3, unchanged — only its wrapping container moved) -->
      <div class="col-lg-9">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3" id="resultsBar">
      <div>
        <span class="fw-bold" id="resultsCount"><?= (int)$totalVehicles ?></span> vehicles listed
        <?php if ($totalVehicles > 0): ?>
        <small class="text-body-secondary d-block" id="resultsRange">
          Showing <?= (int)($offset + 1) ?>-<?= (int)min($offset + $perPage, $totalVehicles) ?> of <?= (int)$totalVehicles ?> vehicles
        </small>
        <?php endif; ?>
      </div>
      <select class="form-select form-select-sm w-auto" id="sortSelect" name="sort" aria-label="Sort vehicles">
        <option value="title_asc" <?= $sortParam === 'title_asc' ? 'selected' : '' ?>>Name A-Z</option>
        <option value="title_desc" <?= $sortParam === 'title_desc' ? 'selected' : '' ?>>Name Z-A</option>
        <option value="price_asc" <?= $sortParam === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
        <option value="price_desc" <?= $sortParam === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
        <option value="newest" <?= $sortParam === 'newest' ? 'selected' : '' ?>>Newest First</option>
      </select>
    </div>



    <div class="row g-4" id="carsGrid" data-reveal-stagger="40">
      <?php
      foreach ($vehicles as $car) {
        // Booked count now comes straight from the LEFT JOIN (Step 6) —
        // no more per-vehicle query here.
        $bookedCount = (int)$car['booked_count'];


        // Compute available units
        $available = $car['units_total'] - $bookedCount;
        $isAvailable = $available > 0;


        // Determine availability text
        $statusText = $isAvailable ? 'Available' : 'Unavailable';
        $btnClass = $isAvailable ? 'btn-primary' : 'btn-secondary';


        // data-reveal is on the .car-card column wrapper, not .vehicle-card
        // itself: .vehicle-card already owns a transition (transform/box-shadow,
        // for its hover lift) and [data-reveal]'s transition (opacity/transform,
        // motion-duration-slow) would fully override it if both landed on the
        // same element — same-specificity, later rule wins the whole shorthand,
        // so the hover box-shadow transition would silently stop animating and
        // the hover lift would inherit the reveal's 500ms entrance easing
        // instead of its own 200ms. Splitting them onto sibling/parent elements
        // avoids the collision entirely instead of reproducing it a third time
        // (it already exists on .feature-card/.testimonial-card on index.php).
        echo "
  <div class='col-md-4 car-card' data-cat='" . strtolower(htmlspecialchars($car['category'])) . "' data-reveal>
    <div class='vehicle-card bg-body rounded-3 shadow-sm overflow-hidden'>
      <div class='vehicle-img-wrap'>
        <img src='assets/" . htmlspecialchars($car['thumbnail']) . "' alt='" . htmlspecialchars($car['title']) . "' class='vehicle-img' loading='lazy'>
      </div>
      <div class='p-3'>
        <h2 class='h5 mb-1'>" . htmlspecialchars($car['title']) . "</h2>
        <small class='text-body-secondary d-block mb-2'>" . ucfirst(htmlspecialchars($car['category'])) . "</small>
        <div class='vehicle-specs mb-2'>
          <div><i class='fas fa-users'></i> " . (int)$car['seats'] . " Seats</div>
          <div><i class='fas fa-gas-pump'></i> " . htmlspecialchars($car['fuel']) . "</div>
          <div><i class='fas fa-briefcase'></i> " . htmlspecialchars($car['transmission']) . "</div>
        </div>
        <div class='vehicle-pricebar mb-2'>
          <div class='price'>₱" . number_format($car['price_per_day']) . "</div>
          <small>/ day</small>
        </div>
        <div class='mb-2'>
          <span class='badge " . ($isAvailable ? "bg-success" : "bg-danger") . "'>$statusText</span>
        </div>
        <button type='button' class='btn $btnClass w-100 rounded-pill mt-2 btn-view-details'
          data-id='" . $car['id'] . "'
          data-car='" . htmlspecialchars($car['title']) . "'
          data-rate='" . $car['price_per_day'] . "'
          data-cat='" . htmlspecialchars($car['category']) . "'
          data-seats='" . (int)$car['seats'] . "'
          data-fuel='" . htmlspecialchars($car['fuel']) . "'
          data-transmission='" . htmlspecialchars($car['transmission']) . "'
          data-thumbnail='" . htmlspecialchars($car['thumbnail']) . "'
          data-available='" . (int)$available . "'
          data-available-text='" . htmlspecialchars($statusText) . "'>
          View Car Details
        </button>
      </div>
    </div>
  </div>
  ";
      }
      ?>
    </div>
    <?php if ($vehicleCount === 0): ?>
    <!-- No results message -->
    <p id="noResultsMessage" class="text-center text-body-secondary mt-4">
      No vehicles match your filters.
    </p>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <nav aria-label="Pagination" class="mt-4">
      <ul class="pagination justify-content-center">
        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars(paginationUrl(max(1, $currentPage - 1), $paginationParams)) ?>" aria-label="Previous">&laquo;</a>
        </li>
        <?php
        $windowSize = 5;
        $windowStart = max(1, $currentPage - (int)floor($windowSize / 2));
        $windowEnd = min($totalPages, $windowStart + $windowSize - 1);
        $windowStart = max(1, $windowEnd - $windowSize + 1);

        if ($windowStart > 1):
        ?>
        <li class="page-item"><a class="page-link" href="<?= htmlspecialchars(paginationUrl(1, $paginationParams)) ?>">1</a></li>
        <?php if ($windowStart > 2): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>" <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
          <a class="page-link" href="<?= htmlspecialchars(paginationUrl($p, $paginationParams)) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>

        <?php if ($windowEnd < $totalPages): ?>
        <?php if ($windowEnd < $totalPages - 1): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
        <?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= htmlspecialchars(paginationUrl($totalPages, $paginationParams)) ?>"><?= $totalPages ?></a></li>
        <?php endif; ?>

        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="<?= htmlspecialchars(paginationUrl(min($totalPages, $currentPage + 1), $paginationParams)) ?>" aria-label="Next">&raquo;</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
      </div>
    </div>
  </main>


  <?php include 'includes/client_footer.php'; ?>


  <?php include 'includes/auth_modals.php'; ?>


  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script
    src="https://cdnjs.cloudflare.com/ajax/libs/jquery-ui-timepicker-addon/1.6.3/jquery-ui-timepicker-addon.min.js"></script>
  <script>
    window.userLoggedIn = <?= isset($_SESSION['user']) && !empty($_SESSION['user']) ? 'true' : 'false' ?>;
  </script>
  <script src="js/app.js?v=<?php echo filemtime(__DIR__ . '/js/app.js'); ?>"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
  <script src="js/voucher-manager.js?v=<?php echo filemtime(__DIR__ . '/js/voucher-manager.js'); ?>"></script>
  <script src="js/booking-validation.js?v=<?php echo filemtime(__DIR__ . '/js/booking-validation.js'); ?>"></script>
  <script>
    document.getElementById('sortSelect').addEventListener('change', function() {
      const params = new URLSearchParams(window.location.search);
      params.set('sort', this.value);
      params.delete('page');
      window.location.href = window.location.pathname + '?' + params.toString();
    });
  </script>
  <script>
    $(function () {
  let userLoggedIn = false;


  // ✅ Step 1: Check login state from PHP session via me.php
  async function checkLoginStatus() {
    try {
      const res = await fetch("me.php");
      const data = await res.json();
      userLoggedIn = data.logged_in;
    } catch (err) {
      console.error("Error checking login:", err);
      userLoggedIn = false;
    }
  }


  // Run on load
  checkLoginStatus();

    // Add file validation on change
    document.getElementById('license_file').addEventListener('change', function(e) {
      const file = e.target.files[0];
      const maxSize = 5 * 1024 * 1024; // 5MB
      
      if (file) {
        if (!file.type.match(/^image\/(jpeg|png)$/i)) {
          alert('Please upload a JPEG or PNG image file only.');
          this.value = '';
          return;
        }
        
        if (file.size > maxSize) {
          alert('License file must be less than 5MB.');
          this.value = '';
          return;
        }
      }
    });

  // ✅ View Car Details: populate and open the details modal (no auth check)
  $(document).on("click", ".btn-view-details", function () {
    const $btn = $(this);

    const carName = $btn.data("car");
    const rate = $btn.data("rate");
    const cat = $btn.data("cat");
    const seats = $btn.data("seats");
    const fuel = $btn.data("fuel");
    const transmission = $btn.data("transmission");
    const thumbnail = $btn.data("thumbnail");
    const available = $btn.data("available");
    const availableText = $btn.data("available-text");

    // Reset fade-in state before swapping src so re-opening with a
    // different vehicle fades in fresh instead of showing the previous
    // image's already-loaded (opacity: 1) state.
    const detailsImgEl = document.getElementById("detailsModalImage");
    detailsImgEl.classList.remove("loaded");
    detailsImgEl.onload = function () {
      detailsImgEl.classList.add("loaded");
    };
    $("#detailsModalImage").attr("src", "assets/" + thumbnail).attr("alt", carName);
    $("#detailsModalTitle").text(carName);
    $("#detailsModalCategory").text(cat);
    $("#detailsModalPrice").text("₱" + Number(rate).toLocaleString() + " / day");
    $("#detailsModalSeats").html('<i class="fas fa-users"></i> ' + seats + " Seats");
    $("#detailsModalFuel").html('<i class="fas fa-gas-pump"></i> ' + fuel);
    $("#detailsModalTransmission").html('<i class="fas fa-briefcase"></i> ' + transmission);

    $("#detailsModalAvailability")
      .text(availableText)
      .removeClass("bg-success bg-danger")
      .addClass(available > 0 ? "bg-success" : "bg-danger");

    $("#vehicleDetailsModal")
      .data("vehicleId", $btn.data("id"))
      .data("vehicleName", carName)
      .data("vehicleThumbnail", thumbnail);

    $("#btnReserveFromDetails").prop("disabled", !(available > 0));

    $("#vehicleDetailsModal").modal("show");
  });


  // ✅ Step 3/4: Reserve Now (from details modal) → auth-gated hand off
  $("#btnReserveFromDetails").on("click", async function () {
    const vehicleId = $("#vehicleDetailsModal").data("vehicleId");
    const carName = $("#vehicleDetailsModal").data("vehicleName");
    const carThumbnail = $("#vehicleDetailsModal").data("vehicleThumbnail");

    await checkLoginStatus();

    $("#vehicleDetailsModal").one("hidden.bs.modal", function () {
      if (!userLoggedIn) {
        $("#loginModal").modal("show");
        return;
      }

      $("#vehicle_id").val(vehicleId);
      $("#bookingModalLabel").text("Book: " + carName);
      $("#bookingModal").data("vehicleName", carName).data("vehicleThumbnail", carThumbnail);

      $("#bookingForm")[0].reset();
      $("#bookingForm").removeClass("d-none");
      $("#bookingAlert").addClass("d-none").text("");
      $("#previewContent").empty();
      $("#bookingPreview").removeClass("is-visible");
      $("#amount_paid").prop("required", false);
      $("#btnConfirm").prop("disabled", true);
      $("#receiptArea").addClass("d-none");
      $("#receiptContent").empty();

      $("#bookingModal").modal("show");
    });

    $("#vehicleDetailsModal").modal("hide");
  });


  // Step 3 (the standalone #btnPreview handler that used to live here) was
  // removed in the System Enhancements initiative, Step 2 — it was a
  // long-dead duplicate of js/app.js's preview handler: this file's own
  // fetch to reserve_preview.php rendered into #previewContent, an element
  // js/app.js's handler (bound first, since js/app.js loads before this
  // inline script) had already destroyed by replacing #bookingPreview's
  // entire innerHTML. Confirmed dead by direct inspection before removal —
  // see SYSTEM_ENHANCEMENTS_ANALYSIS.md §5.2 (Additional Finding A5). Pricing
  // now comes solely from js/app.js's refreshBookingPreview().

  // ✅ Step 4: Confirm booking → reserve.php
  $("#bookingForm").on("submit", async function (e) {
    if ($('#btnConfirm').prop('disabled')) { e.preventDefault(); return; }
    e.preventDefault();


    const vehicle_id = $("#vehicle_id").val();
    const rental_date = $("#rental_date").val();
    const return_date = $("#return_date").val();
    const pickup_time = $("#pickup_time").val();
    const dropoff_time = $("#dropoff_time").val();
    const contact_number = $("#contact_number").val();
    const voucher_code = $("#voucherSelect").val().trim();
    const age = $("#age").val();
    const amount_paid = parseFloat($("#amount_paid").val());


    // Validate amount
    if (isNaN(amount_paid) || amount_paid <= 0) {
      $("#bookingAlert")
        .removeClass("d-none alert-success")
        .addClass("alert-danger")
        .text("Please enter a valid payment amount.");
      return;
    }


    if (age < 18) {
      $("#bookingAlert")
        .removeClass("d-none alert-success")
        .addClass("alert-danger")
        .text("You must be at least 18 years old to rent a car.");
      return;
    }


    try {
      // If a license file was selected, read it as a data URL (base64)
      const licenseInput = document.getElementById('license_file');
      let license_base64 = null;
      let license_name = null;
      if (licenseInput && licenseInput.files && licenseInput.files.length > 0) {
        const file = licenseInput.files[0];
        license_name = file.name;
        license_base64 = await new Promise((resolve, reject) => {
          const fr = new FileReader();
          fr.onload = () => resolve(fr.result);
          fr.onerror = () => reject(new Error('Failed to read license file'));
          fr.readAsDataURL(file);
        });
      }

      const res = await fetch("reserve.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          vehicle_id,
          rental_date,
          return_date,
          pickup_time,
          dropoff_time,
          contact_number,
          voucher_code,
          age,
          amount_paid,
          license_name,
          license_base64
        }),
      });

      const data = await res.json();


      if (!res.ok || data.error) {
        $("#bookingAlert")
          .removeClass("d-none alert-success")
          .addClass("alert-danger")
          .text(data.error || "Booking failed.");
        return;
      }


      // ✅ Show receipt and trigger printing
      $("#bookingForm").addClass("d-none");
      $("#receiptArea").removeClass("d-none");
      $("#receiptContent").html(`
        <p><strong>Booking Reference:</strong> ${data.booking_ref}</p>
        <p><strong>Vehicle:</strong> ${data.vehicle_name}</p>
        <p><strong>Rental Period:</strong> ${data.rental_date} to ${data.return_date}</p>
        <p><strong>Pick-up Time:</strong> ${data.pickup_time}</p>
        <p><strong>Drop-off Time:</strong> ${data.dropoff_time}</p>
        ${data.voucher_code ? `<p><strong>Voucher Applied:</strong> ${data.voucher_code}</p>` : ''}
        <p><strong>Days:</strong> ${data.days}</p>
        <p><strong>Rate per Day:</strong> ₱${data.rate.toLocaleString()}</p>
        <p><strong>Subtotal:</strong> ₱${data.subtotal.toLocaleString()}</p>
        ${data.discount > 0 ? `<p><strong>Discount:</strong> ₱${data.discount.toLocaleString()}</p>` : ''}
        <p><strong>Total:</strong> ₱${data.total.toLocaleString()}</p>
        <p><strong>Amount Paid:</strong> ₱${data.amount_paid.toLocaleString()}</p>
        <p><strong>Change:</strong> ₱${data.change.toLocaleString()}</p>
        <p class="text-success fw-bold">Booking Confirmed!</p>
      `);
      
      // Redirect to receipt page (use booking_id when available)
      const bookingId = data.booking_id || null;
      const ref = data.booking_ref || null;
      const paidVal = (typeof data.amount_paid !== 'undefined' && data.amount_paid !== null) ? data.amount_paid : '';
      if (bookingId) {
        let url = 'receipt.php?id=' + encodeURIComponent(bookingId);
        if (paidVal !== '') url += '&paid=' + encodeURIComponent(paidVal);
        window.location.href = url;
      } else if (ref) {
        let url = 'receipt.php?ref=' + encodeURIComponent(ref);
        if (paidVal !== '') url += '&paid=' + encodeURIComponent(paidVal);
        window.location.href = url;
      } else {
        setTimeout(() => window.location.href = "transactions.php", 1500);
      }


    } catch (err) {
      console.error(err);
      $("#bookingAlert")
        .removeClass("d-none alert-success")
        .addClass("alert-danger")
        .text("Server error. Please try again.");
    }
  });
});
  </script>
</body>
</html>

