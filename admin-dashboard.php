<?php
session_start();
include 'db_connect.php'; // create this file once, it holds your DB credentials

// Optional: check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
  header('Location: admin-login.php');
  exit;
}

// Get dashboard counts
$totalCars = $conn->query("SELECT COUNT(*) AS total FROM vehicles")->fetch_assoc()['total'];
$totalUsers = $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$activeRentals = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status='confirmed'")->fetch_assoc()['total'];
$pendingBookings = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status='pending'")->fetch_assoc()['total'];

// Business Overview: revenue by month, last 6 months. All date bucketing is
// done in MySQL (YEAR()/MONTH()/DATE_SUB() against CURDATE()) — bookings.created_at
// is written by MySQL's own Manila-time NOW(), so this stays correct without
// any PHP-computed date boundary. See docs/BUGS.md item 15.
$revenueByMonth = [];
$revenueByMonthResult = $conn->query("
  SELECT YEAR(created_at) AS yr, MONTH(created_at) AS mo, SUM(total_amount) AS revenue
  FROM bookings
  WHERE status IN ('confirmed','completed')
    AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
  GROUP BY YEAR(created_at), MONTH(created_at)
  ORDER BY yr, mo
");
while ($row = $revenueByMonthResult->fetch_assoc()) {
  $revenueByMonth[] = $row;
}
$maxRevenue = 0;
foreach ($revenueByMonth as $row) {
  $maxRevenue = max($maxRevenue, (float) $row['revenue']);
}
$monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Business Overview: bookings by status (all four enum values, zero-filled)
$bookingsByStatus = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
$bookingsByStatusResult = $conn->query("SELECT status, COUNT(*) AS total FROM bookings GROUP BY status");
while ($row = $bookingsByStatusResult->fetch_assoc()) {
  $key = strtolower($row['status']);
  if (array_key_exists($key, $bookingsByStatus)) {
    $bookingsByStatus[$key] = (int) $row['total'];
  }
}
$totalStatusBookings = array_sum($bookingsByStatus);
$maxStatusCount = max($bookingsByStatus);

// Business Overview: top 5 vehicles by booking count
$topVehicles = [];
$topVehiclesResult = $conn->query("
  SELECT v.title, COUNT(*) AS total
  FROM bookings b
  JOIN vehicles v ON b.vehicle_id = v.id
  GROUP BY b.vehicle_id, v.title
  ORDER BY total DESC
  LIMIT 5
");
while ($row = $topVehiclesResult->fetch_assoc()) {
  $topVehicles[] = $row;
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
  <title>PMS Car Rental — Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>

<body class="bg-body-tertiary">
  <div class="d-flex min-vh-100" id="adminLayout">
    <!-- Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main -->
    <main class="flex-grow-1">

      <!-- Topbar -->
      <?php $topbarTitle = 'Admin Dashboard'; include 'includes/admin_topbar.php'; ?>

      <!-- Metrics -->
      <div class="container py-4">
        <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="dashboardAlert">
          <div class="d-flex justify-content-between align-items-start">
            <span class="js-modal-alert-text"></span>
            <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
          </div>
        </div>
        <div class="row g-3 mb-4" data-reveal-stagger>
          <div class="col-12 col-sm-6 col-xl-3" data-reveal>
            <div class="card metric-card border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="fs-2 text-primary"><i class="fa fa-car" aria-hidden="true"></i></div>
                <div class="fw-bold fs-4">
                  <span class="js-count" aria-hidden="true" data-target="<?php echo (int) $totalCars; ?>">0</span>
                  <span class="visually-hidden"><?php echo (int) $totalCars; ?></span>
                </div>
                <div class="text-body-secondary">Total Cars</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-xl-3" data-reveal>
            <div class="card metric-card border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="fs-2 text-success"><i class="fa fa-users" aria-hidden="true"></i></div>
                <div class="fw-bold fs-4">
                  <span class="js-count" aria-hidden="true" data-target="<?php echo (int) $totalUsers; ?>">0</span>
                  <span class="visually-hidden"><?php echo (int) $totalUsers; ?></span>
                </div>
                <div class="text-body-secondary">Total Users</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-xl-3" data-reveal>
            <div class="card metric-card border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="fs-2 text-warning"><i class="fa fa-key" aria-hidden="true"></i></div>
                <div class="fw-bold fs-4">
                  <span class="js-count" aria-hidden="true" data-target="<?php echo (int) $activeRentals; ?>">0</span>
                  <span class="visually-hidden"><?php echo (int) $activeRentals; ?></span>
                </div>
                <div class="text-body-secondary">Active Rentals</div>
              </div>
            </div>
          </div>
          <div class="col-12 col-sm-6 col-xl-3" data-reveal>
            <div class="card metric-card border-0 shadow-sm">
              <div class="card-body text-center">
                <div class="fs-2 text-danger"><i class="fa fa-hourglass-half" aria-hidden="true"></i></div>
                <div class="fw-bold fs-4">
                  <span class="js-count" aria-hidden="true" data-target="<?php echo (int) $pendingBookings; ?>">0</span>
                  <span class="visually-hidden"><?php echo (int) $pendingBookings; ?></span>
                </div>
                <div class="text-body-secondary">Pending Bookings</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Manage Vehicles and User Accounts quick-link cards removed per request -->

        <!-- ====================== Recent Transactions Table ====================== -->
        <div class="card shadow-sm border-0">
          <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Recent Transactions</h2></div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>User</th>
                  <th>Car</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Pickup Time</th>
                  <th>Dropoff Time</th>
                  <th>Days</th>
                  <th>Amount</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $result = $conn->query("SELECT t.id, u.name AS user, v.title AS car, t.rental_date, t.status,
                t.pickup_time, t.dropoff_time, t.days, t.total_amount 
                        FROM bookings t 
                        JOIN users u ON t.user_id = u.id 
                        JOIN vehicles v ON t.vehicle_id = v.id 
                        ORDER BY t.id DESC 
                        LIMIT 5");
                if ($result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                    $badgeClass = match (strtolower($row['status'])) {
                      'completed' => 'bg-success',
                      'confirmed' => 'bg-primary',
                      'cancelled' => 'bg-danger',
                      'pending' => 'bg-warning text-body',
                      default => 'bg-secondary',
                    };

          // build actions HTML: only allow admin to confirm when status is pending
          $actions = '';
          if (strtolower($row['status']) === 'pending') {
            $actions = '<button class="btn btn-sm btn-success confirm-transaction" data-id="' . htmlspecialchars($row['id']) . '">Confirm</button>';
          } else {
            $actions = '<span class="text-body-secondary">-</span>';
          }

                    echo '<tr>'
                      . '<td>' . htmlspecialchars($row['id']) . '</td>'
                      . '<td>' . htmlspecialchars($row['user']) . '</td>'
                      . '<td>' . htmlspecialchars($row['car']) . '</td>'
                      . '<td>' . htmlspecialchars($row['rental_date']) . '</td>'
                      . '<td><span class="badge ' . $badgeClass . '">' . htmlspecialchars($row['status']) . '</span></td>'
                      . '<td>' . htmlspecialchars($row['pickup_time']) . '</td>'
                      . '<td>' . htmlspecialchars($row['dropoff_time']) . '</td>'
                      . '<td>' . htmlspecialchars($row['days']) . '</td>'
                      . '<td>' . htmlspecialchars($row['total_amount']) . '</td>'
                      . '<td>' . $actions . '</td>'
                      . '</tr>';
                  }
                } else {
                  echo "<tr><td colspan='10' class='text-center text-body-secondary'>No transactions found</td></tr>";
                }
                ?>
              </tbody>
            </table>
            <div class="card-footer bg-body">
              <a href="view-all-data.php?type=transactions" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-list me-1"></i> View All Transactions
              </a>
          </div>
          </div>
        </div>

        <!-- ========================= Feedback Messages ========================= -->
        <div class="card shadow-sm border-0 mt-4">
          <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Recent Messages</h2></div>
          <div class="table-responsive">
            <table id="messagesTable" class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Message</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $messages = $conn->query("SELECT * FROM messages ORDER BY created_at DESC LIMIT 5");

                if ($messages->num_rows > 0) {
                  while ($msg = $messages->fetch_assoc()) {
                    echo "
              <tr>
                <td>" . htmlspecialchars($msg['id']) . "</td>
                <td>" . htmlspecialchars($msg['name']) . "</td>
                <td>" . htmlspecialchars($msg['email']) . "</td>
                <td>" . htmlspecialchars($msg['message']) . "</td>
                <td>" . htmlspecialchars($msg['created_at']) . "</td>
              </tr>
            ";
                  }
                } else {
                  echo "<tr><td colspan='5' class='text-center text-body-secondary'>No messages yet.</td></tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ====================== Business Overview ====================== -->
        <div class="row g-3 mt-1 business-overview" data-reveal-stagger>
          <div class="col-12 col-lg-4" data-reveal>
            <div class="card metric-card shadow-sm border-0 h-100">
              <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Revenue by Month</h2></div>
              <div class="card-body">
                <?php if (empty($revenueByMonth)): ?>
                  <p class="text-body-secondary mb-0">No revenue recorded in the last 6 months.</p>
                <?php else: ?>
                  <?php foreach ($revenueByMonth as $row):
                    $label = $monthNames[(int) $row['mo']] . ' ' . $row['yr'];
                    $revenue = (float) $row['revenue'];
                    $pct = $maxRevenue > 0 ? (int) round($revenue / $maxRevenue * 100) : 0;
                  ?>
                    <div class="mb-3">
                      <div class="d-flex justify-content-between small mb-1">
                        <span><?= htmlspecialchars($label) ?></span>
                        <span class="fw-semibold text-nowrap">₱<?= number_format($revenue, 2) ?></span>
                      </div>
                      <div class="progress" style="height: 0.6rem;">
                        <div class="progress-bar bg-primary" role="progressbar"
                             style="width: <?= $pct ?>%"
                             aria-valuenow="<?= (int) round($revenue) ?>"
                             aria-valuemin="0"
                             aria-valuemax="<?= (int) round($maxRevenue) ?>"
                             aria-label="<?= htmlspecialchars($label) ?> revenue"></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4" data-reveal>
            <div class="card metric-card shadow-sm border-0 h-100">
              <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Bookings by Status</h2></div>
              <div class="card-body">
                <?php if ($totalStatusBookings === 0): ?>
                  <p class="text-body-secondary mb-0">No bookings recorded yet.</p>
                <?php else: ?>
                  <?php foreach ($bookingsByStatus as $status => $count):
                    $badgeClass = match ($status) {
                      'completed' => 'bg-success',
                      'confirmed' => 'bg-primary',
                      'cancelled' => 'bg-danger',
                      'pending' => 'bg-warning',
                      default => 'bg-secondary',
                    };
                    $pct = $maxStatusCount > 0 ? (int) round($count / $maxStatusCount * 100) : 0;
                  ?>
                    <div class="mb-3">
                      <div class="d-flex justify-content-between small mb-1">
                        <span class="text-capitalize"><?= htmlspecialchars($status) ?></span>
                        <span class="fw-semibold"><?= (int) $count ?></span>
                      </div>
                      <div class="progress" style="height: 0.6rem;">
                        <div class="progress-bar <?= $badgeClass ?>" role="progressbar"
                             style="width: <?= $pct ?>%"
                             aria-valuenow="<?= (int) $count ?>"
                             aria-valuemin="0"
                             aria-valuemax="<?= (int) $maxStatusCount ?>"
                             aria-label="<?= htmlspecialchars(ucfirst($status)) ?> bookings"></div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4" data-reveal>
            <div class="card metric-card shadow-sm border-0 h-100">
              <div class="card-header bg-body fw-bold"><h2 class="h6 mb-0">Top Vehicles by Bookings</h2></div>
              <?php if (empty($topVehicles)): ?>
                <div class="card-body">
                  <p class="text-body-secondary mb-0">No bookings recorded yet.</p>
                </div>
              <?php else: ?>
                <ul class="list-group list-group-flush">
                  <?php foreach ($topVehicles as $i => $row): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <span class="text-truncate me-2" title="<?= htmlspecialchars($row['title']) ?>">
                        <span class="fw-semibold">#<?= $i + 1 ?></span> <?= htmlspecialchars($row['title']) ?>
                      </span>
                      <span class="badge bg-primary rounded-pill flex-shrink-0"><?= (int) $row['total'] ?></span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Manage Vehicles and Users are now on their dedicated pages -->
      </div>
    </main>
  </div>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/confirm.js?v=<?php echo filemtime(__DIR__ . '/js/confirm.js'); ?>"></script>
  <script src="js/theme.js?v=<?php echo filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
  <script src="js/motion.js?v=<?php echo filemtime(__DIR__ . '/js/motion.js'); ?>"></script>
  <script src="js/admin.js?v=<?php echo filemtime(__DIR__ . '/js/admin.js'); ?>"></script>
</body>

</html>