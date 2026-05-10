<?php
require_once __DIR__ . '/../includes/header.php';

$stats = getDashboardStats($conn);
$recent_bookings = getRecentBookings($conn, 5);
$base_url = getBaseUrl();

// Get active bookings with countdown for dashboard
$active_bookings_countdown = [];
$ab_sql = "SELECT b.booking_id, t.tractor_name, b.end_date, b.status
           FROM bookings b
           INNER JOIN tractors t ON b.tractor_id = t.tractor_id
           WHERE b.status IN ('active', 'confirmed', 'pending')
           ORDER BY b.end_date ASC LIMIT 5";
$ab_result = mysqli_query($conn, $ab_sql);
while ($ab = mysqli_fetch_assoc($ab_result)) {
    $active_bookings_countdown[] = $ab;
}

// Payment stats for dashboard
$pay_stats_result = mysqli_query($conn, "SELECT 
    COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) AS paid_total,
    COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END), 0) AS pending_total,
    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) AS pending_count
    FROM payments");
$pay_stats = mysqli_fetch_assoc($pay_stats_result);
?>

<div class="main-content dashboard-main">
    <?php echo breadcrumb(['Dashboard' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">📊</span> Dashboard</h1>
        <a href="<?php echo $base_url; ?>/dashboard/bookings.php?action=new" class="btn btn-primary">+ New Booking</a>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid enhanced">
        <div class="stat-card enhanced">
            <div class="stat-icon red">🚜</div>
            <div class="stat-value"><?php echo $stats['total_tractors']; ?></div>
            <div class="stat-label">Total Tractors</div>
        </div>
        <div class="stat-card enhanced">
            <div class="stat-icon green">✓</div>
            <div class="stat-value"><?php echo $stats['available_tractors']; ?></div>
            <div class="stat-label">Available</div>
        </div>
        <div class="stat-card enhanced">
            <div class="stat-icon blue">📅</div>
            <div class="stat-value"><?php echo $stats['active_bookings']; ?></div>
            <div class="stat-label">Active Bookings</div>
        </div>
        <div class="stat-card enhanced">
            <div class="stat-icon gold">💰</div>
            <div class="stat-value"><?php echo formatCurrency($stats['total_booking_value']); ?></div>
            <div class="stat-label">Total Booking Value</div>
        </div>
        <div class="stat-card enhanced">
            <div class="stat-icon green">✅</div>
            <div class="stat-value"><?php echo formatCurrency($pay_stats['paid_total']); ?></div>
            <div class="stat-label">Payments Received</div>
        </div>
        <div class="stat-card enhanced">
            <div class="stat-icon gold">⏳</div>
            <div class="stat-value"><?php echo $pay_stats['pending_count']; ?> (<?php echo formatCurrency($pay_stats['pending_total']); ?>)</div>
            <div class="stat-label">Pending Payments</div>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="table-container enhanced">
        <div class="table-header">
            <h3>📋 Recent Bookings</h3>
            <a href="<?php echo $base_url; ?>/dashboard/bookings.php" class="btn btn-sm btn-secondary btn-hover">View All →</a>
        </div>
        <table class="enhanced">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tractor</th>
                    <th>Customer</th>
                    <th>Dates</th>
                    <th>Time Left</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_bookings)): ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding: 2rem;">No bookings found</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_bookings as $booking): ?>
                        <tr class="booking-row">
                            <td><strong>#<?php echo $booking['booking_id']; ?></strong></td>
                            <td>
                                <div class="tractor-info">
                                    <div><?php echo htmlspecialchars($booking['tractor_name']); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($booking['brand']); ?></div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                            <td>
                                <div class="date-range">
                                    <div style="font-size: 0.85rem;"><?php echo formatDate($booking['start_date']); ?></div>
                                    <div class="text-muted" style="font-size: 0.75rem;">to <?php echo formatDate($booking['end_date']); ?></div>
                                </div>
                            </td>
                            <td><?php echo getRemainingTime($booking['end_date'], $booking['status']); ?></td>
                            <td><strong class="text-gold"><?php echo formatCurrency($booking['total_amount']); ?></strong></td>
                            <td><?php echo statusBadge($booking['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Active Rentals Countdown -->
    <?php if (!empty($active_bookings_countdown)): ?>
    <div class="table-container enhanced mt-4">
        <div class="table-header">
            <h3>⏰ Active Rentals - Time Remaining</h3>
            <a href="<?php echo $base_url; ?>/dashboard/bookings.php" class="btn btn-sm btn-secondary">View All →</a>
        </div>
        <table class="enhanced">
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Tractor</th>
                    <th>Due Date</th>
                    <th>Time Remaining</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_bookings_countdown as $ab): ?>
                    <tr>
                        <td><strong>#<?php echo $ab['booking_id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($ab['tractor_name']); ?></td>
                        <td><?php echo formatDate($ab['end_date']); ?></td>
                        <td><?php echo getRemainingTime($ab['end_date'], $ab['status']); ?></td>
                        <td><?php echo statusBadge($ab['status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="stats-grid enhanced mt-4">
        <div class="stat-card enhanced" onclick="window.location='<?php echo $base_url; ?>/dashboard/tractors.php'">
            <div class="stat-icon red">🚜</div>
            <div class="stat-value">Manage Fleet</div>
            <div class="stat-label">Add, edit & upload tractor images</div>
        </div>
        <div class="stat-card enhanced" onclick="window.location='<?php echo $base_url; ?>/dashboard/customers.php'">
            <div class="stat-icon blue">👥</div>
            <div class="stat-value"><?php echo $stats['total_customers']; ?> Customers</div>
            <div class="stat-label">View & manage customer records</div>
        </div>
        <div class="stat-card enhanced" onclick="window.location='<?php echo $base_url; ?>/dashboard/payments.php'">
            <div class="stat-icon green">💳</div>
            <div class="stat-value">Payments</div>
            <div class="stat-label">Manage payments & invoices</div>
        </div>
        <div class="stat-card enhanced" onclick="window.location='<?php echo $base_url; ?>/dashboard/rental-history.php'">
            <div class="stat-icon blue">📜</div>
            <div class="stat-value">Rental History</div>
            <div class="stat-label">View past & completed rentals</div>
        </div>
        <div class="stat-card enhanced" onclick="window.location='<?php echo $base_url; ?>/dashboard/reports.php'">
            <div class="stat-icon gold">📊</div>
            <div class="stat-value">SQL Reports</div>
            <div class="stat-label">JOIN queries & analytics</div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>