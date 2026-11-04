<?php
// admin_header.php
// Expects (optionally) $pageTitle to be set by the including page before this file is included.
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Dashboard';
}

// Used to highlight the active sidebar link automatically
$currentPage = basename($_SERVER['PHP_SELF']);

/**
 * Helper: echoes 'active' if $file matches the current page.
 */
function navActive($file, $currentPage) {
    return $currentPage === $file ? 'active' : '';
}

// ------------------------------------------------------------------
// Global topbar data: notification bell + admin identity.
// Runs on every admin page (once $pdo is available) so the bell/profile
// stay consistent everywhere, not just on the dashboard.
// ------------------------------------------------------------------
$pendingCommentsCount = 0;
$newMessagesCount     = 0;
$notifComments        = [];
$notifMessages        = [];

if (isset($pdo)) {
    try {
        $pendingCommentsCount = (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE approved = 0")->fetchColumn();
        $notifComments = $pdo->query(
            "SELECT c.id, c.name, c.created_at, b.title AS post_title
             FROM comments c LEFT JOIN blog_posts b ON b.id = c.post_id
             WHERE c.approved = 0 ORDER BY c.created_at DESC LIMIT 3"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* comments table not ready yet -- fail quietly */ }

    try {
        $newMessagesCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM contact_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        )->fetchColumn();
        $notifMessages = $pdo->query(
            "SELECT id, name, subject, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 3"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* fail quietly */ }
}
$totalNotifications = $pendingCommentsCount + $newMessagesCount;

// Admin identity for the profile menu
$adminEmail = $_SESSION['admin_email'] ?? null;
if (!$adminEmail && isset($pdo)) {
    try {
        if (isset($_SESSION['admin_id'])) {
            $stmt = $pdo->prepare("SELECT email FROM admins WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['admin_id']]);
            $adminEmail = $stmt->fetchColumn() ?: null;
        }
        if (!$adminEmail) {
            $adminEmail = $pdo->query("SELECT email FROM admins ORDER BY id ASC LIMIT 1")->fetchColumn() ?: null;
        }
    } catch (Exception $e) { /* fail quietly */ }
}
if (!$adminEmail) { $adminEmail = 'Admin'; }
$adminInitial = strtoupper(substr($adminEmail, 0, 1));

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> | Believe Travel Consult</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- AOS for animations -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <!-- Custom Premium Styles (shared across all admin pages) -->
    <style>
        :root {
            --primary-dark: #033754;
            --primary-light: #10b6d7;
            --accent-green: #89d900;
            --white: #ffffff;
            --bg-light: #f8f9fa;
            --shadow-soft: 0 4px 20px rgba(3,55,84,0.08);
            --shadow-hover: 0 12px 40px rgba(3,55,84,0.15);
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(to bottom, var(--bg-light), #ffffff);
            color: var(--primary-dark);
            overflow-x: hidden;
        }

        /* Hamburger Toggle Button */
        .hamburger-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1060;
            background: var(--white);
            border: none;
            border-radius: 12px;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-hover);
            color: var(--primary-dark);
            font-size: 1.4rem;
            transition: all 0.3s ease, left 0.3s ease;
            cursor: pointer;
            padding: 0;
        }
        .hamburger-toggle:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-hover);
            color: var(--primary-light);
        }
        .hamburger-toggle.hidden {
            display: none;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--primary-dark) 0%, #022d45 100%);
            color: var(--white);
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            padding: 3rem 1.5rem;
            overflow-y: auto;
            box-shadow: var(--shadow-hover);
            transition: transform 0.3s ease, width 0.3s ease;
            z-index: 1050;
            display: flex;
            flex-direction: column;
        }
        .sidebar.collapsed {
            transform: translateX(-100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.9);
            font-weight: 600;
            padding: 1.2rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.15);
            color: var(--accent-green);
            transform: translateX(8px);
        }
        .sidebar .nav-link.active {
            background: var(--primary-light);
            color: var(--primary-dark);
            box-shadow: var(--shadow-soft);
        }
        .sidebar .nav-link i {
            font-size: 1.4rem;
            margin-right: 1rem;
            width: 1.6rem;
            text-align: center;
        }
        .sidebar .nav-item.mt-auto {
            margin-top: auto;
        }

        .main-content {
            margin-left: 280px;
            padding: 3rem 2rem;
            transition: margin-left 0.3s ease;
        }
        .main-content.expanded {
            margin-left: 0;
        }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            background: #ffffff;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-hover);
        }
        .stat-card {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-light) 80%);
            color: var(--white);
            border-radius: 20px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }
        .stat-card i {
            opacity: 0.2;
            font-size: 6rem;
            position: absolute;
            bottom: -20px;
            right: -20px;
        }
        .btn-primary {
            background: var(--accent-green);
            border: none;
            color: var(--primary-dark);
            font-weight: 700;
            border-radius: 50px;
            padding: 0.8rem 2rem;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #7cc400;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(137,217,0,0.3);
            color: var(--primary-dark);
        }
        .table {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
        }
        .table thead {
            background: linear-gradient(to right, var(--primary-dark), #022d45);
            color: var(--white);
        }
        .list-group-item {
            border: none;
            border-bottom: 1px solid rgba(3,55,84,0.1);
            transition: background 0.3s;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            padding: 1.2rem;
        }
        .list-group-item:hover {
            background: var(--bg-light);
        }

        /* Mobile/Tablet Responsive */
        @media (max-width: 991px) {
            .hamburger-toggle {
                display: flex !important;
                top: 15px;
                left: 15px;
                width: 44px;
                height: 44px;
                font-size: 1.2rem;
            }
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                padding: 3rem 1.5rem;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding-top: 5rem;
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
            .main-content.expanded {
                margin-left: 0;
            }
            .navbar {
                box-shadow: var(--shadow-soft);
            }
        }

        /* Desktop */
        @media (min-width: 992px) {
            .sidebar {
                transform: translateX(0);
            }
            /* When sidebar is open on desktop, push the button clear of the logo/title */
            .hamburger-toggle.sidebar-open {
                left: 296px;
            }
        }

        /* Animations */
        [data-aos] {
            transition-duration: 0.8s;
        }

        /* ---------- Topbar (bell + profile) ---------- */
        .admin-topbar {
            margin-bottom: 2rem;
        }
        .topbar-icon-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--white);
            border: none;
            box-shadow: var(--shadow-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark);
            font-size: 1.1rem;
            position: relative;
            transition: all 0.25s ease;
        }
        .topbar-icon-btn:hover {
            box-shadow: var(--shadow-hover);
            color: var(--primary-light);
        }
        .notif-badge {
            position: absolute;
            top: -2px;
            right: -2px;
            background: #ff4d4f;
            color: #fff;
            font-size: 0.65rem;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            border: 2px solid var(--bg-light);
        }
        .notif-dropdown {
            width: 340px;
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow-hover);
            padding: 0.5rem 0;
        }
        .notif-dropdown .notif-header {
            padding: 0.75rem 1.25rem;
            font-weight: 700;
            color: var(--primary-dark);
            border-bottom: 1px solid rgba(3,55,84,0.08);
        }
        .notif-item {
            padding: 0.75rem 1.25rem;
            display: block;
            color: var(--primary-dark);
            text-decoration: none;
            border-bottom: 1px solid rgba(3,55,84,0.05);
        }
        .notif-item:hover {
            background: var(--bg-light);
            color: var(--primary-dark);
        }
        .notif-item small.text-muted { display:block; margin-top:2px; }
        .notif-empty {
            padding: 1.5rem 1.25rem;
            text-align: center;
            color: #9aa5ad;
        }
        .profile-btn {
            background: var(--white);
            border: none;
            border-radius: 50px;
            box-shadow: var(--shadow-soft);
            padding: 0.4rem 1rem 0.4rem 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.25s ease;
        }
        .profile-btn:hover {
            box-shadow: var(--shadow-hover);
        }
        .avatar-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-light));
            color: #fff;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* ---------- Dashboard widgets ---------- */
        .trend-badge {
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .trend-up { background: rgba(137,217,0,0.15); color: #5c9200; }
        .trend-down { background: rgba(255,77,79,0.12); color: #c0392b; }
        .mini-stat {
            background: #fff;
            border-radius: 16px;
            box-shadow: var(--shadow-soft);
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 100%;
        }
        .mini-stat .mini-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        .progress-row + .progress-row { margin-top: 1rem; }
        .progress-row .progress {
            height: 8px;
            border-radius: 10px;
            background: var(--bg-light);
        }
        .progress-row .progress-bar {
            background: linear-gradient(90deg, var(--primary-light), var(--accent-green));
            border-radius: 10px;
        }
        .timeline {
            position: relative;
            padding-left: 2.5rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 0.95rem;
            top: 0.4rem;
            bottom: 0.4rem;
            width: 2px;
            background: rgba(3,55,84,0.1);
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-icon {
            position: absolute;
            left: -2.5rem;
            top: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            color: #fff;
            box-shadow: 0 0 0 4px #fff;
        }
        .comment-card {
            background: var(--bg-light);
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 0.75rem;
        }
        .comment-card:last-child { margin-bottom: 0; }
        .badge-soft-warning {
            background: rgba(255,193,7,0.15);
            color: #b8860b;
        }
        .empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #9aa5ad;
        }
        .empty-state i { font-size: 2.5rem; margin-bottom: 0.75rem; opacity: 0.4; }
    </style>
    <?php if (isset($extraHead)) { echo $extraHead; } // allows a page to inject page-specific <style>/<link> tags ?>
</head>
<body>

<!-- Hamburger Toggle Button -->
<button class="hamburger-toggle sidebar-open" id="hamburgerToggle" aria-label="Toggle sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Top Navbar for Mobile -->
<nav class="navbar navbar-expand-lg navbar-light bg-white d-lg-none fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="../assets/images/logo_new.png" alt="Logo" width="120">
        </a>
    </div>
</nav>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebarNav">
    <div>
        <div class="text-center mb-5">
            <img src="../assets/images/logo_new.png" alt="Logo" width="180" class="mb-3 shadow-sm rounded-circle">
            <h4 class="fw-bold">Admin Dashboard</h4>
            <p class="fs-6 opacity-75">Welcome, Admin</p>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item"><a href="dashboard.php" class="nav-link <?php echo navActive('dashboard.php', $currentPage); ?>"><i class="fas fa-home"></i> Dashboard</a></li>
            <li class="nav-item"><a href="offers.php" class="nav-link <?php echo navActive('offers.php', $currentPage); ?>"><i class="fas fa-briefcase"></i> Manage Offers</a></li>
            <li class="nav-item"><a href="blog.php" class="nav-link <?php echo navActive('blog.php', $currentPage); ?>"><i class="fas fa-blog"></i> Manage Blog</a></li>
            <li class="nav-item"><a href="services.php" class="nav-link <?php echo navActive('services.php', $currentPage); ?>"><i class="fas fa-handshake"></i> Manage Services</a></li>
            <li class="nav-item"><a href="print_receipt.php" class="nav-link <?php echo navActive('print_receipt.php', $currentPage); ?>"><i class="fas fa-file-invoice"></i> Print Receipt</a></li>
            <li class="nav-item"><a href="gallery.php" class="nav-link <?php echo navActive('gallery.php', $currentPage); ?>"><i class="fas fa-images"></i> Manage Gallery</a></li>
            <li class="nav-item"><a href="messages.php" class="nav-link <?php echo navActive('messages.php', $currentPage); ?>"><i class="fas fa-envelope"></i> Contact Messages</a></li>
            <li class="nav-item"><a href="edit_profile.php" class="nav-link <?php echo navActive('edit_profile.php', $currentPage); ?>"><i class="fas fa-user-cog"></i> Change Password</a></li>
        </ul>
    </div>
    <ul class="nav flex-column mt-auto">
        <li class="nav-item"><a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
    <div class="text-center mt-4 small opacity-50">
        <p>© <?php echo date('Y'); ?> Believe Travel Consult<br>All Rights Reserved</p>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <div class="container-fluid">

        <!-- Topbar: notifications + admin profile -->
        <div class="d-flex justify-content-end align-items-center gap-3 admin-topbar">
            <div class="dropdown">
                <button class="topbar-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell"></i>
                    <?php if ($totalNotifications > 0): ?>
                        <span class="notif-badge"><?php echo $totalNotifications > 9 ? '9+' : $totalNotifications; ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                    <div class="notif-header d-flex justify-content-between align-items-center">
                        <span>Notifications</span>
                        <?php if ($totalNotifications > 0): ?><span class="badge bg-danger rounded-pill"><?php echo $totalNotifications; ?></span><?php endif; ?>
                    </div>
                    <?php if (empty($notifComments) && empty($notifMessages)): ?>
                        <div class="notif-empty"><i class="fas fa-check-circle mb-2 d-block fs-4"></i>You're all caught up</div>
                    <?php else: ?>
                        <?php foreach ($notifComments as $c): ?>
                            <a href="dashboard.php#pending-comments" class="notif-item">
                                <strong><?php echo htmlspecialchars($c['name']); ?></strong> commented on
                                "<?php echo htmlspecialchars($c['post_title'] ?? 'a post'); ?>"
                                <small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo timeAgo($c['created_at']); ?> · awaiting approval</small>
                            </a>
                        <?php endforeach; ?>
                        <?php foreach ($notifMessages as $m): ?>
                            <a href="messages.php?view=<?php echo (int) $m['id']; ?>" class="notif-item">
                                <strong><?php echo htmlspecialchars($m['name']); ?></strong> sent an inquiry
                                <small class="text-muted"><i class="fas fa-clock me-1"></i><?php echo timeAgo($m['created_at']); ?> · <?php echo htmlspecialchars(substr($m['subject'], 0, 40)); ?></small>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dropdown">
                <button class="profile-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar-circle"><?php echo htmlspecialchars($adminInitial); ?></span>
                    <span class="d-none d-md-inline fw-semibold small"><?php echo htmlspecialchars($adminEmail); ?></span>
                    <i class="fas fa-chevron-down small d-none d-md-inline"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="border-radius:14px;">
                    <li><a class="dropdown-item py-2" href="edit_profile.php"><i class="fas fa-user-cog me-2"></i>Change Password</a></li>
                    <li><a class="dropdown-item py-2" href="../index.php" target="_blank"><i class="fas fa-external-link-alt me-2"></i>View Website</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hamburgerToggle = document.getElementById('hamburgerToggle');
    const sidebarNav = document.getElementById('sidebarNav');
    const mainContent = document.getElementById('mainContent');
    const toggleIcon = hamburgerToggle.querySelector('i');

    // Keeps the hamburger button positioned correctly relative to the
    // sidebar's open/closed state (desktop only -- on mobile it always
    // stays pinned to the top-left corner since the sidebar overlays content).
    function updateHamburgerPosition() {
        if (window.innerWidth > 991) {
            if (sidebarNav.classList.contains('collapsed')) {
                hamburgerToggle.classList.remove('sidebar-open');
            } else {
                hamburgerToggle.classList.add('sidebar-open');
            }
        } else {
            hamburgerToggle.classList.remove('sidebar-open');
        }
    }

    // Function to toggle sidebar
    function toggleSidebar() {
        // For mobile
        if (window.innerWidth <= 991) {
            sidebarNav.classList.toggle('show');
            // Change icon
            if (sidebarNav.classList.contains('show')) {
                toggleIcon.className = 'fas fa-times';
            } else {
                toggleIcon.className = 'fas fa-bars';
            }
        }
        // For desktop
        else {
            sidebarNav.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            // Change icon
            if (sidebarNav.classList.contains('collapsed')) {
                toggleIcon.className = 'fas fa-bars';
            } else {
                toggleIcon.className = 'fas fa-times';
            }
        }
        updateHamburgerPosition();
    }

    // Click handler for hamburger
    hamburgerToggle.addEventListener('click', toggleSidebar);

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 991) {
            const isClickInsideSidebar = sidebarNav.contains(event.target);
            const isClickOnToggle = hamburgerToggle.contains(event.target);

            if (!isClickInsideSidebar && !isClickOnToggle && sidebarNav.classList.contains('show')) {
                sidebarNav.classList.remove('show');
                toggleIcon.className = 'fas fa-bars';
            }
        }
    });

    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 991) {
                // On desktop, ensure sidebar is visible and content isn't shifted
                sidebarNav.classList.remove('show');
                sidebarNav.classList.remove('collapsed');
                mainContent.classList.remove('expanded');
                toggleIcon.className = 'fas fa-times';
            } else {
                // On mobile, ensure sidebar is hidden by default
                sidebarNav.classList.remove('show');
                toggleIcon.className = 'fas fa-bars';
            }
            updateHamburgerPosition();
        }, 250);
    });

    // Initialize based on screen size
    if (window.innerWidth <= 991) {
        sidebarNav.classList.remove('show');
        toggleIcon.className = 'fas fa-bars';
    } else {
        sidebarNav.classList.remove('collapsed');
        mainContent.classList.remove('expanded');
        toggleIcon.className = 'fas fa-times';
    }
    updateHamburgerPosition();
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<!-- AOS for animations -->
<script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
<script>
    AOS.init();
</script>
</body>
</html>