<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$messages = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$totalMessages = count($messages);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Believe Travel Consult Admin</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/3.0.3/css/responsive.bootstrap5.css">

    <style>
        :root {
            --dark: #033754;
            --blue: #10b6d7;
            --green: #89d900;
            --white: #ffffff;
            --light: #f8f9fa;
            --gray: #6c757d;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--light);
            color: #333;
            margin: 0;
            padding: 0;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: var(--dark);
            color: var(--white);
            padding: 2rem 1.5rem;
            box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar .logo {
            width: 140px;
            margin-bottom: 2rem;
            display: block;
            filter: brightness(0) invert(1);
        }
        .sidebar h4 {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--white);
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.9rem 1.2rem;
            border-radius: 12px;
            margin: 0.4rem 0;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        .nav-link i { margin-right: 12px; width: 20px; }
        .nav-link:hover { background: rgba(255,255,255,0.15); color: var(--green); }
        .nav-link.active {
            background: var(--blue);
            color: var(--dark);
            font-weight: 700;
        }
        .main-content {
            margin-left: 280px;
            padding: 2.5rem;
            min-height: 100vh;
        }
        .page-header {
            background: linear-gradient(135deg, var(--dark), var(--blue));
            color: white;
            padding: 2.5rem;
            border-radius: 18px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(3,55,84,0.2);
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.8rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover { transform: translateY(-8px); }
        .stat-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
        }
        .stat-card p { color: var(--gray); margin: 0.5rem 0 0; }
        .card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .card-header {
            background: var(--dark);
            color: white;
            font-weight: 600;
            padding: 1.2rem 1.8rem;
            border: none;
        }
        .table thead {
            background: var(--dark);
            color: white;
        }
        .table tbody tr:hover {
            background: #f0f8ff;
        }
        .btn-green {
            background: var(--green);
            color: var(--dark);
            border: none;
            font-weight: 600;
        }
        .btn-green:hover { background: #7cc400; color: white; }
        .badge-new {
            background: #e63946;
            font-size: 0.7rem;
            padding: 0.4em 0.8em;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--dark), var(--blue));
            color: white;
        }
        @media (max-width: 992px) {
            .sidebar { 
                width: 100%; 
                height: auto; 
                position: relative; 
                padding: 1.5rem;
            }
            .main-content { margin-left: 0; padding: 1.5rem; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="text-center">
        <img src="../assets/images/logo_new.png" alt="Believe Travel Consult" class="logo">
        <h4>Admin Panel</h4>
    </div>
    <nav class="nav flex-column">
        <a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="offers.php" class="nav-link"><i class="fas fa-briefcase"></i> Job Offers</a>
        <a href="blog.php" class="nav-link"><i class="fas fa-blog"></i> Blog Posts</a>
        <a href="gallery.php" class="nav-link"><i class="fas fa-images"></i> Gallery</a>
        <a href="messages.php" class="nav-link active"><i class="fas fa-envelope"></i> Messages <span class="badge bg-danger ms-2"><?= $totalMessages ?></span></a>
        <a href="edit_profile.php" class="nav-link"><i class="fas fa-user-cog"></i> Change Password</a>
        <a href="logout.php" class="nav-link text-danger mt-5"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">

    <!-- Page Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-0 fw-bold">Customer Messages</h1>
                <p class="mb-0 opacity-90">Manage all inquiries from your website</p>
            </div>
            <div class="col-md-4 text-md-end">
                <h2 class="mb-0"><?= $totalMessages ?> Total</h2>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="stat-card">
                <h3><?= $totalMessages ?></h3>
                <p>Total Messages</p>
            </div>
        </div>
        <div class="col-md-6">
            <div class="stat-card">
                <h3><?= date('F Y') ?></h3>
                <p>Current Month</p>
            </div>
        </div>
    </div>

    <!-- Messages Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">All Customer Inquiries</h5>
            <form method="POST" class="d-inline" onsubmit="return confirm('Delete selected messages?')">
                <button name="bulk_action" value="delete" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <form method="POST" id="bulkForm">
                    <table id="messagesTable" class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll"></th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Preview</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                                    <p class="text-muted">No messages yet</p>
                                </td>
                            </tr>
                            <?php else: foreach ($messages as $msg): ?>
                            <tr>
                                <td><input type="checkbox" name="selected[]" value="<?= $msg['id'] ?>"></td>
                                <td><?= $msg['id'] ?></td>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars($msg['name']) ?>
                                    <span class="badge badge-new ms-2">NEW</span>
                                </td>
                                <td><a href="mailto:<?= $msg['email'] ?>" class="text-decoration-none"><?= htmlspecialchars($msg['email']) ?></a></td>
                                <td><?= htmlspecialchars($msg['subject']) ?></td>
                                <td><?= htmlspecialchars(substr($msg['message'], 0, 60)) ?>...</td>
                                <td><?= date('d M Y, H:i', strtotime($msg['created_at'])) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary view-msg" 
                                        data-bs-toggle="modal" data-bs-target="#viewModal"
                                        data-name="<?= htmlspecialchars($msg['name']) ?>"
                                        data-email="<?= htmlspecialchars($msg['email']) ?>"
                                        data-subject="<?= htmlspecialchars($msg['subject']) ?>"
                                        data-message="<?= htmlspecialchars($msg['message']) ?>"
                                        data-date="<?= $msg['created_at'] ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="id" value="<?= $msg['id'] ?>">
                                        <button name="delete_single" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this message?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 rounded-4 overflow-hidden">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-envelope me-2"></i> Message Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <span id="modalName" class="fw-bold text-primary"></span></p>
                        <p><strong>Email:</strong> <a href="#" id="modalEmailLink"></a></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Subject:</strong> <span id="modalSubject"></span></p>
                        <p><strong>Received:</strong> <span id="modalDate"></span></p>
                    </div>
                </div>
                <hr>
                <div class="bg-light p-4 rounded">
                    <p id="modalMessage" class="mb-0 lh-lg"></p>
                </div>
                <div class="text-end mt-4">
                    <a href="#" id="replyEmail" class="btn btn-primary me-2"><i class="fas fa-reply"></i> Reply Email</a>
                    <a href="#" id="replyWhatsApp" target="_blank" class="btn btn-success"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
<script>
    new DataTable('#messagesTable', {
        responsive: true,
        order: [[6, 'desc']],
        pageLength: 15,
        columnDefs: [{ orderable: false, targets: 0 }]
    });

    document.getElementById('selectAll').onclick = function() {
        document.querySelectorAll('input[name="selected[]"]').forEach(cb => cb.checked = this.checked);
    };

    document.querySelectorAll('.view-msg').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modalName').textContent = this.dataset.name;
            document.getElementById('modalEmailLink').textContent = this.dataset.email;
            document.getElementById('modalEmailLink').href = 'mailto:' + this.dataset.email;
            document.getElementById('modalSubject').textContent = this.dataset.subject;
            document.getElementById('modalMessage').textContent = this.dataset.message;
            document.getElementById('modalDate').textContent = new Date(this.dataset.date).toLocaleString();

            document.getElementById('replyEmail').href = `mailto:${this.dataset.email}?subject=Re: ${encodeURIComponent(this.dataset.subject)}`;
            
            const phone = this.dataset.email.match(/\d{9,10}/);
            const waLink = phone ? `https://wa.me/233${phone[0].slice(-9)}?text=Hello ${encodeURIComponent(this.dataset.name)}! Thank you for contacting Believe Travel Consult.` : '#';
            document.getElementById('replyWhatsApp').href = waLink;
        });
    });
</script>

</body>
</html>