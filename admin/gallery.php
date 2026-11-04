<?php
// gallery.php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) redirect('login.php');

// Set page title for header
$pageTitle = 'Gallery Pro Manager';

// Upload handling
$msg = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['gallery_files'])) {
    $files = $_FILES['gallery_files'];
    $titles = $_POST['title'] ?? [];
    $descs  = $_POST['description'] ?? [];
    $categories = $_POST['category'] ?? [];

    $allowed = ['jpg','jpeg','png','gif','webp','mp4','webm','mov','avi'];
    $dir = '../assets/gallery/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $success_count = 0;
    $failed_count = 0;
    
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== 0) {
            $failed_count++;
            continue;
        }
        
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $failed_count++;
            continue;
        }

        // Check file size (max 50MB)
        if ($files['size'][$i] > 50 * 1024 * 1024) {
            $failed_count++;
            continue;
        }

        $new_name = 'gallery_' . uniqid() . '.' . $ext;
        $dest = $dir . $new_name;
        $type = in_array($ext, ['mp4','webm','mov','avi']) ? 'video' : 'image';

        if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
            $title = trim($titles[$i] ?? ucwords(str_replace(['_', '-'], ' ', pathinfo($files['name'][$i], PATHINFO_FILENAME))));
            $desc  = trim($descs[$i] ?? '');
            $category = trim($categories[$i] ?? 'General');
            
            $pdo->prepare("INSERT INTO gallery (title, description, file_path, type, category, uploaded_at) VALUES (?, ?, ?, ?, ?, NOW())")
                ->execute([$title, $desc, 'assets/gallery/' . $new_name, $type, $category]);
            $success_count++;
        } else {
            $failed_count++;
        }
    }
    
    if ($success_count > 0 && $failed_count == 0) {
        $msg = "$success_count file(s) uploaded successfully!";
    } elseif ($success_count > 0 && $failed_count > 0) {
        $msg = "$success_count file(s) uploaded, $failed_count failed.";
    } else {
        $error = "Upload failed. Please check file types and sizes.";
    }
}

// Delete single item
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $file = $pdo->query("SELECT file_path FROM gallery WHERE id = $id")->fetchColumn();
    if ($file && file_exists('../' . $file)) unlink('../' . $file);
    $pdo->query("DELETE FROM gallery WHERE id = $id");
    $msg = "Item deleted successfully!";
    header("Location: gallery.php?msg=" . urlencode($msg));
    exit;
}

// Bulk delete
if (isset($_POST['bulk_delete']) && !empty($_POST['selected'])) {
    $deleted_count = 0;
    foreach ($_POST['selected'] as $id) {
        $id = (int)$id;
        $file = $pdo->query("SELECT file_path FROM gallery WHERE id = $id")->fetchColumn();
        if ($file && file_exists('../' . $file)) unlink('../' . $file);
        $pdo->query("DELETE FROM gallery WHERE id = $id");
        $deleted_count++;
    }
    $msg = "$deleted_count item(s) deleted successfully!";
    header("Location: gallery.php?msg=" . urlencode($msg));
    exit;
}

// Update item (AJAX support)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $id = (int)$_POST['item_id'];
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'General');
    
    $upd = $pdo->prepare("UPDATE gallery SET title = ?, description = ?, category = ? WHERE id = ?");
    $upd->execute([$title, $description, $category, $id]);
    $msg = "Item updated successfully!";
    header("Location: gallery.php?msg=" . urlencode($msg));
    exit;
}

// Fetch items with filters
$filter = $_GET['filter'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM gallery WHERE 1=1";
$params = [];

if ($filter === 'images') {
    $sql .= " AND type = 'image'";
} elseif ($filter === 'videos') {
    $sql .= " AND type = 'video'";
}

if ($category_filter !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY uploaded_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();
$total = count($items);

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM gallery ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get statistics
$total_images = $pdo->query("SELECT COUNT(*) FROM gallery WHERE type = 'image'")->fetchColumn();
$total_videos = $pdo->query("SELECT COUNT(*) FROM gallery WHERE type = 'video'")->fetchColumn();
$recent_uploads = $pdo->query("SELECT COUNT(*) FROM gallery WHERE uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
?>

<?php include 'admin_header.php'; ?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div data-aos="fade-right">
                <h1 class="fw-bold mb-2">
                    <i class="fas fa-images me-2 text-primary"></i>Gallery Pro Manager
                </h1>
                <p class="text-muted">Upload, manage, and organize your media files</p>
            </div>
            <div class="d-flex gap-2" data-aos="fade-left">
                <span class="badge bg-primary bg-opacity-10 text-primary p-3 rounded-pill">
                    <i class="fas fa-image me-1"></i> <?= $total_images ?> Images
                </span>
                <span class="badge bg-info bg-opacity-10 text-info p-3 rounded-pill">
                    <i class="fas fa-video me-1"></i> <?= $total_videos ?> Videos
                </span>
                <span class="badge bg-success bg-opacity-10 text-success p-3 rounded-pill">
                    <i class="fas fa-clock me-1"></i> <?= $recent_uploads ?> New
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($msg): ?>
    <div class="alert alert-success rounded-4 d-flex align-items-center" data-aos="fade-up">
        <i class="fas fa-check-circle fa-2x me-3"></i>
        <div><?= htmlspecialchars($msg) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4 d-flex align-items-center" data-aos="fade-up">
        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
        <div><?= htmlspecialchars($error) ?></div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Upload Section -->
<div class="card mb-4" data-aos="fade-up">
    <div class="card-body p-4">
        <h5 class="card-title fw-bold mb-3">
            <i class="fas fa-cloud-upload-alt me-2 text-primary"></i>Upload New Media
        </h5>
        
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="drop-zone text-center p-5 border-2 border-dashed rounded-4" 
                 id="dropZone" 
                 style="border-color: var(--primary-light); background: var(--bg-light); transition: all 0.3s;">
                <i class="fas fa-cloud-upload-alt fa-4x mb-3" style="color: var(--primary-light);"></i>
                <h5 class="fw-bold">Drop Files Here or Click to Browse</h5>
                <p class="text-muted small">Supports: JPG, PNG, GIF, WEBP, MP4, WEBM, MOV • Max 50MB per file</p>
                <input type="file" name="gallery_files[]" id="fileInput" multiple accept="image/*,video/*" class="d-none">
                <button type="button" class="btn btn-primary rounded-pill mt-2" onclick="document.getElementById('fileInput').click()">
                    <i class="fas fa-folder-open me-2"></i>Choose Files
                </button>
            </div>
            
            <div id="preview" class="row g-3 mt-4"></div>
            
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5" id="uploadBtn">
                    <i class="fas fa-upload me-2"></i>Upload <span id="fileCount">0</span> File(s)
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Filter & Search Bar -->
<div class="card mb-4" data-aos="fade-up" data-aos-delay="100">
    <div class="card-body p-3">
        <div class="row g-3 align-items-center">
            <div class="col-lg-6">
                <div class="d-flex flex-wrap gap-2">
                    <a href="?filter=all&category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search) ?>" 
                       class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill btn-sm">
                        <i class="fas fa-th me-1"></i>All
                    </a>
                    <a href="?filter=images&category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search) ?>" 
                       class="btn <?= $filter === 'images' ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill btn-sm">
                        <i class="fas fa-image me-1"></i>Images
                    </a>
                    <a href="?filter=videos&category=<?= urlencode($category_filter) ?>&search=<?= urlencode($search) ?>" 
                       class="btn <?= $filter === 'videos' ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill btn-sm">
                        <i class="fas fa-video me-1"></i>Videos
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm rounded-pill" onchange="window.location.href='?filter=<?= urlencode($filter) ?>&category='+this.value+'&search=<?= urlencode($search) ?>'">
                        <option value="all">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <form method="GET" class="d-flex gap-2">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                        <input type="hidden" name="category" value="<?= htmlspecialchars($category_filter) ?>">
                        <input type="text" name="search" class="form-control form-control-sm rounded-pill" 
                               placeholder="Search gallery..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Gallery Grid -->
<?php if (empty($items)): ?>
    <div class="text-center py-5" data-aos="fade-up">
        <i class="fas fa-images fa-5x text-muted mb-4 opacity-25"></i>
        <h4 class="text-muted">No gallery items found</h4>
        <p class="text-muted">Upload your first media file to get started</p>
    </div>
<?php else: ?>
    <form method="POST" id="bulkForm" onsubmit="return confirm('Delete selected items permanently?')">
        <input type="hidden" name="bulk_delete" value="1">
        
        <div class="row g-4">
            <?php foreach ($items as $item): ?>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= $loop->index * 50 ?>">
                <div class="card gallery-item h-100">
                    <div class="position-relative overflow-hidden" style="height: 260px; background: #f8f9fa;">
                        <?php if ($item['type'] === 'image'): ?>
                            <img src="../<?= htmlspecialchars($item['file_path']) ?>" 
                                 class="w-100 h-100 object-fit-cover" 
                                 alt="<?= htmlspecialchars($item['title']) ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <video src="../<?= htmlspecialchars($item['file_path']) ?>" 
                                   class="w-100 h-100 object-fit-cover" 
                                   muted preload="metadata"></video>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <i class="fas fa-play-circle fa-4x text-white opacity-75"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="position-absolute top-0 end-0 p-3">
                            <input type="checkbox" name="selected[]" value="<?= $item['id'] ?>" 
                                   class="form-check-input form-check-input-lg border-2 shadow">
                        </div>
                        
                        <div class="position-absolute top-0 start-0 p-3">
                            <span class="badge bg-dark bg-opacity-75 rounded-pill">
                                <?= ucfirst($item['type']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <h6 class="card-title fw-bold mb-1"><?= htmlspecialchars($item['title']) ?></h6>
                        <?php if (!empty($item['description'])): ?>
                            <p class="card-text small text-muted mb-2"><?= htmlspecialchars(substr($item['description'], 0, 100)) ?></p>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-light text-dark rounded-pill">
                                    <i class="fas fa-tag me-1"></i><?= htmlspecialchars($item['category'] ?? 'General') ?>
                                </span>
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-clock me-1"></i><?= date('M d, Y', strtotime($item['uploaded_at'])) ?>
                                </small>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-outline-primary btn-sm rounded-pill" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editModal<?= $item['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $item['id'] ?>" 
                                   class="btn btn-outline-danger btn-sm rounded-pill" 
                                   onclick="return confirm('Delete this item permanently?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Modal -->
            <div class="modal fade" id="editModal<?= $item['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fas fa-edit me-2 text-primary"></i>Edit Gallery Item</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="update_item" value="1">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Title</label>
                                    <input type="text" name="title" class="form-control" 
                                           value="<?= htmlspecialchars($item['title']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Description</label>
                                    <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($item['description']) ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Category</label>
                                    <input type="text" name="category" class="form-control" 
                                           value="<?= htmlspecialchars($item['category'] ?? 'General') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Preview</label>
                                    <?php if ($item['type'] === 'image'): ?>
                                        <img src="../<?= htmlspecialchars($item['file_path']) ?>" 
                                             class="img-fluid rounded" 
                                             alt="<?= htmlspecialchars($item['title']) ?>">
                                    <?php else: ?>
                                        <video src="../<?= htmlspecialchars($item['file_path']) ?>" 
                                               class="img-fluid rounded" controls></video>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary rounded-pill">
                                    <i class="fas fa-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total > 0): ?>
        <div class="mt-4 d-flex justify-content-between align-items-center" data-aos="fade-up">
            <div class="text-muted">
                <i class="fas fa-images me-1"></i> <?= $total ?> items found
            </div>
            <button type="submit" class="btn btn-danger rounded-pill">
                <i class="fas fa-trash-alt me-2"></i>Delete Selected
            </button>
        </div>
        <?php endif; ?>
    </form>
<?php endif; ?>

<!-- JavaScript Enhancements -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // File upload handling
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const preview = document.getElementById('preview');
    const fileCount = document.getElementById('fileCount');
    
    // Drag and drop events
    ['dragover', 'dragenter'].forEach(event => {
        dropZone.addEventListener(event, (e) => {
            e.preventDefault();
            dropZone.style.borderColor = 'var(--accent-green)';
            dropZone.style.background = 'rgba(137,217,0,0.05)';
        });
    });
    
    ['dragleave', 'dragend', 'drop'].forEach(event => {
        dropZone.addEventListener(event, (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '';
            dropZone.style.background = '';
        });
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        handleFiles(e.dataTransfer.files);
    });
    
    dropZone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => handleFiles(fileInput.files));
    
    // Handle file preview
    function handleFiles(files) {
        preview.innerHTML = '';
        let totalFiles = files.length;
        fileCount.textContent = totalFiles;
        
        Array.from(files).forEach((file, index) => {
            // Validate file size
            if (file.size > 50 * 1024 * 1024) {
                showToast('File "' + file.name + '" exceeds 50MB limit', 'danger');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (e) => {
                const col = document.createElement('div');
                col.className = 'col-md-4 col-lg-3';
                
                const isVideo = file.type.startsWith('video/');
                const previewHtml = isVideo 
                    ? `<video src="${e.target.result}" class="img-fluid rounded" style="height:150px;object-fit:cover;" muted></video>`
                    : `<img src="${e.target.result}" class="img-fluid rounded" style="height:150px;object-fit:cover;" alt="Preview">`;
                
                col.innerHTML = `
                    <div class="card">
                        <div class="card-body p-3">
                            ${previewHtml}
                            <input type="text" name="title[]" class="form-control form-control-sm mt-2" 
                                   placeholder="Title" value="${file.name.split('.').slice(0,-1).join('.').replace(/[_-]/g, ' ')}">
                            <input type="text" name="category[]" class="form-control form-control-sm mt-1" 
                                   placeholder="Category (e.g., Travel, Adventure)">
                            <textarea name="description[]" class="form-control form-control-sm mt-1" 
                                      rows="2" placeholder="Description (optional)"></textarea>
                            <small class="text-muted d-block mt-1">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                        </div>
                    </div>
                `;
                preview.appendChild(col);
            };
            reader.readAsDataURL(file);
        });
    }
    
    // Show toast notifications
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <i class="fas fa-${type === 'danger' ? 'exclamation-circle' : 'check-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
    
    // Select all checkbox (optional)
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => cb.checked = this.checked);
        });
    }
    
    // Auto-submit on filter change
    document.querySelectorAll('.filter-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            // Already handled by href
        });
    });
});
</script>

<style>
/* Additional styles for gallery */
.drop-zone {
    transition: all 0.3s ease;
    cursor: pointer;
}

.drop-zone:hover {
    border-color: var(--accent-green) !important;
    background: rgba(137, 217, 0, 0.05) !important;
}

.gallery-item {
    transition: all 0.3s ease;
    overflow: hidden;
}

.gallery-item:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-hover);
}

.object-fit-cover {
    object-fit: cover;
}

/* Modal styles */
.modal-content {
    border-radius: 20px;
    border: none;
}

/* Video play button overlay */
.position-absolute .fa-play-circle {
    text-shadow: 0 4px 12px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
    cursor: pointer;
}

.position-absolute .fa-play-circle:hover {
    transform: scale(1.1);
    color: var(--accent-green);
}

/* Checkbox styling */
.form-check-input {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.form-check-input:checked {
    background-color: var(--accent-green);
    border-color: var(--accent-green);
}
</style>

<?php include 'admin_footer.php'; ?>