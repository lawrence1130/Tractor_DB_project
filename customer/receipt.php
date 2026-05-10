<?php
require_once __DIR__ . '/../includes/header.php';

// Role check
if ($_SESSION['role'] !== 'customer') {
    header('Location: ' . getBaseUrl() . '/dashboard/index.php');
    exit();
}

$base_url = getBaseUrl();
$customer_id = $_SESSION['customer_id'] ?? 0;
$booking = null;
$payments = [];

// Handle viewing by payment_id (single payment receipt)
if (isset($_GET['payment_id']) && is_numeric($_GET['payment_id'])) {
    $payment_id = intval($_GET['payment_id']);
    
    // Get payment with booking verification
    $pay_sql = "SELECT p.*, b.booking_id, b.total_amount, b.start_date, b.end_date, b.rental_type, b.status as booking_status,
                t.tractor_name, t.brand, t.model, t.description,
                CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email, c.phone, c.address, c.city, c.state
                FROM payments p
                INNER JOIN bookings b ON p.booking_id = b.booking_id
                INNER JOIN tractors t ON b.tractor_id = t.tractor_id
                INNER JOIN customers c ON b.customer_id = c.customer_id
                WHERE p.payment_id = $payment_id AND b.customer_id = $customer_id AND p.payment_status = 'completed'";
    $pay_result = mysqli_query($conn, $pay_sql);
    
    if (mysqli_num_rows($pay_result) > 0) {
        $payment = mysqli_fetch_assoc($pay_result);
        $booking = $payment; // All needed fields present
        // Get all completed payments for this booking to show full history
        $pay_sql_all = "SELECT * FROM payments WHERE booking_id = {$payment['booking_id']} AND payment_status = 'completed' ORDER BY payment_date";
        $pay_result_all = mysqli_query($conn, $pay_sql_all);
        while ($row = mysqli_fetch_assoc($pay_result_all)) {
            $payments[] = $row;
        }
    }
} 
// Fallback to booking_id (for historical receipt viewing)
elseif (isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    $booking_sql = "SELECT b.*, t.tractor_name, t.brand, t.model, t.description,
                    CONCAT(c.first_name, ' ', c.last_name) as customer_name, c.email, c.phone, c.address, c.city, c.state
                    FROM bookings b
                    INNER JOIN tractors t ON b.tractor_id = t.tractor_id
                    INNER JOIN customers c ON b.customer_id = c.customer_id
                    WHERE b.booking_id = $booking_id AND b.customer_id = $customer_id";
    $booking_result = mysqli_query($conn, $booking_sql);
    
    if (mysqli_num_rows($booking_result) === 0) {
        die('Invalid booking or unauthorized access.');
    }
    $booking = mysqli_fetch_assoc($booking_result);
    
    // Get all completed payments for this booking
    $pay_sql = "SELECT * FROM payments WHERE booking_id = $booking_id AND payment_status = 'completed' ORDER BY payment_date";
    $pay_result = mysqli_query($conn, $pay_sql);
    while ($row = mysqli_fetch_assoc($pay_result)) {
        $payments[] = $row;
    }
}

if (!$booking) {
    die('Invalid payment/booking or unauthorized access.');
}

$total_paid = array_sum(array_column($payments, 'amount'));
$balance_due = max(0, $booking['total_amount'] - $total_paid);

// Determine receipt reference and latest payment
$receipt_ref = 'RCPT' . $booking['booking_id'];
$latest_payment = end($payments);
if ($latest_payment && !empty($latest_payment['transaction_ref'])) {
    $receipt_ref = $latest_payment['transaction_ref'];
}
?>
<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <a href="<?php echo $base_url; ?>/customer/history.php" class="back-link">← Back to History</a>
        <button onclick="window.print()" class="btn btn-secondary">🖨️ Print Receipt</button>
    </div>

    <div class="receipt-container animate-fade-in">
        <div class="receipt-header">
            <div class="receipt-brand">🚜 TRACKTOR</div>
            <div class="receipt-meta">
                <div><strong>Receipt #:</strong> <?php echo htmlspecialchars($receipt_ref); ?></div>
                <div><strong>Date:</strong> <?php echo date('M d, Y'); ?></div>
                <div><strong>Booking:</strong> #<?php echo $booking['booking_id']; ?></div>
            </div>
        </div>

        <div class="receipt-section">
            <h4>Customer Information</h4>
            <p>
                <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong><br>
                <?php echo htmlspecialchars($booking['email']); ?><br>
                <?php echo htmlspecialchars($booking['phone']); ?><br>
                <?php echo htmlspecialchars($booking['address'] . ', ' . $booking['city'] . ', ' . $booking['state']); ?>
            </p>
        </div>

        <div class="receipt-section">
            <h4>Rental Details</h4>
            <p>
                <strong>Tractor:</strong> <?php echo htmlspecialchars($booking['tractor_name'] . ' (' . $booking['brand'] . ' ' . $booking['model'] . ')'); ?><br>
                <strong>Rental Period:</strong> <?php echo formatDate($booking['start_date']); ?> to <?php echo formatDate($booking['end_date']); ?><br>
                <strong>Rental Type:</strong> <?php echo ucfirst($booking['rental_type']); ?>
            </p>
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
                        Tractor Rental (<?php echo ucfirst($booking['rental_type']); ?> rate)<br>
                        <small class="text-muted">From <?php echo formatDate($booking['start_date']); ?> to <?php echo formatDate($booking['end_date']); ?></small>
                    </td>
                    <td style="text-align: right;"><?php echo formatCurrency($booking['total_amount']); ?></td>
                </tr>
                 <?php foreach ($payments as $pay): ?>
                     <tr>
                         <td>
                             Payment - <?php echo ucfirst(str_replace('_', ' ', $pay['payment_method'])); ?><br>
                             <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($pay['payment_date'])); ?></small>
                             <?php if (!empty($pay['transaction_ref'])): ?>
                                 <br><small>Ref: <?php echo htmlspecialchars($pay['transaction_ref']); ?></small>
                             <?php endif; ?>
                         </td>
                         <td style="text-align: right;">- <?php echo formatCurrency($pay['amount']); ?></td>
                     </tr>
                     <?php if (!empty($pay['change_amount']) && $pay['change_amount'] > 0): ?>
                         <tr>
                             <td style="padding-left: 2rem; color: #666;">
                                 Change returned<br>
                                 <small class="text-muted">Overpayment refund</small>
                             </td>
                             <td style="text-align: right; color: #28a745;">+ <?php echo formatCurrency($pay['change_amount']); ?></td>
                         </tr>
                     <?php endif; ?>
                 <?php endforeach; ?>
                <?php if ($balance_due > 0): ?>
                    <tr>
                        <td><strong>Balance Due</strong></td>
                        <td style="text-align: right;"><strong class="text-danger"><?php echo formatCurrency($balance_due); ?></strong></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php
        $total_change = array_sum(array_column($payments, 'change_amount'));
        $net_paid = $total_paid - $total_change;
        ?>
        
        <div class="receipt-total">
            <div class="total-row">
                <span>Gross Amount Paid:</span>
                <span><?php echo formatCurrency($total_paid); ?></span>
            </div>
            <?php if ($total_change > 0): ?>
                <div class="total-row">
                    <span>Change Returned:</span>
                    <span style="color: #28a745;">- <?php echo formatCurrency($total_change); ?></span>
                </div>
                <div class="total-row">
                    <span>Net Amount:</span>
                    <span><strong><?php echo formatCurrency($net_paid); ?></strong></span>
                </div>
            <?php endif; ?>
            <?php if ($balance_due > 0): ?>
                <div class="total-row">
                    <span>Balance Due:</span>
                    <span class="text-danger"><?php echo formatCurrency($balance_due); ?></span>
                </div>
            <?php else: ?>
                <div class="total-row grand">
                    <span>Status:</span>
                    <span class="text-success">✓ Fully Paid</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="receipt-footer">
            <p>Thank you for choosing TRACKTOR! For questions, contact support.</p>
            <p><strong>Booking Notes:</strong> <?php echo !empty($booking['notes']) ? htmlspecialchars($booking['notes']) : 'No additional notes'; ?></p>
        </div>

        <div class="receipt-actions">
            <a href="<?php echo $base_url; ?>/customer/payments.php?booking_id=<?php echo $booking['booking_id']; ?>" class="btn btn-primary">Make Additional Payment</a>
            <a href="<?php echo $base_url; ?>/customer/history.php" class="btn btn-secondary">Back to History</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>