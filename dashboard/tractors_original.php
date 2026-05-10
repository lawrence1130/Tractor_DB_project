<?php
require_once __DIR__ . '/../includes/header.php';

$base_url = getBaseUrl();
$message = '';
$message_type = '';

// Ensure upload directory exists
ensureUploadDir();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'CSRF token validation failed. Please refresh the page.';
        $message_type = 'danger';
} else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $tractor_name = sanitizeForDB($conn, $_POST['tractor_name'] ?? '');
            $brand = sanitizeForDB($conn, $_POST['brand'] ?? '');
            $model = sanitizeForDB($conn, $_POST['model'] ?? '');
            $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : 'NULL';
            $year_manufactured = isset($_POST['year_manufactured']) && $_POST['year_manufactured'] !== '' ? intval($_POST['year_manufactured']) : 'NULL';
            $horsepower = isset($_POST['horsepower']) && $_POST['horsepower'] !== '' ? intval($_POST['horsepower']) : 'NULL';
            $hourly_rate = floatval($_POST['hourly_rate'] ?? 0);
            $daily_rate = floatval($_POST['daily_rate'] ?? 0);
            $status = sanitizeForDB($conn, $_POST['status'] ?? 'available');
            $description = sanitizeForDB($conn, $_POST['description'] ?? '');
            $image_url = sanitizeForDB($conn, $_POST['image_url'] ?? '');
            $image_path = '';

            // Handle image upload
            if (isset($_FILES['tractor_image']) && $_FILES['tractor_image']['error'] === UPLOAD_ERR_OK) {
                $existing = '';
                if ($action === 'update' && !empty($_POST['existing_image'])) {
                    $existing = $_POST['existing_image'];
                }
                $upload = uploadTractorImage($_FILES['tractor_image'], $existing);
                if ($upload['success']) {
                    $image_path = sanitizeForDB($conn, $upload['path']);
                } else {
                    $message = $upload['message'];
                    $message_type = 'danger';
                }
            } elseif ($action === 'update') {
                $image_path = sanitizeForDB($conn, $_POST['existing_image'] ?? '');
            }

            if (empty($message)) {
                if ($action === 'create') {
                    $stmt = mysqli_prepare($conn, "INSERT INTO tractors (category_id, tractor_name, brand, model, year_manufactured, horsepower, hourly_rate, daily_rate, status, image_url, image_path, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt, 'issssiiddsss', $category_id, $tractor_name, $brand, $model, $year_manufactured, $horsepower, $hourly_rate, $daily_rate, $status, $image_url, $image_path, $description);
                    if (mysqli_stmt_execute($stmt)) {
                        $tractor_id = mysqli_insert_id($conn);
                        $message = 'Tractor added successfully!';
                        $message_type = 'success';
                        // Log activity
                        logActivity('create', 'tractor', $tractor_id, ['name' => $tractor_name, 'brand' => $brand, 'status' => $status]);
                    } else {
                        $message = 'Error adding tractor: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                } else {
                    $id = intval($_POST['tractor_id']);
                    $img_update = $image_path ? ", image_path = '$image_path'" : '';
                    $stmt = mysqli_prepare($conn, "UPDATE tractors SET category_id = ?, tractor_name = ?, brand = ?, model = ?, year_manufactured = ?, horsepower = ?, hourly_rate = ?, daily_rate = ?, status = ?, image_url = ?$img_update, description = ? WHERE tractor_id = ?");
                    mysqli_stmt_bind_param($stmt, 'isssiiidsssi', $category_id, $tractor_name, $brand, $model, $year_manufactured, $horsepower, $hourly_rate, $daily_rate, $status, $image_url, $description, $id);
                    if (mysqli_stmt_execute($stmt)) {
                        $message = 'Tractor updated successfully!';
                        $message_type = 'success';
                        // Log activity
                        logActivity('update', 'tractor', $id, ['name' => $tractor_name, 'brand' => $brand, 'status' => $status]);
                    } else {
                        $message = 'Error updating tractor: ' . mysqli_error($conn);
                        $message_type = 'danger';
                    }
                    mysqli_stmt_close($stmt);
                }
            } elseif ($action === 'delete') {
                $id = intval($_POST['tractor_id']);
                // Get tractor name for log before deleting
                $tractor_name = '';
                $name_res = mysqli_query($conn, "SELECT tractor_name FROM tractors WHERE tractor_id = $id");
                if ($name_row = mysqli_fetch_assoc($name_res)) {
                    $tractor_name = $name_row['tractor_name'];
                }
                
                // Get image path to delete file
                $res = mysqli_query($conn, "SELECT image_path FROM tractors WHERE tractor_id = $id");
                $row = mysqli_fetch_assoc($res);
                if ($row && !empty($row['image_path'])) {
                    $old_file = __DIR__ . '/../' . $row['image_path'];
                    if (file_exists($old_file) && strpos($row['image_path'], 'uploads/') === 0) {
                        unlink($old_file);
                    }
                }
                $stmt = mysqli_prepare($conn, "DELETE FROM tractors WHERE tractor_id = ?");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Tractor deleted successfully!';
                    $message_type = 'success';
                    // Log activity
                    logActivity('delete', 'tractor', $id, ['name' => $tractor_name]);
                } else {
                    $message = 'Error deleting tractor: ' . mysqli_error($conn);
                    $message_type = 'danger';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Get categories for dropdown
$categories = [];
$cat_result = mysqli_query($conn, "SELECT * FROM categories ORDER BY category_name");
while ($cat = mysqli_fetch_assoc($cat_result)) {
    $categories[] = $cat;
}

// Build search query
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';

$tractors = [];
$sql = "SELECT t.*, c.category_name FROM tractors t LEFT JOIN categories c ON t.category_id = c.category_id WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $sql .= " AND (t.tractor_name LIKE ? OR t.brand LIKE ? OR t.model LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

if (!empty($category_filter)) {
    $sql .= " AND t.category_id = ?";
    $params[] = $category_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $sql .= " AND t.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql .= " ORDER BY t.tractor_name";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($result)) {
    $tractors[] = $row;
}
mysqli_stmt_close($stmt);

// Check if editing
$edit_tractor = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $edit_res = mysqli_query($conn, "SELECT * FROM tractors WHERE tractor_id = $edit_id");
    $edit_tractor = mysqli_fetch_assoc($edit_res);
}

$show_form = isset($_GET['action']) && in_array($_GET['action'], ['new', 'edit']);
?>

<div class="main-content dashboard-main">
    <?php echo breadcrumb(['Tractors' => '']); ?>

    <div class="page-header">
        <h1><span class="icon">≡ƒÜ£</span> Tractors</h1>
        <div class="d-flex gap-2">
            <?php if ($show_form): ?>
                <a href="<?php echo $base_url; ?>/dashboard/tractors.php" class="btn btn-secondary">ΓåÉ Back to List</a>
            <?php else: ?>
                <a href="<?php echo $base_url; ?>/dashboard/tractors.php?action=new" class="btn btn-primary">+ Add Tractor</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <?php echo showAlert($message, $message_type); ?>
    <?php endif; ?>

    <!-- Search/Filter Bar -->
    <?php if (!$show_form): ?>
    <div class="filter-bar" style="margin-bottom: 1.5rem; padding: 1rem; background: var(--dark-card); border-radius: var(--radius-md); border: 1px solid var(--dark-border);">
        <form method="GET" action="" class="filter-form" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" class="form-control" 
                       placeholder="Search by name, brand, model..."
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            </div>
            <div class="form-group" style="margin-bottom: 0; width: 200px;">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" 
                                <?php echo (($_GET['category'] ?? '') == $cat['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 0; width: 150px;">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="available" <?php echo (($_GET['status'] ?? '') === 'available') ? 'selected' : ''; ?>>Available</option>
                    <option value="booked" <?php echo (($_GET['status'] ?? '') === 'booked') ? 'selected' : ''; ?>>Booked</option>
                    <option value="maintenance" <?php echo (($_GET['status'] ?? '') === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="retired" <?php echo (($_GET['status'] ?? '') === 'retired') ? 'selected' : ''; ?>>Retired</option>
                </select>
            </div>
            <div class="d-flex gap-2" style="margin-bottom: 0;">
                <button type="submit" class="btn btn-primary">≡ƒöì Search</button>
                <?php if (!empty($_GET['search']) || !empty($_GET['category']) || !empty($_GET['status'])): ?>
                    <a href="?action=new" class="btn btn-secondary">Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($show_form): ?>
    <!-- Add/Edit Form -->
    <div class="form-container animate-fade-in" style="max-width: 700px;">
        <h2 style="margin-bottom: 1.5rem; font-size: 1.3rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
            <?php echo $edit_tractor ? 'Γ£Å∩╕Å Edit Tractor' : 'Γ₧ò Add New Tractor'; ?>
        </h2>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="<?php echo $edit_tractor ? 'update' : 'create'; ?>">
            <?php echo csrfInput(); ?>
            <?php if ($edit_tractor): ?>
                <input type="hidden" name="tractor_id" value="<?php echo $edit_tractor['tractor_id']; ?>">
                <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_tractor['image_path'] ?? ''); ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="tractor_name">Tractor Name *</label>
                    <input type="text" id="tractor_name" name="tractor_name" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_tractor['tractor_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-control">
                        <option value="">-- Select --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>" 
                                    <?php echo ($edit_tractor['category_id'] ?? '') == $cat['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="brand">Brand *</label>
                    <input type="text" id="brand" name="brand" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_tractor['brand'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="model">Model *</label>
                    <input type="text" id="model" name="model" class="form-control" required
                           value="<?php echo htmlspecialchars($edit_tractor['model'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="year_manufactured">Year</label>
                    <input type="number" id="year_manufactured" name="year_manufactured" class="form-control" 
                           min="1990" max="2030"
                           value="<?php echo htmlspecialchars($edit_tractor['year_manufactured'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="horsepower">Horsepower</label>
                    <input type="number" id="horsepower" name="horsepower" class="form-control" min="1"
                           value="<?php echo htmlspecialchars($edit_tractor['horsepower'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="hourly_rate">Hourly Rate (Γé▒)</label>
                    <input type="number" id="hourly_rate" name="hourly_rate" class="form-control" 
                           step="0.01" min="0"
                           value="<?php echo htmlspecialchars($edit_tractor['hourly_rate'] ?? '0'); ?>">
                </div>
                <div class="form-group">
                    <label for="daily_rate">Daily Rate (Γé▒)</label>
                    <input type="number" id="daily_rate" name="daily_rate" class="form-control" 
                           step="0.01" min="0"
                           value="<?php echo htmlspecialchars($edit_tractor['daily_rate'] ?? '0'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="available" <?php echo ($edit_tractor['status'] ?? 'available') === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="booked" <?php echo ($edit_tractor['status'] ?? '') === 'booked' ? 'selected' : ''; ?>>Booked</option>
                    <option value="maintenance" <?php echo ($edit_tractor['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="retired" <?php echo ($edit_tractor['status'] ?? '') === 'retired' ? 'selected' : ''; ?>>Retired</option>
                </select>
            </div>

            <!-- Image Upload -->
            <div class="form-group">
                <label>Tractor Image</label>
                <div class="file-upload" id="dropZone">
                    <input type="file" name="tractor_image" accept="image/*" id="imageInput">
                    <div class="upload-icon">≡ƒô╖</div>
                    <div class="upload-text">Click or drag to upload an image</div>
                    <div class="upload-hint">JPG, PNG, GIF, WebP ΓÇó Max 5MB</div>
                </div>
                <?php if ($edit_tractor && !empty($edit_tractor['image_path'])): ?>
                    <div class="image-preview mt-2" id="existingImagePreview">
                        <img src="<?php echo getTractorImageUrl($edit_tractor['image_path']); ?>?t=<?php echo time(); ?>" alt="Current image">
                        <small class="text-muted" style="display:block; margin-top:4px;">Current image ΓÇö upload a new one to replace it</small>
                    </div>
                <?php endif; ?>
                <div id="imagePreview" class="image-preview mt-2" style="display:none;"></div>
            </div>

            <div class="form-group">
                <label for="image_url">Or Image URL</label>
                <input type="url" id="image_url" name="image_url" class="form-control" 
                       placeholder="https://example.com/tractor.jpg"
                       value="<?php echo htmlspecialchars($edit_tractor['image_url'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"
                          placeholder="Brief description of the tractor..."><?php echo htmlspecialchars($edit_tractor['description'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_tractor ? '≡ƒÆ╛ Update Tractor' : 'Γ₧ò Add Tractor'; ?>
                </button>
                <a href="<?php echo $base_url; ?>/dashboard/tractors.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <!-- Tractor Cards Grid -->
    <div class="tractor-grid">
        <?php if (empty($tractors)): ?>
            <div class="text-center text-muted" style="grid-column: 1/-1; padding: 4rem;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">≡ƒÜ£</div>
                <p>No tractors found. Add your first tractor to get started!</p>
                <a href="<?php echo $base_url; ?>/dashboard/tractors.php?action=new" class="btn btn-primary mt-2">+ Add Tractor</a>
            </div>
        <?php else: ?>
            <?php foreach ($tractors as $tractor): ?>
                <?php 
                    $img_src = getTractorImageUrl($tractor['image_path'] ?? '', $tractor['image_url'] ?? '');
                ?>
                <div class="tractor-card">
                    <div class="tractor-card-image">
                        <?php if ($img_src): ?>
                            <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($tractor['tractor_name']); ?>" 
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="no-image" style="display:none;">≡ƒÜ£</div>
                        <?php else: ?>
                            <div class="no-image">≡ƒÜ£</div>
                        <?php endif; ?>
                        <div class="status-badge badge-<?php echo $tractor['status']; ?>">
                            <?php echo ucfirst($tractor['status']); ?>
                        </div>
                    </div>
                    <div class="tractor-card-body">
                        <div class="tractor-category"><?php echo htmlspecialchars($tractor['category_name'] ?? 'Uncategorized'); ?></div>
                        <h3><?php echo htmlspecialchars($tractor['tractor_name']); ?></h3>
                        <div class="tractor-brand"><?php echo htmlspecialchars($tractor['brand'] . ' ' . $tractor['model']); ?></div>
                        <div class="tractor-specs">
                            <div class="spec">
                                <div class="spec-value"><?php echo $tractor['horsepower'] ?? 'ΓÇö'; ?></div>
                                <div class="spec-label">HP</div>
                            </div>
                            <div class="spec">
                                <div class="spec-value"><?php echo $tractor['year_manufactured'] ?? 'ΓÇö'; ?></div>
                                <div class="spec-label">Year</div>
                            </div>
                        </div>
                    </div>
                    <div class="tractor-card-footer">
                        <div class="price">
                            <?php echo formatCurrency($tractor['daily_rate']); ?><small>/day</small>
                        </div>
                        <div class="card-actions">
                            <a href="<?php echo $base_url; ?>/dashboard/tractors.php?action=edit&id=<?php echo $tractor['tractor_id']; ?>" 
                               class="btn btn-sm btn-secondary" title="Edit">Γ£Å∩╕Å</a>
                             <form method="POST" action="" style="display:inline;" 
                                   onsubmit="return confirm('Delete this tractor?');">
                                 <input type="hidden" name="action" value="delete">
                                 <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                 <input type="hidden" name="tractor_id" value="<?php echo $tractor['tractor_id']; ?>">
                                 <button type="submit" class="btn btn-sm btn-danger" title="Delete">≡ƒùæ∩╕Å</button>
                             </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
// Image preview on upload
document.getElementById('imageInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('imagePreview');
    const existingPreview = document.getElementById('existingImagePreview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) {
            preview.innerHTML = '<img src="' + ev.target.result + '" alt="Preview">';
            preview.style.display = 'block';
            // Hide existing image preview when new file selected
            if (existingPreview) {
                existingPreview.style.display = 'none';
            }
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
        if (existingPreview) {
            existingPreview.style.display = 'block';
            }
        }
    }
);
?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>