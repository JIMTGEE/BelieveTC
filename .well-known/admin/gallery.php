<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) redirect('login.php');

// Upload handling (same secure logic, enhanced feedback)
$msg = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gallery_files'])) {
    $files = $_FILES['gallery_files'];
    $titles = $_POST['title'] ?? [];
    $descs  = $_POST['description'] ?? [];

    $allowed = ['jpg','jpeg','png','gif','webp','mp4','webm','mov','avi'];
    $dir = '../assets/gallery/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $success_count = 0;
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== 0) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;

        $new_name = 'gallery_' . uniqid() . '.' . $ext;
        $dest = $dir . $new_name;
        $type = in_array($ext, ['mp4','webm','mov','avi']) ? 'video' : 'image';

        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $title = trim($titles[$i] ?? ucwords(str_replace(['_', '-'], ' ', pathinfo($files['name'][$i], PATHINFO_FILENAME))));
            $desc  = trim($descs[$i] ?? '');
            $pdo->prepare("INSERT INTO gallery (title, description, file_path, type) VALUES (?, ?, ?, ?)")
                ->execute([$title, $desc, 'assets/gallery/' . $new_name, $type]);
            $success_count++;
        }
    }
    $msg = "$success_count file(s) uploaded successfully!";
}

// Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $file = $pdo->query("SELECT file_path FROM gallery WHERE id = $id")->fetchColumn();
    if ($file && file_exists('../' . $file)) unlink('../' . $file);
    $pdo->query("DELETE FROM gallery WHERE id = $id");
    $msg = "Deleted successfully!";
    header("Location: gallery.php?$msg");
    exit;
}

// Bulk delete
if (isset($_POST['bulk_delete']) && !empty($_POST['selected'])) {
    foreach ($_POST['selected'] as $id) {
        $id = (int)$id;
        $file = $pdo->query("SELECT file_path FROM gallery WHERE id = $id")->fetchColumn();
        if ($file && file_exists('../' . $file)) unlink('../' . $file);
        $pdo->query("DELETE FROM gallery WHERE id = $id");
    }
    $msg = "Selected items deleted!";
}

// Fetch items
$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT * FROM gallery";
if ($filter === 'images') $sql .= " WHERE type = 'image'";
if ($filter === 'videos') $sql .= " WHERE type = 'video'";
$sql .= " ORDER BY uploaded_at DESC";
$items = $pdo->query($sql)->fetchAll();
$total = count($items);
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Pro • Believe Travel Consult Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #033754;
            --accent: #89d900;
            --secondary: #10b6d7;
            --dark: #0a2d42;
            --glass: rgba(255,255,255,0.15);
            --gradient: linear-gradient(135deg, #033754, #0a6c8a);
        }
        [data-theme="dark"] {
            --primary: #0f4973;
            --glass: rgba(0,0,0,0.4);
            background: #111; color: #eee;
        }
        body { font-family: 'Poppins', sans-serif; background: var(--gradient); min-height: 100vh; }
        .glass-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .sidebar { background: rgba(3,55,84,0.95); backdrop-filter: blur(20px); min-height: 100vh; }
        .nav-link { color: rgba(255,255,255,0.8); border-radius: 16px; padding: 1rem; transition: all 0.4s; }
        .nav-link:hover, .nav-link.active { background: var(--accent); color: var(--primary) !important; transform: translateY(-4px); }
        .drop-zone {
            border: 3px dashed var(--secondary);
            background: rgba(16,182,215,0.1);
            border-radius: 24px;
            padding: 4rem;
            transition: all 0.4s;
        }
        .drop-zone.dragover { background: rgba(137,217,0,0.2); border-color: var(--accent); transform: scale(1.02); }
        .gallery-item {
            transition: all 0.4s;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .gallery-item:hover {
            transform: translateY(-15px) scale(1.03);
            box-shadow: 0 25px 50px rgba(137,217,0,0.4);
        }
        .tilt { transition: transform 0.6s; }
        .form-check-input:checked { background-color: var(--accent); border-color: var(--accent); }
        .badge-pro { background: linear-gradient(45deg, #89d900, #10b6d7); color: white; }
    </style>
</head>
<body class="text-white">

<div class="container-fluid p-0">
    <div class="row g-0">
        <!-- Sidebar -->
        <div class="col-lg-2 sidebar position-fixed h-100 p-4">
            <div class="text-center mb-5">
                <img src="../assets/images/logo_new.png" width="160" class="mb-3">
                <h5 class="fw-bold">Admin Pro</h5>
            </div>
            <ul class="nav flex-column">
                <li><a href="dashboard.php" class="nav-link"><i class="fas fa-home me-3"></i> Dashboard</a></li>
                <li><a href="offers.php" class="nav-link"><i class="fas fa-briefcase me-3"></i> Offers</a></li>
                <li><a href="blog.php" class="nav-link"><i class="fas fa-blog me-3"></i> Blog</a></li>
                <li><a href="messages.php" class="nav-link"><i class="fas fa-envelope me-3"></i> Messages</a></li>
                <li><a href="gallery.php" class="nav-link active"><i class="fas fa-images me-3"></i> Gallery Pro</a></li>
                <li><a href="edit_profile.php" class="nav-link"><i class="fas fa-user-cog me-3"></i> Change Password</a></li>
                <li><a href="logout.php" class="nav-link text-danger"><i class="fas fa-power-off me-3"></i> Logout</a></li>
            </ul>
            <div class="mt-5 text-center">
                <button onclick="document.documentElement.setAttribute('data-theme', document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark')" 
                        class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="fas fa-moon"></i> Dark Mode
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-10 offset-lg-2 p-5">
            <div class="text-center mb-5">
                <h1 class="display-4 fw-bold" style="background: linear-gradient(90deg, #89d900, #10b6d7); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                    Gallery Pro Manager
                </h1>
                <p class="lead opacity-90">Upload unlimited photos & videos • Real-time preview • Drag & drop</p>
                <span class="badge badge-pro fs-5 px-4 py-2">Total Items: <?= $total ?></span>
            </div>

            <?php if ($msg): ?>
                <div class="alert alert-success glass-card border-0 text-center py-4">
                    <i class="fas fa-check-circle fa-2x mb-3 text-success"></i><br><?= $msg ?>
                </div>
            <?php endif; ?>

            <!-- Upload Zone -->
            <div class="glass-card p-5 mb-5">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="drop-zone text-center" id="dropZone">
                        <i class="fas fa-cloud-upload-alt fa-5x mb-4" style="color: var(--accent);"></i>
                        <h3>Drop Files Here or Click to Browse</h3>
                        <p class="text-muted">Supports: JPG, PNG, MP4, MOV, WEBM • Max 500MB</p>
                        <input type="file" name="gallery_files[]" id="fileInput" multiple accept="image/*,video/*" class="d-none">
                    </div>
                    <div id="preview" class="row g-4 mt-4"></div>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-lg px-5 py-3 rounded-pill shadow-lg" style="background: var(--accent); color: var(--primary); font-weight:800;">
                            <i class="fas fa-upload me-3"></i> Upload All Files
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filters -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <a href="?filter=all" class="btn <?= $filter==='all'?'btn-light':'btn-outline-light' ?> rounded-pill">All</a>
                    <a href="?filter=images" class="btn <?= $filter==='images'?'btn-light':'btn-outline-light' ?> rounded-pill">Images</a>
                    <a href="?filter=videos" class="btn <?= $filter==='videos'?'btn-light':'btn-outline-light' ?> rounded-pill">Videos</a>
                </div>
                <?php if ($total > 0): ?>
                <form method="POST" onsubmit="return confirm('Delete selected items?')">
                    <input type="hidden" name="bulk_delete" value="1">
                    <button class="btn btn-danger rounded-pill"><i class="fas fa-trash"></i> Delete Selected</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Gallery Grid -->
            <?php if (empty($items)): ?>
                <div class="text-center py-7">
                    <img src="../assets/images/placeholder.svg" width="200" class="mb-4 opacity-50">
                    <h2>No items yet</h2>
                    <p class="lead">Start uploading your success stories!</p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="row g-4">
                        <?php foreach ($items as $i): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="gallery-item tilt">
                                <div class="position-relative overflow-hidden rounded-4">
                                    <?php if ($i['type'] === 'image'): ?>
                                        <img src="../<?= $i['file_path'] ?>" class="w-100" style="height:280px; object-fit:cover;">
                                    <?php else: ?>
                                        <video src="../<?= $i['file_path'] ?>" class="w-100" style="height:280px; object-fit:cover;" muted></video>
                                        <div class="position-absolute top-50 start-50 translate-middle">
                                            <i class="fas fa-play-circle fa-4x text-white opacity-75"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="position-absolute top-0 end-0 p-3">
                                        <input type="checkbox" name="selected[]" value="<?= $i['id'] ?>" class="form-check-input shadow-lg">
                                    </div>
                                    <div class="position-absolute bottom-0 start-0 end-0 p-4 text-white" style="background: linear-gradient(transparent, rgba(0,0,0,0.8));">
                                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($i['title']) ?></h5>
                                        <small><?= date('d M Y', strtotime($i['uploaded_at'])) ?> • <?= ucfirst($i['type']) ?></small>
                                    </div>
                                </div>
                                <div class="p-3 bg-dark bg-opacity-50 text-center">
                                    <a href="?delete=<?= $i['id'] ?>" class="btn btn-sm btn-danger rounded-pill" onclick="return confirm('Delete forever?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanilla-tilt@1.8.0/dist/vanilla-tilt.min.js"></script>
<script>
    VanillaTilt.init(document.querySelectorAll(".tilt"), { max: 15, speed: 400, glare: true, "max-glare": 0.5 });

    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const preview = document.getElementById('preview');

    ['dragover','dragenter'].forEach(e => dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('dragover'); }));
    ['dragleave','dragend','drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('dragover')));
    dropZone.addEventListener('drop', e => { e.preventDefault(); handleFiles(e.dataTransfer.files); });
    dropZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => handleFiles(fileInput.files));

    function handleFiles(files) {
        preview.innerHTML = '';
        [...files].forEach(file => {
            const reader = new FileReader();
            reader.onload = e => {
                const col = document.createElement('div');
                col.className = 'col-md-4';
                col.innerHTML = `
                    <div class="glass-card p-3 text-center">
                        ${file.type.startsWith('video/') 
                            ? `<video src="${e.target.result}" class="rounded" style="max-height:200px;" controls></video>`
                            : `<img src="${e.target.result}" class="rounded" style="max-height:200px;">`
                        }
                        <input type="text" name="title[]" class="form-control mt-3" placeholder="Title" value="${file.name.split('.').slice(0,-1).join('.')}">
                        <textarea name="description[]" class="form-control mt-2" rows="2" placeholder="Description (optional)"></textarea>
                    </div>`;
                preview.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
    }
</script>
</body>
</html>