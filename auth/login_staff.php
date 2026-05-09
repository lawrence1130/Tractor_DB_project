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
<style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--dark-bg) 0%, #1a1f2e 100%);
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
            background-image: url('/tracktor/assets/image/2026-05-05/n6gqpeyaafmq/tractor-rowcrop-field.png');
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
            padding: 0.875rem 1rem;
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

<body>
    
</body>
</html>