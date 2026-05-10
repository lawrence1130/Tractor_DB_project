<?php
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

// Show current state
echo "<h2>Session & CSRF Debug</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Logged In: " . (isLoggedIn() ? 'YES (User: ' . $_SESSION['user_id'] . ', Role: ' . $_SESSION['role'] . ')' : 'NO') . "<br>";
echo "CSRF Token: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "<br>";
echo "Token Length: " . (isset($_SESSION['csrf_token']) ? strlen($_SESSION['csrf_token']) : 0) . "<br>";
echo "Token Hash (first 10): " . (isset($_SESSION['csrf_token']) ? substr(hash('sha256', $_SESSION['csrf_token']), 0, 10) : 'N/A') . "<br>";

echo "<hr><h3>Tractors in Database</h3>";
$res = mysqli_query($conn, "SELECT tractor_id, tractor_name, status, image_path FROM tractors ORDER BY tractor_id");
echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Status</th><th>Image</th><th>Delete</th></tr>";
while ($row = mysqli_fetch_assoc($res)) {
    $img = $row['image_path'];
    $exists = file_exists(__DIR__ . '/../' . $img) ? 'EXISTS' : 'MISSING';
    echo "<tr>
        <td>{$row['tractor_id']}</td>
        <td>{$row['tractor_name']}</td>
        <td>{$row['status']}</td>
        <td>$img <small>($exists)</small></td>
        <td>
            <form method='POST' style='display:inline;' onsubmit=\"return confirm('Delete?');\">
                <input type='hidden' name='action' value='delete'>
                <input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token'] ?? '') . "'>
                <input type='hidden' name='tractor_id' value='{$row['tractor_id']}'>
                <button type='submit'>Delete</button>
            </form>
        </td>
    </tr>";
}
echo "</table>";

// Show recent activity log
echo "<hr><h3>Recent Activity</h3>";
$log_res = mysqli_query($conn, "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 5");
while ($log = mysqli_fetch_assoc($log_res)) {
    $details = json_decode($log['details'], true);
    echo "#{$log['log_id']} - {$log['action']} {$log['entity_type']} ID:{$log['entity_id']} by User: {$log['user_id']} at {$log['created_at']}<br>";
    if ($details && isset($details['name'])) echo "&nbsp;&nbsp;Name: {$details['name']}<br>";
}

// Handle delete and show result
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    echo "<hr><h3>Delete Test Result</h3>";
    $id = intval($_POST['tractor_id']);
    echo "Attempting to delete tractor ID: $id<br>";
    
    // Check CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo "❌ CSRF FAIL<br>";
        echo "Session token: " . ($_SESSION['csrf_token'] ?? 'MISSING') . "<br>";
        echo "POST token: " . ($_POST['csrf_token'] ?? 'MISSING') . "<br>";
    } else {
        echo "✅ CSRF OK<br>";
        
        // Count bookings before
        $before = mysqli_query($conn, "SELECT COUNT(*) as c FROM bookings WHERE tractor_id = $id");
        $b = mysqli_fetch_assoc($before);
        echo "Bookings before delete: {$b['c']}<br>";
        
        // Delete
        $stmt = mysqli_prepare($conn, "DELETE FROM tractors WHERE tractor_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $exec = mysqli_stmt_execute($stmt);
        echo "Execute: " . ($exec ? 'TRUE' : 'FALSE') . "<br>";
        if (!$exec) echo "MySQL Error: " . mysqli_error($conn) . "<br>";
        
        $affected = mysqli_stmt_affected_rows($stmt);
        echo "Affected rows: $affected<br>";
        mysqli_stmt_close($stmt);
        
        // Count bookings after
        $after = mysqli_query($conn, "SELECT COUNT(*) as c FROM bookings WHERE tractor_id = $id");
        $a = mysqli_fetch_assoc($after);
        echo "Bookings after delete: {$a['c']}<br>";
        
        echo $affected > 0 ? "✅ SUCCESS" : "❌ FAILED - No rows affected";
    }
}

echo "<hr><a href='tractors.php'>Back to Tractors</a>";
?>