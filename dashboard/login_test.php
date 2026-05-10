<?php
// Auto-login as admin and test tractor delete
session_start();

// Show current session state
echo "<h2>Session State</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session data: <pre>" . print_r($_SESSION, true) . "</pre>";

if (!isset($_SESSION['user_id'])) {
    echo "<p>Not logged in. Attempting auto-login as admin...</p>";
    require_once __DIR__ . '/config/database.php';
    
    // Check for admin user
    $result = mysqli_query($conn, "SELECT user_id, username, email, full_name, role, password FROM users WHERE role = 'admin' LIMIT 1");
    if ($user = mysqli_fetch_assoc($result)) {
        echo "Found admin: {$user['username']} ({$user['email']})<br>";
        // For testing only: bypass password check
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        echo "Session set. <a href='?test_delete=1'>Click to test delete</a>";
    } else {
        echo "No admin user found!";
    }
} else {
    echo "<p>Already logged in as {$_SESSION['full_name']} (Role: {$_SESSION['role']})</p>";
    echo "<a href='?test_delete=1'>Test Delete Tractor ID 3</a> | <a href='?logout=1'>Logout</a>";
}

if (isset($_GET['logout'])) {
    session_destroy();
    echo "<p>Logged out. <a href=''>Refresh</a></p>";
}

if (isset($_GET['test_delete'])) {
    echo "<hr><h2>Testing Delete</h2>";
    require_once __DIR__ . '/includes/functions.php';
    generateCsrfToken(); // Ensure token exists
    $csrf = $_SESSION['csrf_token'];
    echo "CSRF Token: $csrf<br>";
    
    // Simulate POST
    $_POST['action'] = 'delete';
    $_POST['tractor_id'] = 3;
    $_POST['csrf_token'] = $csrf;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Capture output
    ob_start();
    include __DIR__ . '/tractors.php';
    $output = ob_get_clean();
    
    // Check if tractor still exists
    $res = mysqli_query($conn, "SELECT * FROM tractors WHERE tractor_id = 3");
    $exists = mysqli_num_rows($res) > 0;
    echo "Tractor 3 exists after simulated delete: " . ($exists ? 'YES' : 'NO') . "<br>";
    
    // Show message from tractors.php
    echo "<h3>Result message:</h3><pre>" . htmlspecialchars($GLOBALS['message'] ?? 'No message set') . "</pre>";
    
    // Show bookings
    $b = mysqli_query($conn, "SELECT COUNT(*) as c FROM bookings WHERE tractor_id = 3");
    $br = mysqli_fetch_assoc($b);
    echo "Bookings remaining for tractor 3: {$br['c']}<br>";
    
    // Show all tractors
    echo "<h3>All Tractors</h3><ul>";
    $all = mysqli_query($conn, "SELECT tractor_id, tractor_name FROM tractors ORDER BY tractor_id");
    while ($t = mysqli_fetch_assoc($all)) {
        echo "<li>{$t['tractor_id']}: {$t['tractor_name']}</li>";
    }
    echo "</ul>";
}
?>