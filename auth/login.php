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
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];

                $redirect = ($user['role'] === 'customer') ? '/customer/index.php' : '/dashboard/index.php';
                header('Location: ' . getBaseUrl() . $redirect);
                exit();
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        mysqli_stmt_close($stmt);
    }
}

$base_url = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TRACKTOR</title>
    <link rel="stylesheet" href="/tracktor/css/style.css?v=2">
    <link rel="icon" type="image/png" href="/tracktor/logo.png">
    <style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }

        .auth-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('/tracktor/assets/image/2026-05-05/n6gqroyaafla/tractor-compact-farm.png');
            background-size: cover;
            background-position: center;
            z-index: 0;
        }

        .auth-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(22, 27, 34, 0.85) 0%, rgba(28, 35, 51, 0.8) 100%);
            z-index: 1;
        }

        .auth-card {
            position: relative;
            z-index: 2;
            background: rgba(28, 35, 51, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid var(--dark-border);
            border-radius: 20px;
            padding: 3rem;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5),
                        0 0 0 1px rgba(255, 255, 255, 0.05) inset;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .auth-logo-img {
            width: 200px;
            height: auto;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
        }

        .auth-logo h2 {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 3px;
            color: var(--primary);
            margin: 0 0 0.5rem 0;
            text-shadow: 0 2px 10px rgba(212, 46, 18, 0.3);
        }

        .auth-logo p {
            color: var(--text-muted);
            font-size: 0.95rem;
            margin: 0;
            font-weight: 500;
            letter-spacing: 1px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            background: rgba(22, 27, 34, 0.8);
            border: 2px solid var(--dark-border);
            border-radius: 10px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(212, 46, 18, 0.15),
                       0 4px 12px rgba(0, 0, 0, 0.2);
            background: rgba(22, 27, 34, 1);
        }

        .form-control::placeholder {
            color: var(--text-muted);
            opacity: 0.6;
        }

        .input-icon {
            position: relative;
        }

        .input-icon::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            opacity: 0.5;
        }

        .input-icon.email::before {
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z'/%3E%3Cpolyline points='22,6 12,13 2,6'/%3E%3C/svg%3E") center/contain no-repeat;
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Cpath d='M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z'/%3E%3Cpolyline points='22,6 12,13 2,6'/%3E%3C/svg%3E") center/contain no-repeat;
            background-color: var(--text-muted);
        }

        .input-icon.password::before {
            mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='11' width='18' height='11' rx='2' ry='2'/%3E%3Cpath d='M7 11V7a5 5 0 0 1 10 0v4'/%3E%3C/svg%3E") center/contain no-repeat;
            -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'%3E%3Crect x='3' y='11' width='18' height='11' rx='2' ry='2'/%3E%3Cpath d='M7 11V7a5 5 0 0 1 10 0v4'/%3E%3C/svg%3E") center/contain no-repeat;
            background-color: var(--text-muted);
        }

        .auth-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--dark-border);
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .auth-footer .back-link {
            color: var(--text-secondary);
            text-decoration: none;
            margin-right: 0.75rem;
            transition: color 0.2s;
        }

        .auth-footer .back-link:hover {
            color: var(--primary);
        }

        .auth-footer a[href*="register"] {
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .auth-footer a[href*="register"]:hover {
            color: var(--primary);
        }

        .btn-primary {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 46, 18, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .alert {
            padding: 0.875rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid transparent;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-danger {
            background: rgba(248, 81, 73, 0.1);
            border-color: rgba(248, 81, 73, 0.3);
            color: var(--danger);
        }

        .alert-success {
            background: rgba(63, 185, 80, 0.1);
            border-color: rgba(63, 185, 80, 0.3);
            color: var(--success);
        }

        @media (max-width: 480px) {
            .auth-card {
                padding: 2rem 1.5rem;
            }

            .auth-logo h2 {
                font-size: 1.6rem;
            }

            .auth-logo-img {
                width: 160px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-bg"></div>
        <div class="auth-overlay"></div>
        <div class="auth-card">
            <div class="auth-logo">
                <img src="/tracktor/logo.png" alt="TRACKTOR" class="auth-logo-img">
                <h2>TRACKTOR</h2>
                <p>Welcome Back</p>
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
                    <div class="input-icon email">
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="Enter your email" required autofocus
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-icon password">
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    Login
                </button>
            </form>

            <div class="auth-footer">
                <a href="<?php echo $base_url; ?>" class="back-link">← Back to Home</a>
                <span style="margin: 0 0.75rem; color: var(--dark-border);">|</span>
                Don't have an account? <a href="<?php echo $base_url; ?>/auth/register.php">Sign up</a>
            </div>
        </div>
    </div>
</body>
</html>
