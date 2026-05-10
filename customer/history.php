<?php
require_once __DIR__ . '/../includes/header.php';

// Role check
if ($_SESSION['role'] !== 'customer') {
    header('Location: ' . getBaseUrl() . '/dashboard/index.php');
    exit();
}

$base_url = getBaseUrl();
$customer_id = $_SESSION['customer_id'] ?? 0;

// Get all past/completed bookings
$history = [];
$history_sql = "SELECT b.*, t.tractor_name, t.brand, t.model,
                (SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = b.booking_id AND payment_status = 'completed') as total_paid
                FROM bookings b 
                INNER JOIN tractors t ON b.tractor_id = t.tractor_id 
                WHERE b.customer_id = $customer_id 
                AND b.status IN ('completed', 'cancelled')
                ORDER BY b.end_date DESC";
$history_result = mysqli_query($conn, $history_sql);
while ($row = mysqli_fetch_assoc($history_result)) {
    $row['payments'] = [];
    $pay_q = mysqli_query($conn, "SELECT * FROM payments WHERE booking_id = {$row['booking_id']} ORDER BY payment_date DESC");
    while ($p = mysqli_fetch_assoc($pay_q)) {
        $row['payments'][] = $p;
    }
    $history[] = $row;
}

// Also show active bookings for reference
$active_bookings = [];
$active_sql = "SELECT b.*, t.tractor_name, t.brand 
               FROM bookings b 
               INNER JOIN tractors t ON b.tractor_id = t.tractor_id 
               WHERE b.customer_id = $customer_id 
               AND b.status IN ('pending', 'confirmed', 'active')
               ORDER BY b.start_date ASC";
$active_result = mysqli_query($conn, $active_sql);
while ($row = mysqli_fetch_assoc($active_result)) {
    $active_bookings[] = $row;
}
?>
<div class="main-content">
    <?php echo breadcrumb(['Customer Portal' => '/customer/index.php', 'Rental History' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">📜</span> Rental History</h1>
        <a href="<?php echo $base_url; ?>/customer/tractors.php" class="btn btn-secondary">Browse Tractors</a>
    </div>

    <!-- Active Bookings -->
    <?php if (!empty($active_bookings)): ?>
        <h2 class="mt-4 mb-2">📌 Active & Upcoming Bookings</h2>
        <div class="table-container mb-4">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tractor</th>
                        <th>Dates</th>
                        <th>Time Left</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_bookings as $ab): ?>
                        <?php
                        $paid_q = mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as paid FROM payments WHERE booking_id = {$ab['booking_id']} AND payment_status = 'completed'");
                        $paid = mysqli_fetch_assoc($paid_q)['paid'];
                        $outstanding = max(0, $ab['total_amount'] - $paid);
                        ?>
                        <tr>
                            <td><strong>#<?php echo $ab['booking_id']; ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($ab['tractor_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($ab['brand']); ?></div>
                            </td>
                            <td>
                                <div><?php echo formatDate($ab['start_date']); ?> → <?php echo formatDate($ab['end_date']); ?></div>
                            </td>
                            <td><?php echo getRemainingTime($ab['end_date'], $ab['status']); ?></td>
                            <td>
                                <div><?php echo formatCurrency($ab['total_amount']); ?></div>
                                <div class="text-muted" style="font-size: 0.85rem;">Paid: <?php echo formatCurrency($paid); ?></div>
                            </td>
                            <td><?php echo statusBadge($ab['status']); ?></td>
                            <td>
                                <?php if ($outstanding > 0): ?>
                                    <a href="<?php echo $base_url; ?>/customer/payments.php?booking_id=<?php echo $ab['booking_id']; ?>" class="btn btn-sm btn-primary">Pay $<?php echo number_format($outstanding, 2); ?></a>
                                <?php else: ?>
                                    <span class="badge badge-completed">✓ Paid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- Completed/Cancelled History -->
    <h2 class="mt-4 mb-2">📁 Past Rentals</h2>
    <?php if (empty($history)): ?>
        <div class="text-center text-muted" style="padding: 3rem; background: var(--dark-card); border-radius: var(--radius-md);">
            <p style="font-size: 1.1rem;">No rental history yet.</p>
            <a href="<?php echo $base_url; ?>/customer/tractors.php" class="btn btn-primary mt-2">Browse Available Tractors</a>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Tractor</th>
                        <th>Rental Period</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><strong>#<?php echo $h['booking_id']; ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($h['tractor_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($h['brand'] . ' ' . $h['model']); ?></div>
                            </td>
                            <td>
                                <div><?php echo formatDate($h['start_date']); ?> → <?php echo formatDate($h['end_date']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo ucfirst($h['rental_type']); ?> rental</div>
                            </td>
                            <td><?php echo formatCurrency($h['total_amount']); ?></td>
                            <td>
                                <strong class="text-success"><?php echo formatCurrency($h['total_paid']); ?></strong>
                            </td>
                            <td><?php echo statusBadge($h['status']); ?></td>
                            <td>
                                <?php if ($h['total_paid'] > 0): ?>
                                    <a href="<?php echo $base_url; ?>/customer/receipt.php?booking_id=<?php echo $h['booking_id']; ?>" class="btn btn-sm btn-secondary" target="_blank">🖨️ Receipt</a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>