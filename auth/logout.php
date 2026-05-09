<?php
/**
 * Logout Handler
 * Securely destroys the user session and redirects to login.
 * Prevents session fixation by clearing all session data.
 */

require_once __DIR__ . '/../includes/functions.php';

// Log the logout activity before destroying session
if (isset($_SESSION['username'])) {
    logActivity("User logged out");
}

// Unset all session variables
$_SESSION = [];

// Delete the session cookie (if it exists)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a new session for flash message
session_start();
$_SESSION['logout_message'] = 'You have been logged out successfully.';

// Redirect to login page
header("Location: " . getBaseUrl() . "/auth/login.php");
exit();
?>