<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Fetch blog posts
$postsStmt = $pdo->query("SELECT * FROM blog_posts ORDER BY date_posted DESC");
$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action == 'add' || $action == 'update') {
            $title = sanitize($_POST['title']);
            $slug = sanitize($_POST['slug']);
            $content = $_POST['content']; // Allow HTML
            $category = sanitize($_POST['category']);
            $salary_package = sanitize($_POST['salary_package'] ?? '');
            $whatsapp_text = sanitize($_POST['whatsapp_text'] ?? '');
            $date_posted = $_POST['date_posted'] ?? date('Y-m-d');
            $read_time = (int)$_POST['read_time'];
            $featured = isset($_POST['featured']) ? 1 : 0;

            $image = '';
            if (isset($_FILES['image']) && $_FILES['image']['name']) {
                $image = uploadImage($_FILES['image']);
            } elseif (isset($_POST['existing_image'])) {
                $image = sanitize($_POST['existing_image']);
            }

            if ($action == 'add') {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, image, category, salary_package, whatsapp_text, date_posted, read_time, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $slug, $content, $image, $category, $salary_package, $whatsapp_text, $date_posted, $read_time, $featured]);
            } elseif ($action == 'update') {
                $id = (int)$_POST['id'];
                $stmt = $pdo->prepare("UPDATE blog_posts SET title=?, slug=?, content=?, image=?, category=?, salary_package=?, whatsapp_text=?, date_posted=?, read_time=?, featured=? WHERE id=?");
                $stmt->execute([$title, $slug, $content, $image, $category, $salary_package, $whatsapp_text, $date_posted, $read_time, $featured, $id]);
            }
        } elseif ($action == 'delete') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
    redirect('blog.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Blog | Believe Travel Consult</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css" rel="stylesheet">
    <!-- TinyMCE CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.0.0/skins/ui/oxide/skin.min.css">
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
        .stat-card {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-light));
            color: var(--white);
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
        .btn-outline-primary {
            border-color: var(--primary-light);
            color: var(--primary-light);
        }
        .btn-outline-primary:hover {
            background: var(--primary-light);
            color: var(--white);
        }
        .table {
            border-radius: 16px;
            overflow: hidden;
        }
        .table thead {
            background: var(--primary-dark);
            color: var(--white);
        }
        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(16,182,215,0.25);
        }
        /* Premium hover effects */
        .table tr:hover {
            background: rgba(16,182,215,0.05);
            transition: background 0.3s;
        }
        .modal-content {
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(3,55,84,0.2);
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-light));
            color: var(--white);
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
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
        <img src="../assets/images/logo_new.png" alt="Logo" width="150" class="mb-3">
        <h5 class="fw-bold">Admin Dashboard</h5>
        <p class="small opacity-75">Welcome, Admin</p>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-home me-2"></i> Dashboard</a></li>
        <li class="nav-item"><a href="offers.php" class="nav-link"><i class="fas fa-briefcase me-2"></i> Manage Offers</a></li>
        <li class="nav-item"><a href="blog.php" class="nav-link active"><i class="fas fa-blog me-2"></i> Manage Blog</a></li>
        <li class="nav-item"><a href="gallery.php" class="nav-link"><i class="fas fa-images me-2"></i> Manage Gallery</a></li>
        <li class="nav-item"><a href="messages.php" class="nav-link"><i class="fas fa-envelope me-2"></i> Messages</a></li>
        <li class="nav-item"><a href="edit_profile.php" class="nav-link"><i class="fas fa-user-cog me-2"></i> Change Password</a></li>
        <li class="nav-item"><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
    </ul>
</nav>

<!-- Main Content -->
<div class="main-content">
    <div class="container-fluid">
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h2 class="fw-bold">Manage Blog Posts</h2>
                <p class="text-muted">Add, edit, or delete premium blog content. Featured posts get top visibility.</p>
            </div>
            <div class="col-auto">
                <button id="addBtn" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#postModal"><i class="fas fa-plus me-2"></i> Add New Post</button>
            </div>
        </div>

        <!-- Posts Table -->
        <div class="card">
            <div class="table-responsive">
                <table id="postsTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date Posted</th>
                            <th>Read Time</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                            <tr><td colspan="8" class="text-center text-muted">No blog posts found. Add one to get started.</td></tr>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <tr>
                                    <td><?php echo $post['id']; ?></td>
                                    <td><img src="../assets/images/<?php echo htmlspecialchars($post['image']); ?>" alt="Post Image" width="50" class="rounded shadow-sm"></td>
                                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                                    <td><?php echo htmlspecialchars($post['category']); ?></td>
                                    <td><?php echo $post['date_posted']; ?></td>
                                    <td><?php echo $post['read_time']; ?> min</td>
                                    <td>
                                        <?php if ($post['featured']): ?>
                                            <span class="badge bg-success"><i class="fas fa-star"></i> Yes</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-2 edit-btn" data-id="<?php echo $post['id']; ?>"><i class="fas fa-edit"></i> Edit</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post? This action cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Post Modal (for Add/Edit) -->
<div class="modal fade" id="postModal" tabindex="-1" aria-labelledby="postModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="postModalLabel">Add New Blog Post</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="postForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="existing_image" value="">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Slug (URL-friendly)</label>
                                <input type="text" name="slug" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="Success Story">Success Story</option>
                                    <option value="Visa Guide">Visa Guide</option>
                                    <option value="Salaries & Jobs">Salaries & Jobs</option>
                                    <option value="Life in Dubai">Life in Dubai</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Salary Package (optional)</label>
                                <input type="text" name="salary_package" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">WhatsApp Text (optional)</label>
                                <input type="text" name="whatsapp_text" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Date Posted</label>
                                <input type="date" name="date_posted" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Read Time (minutes)</label>
                                <input type="number" name="read_time" class="form-control" value="6" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="featured" class="form-check-input" id="featuredCheck">
                                <label class="form-check-label" for="featuredCheck">Featured Post</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Image</label>
                                <input type="file" name="image" class="form-control" accept="image/*" required>
                            </div>
                            <img id="imagePreview" src="" alt="Image Preview" class="img-fluid rounded shadow-sm mb-3" style="display:none; max-height:300px;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Content (Rich Editor)</label>
                        <textarea id="content" name="content" class="form-control" rows="10" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Save Post</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
<!-- TinyMCE JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.0.0/tinymce.min.js"></script>
<script>
    // Posts data for JS
    const posts = <?php echo json_encode($posts); ?>;

    // Initialize DataTable
    new DataTable('#postsTable', {
        responsive: true,
        paging: true,
        searching: true,
        ordering: true,
        order: [[4, 'desc']], // Order by date posted desc
        language: {
            search: 'Filter posts:'
        }
    });

    // Modal events for TinyMCE
    const postModalEl = document.getElementById('postModal');
    postModalEl.addEventListener('shown.bs.modal', () => {
        if (!tinymce.get('content')) {
            tinymce.init({
                selector: '#content',
                height: 500,
                menubar: 'file edit view insert format tools table help',
                plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table paste code help wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                content_style: 'body { font-family: Poppins, sans-serif; font-size: 16px }'
            });
        }
    });
    postModalEl.addEventListener('hidden.bs.modal', () => {
        tinymce.remove('#content');
    });

    // Edit button handler
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const post = posts.find(p => p.id == id);
            if (post) {
                document.getElementById('postModalLabel').textContent = 'Edit Blog Post';
                const form = document.getElementById('postForm');
                form.querySelector('[name="action"]').value = 'update';
                form.querySelector('[name="id"]').value = post.id;
                form.querySelector('[name="title"]').value = post.title;
                form.querySelector('[name="slug"]').value = post.slug;
                form.querySelector('[name="category"]').value = post.category;
                form.querySelector('[name="salary_package"]').value = post.salary_package || '';
                form.querySelector('[name="whatsapp_text"]').value = post.whatsapp_text || '';
                form.querySelector('[name="date_posted"]').value = post.date_posted;
                form.querySelector('[name="read_time"]').value = post.read_time;
                form.querySelector('[name="featured"]').checked = !!post.featured;
                form.querySelector('[name="existing_image"]').value = post.image;
                form.querySelector('[name="image"]').required = false;

                // Set preview
                const preview = document.getElementById('imagePreview');
                preview.src = '../assets/images/' + post.image;
                preview.style.display = 'block';

                // Set content after init, but since shown event inits, set after show
                postModalEl.addEventListener('shown.bs.modal', () => {
                    tinymce.get('content').setContent(post.content);
                }, { once: true });
            }
        });
    });

    // Add button handler
    document.getElementById('addBtn').addEventListener('click', () => {
        document.getElementById('postModalLabel').textContent = 'Add New Blog Post';
        const form = document.getElementById('postForm');
        form.reset();
        form.querySelector('[name="action"]').value = 'add';
        form.querySelector('[name="id"]').value = '';
        form.querySelector('[name="existing_image"]').value = '';
        form.querySelector('[name="image"]').required = true;
        const preview = document.getElementById('imagePreview');
        preview.src = '';
        preview.style.display = 'none';

        // Clear content after init
        postModalEl.addEventListener('shown.bs.modal', () => {
            tinymce.get('content').setContent('');
        }, { once: true });
    });

    // Image preview on change
    document.querySelector('[name="image"]').addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (ev) => {
                const preview = document.getElementById('imagePreview');
                preview.src = ev.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });
</script>

</body>
</html>