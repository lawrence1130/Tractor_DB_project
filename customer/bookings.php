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

// Get available tractors for dropdown
$available_tractors = [];
$t_res = mysqli_query($conn, "SELECT tractor_id, tractor_name, brand, model, daily_rate, hourly_rate FROM tractors WHERE status = 'available' ORDER BY tractor_name");
while ($t = mysqli_fetch_assoc($t_res)) {
    $available_tractors[] = $t;
}

// Get customer's bookings
$bookings = [];
$booking_sql = "SELECT b.*, t.tractor_name, t.brand, t.model 
               FROM bookings b 
               INNER JOIN tractors t ON b.tractor_id = t.tractor_id 
               WHERE b.customer_id = $customer_id 
               ORDER BY b.created_at DESC";
$booking_result = mysqli_query($conn, $booking_sql);
while ($row = mysqli_fetch_assoc($booking_result)) {
    $bookings[] = $row;
}

// Calculate total amount based on dates, tractor rates
function calculateBookingAmount($tractor_id, $start_date, $end_date, $rental_type = 'daily') {
    global $conn;
    $tractor = mysqli_fetch_assoc(mysqli_query($conn, "SELECT hourly_rate, daily_rate FROM tractors WHERE tractor_id = $tractor_id"));
    if (!$tractor) return 0;
    $days = max(1, (strtotime($end_date) - strtotime($start_date)) / 86400);
    return $rental_type === 'hourly' 
        ? $tractor['hourly_rate'] * $days * 8 
        : $tractor['daily_rate'] * $days;
}

// Handle booking creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'CSRF token validation failed. Please refresh the page.';
        $message_type = 'danger';
    } else {
        $tractor_id = intval($_POST['tractor_id'] ?? 0);
        $start_date = sanitizeForDB($conn, $_POST['start_date'] ?? '');
        $end_date = sanitizeForDB($conn, $_POST['end_date'] ?? '');
        $rental_type = sanitizeForDB($conn, $_POST['rental_type'] ?? 'daily');
        $notes = sanitizeForDB($conn, $_POST['notes'] ?? '');
        
        // Validate
        if (empty($tractor_id) || empty($start_date) || empty($end_date)) {
            $message = 'Please fill in all required fields.';
            $message_type = 'danger';
        } elseif (strtotime($start_date) > strtotime($end_date)) {
            $message = 'End date must be after start date.';
            $message_type = 'danger';
        } else {
            // Check tractor availability
            $conflict_check = mysqli_query($conn, "
                SELECT COUNT(*) as count FROM bookings 
                WHERE tractor_id = $tractor_id 
                AND status NOT IN ('cancelled', 'completed')
                AND (
                    (start_date <= '$end_date' AND end_date >= '$start_date')
                )
            ");
            if (mysqli_fetch_assoc($conflict_check)['count'] > 0) {
                $message = 'Tractor is already booked for these dates.';
                $message_type = 'danger';
            } else {
                $total_amount = calculateBookingAmount($tractor_id, $start_date, $end_date, $rental_type);
                $insert_sql = "INSERT INTO bookings (tractor_id, customer_id, user_id, booking_date, start_date, end_date, rental_type, total_amount, status, notes) 
                           VALUES ($tractor_id, $customer_id, {$_SESSION['user_id']}, CURDATE(), '$start_date', '$end_date', '$rental_type', $total_amount, 'pending', '$notes')";
                if (mysqli_query($conn, $insert_sql)) {
                    $message = 'Booking created successfully! Redirecting to payment...';
                    $message_type = 'success';
                    $booking_id = mysqli_insert_id($conn);
                    header("refresh:2;url={$base_url}/customer/payments.php?booking_id={$booking_id}");
                } else {
                    $message = 'Error creating booking: ' . mysqli_error($conn);
                    $message_type = 'danger';
                }
            }
        }
    }
}

// Handle cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $cancel_id = intval($_GET['cancel']);
    // Verify ownership
    $check = mysqli_query($conn, "SELECT booking_id FROM bookings WHERE booking_id = $cancel_id AND customer_id = $customer_id");
    if (mysqli_num_rows($check) > 0) {
        mysqli_query($conn, "UPDATE bookings SET status = 'cancelled' WHERE booking_id = $cancel_id");
        $message = 'Booking cancelled successfully.';
        $message_type = 'success';
        header("Location: {$base_url}/customer/bookings.php");
        exit();
    } else {
        $message = 'Unauthorized action.';
        $message_type = 'danger';
    }
}
?>
<div class="main-content">
    <?php echo breadcrumb(['Customer Portal' => '/customer/index.php', 'My Bookings' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">📅</span> My Bookings</h1>
        <a href="<?php echo $base_url; ?>/customer/bookings.php?action=new" class="btn btn-primary">+ New Booking</a>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $message_type); ?>
    <?php endif; ?>

    <?php if (isset($_GET['action']) && $_GET['action'] === 'new'): ?>
    <!-- New Booking Form -->
    <div class="form-container animate-fade-in" style="max-width: 700px; margin-bottom: 2rem;">
        <h2 style="margin-bottom: 1.5rem; font-size: 1.3rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
            📅 New Booking
        </h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <?php echo csrfInput(); ?>
            
            <div class="form-group">
                <label for="tractor_id">Select Tractor *</label>
                <select id="tractor_id" name="tractor_id" class="form-control" required>
                    <option value="">-- Choose a tractor --</option>
                    <?php foreach ($available_tractors as $t): ?>
                        <option value="<?php echo $t['tractor_id']; ?>" 
                                <?php echo (isset($_GET['tractor_id']) && $_GET['tractor_id'] == $t['tractor_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($t['tractor_name'] . ' (' . $t['brand'] . ')'); ?>
                            - $<?php echo number_format($t['daily_rate'], 2); ?>/day
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Start Date *</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" required
                           value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"
                           min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="end_date">End Date *</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" required
                           value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="rental_type">Rental Type *</label>
                    <select id="rental_type" name="rental_type" class="form-control" required>
                        <option value="daily" <?php echo (($_POST['rental_type'] ?? 'daily') === 'daily') ? 'selected' : ''; ?>>Daily</option>
                        <option value="hourly" <?php echo (($_POST['rental_type'] ?? '') === 'hourly') ? 'selected' : ''; ?>>Hourly</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"
                          placeholder="Any special requirements or instructions..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">Create Booking</button>
                <a href="<?php echo $base_url; ?>/customer/bookings.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
    <?php else: ?>
    <!-- Bookings Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>📋 My Bookings</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Tractor</th>
                    <th>Dates</th>
                    <th>Time Left</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding: 2rem;">No bookings found. <a href="<?php echo $base_url; ?>/customer/bookings.php?action=new">Create your first booking!</a></td></tr>
                <?php else: ?>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong>#<?php echo $b['booking_id']; ?></strong></td>
                            <td>
                                <div><?php echo htmlspecialchars($b['tractor_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($b['brand'] . ' ' . $b['model']); ?></div>
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
                                    <?php if ($b['status'] === 'pending' || $b['status'] === 'confirmed'): ?>
                                        <a href="<?php echo $base_url; ?>/customer/payments.php?booking_id=<?php echo $b['booking_id']; ?>" class="btn btn-sm btn-primary" title="Pay Now">💳</a>
                                    <?php endif; ?>
                                    <?php if ($b['status'] === 'pending' || $b['status'] === 'confirmed'): ?>
                                        <a href="?cancel=<?php echo $b['booking_id']; ?>" class="btn btn-sm btn-danger" title="Cancel" onclick="return confirm('Cancel this booking?')">✕</a>
                                    <?php endif; ?>
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