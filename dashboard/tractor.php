<?php
session_start();
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';

echo "<h2>Debug Test</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "CSRF Token in session: " . ($_SESSION['csrf_token'] ?? 'NOT SET') . "<br>";
echo "Logged in: " . (isLoggedIn() ? 'YES' : 'NO') . "<br>";
if (isLoggedIn()) {
    echo "User ID: " . $_SESSION['user_id'] . "<br>";
    echo "Role: " . $_SESSION['role'] . "<br>";
}

echo "<hr><h3>Test Delete (tractor 3 - has booking)</h3>";
echo "<form method='POST' action=''>";
echo "<input type='hidden' name='action' value='delete'>";
echo "<input type='hidden' name='csrf_token' value='" . ($_SESSION['csrf_token'] ?? '') . "'>";
echo "<input type='hidden' name='tractor_id' value='3'>";
echo "<button type='submit'>Test Delete Tractor 3</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    echo "<hr><h3>Processing Delete...</h3>";
    echo "POST data: " . print_r($_POST, true) . "<br>";
    
    $id = intval($_POST['tractor_id']);
    echo "Tractor ID: $id<br>";
    
    // Check if tractor exists
    $check = mysqli_query($conn, "SELECT * FROM tractors WHERE tractor_id = $id");
    echo "Tractor exists: " . (mysqli_num_rows($check) > 0 ? 'YES' : 'NO') . "<br>";
    
    // Check CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo "CSRF FAILED<br>";
        echo "Session token: " . ($_SESSION['csrf_token'] ?? 'NONE') . "<br>";
        echo "POST token: " . ($_POST['csrf_token'] ?? 'NONE') . "<br>";
    } else {
        echo "CSRF OK<br>";
        
        // Count related bookings before
        $before = mysqli_query($conn, "SELECT COUNT(*) as c FROM bookings WHERE tractor_id = $id");
        $before_row = mysqli_fetch_assoc($before);
        echo "Bookings before: " . $before_row['c'] . "<br>";
        
        // Try delete
        $stmt = mysqli_prepare($conn, "DELETE FROM tractors WHERE tractor_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $exec = mysqli_stmt_execute($stmt);
        echo "Execute result: " . ($exec ? 'TRUE' : 'FALSE') . "<br>";
        if (!$exec) {
            echo "Error: " . mysqli_error($conn) . "<br>";
        }
        echo "Affected rows: " . mysqli_stmt_affected_rows($stmt) . "<br>";
        mysqli_stmt_close($stmt);
        
        // Count related bookings after
        $after = mysqli_query($conn, "SELECT COUNT(*) as c FROM bookings WHERE tractor_id = $id");
        $after_row = mysqli_fetch_assoc($after);
        echo "Bookings after: " . $after_row['c'] . "<br>";
    }
}

echo "<hr><a href='tractors.php'>Back to Tractors</a>";