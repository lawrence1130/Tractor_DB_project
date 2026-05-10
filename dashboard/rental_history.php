<?php
require_once __DIR__ . '/../includes/header.php';

$base_url = getBaseUrl();

// Filters
$filter_customer = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$filter_status = sanitizeForDB($conn, $_GET['status'] ?? '');
$filter_from = sanitizeForDB($conn, $_GET['from_date'] ?? '');
$filter_to = sanitizeForDB($conn, $_GET['to_date'] ?? '');

// Build WHERE clause
$where_parts = [];
if ($filter_customer > 0) $where_parts[] = "b.customer_id = $filter_customer";
if (!empty($filter_status)) $where_parts[] = "b.status = '$filter_status'";
if (!empty($filter_from)) $where_parts[] = "b.start_date >= '$filter_from'";
if (!empty($filter_to)) $where_parts[] = "b.end_date <= '$filter_to'";
$where_clause = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Get rental history with JOINs
$rentals = [];
$rental_sql = "SELECT b.*, t.tractor_name, t.brand, t.model, t.daily_rate, t.hourly_rate,
               CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.phone, c.email AS customer_email,
               COALESCE(p.total_paid, 0) AS total_paid,
               COALESCE(p.payment_count, 0) AS payment_count
               FROM bookings b
               INNER JOIN tractors t ON b.tractor_id = t.tractor_id
               INNER JOIN customers c ON b.customer_id = c.customer_id
               LEFT JOIN (
                   SELECT booking_id, SUM(amount) AS total_paid, COUNT(*) AS payment_count
                   FROM payments WHERE payment_status = 'completed' GROUP BY booking_id
               ) p ON b.booking_id = p.booking_id
               $where_clause
               ORDER BY b.start_date DESC";
$rental_result = mysqli_query($conn, $rental_sql);
while ($row = mysqli_fetch_assoc($rental_result)) {
    $rentals[] = $row;
}

// Get customers for filter dropdown
$customers_list = [];
$c_res = mysqli_query($conn, "SELECT customer_id, first_name, last_name FROM customers ORDER BY last_name");
while ($c = mysqli_fetch_assoc($c_res)) $customers_list[] = $c;

// Summary stats
$summary_sql = "SELECT 
    COUNT(*) AS total_rentals,
    COALESCE(SUM(total_amount), 0) AS total_value,
    COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) AS completed_value,
    COALESCE(SUM(CASE WHEN status = 'active' THEN total_amount ELSE 0 END), 0) AS active_value,
    COALESCE(SUM(CASE WHEN status = 'cancelled' THEN total_amount ELSE 0 END), 0) AS cancelled_value,
    COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_count,
    COUNT(CASE WHEN status = 'active' THEN 1 END) AS active_count,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending_count,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) AS cancelled_count
    FROM bookings $where_clause";
$summary_result = mysqli_query($conn, $summary_sql);
$summary = mysqli_fetch_assoc($summary_result);

// Per-customer breakdown (for the customer detail view)
$customer_breakdown = [];
if ($filter_customer > 0) {
    $cb_sql = "SELECT 
        c.customer_id, c.first_name, c.last_name, c.email, c.phone, c.address, c.city, c.state, c.zip_code,
        COUNT(b.booking_id) AS total_bookings,
        COALESCE(SUM(b.total_amount), 0) AS total_spent,
        COALESCE(SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END), 0) AS completed_spent,
        COALESCE(SUM(CASE WHEN b.status = 'active' THEN b.total_amount ELSE 0 END), 0) AS active_spent,
        MIN(b.start_date) AS first_rental,
        MAX(b.start_date) AS last_rental,
        COALESCE(pp.total_paid, 0) AS total_paid_all
        FROM customers c
        LEFT JOIN bookings b ON c.customer_id = b.customer_id
        LEFT JOIN (
            SELECT bk.customer_id, SUM(py.amount) AS total_paid
            FROM payments py INNER JOIN bookings bk ON py.booking_id = bk.booking_id
            WHERE py.payment_status = 'completed' GROUP BY bk.customer_id
        ) pp ON c.customer_id = pp.customer_id
        WHERE c.customer_id = $filter_customer
        GROUP BY c.customer_id";
    $cb_result = mysqli_query($conn, $cb_sql);
    $customer_breakdown = mysqli_fetch_assoc($cb_result);
}

// Get recent payments for the customer (if filtered)
$customer_payments = [];
if ($filter_customer > 0) {
    $cp_res = mysqli_query($conn, "SELECT p.*, b.total_amount AS booking_total, t.tractor_name
                                   FROM payments p
                                   INNER JOIN bookings b ON p.booking_id = b.booking_id
                                   INNER JOIN tractors t ON b.tractor_id = t.tractor_id
                                   WHERE b.customer_id = $filter_customer
                                   ORDER BY p.payment_date DESC LIMIT 10");
    while ($cp = mysqli_fetch_assoc($cp_res)) $customer_payments[] = $cp;
}
?>

<div class="main-content">
    <?php echo breadcrumb(['Rental History' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">📜</span> Rental History</h1>
        <div class="d-flex gap-2">
            <?php if ($filter_customer > 0): ?>
                <a href="<?php echo $base_url; ?>/dashboard/payments.php" class="btn btn-primary">💳 Record Payment</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">📅</div>
            <div class="stat-value"><?php echo $summary['total_rentals']; ?></div>
            <div class="stat-label">Total Rentals</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-value"><?php echo $summary['completed_count']; ?></div>
            <div class="stat-label">Completed (<?php echo formatCurrency($summary['completed_value']); ?>)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon gold">▶</div>
            <div class="stat-value"><?php echo $summary['active_count']; ?></div>
            <div class="stat-label">Active (<?php echo formatCurrency($summary['active_value']); ?>)</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red">✕</div>
            <div class="stat-value"><?php echo $summary['cancelled_count']; ?></div>
            <div class="stat-label">Cancelled (<?php echo formatCurrency($summary['cancelled_value']); ?>)</div>
        </div>
    </div>

    <!-- Customer Detail Card (shown when filtering by customer) -->
    <?php if (!empty($customer_breakdown)): ?>
    <div class="join-info mb-4">
        <h4>👤 Customer Profile: <?php echo htmlspecialchars($customer_breakdown['first_name'] . ' ' . $customer_breakdown['last_name']); ?></h4>
        <p>
            <strong>Email:</strong> <?php echo htmlspecialchars($customer_breakdown['email']); ?> &nbsp;|&nbsp;
            <strong>Phone:</strong> <?php echo htmlspecialchars($customer_breakdown['phone']); ?> &nbsp;|&nbsp;
            <strong>Location:</strong> <?php echo htmlspecialchars($customer_breakdown['city'] . ', ' . $customer_breakdown['state'] . ' ' . $customer_breakdown['zip_code']); ?>
        </p>
        <p>
            <strong>Total Bookings:</strong> <?php echo $customer_breakdown['total_bookings']; ?> &nbsp;|&nbsp;
            <strong>Total Spent:</strong> <?php echo formatCurrency($customer_breakdown['total_spent']); ?> &nbsp;|&nbsp;
            <strong>Completed Spent:</strong> <?php echo formatCurrency($customer_breakdown['completed_spent']); ?> &nbsp;|&nbsp;
            <strong>Active Spent:</strong> <?php echo formatCurrency($customer_breakdown['active_spent']); ?> &nbsp;|&nbsp;
            <strong>First Rental:</strong> <?php echo formatDate($customer_breakdown['first_rental']); ?> &nbsp;|&nbsp;
            <strong>Last Rental:</strong> <?php echo formatDate($customer_breakdown['last_rental']); ?>
        </p>
        <p>
            <strong>Total Paid:</strong> 
            <span class="text-success"><?php echo formatCurrency($customer_breakdown['total_paid_all']); ?></span> &nbsp;|&nbsp;
            <strong>Outstanding Balance:</strong>
            <?php $outstanding = $customer_breakdown['total_spent'] - $customer_breakdown['total_paid_all']; ?>
            <?php if ($outstanding > 0): ?>
                <span class="balance-owed"><strong class="text-danger"><?php echo formatCurrency($outstanding); ?></strong> owed</span>
            <?php else: ?>
                <span class="balance-paid"><strong class="text-success">✓ Fully Paid</strong></span>
            <?php endif; ?>
        </p>
    </div>

    <!-- Customer Recent Payments -->
    <?php if (!empty($customer_payments)): ?>
    <div class="table-container mb-4">
        <div class="table-header">
            <h3>💳 Recent Payments for <?php echo htmlspecialchars($customer_breakdown['first_name']); ?></h3>
            <a href="<?php echo $base_url; ?>/dashboard/payments.php" class="btn btn-sm btn-secondary">View All Payments →</a>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Booking</th>
                    <th>Tractor</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customer_payments as $cp): ?>
                <tr>
                    <td><strong>#<?php echo $cp['payment_id']; ?></strong></td>
                    <td>#<?php echo $cp['booking_id']; ?></td>
                    <td><?php echo htmlspecialchars($cp['tractor_name']); ?></td>
                    <td><strong class="text-gold"><?php echo formatCurrency($cp['amount']); ?></strong></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $cp['payment_method'])); ?></td>
                    <td><?php echo statusBadge($cp['payment_status']); ?></td>
                    <td style="font-size: 0.85rem;"><?php echo formatDateTime($cp['payment_date']); ?></td>
                    <td>
                        <a href="<?php echo $base_url; ?>/dashboard/payments.php?action=receipt&id=<?php echo $cp['payment_id']; ?>" 
                           class="btn btn-sm btn-secondary" title="View Receipt">🧾</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Filters -->
    <div class="form-container animate-fade-in" style="max-width: 100%; margin-bottom: 2rem;">
        <h3 style="margin-bottom: 1rem; font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">🔍 Filter Rental History</h3>
        <form method="GET" action="" class="d-flex gap-2 align-center flex-wrap">
            <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
                <select name="customer_id" class="form-control">
                    <option value="">All Customers</option>
                    <?php foreach ($customers_list as $c): ?>
                        <option value="<?php echo $c['customer_id']; ?>" <?php echo $filter_customer == $c['customer_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['first_name'] . ' ' . $c['last_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <input type="date" name="from_date" class="form-control" value="<?php echo htmlspecialchars($filter_from); ?>" placeholder="From">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <input type="date" name="to_date" class="form-control" value="<?php echo htmlspecialchars($filter_to); ?>" placeholder="To">
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="<?php echo $base_url; ?>/dashboard/rental-history.php" class="btn btn-secondary">Clear</a>
        </form>
    </div>

    <!-- Rental History Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>📜 Rental Records (LEFT JOIN: bookings + tractors + customers + payments)</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Tractor</th>
                    <th>Customer</th>
                    <th>Rental Period</th>
                    <th>Time Left</th>
                    <th>Type</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Balance</th>
                    <th>Payments</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rentals)): ?>
                    <tr><td colspan="11" class="text-center text-muted" style="padding: 2rem;">No rental records found</td></tr>
                <?php else: ?>
                    <?php foreach ($rentals as $r): ?>
                        <?php $balance = $r['total_amount'] - $r['total_paid']; ?>
                        <tr>
                            <td><strong>#<?php echo $r['booking_id']; ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($r['tractor_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($r['brand'] . ' ' . $r['model']); ?></div>
                            </td>
                            <td>
                                <div>
                                    <a href="<?php echo $base_url; ?>/dashboard/rental-history.php?customer_id=<?php echo $r['customer_id']; ?>" style="color: var(--primary-light);">
                                        <?php echo htmlspecialchars($r['customer_name']); ?>
                                    </a>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($r['phone']); ?></div>
                            </td>
                            <td>
                                <div style="font-size: 0.85rem;"><?php echo formatDate($r['start_date']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">to <?php echo formatDate($r['end_date']); ?></div>
                            </td>
                            <td><?php echo getRemainingTime($r['end_date'], $r['status']); ?></td>
                            <td><?php echo ucfirst($r['rental_type']); ?></td>
                            <td><strong class="text-gold"><?php echo formatCurrency($r['total_amount']); ?></strong></td>
                            <td class="text-success"><?php echo formatCurrency($r['total_paid']); ?></td>
                            <td>
                                <?php if ($balance > 0): ?>
                                    <span class="balance-owed"><strong class="text-danger"><?php echo formatCurrency($balance); ?></strong></span>
                                <?php else: ?>
                                    <span class="balance-paid"><strong class="text-success">✓ Paid</strong></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($r['payment_count'] > 0): ?>
                                    <a href="<?php echo $base_url; ?>/dashboard/payments.php" class="btn btn-sm btn-secondary" title="View payments">
                                        💳 <?php echo $r['payment_count']; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size: 0.8rem;">None</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo statusBadge($r['status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Total Summary Row -->
    <?php if (!empty($rentals)): ?>
    <div class="join-info mt-4">
        <h4>📊 Summary</h4>
        <p>
            <strong>Total Rentals:</strong> <?php echo count($rentals); ?> &nbsp;|&nbsp;
            <strong>Total Value:</strong> <?php echo formatCurrency(array_sum(array_column($rentals, 'total_amount'))); ?> &nbsp;|&nbsp;
            <strong>Total Paid:</strong> <span class="text-success"><?php echo formatCurrency(array_sum(array_column($rentals, 'total_paid'))); ?></span> &nbsp;|&nbsp;
            <strong>Outstanding Balance:</strong> 
            <?php $total_outstanding = array_sum(array_column($rentals, 'total_amount')) - array_sum(array_column($rentals, 'total_paid')); ?>
            <?php if ($total_outstanding > 0): ?>
                <span class="balance-owed"><strong class="text-danger"><?php echo formatCurrency($total_outstanding); ?></strong></span>
            <?php else: ?>
                <span class="balance-paid"><strong class="text-success">✓ All Paid</strong></span>
            <?php endif; ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>