<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . getBaseUrl() . '/dashboard/index.php');
    exit();
}

$error = '';
$success = '';

if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'CSRF token validation failed. Please refresh the page.';
    } else {
        $email = sanitizeForDB($conn, $_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            $sql = "SELECT user_id, username, email, full_name, role, password FROM users WHERE email = ? AND is_active = 1";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($user = mysqli_fetch_assoc($result)) {
                if (password_verify($password, $user['password'])) {
                    if (in_array($user['role'], ['staff', 'admin'])) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];

                        header('Location: ' . getBaseUrl() . '/dashboard/index.php');
                        exit();
                    } else {
                        $error = 'Access denied. This login is for staff and admin only.';
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
            mysqli_stmt_close($stmt);
        }
    }
}

$base_url = getBaseUrl();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - TRACKTOR</title>
    <link rel="stylesheet" href="/tracktor/css/style.css?v=2">
    <link rel="icon" type="image/png" href="/tracktor/logo.png">
</head>


<body>
     <div class="auth-wrapper">
        <div class="auth-bg"></div>
        <div class="auth-overlay"></div>
        <div class="auth-card">
            <div class="auth-logo">
                <img src="/tracktor/logo.png" alt="TRACKTOR" class="auth-logo-img">
                <h2>TRACKTOR</h2>
                <p>Staff & Admin Login</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfInput(); ?>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="Enter your email" required autofocus
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    Login
                </button>
            </form>

            <div class="auth-footer">
                <a href="<?php echo $base_url; ?>" class="back-link">← Back to Home</a>
                <span style="margin: 0 0.75rem; color: var(--dark-border);">|</span>
                Need an account? <a href="<?php echo $base_url; ?>/auth/register-staff.php">Contact admin</a>
        </div>
        </div>
    </div>
    
</body>
</html>