<?php
session_start();
require_once '../includes/db.php';

$error = $success = '';

// Rate limiting
if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
$lockout = ($_SESSION['login_attempts'] >= 5 && (time() - ($_SESSION['last_attempt'] ?? 0)) < 300);

// AUTO-CREATE missing columns (for password reset)
try {
    $pdo->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL");
    $pdo->query("ALTER TABLE admins ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL");
} catch (Exception $e) { /* ignore */ }

if ($_POST && !$lockout) {
    $action = $_POST['action'] ?? '';

    // FORGOT PASSWORD
    if ($action === 'forgot') {
        $email = trim($_POST['email']);
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token    = bin2hex(random_bytes(50));
            $expires  = date("Y-m-d H:i:s", strtotime('+1 hour'));

            $upd = $pdo->prepare("UPDATE admins SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $upd->execute([$token, $expires, $user['id']]);

            $reset_link = "http://localhost/beleive_travel_consult/admin/reset_password.php?token=$token";
            $subject    = "Admin Password Reset – Believe Travel Consult";
            $message    = "<!DOCTYPE html><html><body style='font-family:Arial;max-width:600px;margin:auto;padding:30px;background:#f9f9f9;border-radius:15px;text-align:center;'>
                <img src='http://localhost/beleive_travel_consult/assets/images/logo_new.png' width='120'><br><br>
                <h2 style='color:#033754;'>Password Reset Request</h2>
                <p style='font-size:16px;'>Click below to reset your password:</p>
                <a href='$reset_link' style='display:inline-block;background:#89d900;color:#033754;text-decoration:none;padding:15px 40px;border-radius:50px;font-weight:700;font-size:18px;margin:20px 0;'>Reset Password Now</a>
                <p style='font-size:14px;color:#666;'>Link expires in 1 hour.</p>
                </body></html>";

            $headers  = "MIME-Version: 1.0\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
            $headers .= "From: no-reply@beleivetravelconsult.com\r\n";

            if (mail($email, $subject, $message, $headers)) {
                $success = "Reset link sent to your email!";
            } else {
                $error = "Failed to send email. Try again.";
            }
        } else {
            $error = "No account found with this email.";
        }
    }

    // LOGIN - FIXED: Now uses password_verify() for proper bcrypt validation
    if ($action === 'login') {
        $email    = trim($_POST['email']);
        $password = trim($_POST['password']);

        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // CORRECT WAY: Use password_verify() to compare raw password with stored hash
       if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);
    
    $_SESSION['admin_logged_in'] = true;     // ← THIS IS THE KEY LINE
    $_SESSION['admin_id']        = $user['id'];
    $_SESSION['admin_email']     = $user['email'];
    $_SESSION['admin_name']      = 'Admin';

    $_SESSION['login_attempts'] = 0;
    unset($_SESSION['last_attempt']);

    header("Location: dashboard.php");
    exit;
}
        else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
            $error = "Invalid email or password!";
        }
    }
}

if ($lockout) {
    $remaining = 300 - (time() - ($_SESSION['last_attempt'] ?? 0));
    $error = "Too many attempts. Please wait $remaining seconds.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Believe Travel Consult</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- hCaptcha script kept but disabled on localhost -->
    <script src="https://js.hcaptcha.com/1/api.js" async defer></script>
    
    <style>
        body{font-family:'Poppins',sans-serif;background:linear-gradient(135deg,#033754 0%,#0a6c8a 100%);height:100vh;display:flex;align-items:center;justify-content:center;margin:0;overflow:hidden}
        .login-card{background:rgba(255,255,255,.98);border-radius:24px;box-shadow:0 25px 60px rgba(0,0,0,.4);overflow:hidden;max-width:460px;width:100%;backdrop-filter:blur(12px);animation:float 6s ease-in-out infinite}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-15px)}}
        .login-header{background:linear-gradient(135deg,#033754,#0a6c8a);color:white;padding:2.5rem;text-align:center}
        .login-header img{height:80px;filter:drop-shadow(0 4px 10px rgba(0,0,0,.5))}
        .login-body{padding:3rem}
        .form-control{border-radius:14px;padding:.9rem 1.2rem;border:2.5px solid #e0e0e0;transition:all .4s}
        .form-control:focus{border-color:#10b6d7;box-shadow:0 0 0 .3rem rgba(16,182,215,.2)}
        .btn-login{background:#89d900;color:#033754;font-weight:800;border-radius:14px;padding:1rem;font-size:1.15rem;border:none;transition:all .4s}
        .btn-login:hover{background:#7cc400;transform:translateY(-4px);box-shadow:0 15px 30px rgba(137,217,0,.5)}
        .alert{border-radius:14px;border:none}
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <img src="../assets/images/logo_new.png" alt="Believe Travel Consult">
        <h1>Admin Portal</h1>
        <p>Secure Access • © 2025</p>
    </div>

    <div class="login-body">
        <?php if ($success): ?>
            <div class="alert alert-success text-center"><i class="fas fa-check-circle"></i> <?=htmlspecialchars($success)?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger text-center"><i class="fas fa-exclamation-triangle"></i> <?=htmlspecialchars($error)?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="Admin Email" value="believetravelconsult903@gmail.com" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
            </div>

            <!-- hCaptcha DISABLED on localhost -->
            <!--
            <div class="text-center mb-4">
                <div class="h-captcha" data-sitekey="be286160-9964-48f2-ba90-443415c8ad5c"></div>
            </div>
            -->

            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-shield-alt me-2"></i> Secure Login
                </button>
            </div>

            <div class="text-center">
                <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#forgotModal" style="color:#10b6d7;font-weight:600;">
                    <i class="fas fa-key"></i> Forgot Password?
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header border-0" style="background:#033754;color:white;">
                <h5 class="modal-title"><i class="fas fa-key"></i> Reset Admin Password</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="forgot">
                <div class="modal-body p-4">
                    <p>Enter your admin email to receive a password reset link.</p>
                    <input type="email" name="email" class="form-control form-control-lg rounded-pill" placeholder="Admin Email" required>
                </div>
                <div class="modal-footer border-0 justify-content-center pb-4">
                    <button type="submit" class="btn btn-login px-5">
                        <i class="fas fa-paper-plane"></i> Send Reset Link
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>