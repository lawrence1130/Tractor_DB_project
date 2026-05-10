<?php
require_once __DIR__ . '/../includes/header.php';

// Role check
if ($_SESSION['role'] !== 'customer') {
    header('Location: ' . getBaseUrl() . '/dashboard/index.php');
    exit();
}

$base_url = getBaseUrl();
$customer_id = $_SESSION['customer_id'] ?? 0;

// Get all available tractors
$tractors = [];
$tractor_sql = "SELECT t.*, c.category_name 
                FROM tractors t 
                LEFT JOIN categories c ON t.category_id = c.category_id 
                WHERE t.status = 'available' 
                ORDER BY t.tractor_name";
$tractor_result = mysqli_query($conn, $tractor_sql);
while ($row = mysqli_fetch_assoc($tractor_result)) {
    $tractors[] = $row;
}

// Get image URL helper
function getTractorImage($tractor) {
    if (!empty($tractor['image_path']) && file_exists(__DIR__ . '/../' . $tractor['image_path'])) {
        return getBaseUrl() . '/' . $tractor['image_path'];
    }
    return '';
}
?>
<div class="main-content">
    <?php echo breadcrumb(['Customer Portal' => '/customer/index.php', 'Browse Tractors' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">🚜</span> Available Tractors</h1>
        <a href="<?php echo $base_url; ?>/customer/bookings.php?action=new" class="btn btn-primary">+ New Booking</a>
    </div>

    <p class="text-secondary mb-4">Browse our available tractor fleet. Click "Book Now" to reserve your equipment.</p>

    <div class="tractor-grid">
        <?php foreach ($tractors as $tractor): ?>
            <div class="tractor-card">
                <div class="tractor-card-image">
                    <?php $img = getTractorImage($tractor); ?>
                    <?php if ($img): ?>
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($tractor['tractor_name']); ?>">
                    <?php else: ?>
                        <div class="no-image">🚜</div>
                    <?php endif; ?>
                    <span class="badge badge-available">Available</span>
                </div>
                <div class="tractor-card-body">
                    <div class="tractor-category"><?php echo htmlspecialchars($tractor['category_name'] ?? 'Uncategorized'); ?></div>
                    <h3><?php echo htmlspecialchars($tractor['tractor_name']); ?></h3>
                    <div class="tractor-brand"><?php echo htmlspecialchars($tractor['brand'] . ' ' . $tractor['model']); ?></div>
                    
                    <div class="tractor-specs">
                        <div class="spec">
                            <div class="spec-value"><?php echo $tractor['horsepower'] ?: 'N/A'; ?></div>
                            <div class="spec-label">HP</div>
                        </div>
                        <div class="spec">
                            <div class="spec-value"><?php echo $tractor['year_manufactured'] ?: 'N/A'; ?></div>
                            <div class="spec-label">Year</div>
                        </div>
                        <div class="spec">
                            <div class="spec-value">$<?php echo number_format($tractor['hourly_rate'], 2); ?></div>
                            <div class="spec-label">Hourly</div>
                        </div>
                        <div class="spec">
                            <div class="spec-value">$<?php echo number_format($tractor['daily_rate'], 2); ?></div>
                            <div class="spec-label">Daily</div>
                    </div>

                    <div class="tractor-card-body">
                        <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 1rem;">
                            <?php echo !empty($tractor['description']) ? htmlspecialchars(substr($tractor['description'], 0, 80)) . '...' : 'No description available.'; ?>
                        </p>
                    </div>
                </div>
                </div>
                <div class="tractor-card-footer">
                    <div class="price">
                        <small>From</small> $<?php echo number_format($tractor['daily_rate'], 2); ?><small>/day</small>
                    </div>
                    <div class="card-actions">
                        <a href="<?php echo $base_url; ?>/customer/bookings.php?action=new&tractor_id=<?php echo $tractor['tractor_id']; ?>" class="btn btn-sm btn-primary">Book Now</a>
                        <a href="<?php echo $base_url; ?>/customer/tractors.php?view=<?php echo $tractor['tractor_id']; ?>" class="btn btn-sm btn-secondary">Details</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($tractors)): ?>
            <tr><td colspan="8" class="text-center text-muted" style="padding: 2rem;">No tractors available at the moment. Please check back later.</td></tr>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>