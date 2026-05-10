<?php
require_once __DIR__ . '/../includes/header.php';

$base_url = getBaseUrl();
$active_tab = $_GET['tab'] ?? 'inner_join';
?>

<div class="main-content">
    <?php echo breadcrumb(['Reports' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">📊</span> SQL Reports</h1>
    </div>

    <!-- Tabs Navigation -->
    <div class="tabs">
        <a href="?tab=inner_join" class="tab-btn <?php echo $active_tab === 'inner_join' ? 'active' : ''; ?>">INNER JOIN</a>
        <a href="?tab=left_join" class="tab-btn <?php echo $active_tab === 'left_join' ? 'active' : ''; ?>">LEFT JOIN</a>
        <a href="?tab=right_join" class="tab-btn <?php echo $active_tab === 'right_join' ? 'active' : ''; ?>">RIGHT JOIN</a>
        <a href="?tab=full_join" class="tab-btn <?php echo $active_tab === 'full_join' ? 'active' : ''; ?>">FULL OUTER JOIN</a>
        <a href="?tab=aggregates" class="tab-btn <?php echo $active_tab === 'aggregates' ? 'active' : ''; ?>">Aggregates</a>
    </div>

    <!-- INNER JOIN Tab -->
    <div class="tab-content <?php echo $active_tab === 'inner_join' ? 'active' : ''; ?>">
<div class="join-info">
            <h4>🔗 INNER JOIN — Bookings + Tractors + Customers</h4>
            <p>Returns only rows where there is a match in ALL joined tables. Bookings without a valid tractor or customer are excluded.</p>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Tractor</th>
                        <th>Customer</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT b.booking_id, b.start_date, b.end_date, b.total_amount, b.status,
                            t.tractor_name, t.brand,
                            CONCAT(c.first_name, ' ', c.last_name) AS customer_name
                            FROM bookings b
                            INNER JOIN tractors t ON b.tractor_id = t.tractor_id
                            INNER JOIN customers c ON b.customer_id = c.customer_id
                            ORDER BY b.booking_date DESC";
                    $result = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($result)):
                    ?>
                        <tr>
                            <td><strong>#<?php echo $row['booking_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['tractor_name'] . ' (' . $row['brand'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                            <td><?php echo formatDate($row['start_date']); ?></td>
                            <td><?php echo formatDate($row['end_date']); ?></td>
                            <td><strong class="text-gold"><?php echo formatCurrency($row['total_amount']); ?></strong></td>
                            <td><?php echo statusBadge($row['status']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LEFT JOIN Tab -->
    <div class="tab-content <?php echo $active_tab === 'left_join' ? 'active' : ''; ?>">
        <div class="join-info">
            <h4>⬅️ LEFT JOIN — All Tractors + Their Bookings</h4>
            <p>Returns ALL tractors from the left table, with matching bookings from the right. Tractors without bookings show NULL values for booking columns.</p>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Tractor ID</th>
                        <th>Tractor Name</th>
                        <th>Brand</th>
                        <th>Status</th>
                        <th>Booking ID</th>
                        <th>Booking Amount</th>
                        <th>Booking Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT t.tractor_id, t.tractor_name, t.brand, t.status AS tractor_status,
                            b.booking_id, b.total_amount, b.status AS booking_status
                            FROM tractors t
                            LEFT JOIN bookings b ON t.tractor_id = b.tractor_id
                            ORDER BY t.tractor_name";
                    $result = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($result)):
                    ?>
                        <tr>
                            <td><strong>#<?php echo $row['tractor_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($row['tractor_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['brand']); ?></td>
                            <td><?php echo statusBadge($row['tractor_status']); ?></td>
                            <td><?php echo $row['booking_id'] ? '#' . $row['booking_id'] : '<span class="text-muted">No bookings</span>'; ?></td>
                            <td><?php echo $row['total_amount'] ? '<strong class="text-gold">' . formatCurrency($row['total_amount']) . '</strong>' : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php echo $row['booking_status'] ? statusBadge($row['booking_status']) : '<span class="text-muted">—</span>'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RIGHT JOIN Tab -->
    <div class="tab-content <?php echo $active_tab === 'right_join' ? 'active' : ''; ?>">
        <div class="join-info">
            <h4>➡️ RIGHT JOIN — All Bookings + Payment Records</h4>
            <p>Returns ALL bookings from the right table, with matching payments. Bookings without payments show NULL for payment columns.</p>
        </div>
            <div class="<?php echo $active_tab === 'right_join' ? '' : 'table-container'; ?>">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>Booking Amount</th>
                            <th>Booking Status</th>
                            <th>Payment ID</th>
                            <th>Paid Amount</th>
                            <th>Payment Method</th>
                            <th>Payment Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = "SELECT p.payment_id, p.amount AS paid_amount, p.payment_method, p.payment_status,
                                b.booking_id, b.total_amount, b.status AS booking_status,
                                CONCAT(c.first_name, ' ', c.last_name) AS customer_name
                                FROM payments p
                                RIGHT JOIN bookings b ON p.booking_id = b.booking_id
                                LEFT JOIN customers c ON b.customer_id = c.customer_id
                                ORDER BY b.booking_id";
                        $result = mysqli_query($conn, $sql);
                        while ($row = mysqli_fetch_assoc($result)):
                        ?>
                            <tr>
                                <td><strong>#<?php echo $row['booking_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></td>
                                <td><strong class="text-gold"><?php echo formatCurrency($row['total_amount']); ?></strong></td>
                                <td><?php echo statusBadge($row['booking_status']); ?></td>
                                <td><?php echo $row['payment_id'] ? '#' . $row['payment_id'] : '<span class="text-muted">No payment</span>'; ?></td>
                                <td><?php echo $row['paid_amount'] ? formatCurrency($row['paid_amount']) : '<span class="text-muted">—</span>'; ?></td>
                                <td><?php echo $row['payment_method'] ? ucfirst(str_replace('_', ' ', $row['payment_method'])) : '<span class="text-muted">—</span>'; ?></td>
                                <td><?php echo $row['payment_status'] ? statusBadge($row['payment_status']) : '<span class="text-muted">—</span>'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- FULL OUTER JOIN Tab -->
    <div class="tab-content <?php echo $active_tab === 'full_join' ? 'active' : ''; ?>">
        <div class="join-info">
            <h4>🔄 FULL OUTER JOIN — All Customers + All Bookings (MySQL Emulation)</h4>
            <p>MySQL doesn't natively support FULL OUTER JOIN, so we emulate it using <code>UNION</code> of a LEFT JOIN and a RIGHT JOIN. This shows ALL customers and ALL bookings, including those without matches.</p>
        </div>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Booking ID</th>
                        <th>Start Date</th>
                        <th>Amount</th>
                        <th>Booking Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT c.customer_id, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.phone,
                            b.booking_id, b.start_date, b.total_amount, b.status AS booking_status
                            FROM customers c
                            LEFT JOIN bookings b ON c.customer_id = b.customer_id
                            UNION
                            SELECT c.customer_id, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, c.phone,
                            b.booking_id, b.start_date, b.total_amount, b.status AS booking_status
                            FROM customers c
                            RIGHT JOIN bookings b ON c.customer_id = b.customer_id
                            WHERE c.customer_id IS NULL
                            ORDER BY customer_name";
                    $result = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($result)):
                    ?>
                        <tr>
                            <td><?php echo $row['customer_id'] ? '#' . $row['customer_id'] : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($row['phone'] ?? '—'); ?></td>
                            <td><?php echo $row['booking_id'] ? '<strong>#' . $row['booking_id'] . '</strong>' : '<span class="text-muted">No bookings</span>'; ?></td>
                            <td><?php echo formatDate($row['start_date']); ?></td>
                            <td><?php echo $row['total_amount'] ? '<strong class="text-gold">' . formatCurrency($row['total_amount']) . '</strong>' : '<span class="text-muted">—</span>'; ?></td>
                            <td><?php echo $row['booking_status'] ? statusBadge($row['booking_status']) : '<span class="text-muted">—</span>'; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Aggregates Tab -->
    <div class="tab-content <?php echo $active_tab === 'aggregates' ? 'active' : ''; ?>">
        <div class="join-info">
            <h4>📊 Aggregate Functions — Summary Statistics</h4>
            <p>Using <code>COUNT()</code>, <code>SUM()</code>, <code>AVG()</code>, <code>MIN()</code>, <code>MAX()</code> with <code>GROUP BY</code> to generate business insights from joined data.</p>
        </div>

        <!-- Revenue by Tractor -->
        <div class="table-container mb-4">
            <div class="table-header">
                <h3>💰 Revenue by Tractor (GROUP BY with SUM)</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Tractor</th>
                        <th>Brand</th>
                        <th>Total Bookings</th>
                        <th>Total Revenue</th>
                        <th>Avg Booking Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT t.tractor_name, t.brand, t.status,
                            COUNT(b.booking_id) AS total_bookings,
                            COALESCE(SUM(b.total_amount), 0) AS total_revenue,
                            COALESCE(AVG(b.total_amount), 0) AS avg_value
                            FROM tractors t
                            LEFT JOIN bookings b ON t.tractor_id = b.tractor_id
                            GROUP BY t.tractor_id
                            ORDER BY total_revenue DESC";
                    $result = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($result)):
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['tractor_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['brand']); ?></td>
                            <td><?php echo $row['total_bookings']; ?></td>
                            <td><strong class="text-gold"><?php echo formatCurrency($row['total_revenue']); ?></strong></td>
                            <td><?php echo formatCurrency($row['avg_value']); ?></td>
                            <td><?php echo statusBadge($row['status']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Customer Spending -->
        <div class="table-container mb-4">
            <div class="table-header">
                <h3>👥 Customer Spending (GROUP BY with SUM, COUNT)</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Total Bookings</th>
                        <th>Total Spent</th>
                        <th>Avg Per Booking</th>
                        <th>Max Single Booking</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                            COUNT(b.booking_id) AS total_bookings,
                            COALESCE(SUM(b.total_amount), 0) AS total_spent,
                            COALESCE(AVG(b.total_amount), 0) AS avg_spent,
                            COALESCE(MAX(b.total_amount), 0) AS max_booking
                            FROM customers c
                            LEFT JOIN bookings b ON c.customer_id = b.customer_id
                            GROUP BY c.customer_id
                            ORDER BY total_spent DESC";
                    $result = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($result)):
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['customer_name']); ?></strong></td>
                            <td><?php echo $row['total_bookings']; ?></td>
                            <td><strong class="text-gold"><?php echo formatCurrency($row['total_spent']); ?></strong></td>
                            <td><?php echo formatCurrency($row['avg_spent']); ?></td>
                            <td><?php echo formatCurrency($row['max_booking']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Monthly Summary -->
        <div class="table-container">
            <div class="table-header">
                <h3>📅 Booking Status Summary (GROUP BY status)</h3>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Total Amount</th>
                        <th>Avg Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT status, COUNT(*) AS count, SUM(total_amount) AS total, AVG(total_amount) AS avg_amount
                            FROM bookings
                            GROUP BY status
                            ORDER BY count DESC";
                    $result = mysqli_query($conn, $sql);
                    while ($row = mysqli_fetch_assoc($result)):
                    ?>
                        <tr>
                            <td><?php echo statusBadge($row['status']); ?></td>
                            <td><strong><?php echo $row['count']; ?></strong></td>
                            <td><strong class="text-gold"><?php echo formatCurrency($row['total']); ?></strong></td>
                            <td><?php echo formatCurrency($row['avg_amount']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>