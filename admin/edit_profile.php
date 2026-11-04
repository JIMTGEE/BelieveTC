<?php
// edit_profile.php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Set page title for header
$pageTitle = 'Edit Profile';

$error = $success = '';

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (!$current_password || !$new_password || !$confirm_password) {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    } else {
        // Fetch current user
        $admin_id = $_SESSION['admin_id'];
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

            // Update password
            $upd = $pdo->prepare("UPDATE admins SET password = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$hashed_password, $admin_id]);

            $success = 'Password updated successfully!';
            
            // Log the activity (optional - if you have an activity log)
            // logActivity($admin_id, 'Password changed');
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

// Handle profile update (if additional fields exist)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    $admin_id = $_SESSION['admin_id'];
    
    // Check if email is already taken by another admin
    $check = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
    $check->execute([$email, $admin_id]);
    if ($check->rowCount() > 0) {
        $error = 'Email address is already in use.';
    } else {
        $upd = $pdo->prepare("UPDATE admins SET full_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$full_name, $email, $admin_id]);
        $success = 'Profile updated successfully!';
        $_SESSION['admin_email'] = $email;
    }
}

// Get current admin data
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    redirect('logout.php');
}

// Get security info
$last_login = $_SESSION['last_login'] ?? 'Never';
$last_ip = $_SESSION['last_ip'] ?? 'Unknown';
?>

<?php include 'admin_header.php'; ?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <h1 class="fw-bold mb-4" data-aos="fade-right">
            <i class="fas fa-user-edit me-2 text-primary"></i>Edit Profile
        </h1>
        <p class="text-muted mb-5" data-aos="fade-right" data-aos-delay="100">
            Manage your account settings and security preferences.
        </p>
    </div>
</div>

<div class="row g-4">
    <!-- Profile Information -->
    <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-4">
                    <i class="fas fa-id-card me-2 text-primary"></i>Profile Information
                </h5>
                
                <?php if ($success): ?>
                    <div class="alert alert-success rounded-4 py-3 d-flex align-items-center">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger rounded-4 py-3 d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="full_name" class="form-label fw-semibold">
                                <i class="fas fa-user me-1"></i>Full Name
                            </label>
                            <input type="text" name="full_name" id="full_name" 
                                   class="form-control form-control-lg" 
                                   value="<?= htmlspecialchars($admin['full_name'] ?? $admin['email'] ?? '') ?>" 
                                   required>
                        </div>
                        
                        <div class="col-12">
                            <label for="email" class="form-label fw-semibold">
                                <i class="fas fa-envelope me-1"></i>Email Address
                            </label>
                            <input type="email" name="email" id="email" 
                                   class="form-control form-control-lg" 
                                   value="<?= htmlspecialchars($admin['email']) ?>" 
                                   required>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                <i class="fas fa-user-shield me-1"></i>Role
                            </label>
                            <input type="text" class="form-control form-control-lg" 
                                   value="<?= htmlspecialchars($admin['role'] ?? 'Administrator') ?>" 
                                   disabled>
                            <small class="text-muted">Contact system administrator for role changes.</small>
                        </div>
                        
                        <div class="col-12 mt-3">
                            <button type="submit" name="update_profile" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Security & Password Change -->
    <div class="col-lg-5" data-aos="fade-up" data-aos-delay="200">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-4">
                    <i class="fas fa-lock me-2 text-primary"></i>Security Settings
                </h5>
                
                <form method="POST">
                    <div class="mb-3">
                        <label for="current_password" class="form-label fw-semibold">
                            <i class="fas fa-key me-1"></i>Current Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-lock"></i></span>
                            <input type="password" name="current_password" id="current_password" 
                                   class="form-control form-control-lg" 
                                   placeholder="Enter current password" 
                                   required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">
                            <i class="fas fa-key me-1"></i>New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-key"></i></span>
                            <input type="password" name="new_password" id="new_password" 
                                   class="form-control form-control-lg" 
                                   placeholder="Enter new password" 
                                   required minlength="8">
                        </div>
                        <div id="passwordStrength" class="mt-2"></div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>Must be at least 8 characters with uppercase, lowercase, and numbers.
                        </small>
                    </div>
                    
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">
                            <i class="fas fa-check-double me-1"></i>Confirm New Password
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-0"><i class="fas fa-check"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   class="form-control form-control-lg" 
                                   placeholder="Confirm new password" 
                                   required>
                        </div>
                        <div id="passwordMatch" class="mt-1"></div>
                    </div>
                    
                    <button type="submit" name="update_password" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-shield-alt me-2"></i>Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Session & Activity Info -->
    <div class="col-12" data-aos="fade-up" data-aos-delay="300">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3">
                    <i class="fas fa-history me-2 text-primary"></i>Session & Activity
                </h5>
                <div class="row g-4">
                    <div class="col-md-3 col-6">
                        <div class="mini-stat">
                            <div class="mini-icon bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Last Login</small>
                                <strong><?= htmlspecialchars($last_login) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="mini-stat">
                            <div class="mini-icon bg-info bg-opacity-10 text-info">
                                <i class="fas fa-network-wired"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">IP Address</small>
                                <strong><?= htmlspecialchars($last_ip) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="mini-stat">
                            <div class="mini-icon bg-success bg-opacity-10 text-success">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Account Created</small>
                                <strong><?= date('M d, Y', strtotime($admin['created_at'] ?? 'now')) ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="mini-stat">
                            <div class="mini-icon bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Last Updated</small>
                                <strong><?= date('M d, Y', strtotime($admin['updated_at'] ?? 'now')) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Strength Checker Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordStrength = document.getElementById('passwordStrength');
    const passwordMatch = document.getElementById('passwordMatch');
    
    // Password strength checker
    newPassword.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        let message = '';
        let color = '';
        
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        switch(strength) {
            case 0:
            case 1:
                message = 'Very Weak';
                color = '#dc3545';
                break;
            case 2:
                message = 'Weak';
                color = '#ffc107';
                break;
            case 3:
                message = 'Fair';
                color = '#fd7e14';
                break;
            case 4:
                message = 'Good';
                color = '#0dcaf0';
                break;
            case 5:
                message = 'Strong';
                color = '#198754';
                break;
        }
        
        if (password.length > 0) {
            passwordStrength.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <div class="flex-grow-1" style="height:4px;background:#e9ecef;border-radius:2px;">
                        <div style="width:${(strength/5)*100}%;height:100%;background:${color};border-radius:2px;transition:width 0.3s;"></div>
                    </div>
                    <small style="color:${color};font-weight:600;">${message}</small>
                </div>
            `;
        } else {
            passwordStrength.innerHTML = '';
        }
        
        // Check match
        if (confirmPassword.value.length > 0) {
            checkPasswordMatch();
        }
    });
    
    // Password match checker
    confirmPassword.addEventListener('input', checkPasswordMatch);
    
    function checkPasswordMatch() {
        if (confirmPassword.value.length === 0) {
            passwordMatch.innerHTML = '';
            return;
        }
        
        if (newPassword.value === confirmPassword.value) {
            passwordMatch.innerHTML = '<small class="text-success"><i class="fas fa-check-circle me-1"></i>Passwords match</small>';
        } else {
            passwordMatch.innerHTML = '<small class="text-danger"><i class="fas fa-times-circle me-1"></i>Passwords do not match</small>';
        }
    }
    
    // Toggle password visibility (optional enhancement)
    document.querySelectorAll('.input-group .form-control[type="password"]').forEach(input => {
        const wrapper = input.closest('.input-group');
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-outline-secondary border-0';
        toggleBtn.innerHTML = '<i class="fas fa-eye"></i>';
        toggleBtn.style.zIndex = '3';
        toggleBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        });
        wrapper.appendChild(toggleBtn);
    });
});
</script>

<?php include 'admin_footer.php'; ?>