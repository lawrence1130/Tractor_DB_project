<?php
session_start();
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: ' . getBaseUrl() . '/dashboard/index.php');
    exit();
}
$base_url = getBaseUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TRACKTOR - Professional Tractor Booking & Fleet Management System</title>
    <link rel="stylesheet" href="/tracktor/css/style.css">
    <link rel="icon" type="image/png" href="/tracktor/logo.png">
</head>
<body>
<!-- Navigation -->
<nav class="navbar">
    <div class="nav-container">
        <a href="/tracktor/" class="nav-brand">
            <img src="/tracktor/logo.png" alt="TRACKTOR" class="brand-logo-img">
            TRACKTOR
        </a>
        <div class="nav-cta">
            <a href="/tracktor/auth/login-staff.php" class="btn btn-outline btn-sm">Staff/Admin Login</a>
            <a href="/tracktor/auth/login.php" class="btn btn-primary btn-sm">Sign In</a>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<section class="hero hero-home">
    <div class="hero-bg" style="background-image: url('https://mgx-backend-cdn.metadl.com/generate/images/1191191/2026-05-05/n6gqqwaaafna/hero-banner-tractor-field.png');"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-badge animate-fade-in">🚜 Professional Fleet Management</div>
        <h1 class="animate-fade-in">Power Your <span class="highlight">Farm</span> Forward</h1>
        <p class="animate-fade-in">Book the right tractor for every job. Manage your fleet, track bookings, and maximize productivity with our professional tractor rental system.</p>
        
        <div class="hero-buttons hero-buttons-center animate-slide-up">
            <a href="/tracktor/auth/register.php" class="btn btn-primary btn-lg btn-hover">
                <span>Register now</span>
                <span>→</span>
            </a>
            <a href="/tracktor/auth/login.php" class="btn btn-outline btn-lg btn-hover">
                <span>Sign In</span>
                <span>→</span>
            </a>
        </div>
        
        <div class="hero-features animate-fade-in">
            <div class="hero-feature">
                <span class="hero-feature-icon">✓</span>
                <span>Easy Booking</span>
            </div>
            <div class="hero-feature">
                <span class="hero-feature-icon">✓</span>
                <span>Real-time Tracking</span>
            </div>
            <div class="hero-feature">
                <span class="hero-feature-icon">✓</span>
                <span>Secure Payments</span>
            </div>
        </div>
    </div>
    
    <!-- Wave separator -->
    <div class="hero-wave">
        <svg viewBox="0 0 1440 120" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 120L60 110C120 100 240 80 360 75C480 70 600 80 720 85C840 90 960 90 1080 85C1200 80 1320 70 1380 65L1440 60V120H1380C1320 120 1200 120 1080 120C960 120 840 120 720 120C600 120 480 120 360 120C240 120 120 120 60 120H0Z" fill="var(--dark-bg)"/>
        </svg>
    </div>
</section>

<!-- Features Section -->
<section class="features-section">
    <div class="features-bg" style="background-image: url('/tracktor/assets/image/2026-05-05/n6gqroyaafla/tractor-compact-farm.png');"></div>
    <div class="features-overlay"></div>
    <div class="main-content" style="max-width: 1400px; padding: 5rem 2rem; position: relative; z-index: 1;">
        <div class="section-header text-center">
            <h2 class="section-title">Why Choose <span class="text-primary">TRACKTOR</span>?</h2>
            <p class="section-subtitle">Streamline your agricultural equipment management with powerful tools designed for modern farming operations.</p>
        </div>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon feature-icon-red">🚜</div>
                <h3>Fleet Management</h3>
                <p>Complete tractor inventory control with status tracking, maintenance schedules, and availability management.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon feature-icon-blue">📅</div>
                <h3>Smart Booking</h3>
                <p>Easy scheduling system with calendar view, conflict prevention, and automated confirmations.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon feature-icon-green">👥</div>
                <h3>Customer CRM</h3>
                <p>Manage customer profiles, rental history, and preferences for personalized service.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon feature-icon-gold">📊</div>
                <h3>Analytics & Reports</h3>
                <p>SQL-based reporting engine with revenue tracking, utilization metrics, and custom queries.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon feature-icon-purple">💳</div>
                <h3>Payment Processing</h3>
                <p>Handle multiple payment methods with invoicing, receipts, and financial tracking.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon feature-icon-orange">🔔</div>
                <h3>Notifications</h3>
                <p>Automated alerts for bookings, maintenance reminders, and system updates.</p>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="stats-bg" style="background-image: url('/tracktor/assets/image/2026-05-05/n6gqpeyaafmq/tractor-rowcrop-field.png');"></div>
    <div class="stats-overlay"></div>
    <div class="main-content" style="max-width: 1400px; padding: 5rem 2rem; position: relative; z-index: 1;">
        <div class="stats-header text-center mb-4">
            <h2 class="section-title" style="font-size: 2.2rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem;">
                <span class="text-primary">TRACKTOR</span> at a Glance
            </h2>
            <p class="text-secondary" style="font-size: 1.1rem; max-width: 700px; margin: 0 auto;">A complete solution for tractor rental and fleet management</p>
        </div>

        <div class="stats-grid-enhanced">
            <div class="stat-card-enhanced">
                <div class="stat-icon-circle stat-icon-red">
                    <span>🚜</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">100+</div>
                    <div class="stat-label">Tractors Managed</div>
                </div>
            </div>
            <div class="stat-card-enhanced">
                <div class="stat-icon-circle stat-icon-green">
                    <span>✓</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Bookings Completed</div>
                </div>
            </div>
            <div class="stat-card-enhanced">
                <div class="stat-icon-circle stat-icon-blue">
                    <span>👥</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">200+</div>
                    <div class="stat-label">Happy Customers</div>
                </div>
            </div>
            <div class="stat-card-enhanced">
                <div class="stat-icon-circle stat-icon-gold">
                    <span>📈</span>
                </div>
                <div class="stat-content">
                    <div class="stat-number">99%</div>
                    <div class="stat-label">Uptime Guarantee</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="cta-bg" style="background-image: url('/tracktor/assets/image/2026-05-05/n6gqqwaaafna/John-Deere-Tractor-at-Night-1024x640.jpg');"></div>
    <div class="cta-overlay"></div>
    <div class="main-content text-center" style="max-width: 900px; padding: 4rem 2rem; position: relative; z-index: 1;">
        <h2 class="mb-3">Ready to Get Started?</h2>
        <p class="text-secondary mb-4" style="font-size: 1.1rem; max-width: 700px; margin: 0 auto 2rem;">
            Join TRACKTOR today and transform how you manage your tractor fleet. Whether you're a customer needing equipment or staff managing operations, we've got you covered.
        </p>
        <div class="cta-buttons d-flex gap-2 justify-center">
            <a href="/tracktor/auth/register.php" class="btn btn-primary btn-lg btn-hover">Create Account</a>
            <a href="/tracktor/auth/login.php" class="btn btn-secondary btn-lg btn-hover">Sign In</a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
initial commit