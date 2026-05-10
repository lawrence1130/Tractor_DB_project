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
            $booking_id = intval($_POST['booking_id'] ?? 0);
            $amount = floatval($_POST['amount'] ?? 0);
            $change_amount = floatval($_POST['change_amount'] ?? 0);
            $payment_method = sanitizeForDB($conn, $_POST['payment_method'] ?? 'cash');
            $payment_status = sanitizeForDB($conn, $_POST['payment_status'] ?? 'pending');
            $transaction_ref = sanitizeForDB($conn, $_POST['transaction_ref'] ?? '');
            $notes = sanitizeForDB($conn, $_POST['notes'] ?? '');

            if ($booking_id <= 0 || $amount <= 0) {
                $message = 'Please select a booking and enter a valid amount.';
                $message_type = 'danger';
            } else {
                if ($action === 'create') {
                    $stmt = mysqli_prepare($conn, "INSERT INTO payments (booking_id, amount, change_amount, payment_method, payment_status, transaction_ref, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, 'iddssss', $booking_id, $amount, $change_amount, $payment_method, $payment_status, $transaction_ref, $notes);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Payment recorded successfully!';
                        $message_type = 'success';
                        logActivity('create', 'payment', mysqli_insert_id($conn), ['booking_id' => $booking_id, 'amount' => $amount, 'method' => $payment_method]);
                    } else {
                        $message = 'Error recording payment: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $id = intval($_POST['payment_id']);
                    $stmt = mysqli_prepare($conn, "UPDATE payments SET booking_id = ?, amount = ?, change_amount = ?, payment_method = ?, payment_status = ?, transaction_ref = ?, notes = ? WHERE payment_id = ?");
                    mysqli_stmt_bind_param($stmt, 'iddssssi', $booking_id, $amount, $change_amount, $payment_method, $payment_status, $transaction_ref, $notes, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Payment updated successfully!';
                        $message_type = 'success';
                        logActivity('update', 'payment', $id, ['booking_id' => $booking_id, 'amount' => $amount, 'method' => $payment_method]);
                    } else {
                        $message = 'Error updating payment: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['payment_id']);
            $stmt = mysqli_prepare($conn, "DELETE FROM payments WHERE payment_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $id);
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Payment deleted successfully!';
                $message_type = 'success';
                logActivity('delete', 'payment', $id, []);
            } else {
                $message = 'Error deleting payment: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

// Get payments with JOINs
$payments = [];
$payment_sql = "SELECT p.*, b.total_amount AS booking_total, b.start_date, b.end_date, b.status AS booking_status,
                t.tractor_name, t.brand, t.model,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.phone, c.email AS customer_email
                FROM payments p
                INNER JOIN bookings b ON p.booking_id = b.booking_id
                INNER JOIN tractors t ON b.tractor_id = t.tractor_id
                INNER JOIN customers c ON b.customer_id = c.customer_id
                ORDER BY p.payment_date DESC";
$payment_result = mysqli_query($conn, $payment_sql);
while ($row = mysqli_fetch_assoc($payment_result)) {
    $payments[] = $row;
}

// Get bookings for dropdown (only those that exist)
$bookings_list = [];
$b_res = mysqli_query($conn, "SELECT b.booking_id, b.total_amount, b.status, t.tractor_name, 
                               CONCAT(c.first_name, ' ', c.last_name) AS customer_name
                               FROM bookings b
                               INNER JOIN tractors t ON b.tractor_id = t.tractor_id
                               INNER JOIN customers c ON b.customer_id = c.customer_id
                               ORDER BY b.booking_id DESC");
while ($b = mysqli_fetch_assoc($b_res)) $bookings_list[] = $b;

// Build a JS map for auto-fill
$bookings_json = [];
foreach ($bookings_list as $b) {
    $bookings_json[$b['booking_id']] = floatval($b['total_amount']);
}

// Payment stats
$stats_sql = "SELECT 
    COUNT(*) AS total_payments,
    COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) AS completed_total,
    COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END), 0) AS pending_total,
    COALESCE(SUM(CASE WHEN payment_status = 'failed' THEN amount ELSE 0 END), 0) AS failed_total,
    COALESCE(SUM(CASE WHEN payment_status = 'refunded' THEN amount ELSE 0 END), 0) AS refunded_total
    FROM payments";
$stats_result = mysqli_query($conn, $stats_sql);
$payment_stats = mysqli_fetch_assoc($stats_result);

// Check if editing
$edit_payment = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $edit_res = mysqli_query($conn, "SELECT * FROM payments WHERE payment_id = $edit_id");
    $edit_payment = mysqli_fetch_assoc($edit_res);
}

// Check if viewing receipt
$receipt_payment = null;
if (isset($_GET['action']) && $_GET['action'] === 'receipt' && isset($_GET['id'])) {
    $receipt_id = intval($_GET['id']);
    $receipt_res = mysqli_query($conn, "SELECT p.*, b.total_amount AS booking_total, b.start_date, b.end_date, b.status AS booking_status, b.rental_type,
                    t.tractor_name, t.brand, t.model, t.daily_rate, t.hourly_rate,
                    CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.phone, c.email AS customer_email,
                    c.address, c.city, c.state, c.zip_code
                    FROM payments p
                    INNER JOIN bookings b ON p.booking_id = b.booking_id
                    INNER JOIN tractors t ON b.tractor_id = t.tractor_id
                    INNER JOIN customers c ON b.customer_id = c.customer_id
                    WHERE p.payment_id = $receipt_id");
    $receipt_payment = mysqli_fetch_assoc($receipt_res);
}

// Get all payments for a specific booking (for receipt view)
$receipt_booking_payments = [];
if ($receipt_payment) {
    $rbid = intval($receipt_payment['booking_id']);
    $rbp_res = mysqli_query($conn, "SELECT * FROM payments WHERE booking_id = $rbid ORDER BY payment_date DESC");
    while ($rbp = mysqli_fetch_assoc($rbp_res)) $receipt_booking_payments[] = $rbp;
}

$show_form = isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit']);
$show_receipt = isset($_GET['action']) && $_GET['action'] === 'receipt' && $receipt_payment;
?>

 <script>
 // Auto-fill amount when booking is selected
 var bookingAmounts = <?php echo json_encode($bookings_json); ?>;
 function onBookingChange(selectEl) {
     var bookingId = parseInt(selectEl.value);
     var amountInput = document.getElementById('amount');
     if (bookingId && bookingAmounts[bookingId] && amountInput) {
         amountInput.value = bookingAmounts[bookingId].toFixed(2);
     }
 }
 </script>

<div class="main-content">
    <?php echo breadcrumb(['Payments' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">💳</span> Payments</h1>
        <div class="d-flex gap-2">
            <?php if ($show_form || $show_receipt): ?>
                <a href="<?php echo $base_url; ?>/dashboard/payments.php" class="btn btn-secondary">← Back to List</a>
            <?php else: ?>
                <a href="<?php echo $base_url; ?>/dashboard/payments.php?action=new" class="btn btn-primary">+ Record Payment</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $message_type); ?>
    <?php endif; ?>

    <?php if ($show_receipt && $receipt_payment): ?>
    <!-- Receipt View -->
    <div class="receipt-container animate-fade-in">
        <div class="receipt-header">
            <div>
                <div class="receipt-brand">🚜 Tracktor</div>
                <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 4px;">Tractor Rental Services</p>
            </div>
            <div class="receipt-meta">
                <strong>Payment #<?php echo $receipt_payment['payment_id']; ?></strong><br>
                Date: <?php echo formatDateTime($receipt_payment['payment_date']); ?><br>
                Ref: <code><?php echo htmlspecialchars($receipt_payment['transaction_ref'] ?? 'N/A'); ?></code>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 1.5rem;">
            <div class="receipt-section">
                <h4>Customer</h4>
                <p>
                    <strong><?php echo htmlspecialchars($receipt_payment['customer_name']); ?></strong><br>
                    📧 <?php echo htmlspecialchars($receipt_payment['customer_email']); ?><br>
                    📱 <?php echo htmlspecialchars($receipt_payment['phone']); ?><br>
                    📍 <?php echo htmlspecialchars($receipt_payment['city'] . ', ' . $receipt_payment['state'] . ' ' . $receipt_payment['zip_code']); ?>
                </p>
            </div>
            <div class="receipt-section">
                <h4>Booking Details</h4>
                <p>
                    <strong>Booking #<?php echo $receipt_payment['booking_id']; ?></strong><br>
                    🚜 <?php echo htmlspecialchars($receipt_payment['tractor_name']); ?> (<?php echo htmlspecialchars($receipt_payment['brand'] . ' ' . $receipt_payment['model']); ?>)<br>
                    📅 <?php echo formatDate($receipt_payment['start_date']); ?> → <?php echo formatDate($receipt_payment['end_date']); ?><br>
                    🔧 Rental Type: <?php echo ucfirst($receipt_payment['rental_type']); ?>
                </p>
            </div>
        </div>

        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($receipt_payment['tractor_name']); ?> Rental</strong><br>
                        <span style="font-size: 0.8rem; color: var(--text-muted);">
                            <?php echo formatDate($receipt_payment['start_date']); ?> to <?php echo formatDate($receipt_payment['end_date']); ?>
                        </span>
                    </td>
                    <td style="text-align: right;"><strong><?php echo formatCurrency($receipt_payment['booking_total']); ?></strong></td>
                </tr>
            </tbody>
        </table>

        <div class="receipt-total">
            <div style="width: 300px;">
                <div class="total-row">
                    <span>Booking Total</span>
                    <span><?php echo formatCurrency($receipt_payment['booking_total']); ?></span>
                </div>
                <div class="total-row">
                    <span>This Payment</span>
                    <span style="color: var(--accent-gold);"><?php echo formatCurrency($receipt_payment['amount']); ?></span>
                </div>
                <?php if (!empty($receipt_payment['change_amount']) && $receipt_payment['change_amount'] > 0): ?>
                    <div class="total-row">
                        <span>Change Returned</span>
                        <span style="color: var(--success);">- <?php echo formatCurrency($receipt_payment['change_amount']); ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row">
                    <span>Total Paid (all payments)</span>
                    <span style="color: var(--success);">
                        <?php 
                            $total_paid = array_sum(array_column(array_filter($receipt_booking_payments, function($p) { return $p['payment_status'] === 'completed'; }), 'amount'));
                            echo formatCurrency($total_paid); 
                        ?>
                    </span>
                </div>
                <?php
                    $total_change = array_sum(array_column(array_filter($receipt_booking_payments, function($p) { return $p['payment_status'] === 'completed'; }), 'change_amount'));
                    $net_paid = $total_paid - $total_change;
                    if ($total_change > 0):
                ?>
                <div class="total-row" style="border-top: 1px solid var(--dark-border); padding-top: 0.5rem;">
                    <span>Net Amount Received</span>
                    <span><strong><?php echo formatCurrency($net_paid); ?></strong></span>
                </div>
                <?php endif; ?>
                <div class="total-row grand">
                    <span>Balance Due</span>
                    <span><?php echo formatCurrency(max(0, $receipt_payment['booking_total'] - $total_paid)); ?></span>
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--dark-border);">
            <div>
                <strong style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Payment Method</strong>
                <p style="margin-top: 4px;">
                    <?php 
                        $method_icons = ['cash' => '💵 Cash', 'credit_card' => '💳 Credit Card', 'bank_transfer' => '🏦 Bank Transfer', 'check' => '📝 Check'];
                        echo $method_icons[$receipt_payment['payment_method']] ?? '💰 ' . ucfirst($receipt_payment['payment_method']);
                    ?>
                </p>
            </div>
            <div>
                <strong style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Payment Status</strong>
                <p style="margin-top: 4px;"><?php echo statusBadge($receipt_payment['payment_status']); ?></p>
            </div>
        </div>

        <?php if (!empty($receipt_payment['notes'])): ?>
        <div style="margin-top: 1rem; padding: 1rem; background: var(--dark-surface); border-radius: var(--radius-sm);">
            <strong style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Notes</strong>
            <p style="margin-top: 4px; font-size: 0.9rem; color: var(--text-secondary);"><?php echo htmlspecialchars($receipt_payment['notes']); ?></p>
        </div>
        <?php endif; ?>

        <!-- All payments for this booking -->
        <?php if (count($receipt_booking_payments) > 1): ?>
        <div style="margin-top: 1.5rem;">
            <h4 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 0.75rem;">📋 All Payments for Booking #<?php echo $receipt_payment['booking_id']; ?></h4>
             <table class="receipt-table">
                 <thead>
                     <tr>
                         <th>ID</th>
                         <th>Date</th>
                         <th>Amount</th>
                         <th>Change</th>
                         <th>Method</th>
                         <th>Status</th>
                     </tr>
                 </thead>
                 <tbody>
                     <?php foreach ($receipt_booking_payments as $bp): ?>
                     <tr style="<?php echo $bp['payment_id'] == $receipt_payment['payment_id'] ? 'background: rgba(212,46,18,0.05);' : ''; ?>">
                         <td>#<?php echo $bp['payment_id']; ?></td>
                         <td><?php echo formatDateTime($bp['payment_date']); ?></td>
                         <td><strong><?php echo formatCurrency($bp['amount']); ?></strong></td>
                         <?php if (!empty($bp['change_amount']) && $bp['change_amount'] > 0): ?>
                             <td style="color: var(--success);">- <?php echo formatCurrency($bp['change_amount']); ?></td>
                         <?php else: ?>
                             <td>-</td>
                         <?php endif; ?>
                         <td><?php echo ucfirst(str_replace('_', ' ', $bp['payment_method'])); ?></td>
                         <td><?php echo statusBadge($bp['payment_status']); ?></td>
                     </tr>
                     <?php endforeach; ?>
                 </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="receipt-footer">
            Thank you for your business! — Tracktor Tractor Rental Services
        </div>

        <div class="receipt-actions">
            <a href="<?php echo $base_url; ?>/dashboard/payments.php" class="btn btn-secondary">← Back to Payments</a>
            <a href="<?php echo $base_url; ?>/dashboard/payments.php?action=edit&id=<?php echo $receipt_payment['payment_id']; ?>" class="btn btn-primary">✏️ Edit Payment</a>
            <a href="<?php echo $base_url; ?>/dashboard/rental-history.php?customer_id=<?php echo $receipt_payment['customer_id'] ?? ''; ?>" class="btn btn-secondary">📜 View Rental History</a>
        </div>
    </div>

    <?php elseif ($show_form): ?>
    <!-- Payment Form -->
    <div class="form-container animate-fade-in" style="max-width: 700px;">
        <h2 style="margin-bottom: 1.5rem; font-size: 1.3rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
            <?php echo $edit_payment ? '✏️ Edit Payment' : '➕ Record New Payment'; ?>
        </h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="<?php echo $edit_payment ? 'update' : 'create'; ?>">
            <?php echo csrfInput(); ?>
            <?php if ($edit_payment): ?>
                <input type="hidden" name="payment_id" value="<?php echo $edit_payment['payment_id']; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="booking_id">Booking *</label>
                <select id="booking_id" name="booking_id" class="form-control" required onchange="onBookingChange(this)">
                    <option value="">-- Select Booking --</option>
                    <?php foreach ($bookings_list as $b): ?>
                        <option value="<?php echo $b['booking_id']; ?>"
                                <?php echo ($edit_payment['booking_id'] ?? '') == $b['booking_id'] ? 'selected' : ''; ?>>
                            #<?php echo $b['booking_id']; ?> — <?php echo htmlspecialchars($b['tractor_name']); ?> 
                            (<?php echo htmlspecialchars($b['customer_name']); ?>) — <?php echo formatCurrency($b['total_amount']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 4px; display: block;">
                    💡 Selecting a booking will auto-fill the amount with the booking total
                </small>
            </div>

             <div class="form-row">
                 <div class="form-group">
                     <label for="amount">Amount ($) *</label>
                     <input type="number" id="amount" name="amount" class="form-control" 
                            step="0.01" min="0.01" required
                            value="<?php echo htmlspecialchars($edit_payment['amount'] ?? ''); ?>">
                 </div>
                 <div class="form-group">
                     <label for="change_amount">Change Returned ($)</label>
                     <input type="number" id="change_amount" name="change_amount" class="form-control" 
                            step="0.01" min="0" value="0"
                            value="<?php echo htmlspecialchars($edit_payment['change_amount'] ?? '0'); ?>">
                     <small style="color: var(--text-muted); font-size: 0.75rem; margin-top: 4px; display: block;">
                         Amount returned to customer as change (if overpayment)
                     </small>
                 </div>
                 <div class="form-group">
                     <label for="payment_method">Payment Method</label>
                     <select id="payment_method" name="payment_method" class="form-control">
                         <option value="cash" <?php echo ($edit_payment['payment_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>💵 Cash</option>
                         <option value="credit_card" <?php echo ($edit_payment['payment_method'] ?? '') === 'credit_card' ? 'selected' : ''; ?>>💳 Credit Card</option>
                         <option value="bank_transfer" <?php echo ($edit_payment['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>🏦 Bank Transfer</option>
                         <option value="check" <?php echo ($edit_payment['payment_method'] ?? '') === 'check' ? 'selected' : ''; ?>>📝 Check</option>
                     </select>
                 </div>
             </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="payment_status">Payment Status</label>
                    <select id="payment_status" name="payment_status" class="form-control">
                        <option value="pending" <?php echo ($edit_payment['payment_status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                        <option value="completed" <?php echo ($edit_payment['payment_status'] ?? '') === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                        <option value="failed" <?php echo ($edit_payment['payment_status'] ?? '') === 'failed' ? 'selected' : ''; ?>>❌ Failed</option>
                        <option value="refunded" <?php echo ($edit_payment['payment_status'] ?? '') === 'refunded' ? 'selected' : ''; ?>>🔄 Refunded</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="transaction_ref">Transaction Reference</label>
                    <input type="text" id="transaction_ref" name="transaction_ref" class="form-control" 
                           placeholder="e.g. TXN-2026-009"
                           value="<?php echo htmlspecialchars($edit_payment['transaction_ref'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"
                          placeholder="Additional payment notes..."><?php echo htmlspecialchars($edit_payment['notes'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_payment ? '💾 Update Payment' : '➕ Record Payment'; ?>
                </button>
                <a href="<?php echo $base_url; ?>/dashboard/payments.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <!-- Payment Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-value"><?php echo formatCurrency($payment_stats['completed_total']); ?></div>
            <div class="stat-label">Completed Payments</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold">⏳</div>
            <div class="stat-value"><?php echo formatCurrency($payment_stats['pending_total']); ?></div>
            <div class="stat-label">Pending Payments</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">❌</div>
            <div class="stat-value"><?php echo formatCurrency($payment_stats['failed_total']); ?></div>
            <div class="stat-label">Failed Payments</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">🔄</div>
            <div class="stat-value"><?php echo formatCurrency($payment_stats['refunded_total']); ?></div>
            <div class="stat-label">Refunded</div>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>💳 All Payments (INNER JOIN: payments + bookings + tractors + customers)</h3>
        </div>
        <table>
             <thead>
                 <tr>
                     <th>ID</th>
                     <th>Booking</th>
                     <th>Customer</th>
                     <th>Amount</th>
                     <th>Change</th>
                     <th>Method</th>
                     <th>Status</th>
                     <th>Reference</th>
                     <th>Date</th>
                     <th>Actions</th>
                 </tr>
             </thead>
             <tbody>
                 <?php if (empty($payments)): ?>
                     <tr><td colspan="10" class="text-center text-muted" style="padding: 2rem;">No payments found</td></tr>
                 <?php else: ?>
                     <?php foreach ($payments as $p): ?>
                         <tr>
                             <td><strong>#<?php echo $p['payment_id']; ?></strong></td>
                             <td>
                                 <div>#<?php echo $p['booking_id']; ?> — <?php echo htmlspecialchars($p['tractor_name']); ?></div>
                                 <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($p['brand']); ?></div>
                             </td>
                             <td><?php echo htmlspecialchars($p['customer_name']); ?></td>
                             <td><strong class="text-gold"><?php echo formatCurrency($p['amount']); ?></strong></td>
                             <td>
                                 <?php if (!empty($p['change_amount']) && $p['change_amount'] > 0): ?>
                                     <span style="color: var(--success);"><?php echo formatCurrency($p['change_amount']); ?></span>
                                 <?php else: ?>
                                     —
                                 <?php endif; ?>
                             </td>
                             <td>
                                 <?php 
                                     $method_icons = ['cash' => '💵', 'credit_card' => '💳', 'bank_transfer' => '🏦', 'check' => '📝'];
                                     echo ($method_icons[$p['payment_method']] ?? '💰') . ' ' . ucfirst(str_replace('_', ' ', $p['payment_method']));
                                 ?>
                             </td>
                            <td><?php echo statusBadge($p['payment_status']); ?></td>
                            <td><code style="font-size: 0.8rem;"><?php echo htmlspecialchars($p['transaction_ref'] ?? '—'); ?></code></td>
                            <td style="font-size: 0.85rem;"><?php echo formatDateTime($p['payment_date']); ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="<?php echo $base_url; ?>/dashboard/payments.php?action=receipt&id=<?php echo $p['payment_id']; ?>" 
                                       class="btn btn-sm btn-secondary" title="View Receipt">🧾</a>
                                    <a href="<?php echo $base_url; ?>/dashboard/payments.php?action=edit&id=<?php echo $p['payment_id']; ?>" 
                                       class="btn btn-sm btn-secondary" title="Edit">✏️</a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this payment?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="payment_id" value="<?php echo $p['payment_id']; ?>">
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