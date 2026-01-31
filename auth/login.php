<?php
session_start();
require "../config/database.php";
require "../config/flash.php";
require "../config/app.php";

$base = app_base_url();

if (isset($_SESSION['user'])) {
    $role = $_SESSION['user']['role'] ?? 'staff';

switch ($role) {
  case 'admin':
  case 'manager':
  case 'staff':
    header("Location: $base/index.php");
    break;

  case 'project':
    header("Location: $base/views/projects.php");
    break;

  case 'procurement':
    header("Location: $base/views/purchase_orders.php");
    break;

  case 'warehouse':
    header("Location: $base/views/inventory.php");
    break;

  case 'mro':
    header("Location: $base/views/maintenance.php");
    break;

  case 'asset':
  default:
    header("Location: $base/index.php");
    break;
}
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        set_flash('success', 'Welcome back, ' . $user['fullname'] . '!');
        header("Location: $base/index.php");
        exit;
    } else {
        set_flash('error', 'Invalid email or password.');
        header("Location: $base/auth/login.php");
        exit;
    }
}

$success = get_flash('success');
$error   = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>byaHERO - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/login-styles.css">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/design.css">
  </head>

<body class="login-page">
    <div class="login-container">
        <section class="promo-section">
            <div class="logo-area">
                <div class="sidebar-header">
    <img src="<?= $base ?>/assets/logo1.png" alt="Logo" class="logo-icon" style="width:60px;height:60px;object-fit:contain;border-radius:3px;">
  </div>
                <div>
                    <h1>byaHERO</h1>
                    <p>Logistics 1</p>
                </div>
            </div>

            <h2>Manage Logistics <span class="neon-text">Intelligently</span></h2>
            <p class="description">//</p>

            <div class="stats-grid">
                <div class="stat-item">
                    <p class="stat-value">24/7</p>
                    <p class="stat-label">Real-Time Tracking</p>
                </div>
                <div class="stat-item">
                    <p class="stat-value">94%</p>
                    <p class="stat-label">Fleet Utilization</p>
                </div>
                <div class="stat-item">
                    <p class="stat-value">35%</p>
                    <p class="stat-label">Cost Savings</p>
                </div>
                <div class="stat-item">
                    <p class="stat-value">99.9%</p>
                    <p class="stat-label">Uptime</p>
                </div>
            </div>
        </section>

        <section class="login-form-section">
            <h3 class="welcome-text">Welcome back</h3>
            <p class="subtitle">Sign in to access your TNVS dashboard</p>

            <form class="login-form" method="POST" action="">
              <label for="email">Email</label>
              <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
              </div>

              <label for="password">Password</label>
              <div class="input-group">
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <i class="fas fa-eye password-toggle" id="passwordToggle"></i>
              </div>

              <div class="form-options">
                <label class="checkbox-container">
                  <input type="checkbox" checked="checked">
                  <span class="checkmark"></span>
                    Remember me
                </label>
                  <a href="#" id="forgotPasswordLink" class="forgot-password">Forgot password?</a>
              </div>
                <button type="submit" class="sign-in-btn">Sign In</button>
            </form>

            <div class="admin-contact">
                Don't have an account?
                <a href="#" id="contactAdminLink">Contact administrator</a>
            </div>

        </section>
    </div>

    <div id="forgotPasswordModal" class="modal-backdrop">
        <div class="modal-content">
            <button class="close-btn" data-close-modal="forgotPasswordModal">&times;</button>
            <h3>Reset your password</h3>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
            <label for="resetEmail">Email address</label>
            <div class="input-group">
                <i class="fas fa-envelope"></i>
                <input type="email" id="resetEmail" placeholder="Enter your email" required>
            </div>
            <div class="modal-actions">
                <button class="modal-btn cancel-btn" data-close-modal="forgotPasswordModal">Cancel</button>
                <button class="modal-btn submit-btn" id="sendResetLink">Send Reset Link</button>
            </div>
        </div>
    </div>

    <div id="resetSuccessModal" class="modal-backdrop">
        <div class="modal-content small-modal">
            <button class="close-btn" data-close-modal="resetSuccessModal">&times;</button>
            <div class="success-icon"><i class="fas fa-check-circle"></i></div>
            <h3>Check your email</h3>
            <p>We've sent a password reset link to **brian.tanael@example.com**. Please check your inbox and follow the instructions.</p>
            <button class="modal-btn submit-btn" data-close-modal="resetSuccessModal">Done</button>
            <a href="#" class="try-different-email" id="tryDifferentEmailLink"><i class="fas fa-arrow-left"></i> Try a different email</a>
            <p class="resend-info">Didn't receive the email? Check your spam folder or try again.</p>
        </div>
    </div>

    <div id="contactAdminModal" class="modal-backdrop">
        <div class="modal-content">
            <button class="close-btn" data-close-modal="contactAdminModal">&times;</button>
            <h3>Contact Administrator</h3>
            <p>Request a new account or get help with access issues. Our team will respond within 24 hours.</p>

            <form class="contact-form">
                <div class="form-row">
                    <div class="input-col">
                        <label for="fullName">Full Name *</label>
                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" id="fullName" placeholder="Juan Dela Cruz" required>
                        </div>
                    </div>
                    <div class="input-col">
                        <label for="contactEmail">Email *</label>
                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="contactEmail" placeholder="juan@tnvs.com" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-col">
                        <label for="phone">Phone (Optional)</label>
                        <div class="input-group">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone" placeholder="+63 0000000000">
                        </div>
                    </div>
                    <div class="input-col">
                        <label for="department">Department (Optional)</label>
                        <div class="input-group">
                            <i class="fas fa-briefcase"></i>
                            <input type="text" id="department" placeholder="Operations">
                        </div>
                    </div>
                </div>

                <label for="requestType">Request Type *</label>
                <div class="input-group select-group">
                    <select id="requestType" required>
                        <option value="" disabled selected>Select request type</option>
                        <option value="access">Access Issue</option>
                        <option value="new-account">New Account Request</option>
                        <option value="other">Other</option>
                    </select>
                    <i class="fas fa-caret-down select-icon"></i>
                </div>

                <label for="message">Message *</label>
                <div class="input-group text-area-group">
                    <i class="fas fa-comment-dots"></i>
                    <textarea id="message" placeholder="Please describe your request or issue in detail..." maxlength="1000" required></textarea>
                    <span class="char-count">0/1000</span>
                </div>

                <div class="modal-actions-contact">
                    <button class="modal-btn cancel-btn" data-close-modal="contactAdminModal">Cancel</button>
                    <button class="modal-btn submit-btn" id="submitContact">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast-notification" id="successToast">
        <i class="fas fa-check-circle"></i>
        <span>Sent successfully</span>
    </div>

    <script src="<?= $base ?>/assets/js/login-script.js?v=2"></script>

</body>

</html>
