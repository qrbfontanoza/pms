<?php
session_start();
require 'db_connect.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit;
}

// Get all transactions
$transactions = $conn->query("
    SELECT b.*, u.name as user_name, v.title as vehicle_name,
     b.status, b.total_amount
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN vehicles v ON b.vehicle_id = v.id
    ORDER BY b.rental_date DESC
");
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
  <title>PMS — All Transactions</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css"/>
  <link href="css/styles.css?v=<?php echo filemtime(__DIR__ . '/css/styles.css'); ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex min-vh-100" id="adminLayout">

<?php include 'includes/admin_sidebar.php'; ?>

<main class="flex-grow-1">
  <!-- Topbar -->
  <?php $topbarTitle = 'All Transactions'; include 'includes/admin_topbar.php'; ?>

  <div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-body d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">All Transactions</h2>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1" id="pageAlert">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="js-modal-alert-text"></span>
                            <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>User</th>
                                    <th>Vehicle</th>
                                    <th>Rental Date</th>
                                    <th>Return Date</th>
                                    <th>Pickup Time</th>
                                    <th>Dropoff Time</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transactions->num_rows === 0): ?>
                                <tr>
                                    <td colspan="10" class="text-center text-body-secondary">No transactions found</td>
                                </tr>
                                <?php endif; ?>
                                <?php while($transaction = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td data-order="<?php echo (int) $transaction['id']; ?>">#<?php echo $transaction['id']; ?></td>
                                    <td><?php echo htmlspecialchars($transaction['user_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($transaction['vehicle_name']); ?><br>
                                        <small class="text-body-secondary"><?php echo $transaction['vehicle_id']; ?></small>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($transaction['rental_date'])); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($transaction['return_date'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($transaction['pickup_time'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($transaction['dropoff_time'])); ?></td>
                                    <td>
                                        <?php
                                            $badgeClass = match (strtolower($transaction['status'])) {
                                                'completed' => 'bg-success',
                                                'confirmed' => 'bg-primary',
                                                'cancelled' => 'bg-danger',
                                                'pending' => 'bg-warning text-body',
                                                default => 'bg-secondary',
                                            };
                                        ?>
                                        <span class="badge <?php echo $badgeClass; ?>">
                                            <?php echo ucfirst($transaction['status']); ?>
                                        </span>
                                    </td>
                                    <td>₱<?php echo number_format($transaction['total_amount'], 2); ?></td>
                                    <td>
                                        <?php // Show confirm button only for pending bookings ?>
                                        <?php if(strtolower($transaction['status']) === 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-success confirm-transaction me-1"
                                                data-id="<?php echo $transaction['id']; ?>"
                                                aria-label="Confirm booking #<?php echo $transaction['id']; ?>">
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                        </button>
                                        <?php endif; ?>

                                        <!-- Allow deleting in All Transactions for any status -->
                                        <button class="btn btn-sm btn-outline-danger delete-transaction me-1"
                                                data-id="<?php echo $transaction['id']; ?>"
                                                aria-label="Delete booking #<?php echo $transaction['id']; ?>">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>

                                        <button class="btn btn-sm btn-outline-primary edit-transaction"
                                                data-id="<?php echo $transaction['id']; ?>"
                                                data-pickup="<?php echo $transaction['pickup_time']; ?>"
                                                data-dropoff="<?php echo $transaction['dropoff_time']; ?>"
                                                aria-label="Edit times for booking #<?php echo $transaction['id']; ?>">
                                            <i class="fas fa-edit" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
  </div>
</main> <!-- End flex-grow-1 -->

<!-- Edit Transaction Modal -->
<div class="modal fade" id="editTransactionModal" tabindex="-1" aria-labelledby="editTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="editTransactionForm" novalidate>
            <div class="modal-header">
                <h5 class="modal-title" id="editTransactionModalLabel">Edit Booking Times</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <input type="hidden" name="transaction_id" id="editTransactionId">
            <div class="modal-body">
                <div class="alert alert-danger d-none js-modal-alert" role="alert" tabindex="-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="js-modal-alert-text"></span>
                        <button type="button" class="btn-close ms-2 js-modal-alert-close" aria-label="Close"></button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editPickupTime">Pickup Time</label>
                    <input type="time" class="form-control" name="pickup_time" id="editPickupTime" required>
                    <div class="invalid-feedback" id="editPickupTimeFeedback"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="editDropoffTime">Dropoff Time</label>
                    <input type="time" class="form-control" name="dropoff_time" id="editDropoffTime" required>
                    <div class="invalid-feedback" id="editDropoffTimeFeedback"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
            </form>
        </div>
    </div>
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
