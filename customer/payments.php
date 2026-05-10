<?php
require_once __DIR__ . '/../includes/header.php';

// Role check
if ($_SESSION['role'] !== 'customer') {
    header('Location: ' . getBaseUrl() . '/dashboard/index.php');
    exit();
}

$base_url = getBaseUrl();
$customer_id = $_SESSION['customer_id'] ?? 0;
$message = '';
$message_type = '';

// Get unpaid bookings
$unpaid_bookings = [];
$unpaid_sql = "SELECT b.booking_id, b.total_amount, b.status, 
               t.tractor_name, t.brand,
               (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.booking_id AND payment_status = 'completed') as paid_amount
               FROM bookings b
               INNER JOIN tractors t ON b.tractor_id = t.tractor_id
               WHERE b.customer_id = $customer_id 
               AND b.status NOT IN ('cancelled', 'completed')
               AND (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.booking_id AND payment_status = 'completed') < b.total_amount
               ORDER BY b.created_at DESC";
$unpaid_result = mysqli_query($conn, $unpaid_sql);
while ($row = mysqli_fetch_assoc($unpaid_result)) {
    $unpaid_bookings[] = $row;
}

// Process payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'CSRF token validation failed. Please refresh the page.';
        $message_type = 'danger';
    } else {
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = sanitizeForDB($conn, $_POST['payment_method'] ?? 'cash');
        
        // Verify booking belongs to customer using prepared statement
        $stmt = mysqli_prepare($conn, "SELECT total_amount FROM bookings WHERE booking_id = ? AND customer_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $booking_id, $customer_id);
        mysqli_stmt_execute($stmt);
        $check_booking = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($check_booking) === 0) {
            $message = 'Invalid booking.';
            $message_type = 'danger';
        } elseif ($amount <= 0) {
            $message = 'Invalid payment amount.';
            $message_type = 'danger';
        } else {
            $transaction_ref = 'TXN' . time() . rand(1000, 9999);
            $notes = 'Customer payment via ' . $payment_method;
            
            // Calculate change if overpayment
            $change = max(0, $amount - $selected_booking['outstanding']);
            if ($change > 0) {
                $notes .= '. Overpayment of ' . formatCurrency($change) . ' returned as change.';
            }
            
            $insert_stmt = mysqli_prepare($conn, "INSERT INTO payments (booking_id, amount, change_amount, payment_method, payment_status, transaction_ref, notes) VALUES (?, ?, ?, ?, 'completed', ?, ?)");
            mysqli_stmt_bind_param($insert_stmt, 'iddsss', $booking_id, $amount, $change, $payment_method, $transaction_ref, $notes);
            if (mysqli_stmt_execute($insert_stmt)) {
                $payment_id = mysqli_insert_id($conn);
                $message = 'Payment successful! Transaction ID: ' . $transaction_ref;
                if ($change > 0) {
                    $message .= ' Change returned: ' . formatCurrency($change);
                }
                $message_type = 'success';
                logActivity('payment', 'payment', $payment_id, ['booking_id' => $booking_id, 'amount' => $amount, 'change' => $change, 'method' => $payment_method, 'transaction_ref' => $transaction_ref, 'source' => 'customer_portal']);
                header("Location: {$base_url}/customer/receipt.php?payment_id={$payment_id}");
                exit();
            } else {
                $message = 'Payment failed: ' . mysqli_error($conn);
                $message_type = 'danger';
            }
            mysqli_stmt_close($insert_stmt);
        }
        mysqli_stmt_close($stmt);
    }
}

// Get booking details if specified
$selected_booking = null;
if (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
    $bid = intval($_GET['booking_id']);
    $sel_res = mysqli_query($conn, "SELECT b.*, t.tractor_name FROM bookings b INNER JOIN tractors t ON b.tractor_id = t.tractor_id WHERE b.booking_id = $bid AND b.customer_id = $customer_id");
    if (mysqli_num_rows($sel_res) > 0) {
        $selected_booking = mysqli_fetch_assoc($sel_res);
        // Calculate outstanding: total - sum(completed payments)
        $paid_q = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as paid FROM payments WHERE booking_id = $bid AND payment_status = 'completed'");
        $paid = mysqli_fetch_assoc($paid_q)['paid'];
        $selected_booking['outstanding'] = max(0, $selected_booking['total_amount'] - $paid);
    }
}
?>
<div class="main-content">
    <?php echo breadcrumb(['Customer Portal' => '/customer/index.php', 'Make Payment' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">💳</span> Make a Payment</h1>
        <a href="<?php echo $base_url; ?>/customer/history.php" class="btn btn-secondary">Payment History</a>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $message_type); ?>
    <?php endif; ?>

    <div style="max-width: 900px; margin: 0 auto;">
        <div class="form-container">
            <?php if ($selected_booking): ?>
                <h3 style="margin-bottom: 1.5rem;">Pay for Booking #<?php echo $selected_booking['booking_id']; ?> - <?php echo htmlspecialchars($selected_booking['tractor_name']); ?></h3>
                <div class="mb-3">
                    <strong>Total Amount:</strong> <?php echo formatCurrency($selected_booking['total_amount']); ?><br>
                    <strong>Already Paid:</strong> <?php echo formatCurrency($paid ?? 0); ?><br>
                    <strong class="text-primary" style="font-size: 1.1rem;">Outstanding: <?php echo formatCurrency($selected_booking['outstanding']); ?></strong>
                </div>

                <form method="POST" action="" id="payment-form">
                    <input type="hidden" name="action" value="pay">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="booking_id" value="<?php echo $selected_booking['booking_id']; ?>">
                    
                    <div class="form-group">
                        <label for="amount">Payment Amount *</label>
                        <input type="number" id="amount" name="amount" class="form-control" 
                               step="0.01" min="0.01" value="<?php echo $selected_booking['outstanding']; ?>" required>
                        <small class="form-text text-muted">
                            Outstanding: <strong><?php echo formatCurrency($selected_booking['outstanding']); ?></strong>
                            <span id="change-notice" style="display: none; color: var(--success); font-weight: 600;">
                                &nbsp;| Change: <span id="change-amount"></span>
                            </span>
                        </small>
                     </div>

                     <div class="form-group">
                         <label for="payment_method">Payment Method *</label>
                         <select id="payment_method" name="payment_method" class="form-control" required>
                             <option value="cash">Cash</option>
                             <option value="credit_card">Credit Card</option>
                             <option value="bank_transfer">Bank Transfer</option>
                             <option value="check">Check</option>
                         </select>
                     </div>

                     <?php if ($selected_booking['outstanding'] > 0): ?>
                     <div class="alert alert-info" style="font-size: 0.9rem;">
                         <strong>💡 Tip:</strong> You can pay more than the outstanding amount if paying by cash. Any overpayment will be returned as change.
                     </div>
                     <?php endif; ?>

                     <button type="submit" class="btn btn-primary" style="width: 100%;">Process Payment</button>
                 </form>

                 <script>
                 document.addEventListener('DOMContentLoaded', function() {
                     const amountInput = document.getElementById('amount');
                     const changeNotice = document.getElementById('change-notice');
                     const changeAmountSpan = document.getElementById('change-amount');
                     const outstanding = <?php echo floatval($selected_booking['outstanding']); ?>;
                     
                     amountInput.addEventListener('input', function() {
                         const entered = parseFloat(this.value) || 0;
                         if (entered > outstanding) {
                             const change = (entered - outstanding).toFixed(2);
                             changeAmountSpan.textContent = '<?php echo formatCurrency(0); ?>'.replace('0.00', change);
                             changeNotice.style.display = 'inline';
                         } else {
                             changeNotice.style.display = 'none';
                         }
                     });
                 });
                 </script>
            <?php else: ?>
                <h3 style="margin-bottom: 1.5rem;">Select a Booking to Pay</h3>
                <?php if (empty($unpaid_bookings)): ?>
                    <div class="text-center text-muted" style="padding: 2rem;">
                        <p>No outstanding payments. You're all caught up!</p>
                        <a href="<?php echo $base_url; ?>/customer/bookings.php" class="btn btn-secondary mt-2">View My Bookings</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Tractor</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Outstanding</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($unpaid_bookings as $ub): 
                                    $outstanding = max(0, $ub['total_amount'] - $ub['paid_amount']);
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo $ub['booking_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($ub['tractor_name']); ?></td>
                                        <td><?php echo formatCurrency($ub['total_amount']); ?></td>
                                        <td><?php echo formatCurrency($ub['paid_amount']); ?></td>
                                        <td class="text-danger"><strong><?php echo formatCurrency($outstanding); ?></strong></td>
                                        <td>
                                            <a href="<?php echo $base_url; ?>/customer/payments.php?booking_id=<?php echo $ub['booking_id']; ?>" class="btn btn-sm btn-primary">Pay Now</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
