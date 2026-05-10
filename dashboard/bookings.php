<?php
require_once __DIR__ . '/../includes/header.php';

$base_url = getBaseUrl();
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'CSRF token validation failed. Please refresh the page.';
        $message_type = 'danger';
} else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $tractor_id = intval($_POST['tractor_id'] ?? 0);
            $customer_id = intval($_POST['customer_id'] ?? 0);
            $start_date = sanitizeForDB($conn, $_POST['start_date'] ?? '');
            $end_date = sanitizeForDB($conn, $_POST['end_date'] ?? '');
            $rental_type = sanitizeForDB($conn, $_POST['rental_type'] ?? 'daily');
            $status = sanitizeForDB($conn, $_POST['status'] ?? 'pending');
            $notes = sanitizeForDB($conn, $_POST['notes'] ?? '');

            // Calculate total amount
            $total_amount = 0;
            if ($tractor_id && $start_date && $end_date) {
                $tractor_res = mysqli_query($conn, "SELECT hourly_rate, daily_rate FROM tractors WHERE tractor_id = $tractor_id");
                $tractor_info = mysqli_fetch_assoc($tractor_res);
                $days = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400);
                $total_amount = $rental_type === 'hourly' 
                    ? $tractor_info['hourly_rate'] * $days * 8 
                    : $tractor_info['daily_rate'] * $days;
            }

            if ($action === 'create') {
                $sql = "INSERT INTO bookings (tractor_id, customer_id, user_id, booking_date, start_date, end_date, rental_type, total_amount, status, notes) 
                        VALUES ($tractor_id, $customer_id, {$_SESSION['user_id']}, CURDATE(), '$start_date', '$end_date', '$rental_type', $total_amount, '$status', '$notes')";
                if (mysqli_query($conn, $sql)) {
                    $message = 'Booking created successfully!';
                    $message_type = 'success';
                    logActivity('create', 'booking', mysqli_insert_id($conn), ['tractor_id' => $tractor_id, 'customer_id' => $customer_id, 'amount' => $total_amount]);
                } else {
                    $message = 'Error creating booking: ' . mysqli_error($conn);
                    $message_type = 'danger';
                }
            } else {
                $id = intval($_POST['booking_id']);
                $sql = "UPDATE bookings SET tractor_id = $tractor_id, customer_id = $customer_id, 
                        start_date = '$start_date', end_date = '$end_date', rental_type = '$rental_type', 
                        total_amount = $total_amount, status = '$status', notes = '$notes' 
                        WHERE booking_id = $id";
                if (mysqli_query($conn, $sql)) {
                    $message = 'Booking updated successfully!';
                    $message_type = 'success';
                    logActivity('update', 'booking', $id, ['tractor_id' => $tractor_id, 'customer_id' => $customer_id, 'amount' => $total_amount]);
                } else {
                    $message = 'Error updating booking: ' . mysqli_error($conn);
                    $message_type = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['booking_id']);
            if (mysqli_query($conn, "DELETE FROM bookings WHERE booking_id = $id")) {
                $message = 'Booking deleted successfully!';
                $message_type = 'success';
                logActivity('delete', 'booking', $id, []);
            } else {
                $message = 'Error deleting booking: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
        }
    }
}

// Get bookings with JOINs
$bookings = [];
$booking_sql = "SELECT b.*, t.tractor_name, t.brand, t.model, 
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.phone, c.email AS customer_email
                FROM bookings b
                INNER JOIN tractors t ON b.tractor_id = t.tractor_id
                INNER JOIN customers c ON b.customer_id = c.customer_id
                ORDER BY b.created_at DESC";
$booking_result = mysqli_query($conn, $booking_sql);
while ($row = mysqli_fetch_assoc($booking_result)) {
    $bookings[] = $row;
}

// Get tractors and customers for dropdowns
$tractors_list = [];
$t_res = mysqli_query($conn, "SELECT tractor_id, tractor_name, brand, model, daily_rate, hourly_rate, status FROM tractors ORDER BY tractor_name");
while ($t = mysqli_fetch_assoc($t_res)) $tractors_list[] = $t;

$customers_list = [];
$c_res = mysqli_query($conn, "SELECT customer_id, first_name, last_name, phone FROM customers ORDER BY last_name");
while ($c = mysqli_fetch_assoc($c_res)) $customers_list[] = $c;

// Check if editing
$edit_booking = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $edit_res = mysqli_query($conn, "SELECT * FROM bookings WHERE booking_id = $edit_id");
    $edit_booking = mysqli_fetch_assoc($edit_res);
}

$show_form = isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit']);
?>

<div class="main-content">
    <?php echo breadcrumb(['Bookings' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">📅</span> Bookings</h1>
        <div class="d-flex gap-2">
            <?php if ($show_form): ?>
                <a href="<?php echo $base_url; ?>/dashboard/bookings.php" class="btn btn-secondary">← Back to List</a>
            <?php else: ?>
                <a href="<?php echo $base_url; ?>/dashboard/bookings.php?action=new" class="btn btn-primary">+ New Booking</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $message_type); ?>
    <?php endif; ?>

    <?php if ($show_form): ?>
    <!-- Booking Form -->
    <div class="form-container animate-fade-in" style="max-width: 700px;">
        <h2 style="margin-bottom: 1.5rem; font-size: 1.3rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
            <?php echo $edit_booking ? '✏️ Edit Booking' : '➕ New Booking'; ?>
        </h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="<?php echo $edit_booking ? 'update' : 'create'; ?>">
            <?php echo csrfInput(); ?>
            <?php if ($edit_booking): ?>
                <input type="hidden" name="booking_id" value="<?php echo $edit_booking['booking_id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="tractor_id">Tractor *</label>
                    <select id="tractor_id" name="tractor_id" class="form-control" required>
                        <option value="">-- Select Tractor --</option>
                        <?php foreach ($tractors_list as $t): ?>
                            <option value="<?php echo $t['tractor_id']; ?>" 
                                    <?php echo ($edit_booking['tractor_id'] ?? '') == $t['tractor_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t['tractor_name'] . ' (' . $t['brand'] . ')'); ?>
                                <?php echo $t['status'] !== 'available' ? ' [' . ucfirst($t['status']) . ']' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="customer_id">Customer *</label>
                    <select id="customer_id" name="customer_id" class="form-control" required>
                        <option value="">-- Select Customer --</option>
                        <?php foreach ($customers_list as $c): ?>
                            <option value="<?php echo $c['customer_id']; ?>"
                                    <?php echo ($edit_booking['customer_id'] ?? '') == $c['customer_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Start Date *</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_booking['start_date'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">End Date *</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_booking['end_date'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="rental_type">Rental Type</label>
                    <select id="rental_type" name="rental_type" class="form-control">
                        <option value="daily" <?php echo ($edit_booking['rental_type'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                        <option value="hourly" <?php echo ($edit_booking['rental_type'] ?? '') === 'hourly' ? 'selected' : ''; ?>>Hourly</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="pending" <?php echo ($edit_booking['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo ($edit_booking['status'] ?? '') === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="active" <?php echo ($edit_booking['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo ($edit_booking['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo ($edit_booking['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"
                          placeholder="Additional notes about this booking..."><?php echo htmlspecialchars($edit_booking['notes'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_booking ? '💾 Update Booking' : '➕ Create Booking'; ?>
                </button>
                <a href="<?php echo $base_url; ?>/dashboard/bookings.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <!-- Bookings Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>📋 All Bookings (INNER JOIN: bookings + tractors + customers)</h3>
        </div>
<table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tractor</th>
                    <th>Customer</th>
                    <th>Dates</th>
                    <th>Time Remaining</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="9" class="text-center text-muted" style="padding: 2rem;">No bookings found</td></tr>
                <?php else: ?>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong>#<?php echo $b['booking_id']; ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($b['tractor_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($b['brand'] . ' ' . $b['model']); ?></div>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($b['customer_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($b['phone']); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;"><?php echo formatDate($b['start_date']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">to <?php echo formatDate($b['end_date']); ?></div>
                            </td>
                            <td><?php echo getRemainingTime($b['end_date'], $b['status']); ?></td>
                            <td><?php echo ucfirst($b['rental_type']); ?></td>
                            <td><strong class="text-gold"><?php echo formatCurrency($b['total_amount']); ?></strong></td>
                            <td><?php echo statusBadge($b['status']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?php echo $base_url; ?>/dashboard/bookings.php?action=edit&id=<?php echo $b['booking_id']; ?>" 
                                       class="btn btn-sm btn-secondary">✏️</a>
                                     <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this booking?');">
                                  <input type="hidden" name="action" value="delete">
                                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                  <input type="hidden" name="booking_id" value="<?php echo $b['booking_id']; ?>">
                                  <button type="submit" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                              </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>