<?php
require_once __DIR__ . '/../includes/header.php';

// Role check - only customers allowed
if ($_SESSION['role'] !== 'customer') {
    header('Location: ' . getBaseUrl() . '/dashboard/index.php');
    exit();
}

$base_url = getBaseUrl();

// Get customer ID for current user
$customer_sql = "SELECT customer_id FROM customers WHERE email = ?";
$stmt = mysqli_prepare($conn, $customer_sql);
mysqli_stmt_bind_param($stmt, 's', $_SESSION['email']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    // Create customer record if doesn't exist
    $names = explode(' ', $_SESSION['full_name']);
    $first = $names[0];
    $last = count($names) > 1 ? implode(' ', array_slice($names, 1)) : 'N/A';
    
    $insert_customer = "INSERT INTO customers (first_name, last_name, email) VALUES (?, ?, ?)";
    $stmt2 = mysqli_prepare($conn, $insert_customer);
    mysqli_stmt_bind_param($stmt2, 'sss', $first, $last, $_SESSION['email']);
    mysqli_stmt_execute($stmt2);
    $customer_id = mysqli_insert_id($conn);
} else {
    $customer_id = $customer['customer_id'];
}

// Store customer_id in session for later use
$_SESSION['customer_id'] = $customer_id;

// Get customer's stats
$my_bookings_sql = "SELECT COUNT(*) as total FROM bookings WHERE customer_id = $customer_id";
$my_bookings = mysqli_fetch_assoc(mysqli_query($conn, $my_bookings_sql))['total'];

$active_bookings_sql = "SELECT COUNT(*) as total FROM bookings WHERE customer_id = $customer_id AND status IN ('pending', 'confirmed', 'active')";
$active_bookings = mysqli_fetch_assoc(mysqli_query($conn, $active_bookings_sql))['total'];

$pending_payments_sql = "SELECT COALESCE(SUM(amount), 0) as total FROM payments 
                        WHERE booking_id IN (SELECT booking_id FROM bookings WHERE customer_id = $customer_id) 
                        AND payment_status = 'pending'";
$pending_payments = mysqli_fetch_assoc(mysqli_query($conn, $pending_payments_sql))['total'];

// Get recent bookings
$recent_bookings = [];
$recent_sql = "SELECT b.*, t.tractor_name, t.brand 
               FROM bookings b 
               INNER JOIN tractors t ON b.tractor_id = t.tractor_id 
               WHERE b.customer_id = $customer_id 
               ORDER BY b.created_at DESC LIMIT 5";
$recent_result = mysqli_query($conn, $recent_sql);
while ($row = mysqli_fetch_assoc($recent_result)) {
    $recent_bookings[] = $row;
}

// Get available tractors count
$available_tractors = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM tractors WHERE status = 'available'"))['total'];
?>
<div class="main-content">
    <?php echo breadcrumb(['Customer Portal' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">🏠</span> Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
        <a href="<?php echo $base_url; ?>/customer/bookings.php?action=new" class="btn btn-primary">+ New Booking</a>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📅</div>
            <div class="stat-value"><?php echo $my_bookings; ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✓</div>
            <div class="stat-value"><?php echo $active_bookings; ?></div>
            <div class="stat-label">Active Bookings</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold">💰</div>
            <div class="stat-value"><?php echo formatCurrency($pending_payments); ?></div>
            <div class="stat-label">Pending Payments</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">🚜</div>
            <div class="stat-value"><?php echo $available_tractors; ?></div>
            <div class="stat-label">Available Tractors</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="stats-grid mt-4">
        <div class="stat-card" style="cursor: pointer;" onclick="window.location='<?php echo $base_url; ?>/customer/tractors.php'">
            <div class="stat-icon red">🚜</div>
            <div class="stat-value" style="font-size: 1.2rem;">Browse Tractors</div>
            <div class="stat-label">View available equipment</div>
        </div>
        <div class="stat-card" style="cursor: pointer;" onclick="window.location='<?php echo $base_url; ?>/customer/bookings.php'">
            <div class="stat-icon blue">📅</div>
            <div class="stat-value" style="font-size: 1.2rem;">My Bookings</div>
            <div class="stat-label">View & manage bookings</div>
        </div>
        <div class="stat-card" style="cursor: pointer;" onclick="window.location='<?php echo $base_url; ?>/customer/payments.php'">
            <div class="stat-icon green">💳</div>
            <div class="stat-value" style="font-size: 1.2rem;">Make Payment</div>
            <div class="stat-label">Pay for bookings</div>
        </div>
        <div class="stat-card" style="cursor: pointer;" onclick="window.location='<?php echo $base_url; ?>/customer/history.php'">
            <div class="stat-icon gold">📜</div>
            <div class="stat-value" style="font-size: 1.2rem;">Rental History</div>
            <div class="stat-label">Past rentals & receipts</div>
        </div>
    </div>

    <!-- Recent Bookings -->
    <div class="table-container mt-4">
        <div class="table-header">
            <h3>📋 Recent Bookings</h3>
            <a href="<?php echo $base_url; ?>/customer/bookings.php" class="btn btn-sm btn-secondary">View All →</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tractor</th>
                    <th>Dates</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_bookings)): ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding: 2rem;">No bookings yet. <a href="<?php echo $base_url; ?>/customer/bookings.php?action=new">Create your first booking!</a></td></tr>
                <?php else: ?>
                    <?php foreach ($recent_bookings as $b): ?>
                        <tr>
                            <td><strong>#<?php echo $b['booking_id']; ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($b['tractor_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($b['brand']); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;"><?php echo formatDate($b['start_date']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">to <?php echo formatDate($b['end_date']); ?></div>
                            </td>
                            <td><strong class="text-gold"><?php echo formatCurrency($b['total_amount']); ?></strong></td>
                            <td><?php echo statusBadge($b['status']); ?></td>
                            <td>
                                <?php if ($b['status'] === 'pending' || $b['status'] === 'confirmed'): ?>
                                    <a href="<?php echo $base_url; ?>/customer/payments.php?booking_id=<?php echo $b['booking_id']; ?>" class="btn btn-sm btn-primary">Pay Now</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>