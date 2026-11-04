<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (!$current_password || !$new_password || !$confirm_password) {
        $error = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
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
            $upd = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $upd->execute([$hashed_password, $admin_id]);

            $success = 'Password updated successfully!';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Believe Travel Consult</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Premium Styles -->
    <style>
        :root {
            --primary-dark: #033754;
            --primary-light: #10b6d7;
            --accent-green: #89d900;
            --white: #ffffff;
            --bg-light: #f8f9fa;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-light);
            color: var(--primary-dark);
        }
        .sidebar {
            background: var(--primary-dark);
            color: var(--white);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            padding: 2rem 1rem;
            overflow-y: auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .sidebar .nav-link {
            color: var(--white);
            font-weight: 500;
            padding: 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: var(--accent-green);
        }
        .sidebar .nav-link.active {
            background: var(--primary-light);
            color: var(--primary-dark);
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        .card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 20px rgba(3,55,84,0.05);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .btn-primary {
            background: var(--accent-green);
            border: none;
            color: var(--primary-dark);
            font-weight: 700;
        }
        .btn-primary:hover {
            background: #7cc400;
        }
        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(16,182,215,0.25);
        }
        /* Responsive adjustments */
        @media (max-width: 991px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<nav class="sidebar">
    <div class="text-center mb-4">
        <img src="../assets/images/logo_new.png" alt="Logo" width="120" class="mb-3">
        <h4 class="fw-bold">Admin Panel</h4>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="offers.php"><i class="fas fa-tags me-2"></i> Manage Offers</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="blog.php"><i class="fas fa-blog me-2"></i> Manage Blog</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="messages.php"><i class="fas fa-envelope me-2"></i> Inquiries</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="edit_profile.php"><i class="fas fa-user-edit me-2"></i> Edit Profile</a>
        </li>
        <li class="nav-item mt-4">
            <a class="nav-link text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </li>
    </ul>
</nav>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <h1 class="fw-bold mb-4">Edit Profile</h1>
                <p class="text-muted mb-5">Update your admin password securely.</p>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card p-5">
                    <?php if ($success): ?>
                        <div class="alert alert-success rounded-4 text-center py-4">
                            <i class="fas fa-check-circle fa-2x mb-3"></i><br>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-4 text-center py-4">
                            <i class="fas fa-exclamation-triangle fa-2x mb-3"></i><br>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-4">
                            <label for="current_password" class="form-label fw-semibold">Current Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" name="current_password" id="current_password" class="form-control form-control-lg rounded-pill" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="new_password" class="form-label fw-semibold">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="new_password" id="new_password" class="form-control form-control-lg rounded-pill" required minlength="8">
                            </div>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control form-control-lg rounded-pill" required>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg rounded-pill py-3">
                                <i class="fas fa-save me-2"></i> Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

</body>
</html>