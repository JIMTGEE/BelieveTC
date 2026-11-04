<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password | Believe Travel Consult</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #033754 0%, #0a6c8a 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }
        .reset-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.4);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
            backdrop-filter: blur(12px);
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        .reset-header {
            background: linear-gradient(135deg, #033754, #0a6c8a);
            color: white;
            padding: 2.5rem;
            text-align: center;
        }
        .reset-header img {
            height: 80px;
            filter: drop-shadow(0 4px 10px rgba(0,0,0,0.5));
        }
        .reset-header h1 { font-size: 2rem; font-weight: 700; margin-top: 15px; }
        .reset-body { padding: 3rem; }
        .form-control {
            border-radius: 14px;
            padding: 0.9rem 1.2rem;
            border: 2.5px solid #e0e0e0;
            transition: all 0.4s;
        }
        .form-control:focus {
            border-color: #10b6d7;
            box-shadow: 0 0 0 0.3rem rgba(16, 182, 215, 0.2);
        }
        .btn-reset {
            background: #89d900;
            color: #033754;
            font-weight: 800;
            border-radius: 14px;
            padding: 1rem;
            font-size: 1.15rem;
            border: none;
            transition: all 0.4s;
        }
        .btn-reset:hover {
            background: #7cc400;
            transform: translateY(-4px);
            box-shadow: 0 15px 30px rgba(137, 217, 0, 0.5);
        }
        .alert { border-radius: 14px; border: none; }
        .password-strength {
            height: 8px;
            border-radius: 4px;
            margin-top: 8px;
            background: #eee;
        }
        .strength-weak { background: #ff4d4d; width: 33%; }
        .strength-medium { background: #ffa500; width: 66%; }
        .strength-strong { background: #89d900; width: 100%; }
    </style>
</head>
<body>

<div class="reset-card">
    <div class="reset-header">
        <img src="../assets/images/logo_new.png" alt="Believe Travel Consult">
        <h1>Reset Password</h1>
        <p>Secure Admin Access • © 2025</p>
    </div>

    <div class="reset-body">
        <?php
        session_start();
        require_once '../includes/db.php';

        $token = $_GET['token'] ?? '';
        $error = $success = '';

        if (!$token) {
            $error = "Invalid or missing reset token.";
        } else {
            // Check token validity
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = "This reset link is invalid or has expired.";
            }
        }

        if ($_POST && !$error && $user) {
            $password = $_POST['password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (strlen($password) < 8) {
                $error = "Password must be at least 8 characters long.";
            } elseif (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $error = "Password must include uppercase, lowercase, and number.";
            } elseif ($password !== $confirm) {
                $error = "Passwords do not match!";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                if ($upd->execute([$hashed, $user['id']])) {
                    $success = "Password reset successfully! Please login.";
                } else {
                    $error = "Failed to update password. Try again.";
                }
            }
        }
        ?>

        <?php if ($success): ?>
            <div class="alert alert-success text-center">
                <i class="fas fa-check-circle fa-2x mb-3"></i><br>
                <strong><?= htmlspecialchars($success) ?></strong>
                <hr>
                <a href="login.php" class="btn btn-primary mt-3 rounded-pill px-5">
                    <i class="fas fa-sign-in-alt me-2"></i> Go to Login
                </a>
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger text-center">
                <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
                <strong><?= htmlspecialchars($error) ?></strong>
                <?php if (strpos($error, 'expired') !== false || strpos($error, 'invalid') !== false): ?>
                    <hr>
                    <a href="login.php" class="btn btn-outline-primary mt-3 rounded-pill">
                        <i class="fas fa-arrow-left me-2"></i> Back to Login
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label fw-bold">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <div class="password-strength mt-2">
                        <div id="strength-bar" class="strength-weak"></div>
                    </div>
                    <small class="text-muted">Must be 8+ characters with letters & numbers</small>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="togglePassword('confirm_password')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-reset">
                        <i class="fas fa-shield-alt me-2"></i> Update Password
                    </button>
                </div>

                <div class="text-center mt-4">
                    <a href="login.php" class="text-decoration-none" style="color:#10b6d7; font-weight:600;">
                        <i class="fas fa-arrow-left me-2"></i> Back to Login
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function togglePassword(id) {
    const input = document.getElementById(id);
    const icon = input.parentElement.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Live password strength
document.getElementById('password').addEventListener('input', function() {
    const val = this.value;
    const bar = document.getElementById('strength-bar');
    let strength = 0;
    if (val.length >= 8) strength++;
    if (/[a-z]/.test(val) && /[A-Z]/.test(val)) strength++;
    if (/[0-9]/.test(val)) strength++;
    if (/[^A-Za-z0-9]/.test(val)) strength++;

    bar.className = strength <= 1 ? 'strength-weak' : 
                    strength <= 2 ? 'strength-medium' : 'strength-strong';
});
</script>
</body>
</html>