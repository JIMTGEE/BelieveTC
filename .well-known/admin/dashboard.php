<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Fetch counts for overview
$offersCount = $pdo->query("SELECT COUNT(*) FROM offers")->fetchColumn();
$blogCount = $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
$messagesCount = $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn(); // Using contact_messages as primary

// Fetch recent offers (last 5)
$recentOffersStmt = $pdo->query("SELECT id, title, price, created_at FROM offers ORDER BY created_at DESC LIMIT 5");
$recentOffers = $recentOffersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent blog posts (last 5)
$recentBlogsStmt = $pdo->query("SELECT id, title, date_posted FROM blog_posts ORDER BY date_posted DESC LIMIT 5");
$recentBlogs = $recentBlogsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent messages (last 5)
$recentMessagesStmt = $pdo->query("SELECT id, name, email, subject, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 5");
$recentMessages = $recentMessagesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Believe Travel Consult</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js for premium charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- AOS for animations -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <!-- Custom Premium Styles -->
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
            transition: width 0.3s ease;
            z-index: 1050;
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
        }
        .main-content {
            margin-left: 280px;
            padding: 3rem 2rem;
            transition: margin-left 0.3s ease;
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
        /* Mobile Menu Toggle */
        .navbar-toggler {
            border: none;
            color: var(--primary-dark);
        }
        @media (max-width: 991px) {
            .sidebar {
                width: 0;
                padding: 0;
                overflow: hidden;
            }
            .sidebar.show {
                width: 280px;
                padding: 3rem 1.5rem;
            }
            .main-content {
                margin-left: 0;
            }
            .navbar {
                box-shadow: var(--shadow-soft);
            }
        }
        /* Animations */
        [data-aos] {
            transition-duration: 0.8s;
        }
    </style>
</head>
<body>

<!-- Top Navbar for Mobile -->
<nav class="navbar navbar-expand-lg navbar-light bg-white d-lg-none fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <img src="../assets/images/logo_new.png" alt="Logo" width="120">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse" aria-controls="sidebarCollapse" aria-expanded="false" aria-label="Toggle navigation">
            <i class="fas fa-bars fs-3"></i>
        </button>
    </div>
</nav>

<!-- Sidebar Navigation -->
<nav class="sidebar collapse d-lg-block" id="sidebarCollapse">
    <div class="text-center mb-5">
        <img src="../assets/images/logo_new.png" alt="Logo" width="180" class="mb-3 shadow-sm rounded-circle">
        <h4 class="fw-bold">Admin Dashboard</h4>
        <p class="fs-6 opacity-75">Welcome, Admin</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item"><a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a></li>
        <li class="nav-item"><a href="offers.php" class="nav-link"><i class="fas fa-briefcase"></i> Manage Offers</a></li>
        <li class="nav-item"><a href="blog.php" class="nav-link"><i class="fas fa-blog"></i> Manage Blog</a></li>
        <li class="nav-item"><a href="print_receipt.php" class="nav-link"><i class="fas fa-paper"></i>Print Receipt</a></li>
        <li class="nav-item"><a href="gallery.php" class="nav-link"><i class="fas fa-images"></i> Manage Gallery</a></li>
        <li class="nav-item"><a href="messages.php" class="nav-link"><i class="fas fa-envelope"></i> Contact Messages</a></li>
        <li class="nav-item"><a href="edit_profile.php" class="nav-link"><i class="fas fa-user-cog"></i>Change Password</a></li>
        <li class="nav-item mt-auto"><a href="logout.php" class="nav-link text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
    <div class="text-center mt-4 small opacity-50">
        <p>© 2025 Believe Travel Consult<br>All Rights Reserved</p>
    </div>
</nav>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-5 align-items-center">
            <div class="col-12">
                <h2 class="fw-bold display-6" style="color:#033754;" data-aos="fade-down">Dashboard Overview</h2>
                <p class="text-muted lead">Monitor your site's performance and manage operations seamlessly.</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-6">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card position-relative text-center">
                    <i class="fas fa-briefcase"></i>
                    <h1 class="fw-bold display-4 mb-2"><?php echo $offersCount; ?></h1>
                    <p class="fs-5 mb-0 opacity-90">Active Offers</p>
                    <a href="offers.php" class="btn btn-sm btn-light mt-3 rounded-pill">Manage Offers</a>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card position-relative text-center">
                    <i class="fas fa-blog"></i>
                    <h1 class="fw-bold display-4 mb-2"><?php echo $blogCount; ?></h1>
                    <p class="fs-5 mb-0 opacity-90">Blog Posts</p>
                    <a href="blog.php" class="btn btn-sm btn-light mt-3 rounded-pill">Manage Blog</a>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card position-relative text-center">
                    <i class="fas fa-envelope"></i>
                    <h1 class="fw-bold display-4 mb-2"><?php echo $messagesCount; ?></h1>
                    <p class="fs-5 mb-0 opacity-90">Inquiries</p>
                    <a href="messages.php" class="btn btn-sm btn-light mt-3 rounded-pill">View Messages</a>
                </div>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="row mb-6">
            <div class="col-12" data-aos="fade-up">
                <div class="card p-5">
                    <h4 class="fw-bold mb-4" style="color:#033754;">Site Performance Analytics</h4>
                    <p class="text-muted mb-4">Track monthly visits and inquiries with interactive insights.</p>
                    <canvas id="performanceChart" height="180"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activity Sections -->
        <div class="row g-4">
            <!-- Recent Offers -->
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card p-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold" style="color:#033754;">Recent Offers</h4>
                        <a href="offers.php" class="btn btn-sm btn-primary">Add New</a>
                    </div>
                    <?php if (empty($recentOffers)): ?>
                        <p class="text-muted text-center py-3">No recent offers yet</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentOffers as $offer): ?>
                                <a href="offers.php?edit=<?php echo $offer['id']; ?>" class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($offer['title']); ?></h6>
                                            <small class="text-muted">GH₵ <?php echo number_format($offer['price'], 2); ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($offer['created_at'])); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Blog Posts -->
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card p-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold" style="color:#033754;">Recent Blog Posts</h4>
                        <a href="blog.php" class="btn btn-sm btn-primary">Add New</a>
                    </div>
                    <?php if (empty($recentBlogs)): ?>
                        <p class="text-muted text-center py-3">No recent posts yet</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentBlogs as $blog): ?>
                                <a href="blog.php?edit=<?php echo $blog['id']; ?>" class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($blog['title']); ?></h6>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($blog['date_posted'])); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="300">
                <div class="card p-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold" style="color:#033754;">Recent Inquiries</h4>
                        <a href="messages.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <?php if (empty($recentMessages)): ?>
                        <p class="text-muted text-center py-3">No recent inquiries yet</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentMessages as $msg): ?>
                                <a href="messages.php?view=<?php echo $msg['id']; ?>" class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($msg['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($msg['subject'], 0, 30)) . '...'; ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Chart JS Script -->
<script>
    const ctx = document.getElementById('performanceChart').getContext('2d');
    const performanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Site Visits',
                data: [1200, 1900, 3000, 5000, 2000, 3000, 4500, 3800, 5200, 4100, 6000, 5500],
                borderColor: '#10b6d7',
                backgroundColor: 'rgba(16,182,215,0.3)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#10b6d7',
                pointHoverBackgroundColor: '#10b6d7',
                pointHoverBorderColor: '#ffffff'
            }, {
                label: 'Inquiries',
                data: [800, 1200, 1800, 2500, 1500, 2200, 3000, 2800, 3500, 3200, 4000, 3800],
                borderColor: '#89d900',
                backgroundColor: 'rgba(137,217,0,0.3)',
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#89d900',
                pointHoverBackgroundColor: '#89d900',
                pointHoverBorderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 14, weight: '600' } } },
                title: { display: true, text: 'Monthly Performance Trends', font: { size: 18, weight: 'bold' } },
                tooltip: { backgroundColor: 'rgba(3,55,84,0.9)', titleFont: { weight: 'bold' }, bodyFont: { size: 14 } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(3,55,84,0.1)' } },
                x: { grid: { display: false } }
            }
        }
    });
</script>

<!-- AOS Init -->
<script>
    AOS.init({ duration: 800, once: true });
</script>

</body>
</html>