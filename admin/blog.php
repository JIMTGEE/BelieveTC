<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

/* =========================================================================
   HELPERS
   ========================================================================= */

// Turns any string into a clean, URL-safe slug.
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    if (function_exists('iconv')) {
        $converted = @iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        if ($converted !== false) $text = $converted;
    }
    $text = strtolower($text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'post';
}

function slugExists(PDO $pdo, string $slug, int $excludeId = 0): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE slug = ? AND id != ?");
    $stmt->execute([$slug, $excludeId]);
    return (int) $stmt->fetchColumn() > 0;
}

// Appends -2, -3, etc. until the slug is free (so posts never collide silently).
function uniqueSlug(PDO $pdo, string $base, int $excludeId = 0): string {
    $slug = $base;
    $i = 2;
    while (slugExists($pdo, $slug, $excludeId)) {
        $slug = $base . '-' . $i;
        $i++;
    }
    return $slug;
}

function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/* =========================================================================
   AJAX: quick "toggle featured" star from the table (no full page reload)
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_featured') {
    header('Content-Type: application/json');
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT featured FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            echo json_encode(['success' => false, 'error' => 'Post not found.']);
            exit;
        }
        $new = $current ? 0 : 1;
        $upd = $pdo->prepare("UPDATE blog_posts SET featured = ? WHERE id = ?");
        $upd->execute([$new, $id]);
        echo json_encode(['success' => true, 'featured' => $new]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Could not update the post.']);
    }
    exit;
}

/* =========================================================================
   Add / Update / Delete
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'update') {
        $title               = trim(sanitize($_POST['title'] ?? ''));
        $slugInput           = trim(sanitize($_POST['slug'] ?? ''));
        $content             = $_POST['content'] ?? ''; // Allow HTML -- comes from the rich editor
        $category            = trim(sanitize($_POST['category'] ?? ''));
        $destination_country = trim(sanitize($_POST['destination_country'] ?? ''));
        $salary_package      = sanitize($_POST['salary_package'] ?? '');
        $whatsapp_text       = sanitize($_POST['whatsapp_text'] ?? '');
        $date_posted         = $_POST['date_posted'] ?? date('Y-m-d');
        $read_time           = max(1, (int) ($_POST['read_time'] ?? 1));
        $featured            = isset($_POST['featured']) ? 1 : 0;
        $id                  = (int) ($_POST['id'] ?? 0);

        $errors = [];
        if ($title === '') $errors[] = 'Title is required.';
        if ($category === '') $errors[] = 'Category is required.';
        if (trim(strip_tags($content)) === '') $errors[] = 'Content cannot be empty.';

        // Always derive a clean, guaranteed-unique slug rather than trusting raw input
        $slugBase = slugify($slugInput !== '' ? $slugInput : $title);
        $slug     = uniqueSlug($pdo, $slugBase, $action === 'update' ? $id : 0);

        // Image handling
        $image = sanitize($_POST['existing_image'] ?? '');
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'The image failed to upload. Please try again.';
            } else {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedTypes, true)) {
                    $errors[] = 'Image must be a JPEG, PNG, GIF, or WEBP file.';
                } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                    $errors[] = 'Image must be smaller than 5MB.';
                } else {
                    $image = uploadImage($_FILES['image']);
                }
            }
        }
        if ($action === 'add' && $image === '') {
            $errors[] = 'A cover image is required.';
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
        } elseif ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, slug, content, image, category, destination_country, salary_package, whatsapp_text, date_posted, read_time, featured) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $content, $image, $category, $destination_country, $salary_package, $whatsapp_text, $date_posted, $read_time, $featured]);
            flash('success', 'Blog post "' . $title . '" was published.');
        } else {
            $stmt = $pdo->prepare("UPDATE blog_posts SET title=?, slug=?, content=?, image=?, category=?, destination_country=?, salary_package=?, whatsapp_text=?, date_posted=?, read_time=?, featured=? WHERE id=?");
            $stmt->execute([$title, $slug, $content, $image, $category, $destination_country, $salary_package, $whatsapp_text, $date_posted, $read_time, $featured, $id]);
            flash('success', 'Blog post "' . $title . '" was updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT title, image FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $del = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $del->execute([$id]);

            // Clean up the image file on disk, but only if no other post still uses it
            // (protects shared/reused images from being deleted out from under them).
            if (!empty($row['image'])) {
                $chk = $pdo->prepare("SELECT COUNT(*) FROM blog_posts WHERE image = ?");
                $chk->execute([$row['image']]);
                if ((int) $chk->fetchColumn() === 0) {
                    $imagePath = __DIR__ . '/../assets/images/' . $row['image'];
                    if (is_file($imagePath)) {
                        @unlink($imagePath);
                    }
                }
            }
            flash('success', 'Blog post "' . $row['title'] . '" was deleted and removed from the public site.');
        } else {
            flash('error', 'That post could not be found -- it may have already been deleted.');
        }
    }
    redirect('blog.php');
}

/* =========================================================================
   DATA LAYER
   ========================================================================= */
$postsStmt = $pdo->query("SELECT * FROM blog_posts ORDER BY date_posted DESC, id DESC");
$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPosts     = count($posts);
$featuredCount  = 0;
$totalReadTime  = 0;
$categoryCounts = [];
$countryCounts  = [];
foreach ($posts as $p) {
    if ($p['featured']) $featuredCount++;
    $totalReadTime += (int) $p['read_time'];
    $cat = $p['category'] !== '' ? $p['category'] : 'Uncategorized';
    $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
    if (!empty($p['destination_country'])) {
        $countryCounts[$p['destination_country']] = ($countryCounts[$p['destination_country']] ?? 0) + 1;
    }
}
$avgReadTime  = $totalPosts > 0 ? round($totalReadTime / $totalPosts, 1) : 0;

// Seed sensible defaults so the category picker isn't empty on a brand new
// site, then merge in whatever categories are actually in use. This list
// feeds the datalist below AND the filter pills -- and every category that
// shows up here automatically becomes a live filter tab on the public
// blog.php page, with no code changes required on either side.
// Updated to match Believe Travel Consult's global service lines rather
// than a single UAE/Dubai focus.
$defaultCategories = [
    'Success Story',
    'International Recruitment',
    'Visa & Immigration',
    'Travel & Tourism',
    'Relocation Services',
    'Corporate Travel',
    'Student Travel',
    'Ghana Services',
];
$categoryList = array_values(array_unique(array_merge($defaultCategories, array_keys($categoryCounts))));
sort($categoryList);

// Same idea for destinations -- seed the full "Countries We Serve" list from
// the rebrand brief, then merge in anything already tagged on real posts so
// the datalist always reflects both the target portfolio and actual usage.
$defaultCountries = [
    'United Arab Emirates', 'Saudi Arabia', 'Qatar', 'Kuwait', 'Oman', 'Bahrain',
    'United Kingdom', 'Germany', 'Poland', 'Romania', 'Croatia', 'Hungary',
    'Canada', 'Australia', 'Turkey', 'Malaysia', 'Singapore', 'Thailand',
    'Egypt', 'Georgia', 'Azerbaijan',
];
$countryList = array_values(array_unique(array_merge($defaultCountries, array_keys($countryCounts))));
sort($countryList);

// One-time flash message (success/error banner)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Page-specific setup for the shared header
$pageTitle = 'Manage Blog Posts';
$extraHead = <<<HTML
<link href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.0.0/skins/ui/oxide/skin.min.css">
<style>
    .post-thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 10px; }
    .category-pill { cursor: pointer; border: none; }
    .category-pill.active { background: var(--primary-light) !important; color: #fff !important; }
    .featured-toggle { cursor: pointer; border: none; background: none; font-size: 1.15rem; line-height: 1; padding: 0; }
    .featured-toggle.is-featured { color: #ffc107; }
    .featured-toggle:not(.is-featured) { color: #d5dbe0; }
    .featured-toggle:disabled { opacity: 0.5; cursor: wait; }
    #imageDropZone { border: 2px dashed rgba(3,55,84,0.2); border-radius: 14px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all .2s ease; }
    #imageDropZone:hover, #imageDropZone.dragover { border-color: var(--primary-light); background: rgba(16,182,215,0.05); }
    .word-count-pill { font-size: 0.75rem; }
    .slug-input-group .form-control:disabled { background: var(--bg-light); }
    #previewBody img { max-width: 100%; border-radius: 12px; }
    #previewBody { line-height: 1.7; }
    .live-url-group .form-control:disabled { background: var(--bg-light); font-size: 0.85rem; }
    .slug-warning { display:none; }
</style>
HTML;

include 'admin_header.php';
?>

        <!-- Header + Add button -->
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h2 class="fw-bold display-6" style="color:#033754;">Manage Blog Posts</h2>
                <p class="text-muted lead mb-0">Add, edit, or feature premium blog content across every destination we serve.</p>
            </div>
            <div class="col-auto">
                <button id="addBtn" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#postModal"><i class="fas fa-plus me-2"></i> Add New Post</button>
            </div>
        </div>

        <!-- Flash message -->
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert" data-aos="fade-in">
                <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation'; ?> me-2"></i>
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Mini stats -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3" data-aos="fade-up">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(16,182,215,0.12); color:var(--primary-light);"><i class="fas fa-blog"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $totalPosts; ?></h4>
                        <small class="text-muted">Total Posts</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(255,193,7,0.15); color:#b8860b;"><i class="fas fa-star"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $featuredCount; ?></h4>
                        <small class="text-muted">Featured</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(137,217,0,0.15); color:#5c9200;"><i class="fas fa-tags"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo count($categoryCounts); ?></h4>
                        <small class="text-muted">Categories In Use</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(3,55,84,0.08); color:var(--primary-dark);"><i class="fas fa-earth-americas"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo count($countryCounts); ?></h4>
                        <small class="text-muted">Destinations Covered</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posts Table -->
        <div class="card p-4">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2 px-2">
                <span class="fw-semibold small text-muted me-1">Category:</span>
                <button class="btn btn-sm btn-outline-primary rounded-pill category-pill active" data-category="">All</button>
                <?php foreach ($categoryList as $cat): if (!isset($categoryCounts[$cat])) continue; ?>
                    <button class="btn btn-sm btn-outline-primary rounded-pill category-pill" data-category="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></button>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($countryCounts)): ?>
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3 px-2">
                <span class="fw-semibold small text-muted me-1">Destination:</span>
                <button class="btn btn-sm btn-outline-secondary rounded-pill country-pill active" data-country="">All</button>
                <?php foreach (array_keys($countryCounts) as $country): ?>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill country-pill" data-country="<?php echo htmlspecialchars($country); ?>"><?php echo htmlspecialchars($country); ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table id="postsTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Destination</th>
                            <th>Date Posted</th>
                            <th>Read Time</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($posts)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-5">No blog posts found. Add one to get started.</td></tr>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <tr id="post-row-<?php echo $post['id']; ?>">
                                    <td><?php echo $post['id']; ?></td>
                                    <td><img src="../assets/images/<?php echo htmlspecialchars($post['image']); ?>" alt="Post Image" class="post-thumb shadow-sm"></td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($post['title']); ?></td>
                                    <td><span class="badge rounded-pill" style="background:rgba(16,182,215,0.12); color:var(--primary-light);"><?php echo htmlspecialchars($post['category']); ?></span></td>
                                    <td>
                                        <?php if (!empty($post['destination_country'])): ?>
                                            <span class="badge rounded-pill" style="background:rgba(3,55,84,0.08); color:#033754;"><?php echo htmlspecialchars($post['destination_country']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($post['date_posted'])); ?></td>
                                    <td><?php echo (int) $post['read_time']; ?> min</td>
                                    <td class="text-center">
                                        <button class="featured-toggle <?php echo $post['featured'] ? 'is-featured' : ''; ?>" data-id="<?php echo $post['id']; ?>" title="Toggle featured">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    </td>
                                    <td class="text-nowrap">
                                        <a href="../blog_post.php?slug=<?php echo urlencode($post['slug']); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info me-1" title="View live on the public site"><i class="fas fa-arrow-up-right-from-square"></i></a>
                                        <button class="btn btn-sm btn-outline-secondary me-1 preview-btn" data-id="<?php echo $post['id']; ?>" title="Quick preview"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-primary me-1 edit-btn" data-id="<?php echo $post['id']; ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" class="delete-form">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" data-title="<?php echo htmlspecialchars($post['title'], ENT_QUOTES); ?>"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
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
                                <label class="form-label fw-semibold d-flex justify-content-between">
                                    <span>Title</span>
                                    <small class="text-muted"><span id="titleCount">0</span> chars</small>
                                </label>
                                <input type="text" name="title" id="titleInput" class="form-control" maxlength="150" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Slug (URL-friendly)</label>
                                <div class="input-group slug-input-group">
                                    <input type="text" name="slug" id="slugInput" class="form-control" disabled>
                                    <button type="button" class="btn btn-outline-secondary" id="slugLockBtn" title="Edit slug manually"><i class="fas fa-lock"></i></button>
                                </div>
                                <small class="text-muted">Auto-generated from the title. Click the lock to edit it yourself.</small>
                                <small class="text-warning d-block slug-warning" id="slugWarning"><i class="fas fa-triangle-exclamation me-1"></i>Changing the slug on a published post breaks any links already shared to it.</small>
                            </div>
                            <div class="mb-3 live-url-group" id="liveUrlGroup" style="display:none;">
                                <label class="form-label fw-semibold">Public URL</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="liveUrlInput" disabled>
                                    <button type="button" class="btn btn-outline-secondary" id="copyLiveUrlBtn" title="Copy link"><i class="fas fa-copy"></i></button>
                                    <a href="#" target="_blank" rel="noopener" class="btn btn-outline-info" id="openLiveUrlBtn" title="Open live page"><i class="fas fa-arrow-up-right-from-square"></i></a>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Category</label>
                                    <input type="text" name="category" id="categoryInput" class="form-control" list="categoryOptions" placeholder="e.g. Visa & Immigration" required>
                                    <datalist id="categoryOptions">
                                        <?php foreach ($categoryList as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Destination Country</label>
                                    <input type="text" name="destination_country" id="destinationInput" class="form-control" list="countryOptions" placeholder="e.g. United Kingdom">
                                    <datalist id="countryOptions">
                                        <?php foreach ($countryList as $country): ?>
                                            <option value="<?php echo htmlspecialchars($country); ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                            <small class="text-muted d-block mb-3" style="margin-top:-0.75rem;">Pick an existing category/destination, or type a new one -- both appear as live filters on the public blog automatically.</small>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Salary Package (optional)</label>
                                <input type="text" name="salary_package" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">WhatsApp Text (optional)</label>
                                <input type="text" name="whatsapp_text" class="form-control">
                            </div>
                            <div class="row">
                                <div class="col-7 mb-3">
                                    <label class="form-label fw-semibold">Date Posted</label>
                                    <input type="date" name="date_posted" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-5 mb-3">
                                    <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                                        <span>Read Time</span>
                                        <button type="button" class="btn btn-sm btn-link p-0" id="autoReadTimeBtn" title="Estimate from content">Auto</button>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" name="read_time" id="readTimeInput" class="form-control" value="6" min="1" required>
                                        <span class="input-group-text">min</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3 form-check form-switch">
                                <input type="checkbox" name="featured" class="form-check-input" id="featuredCheck" role="switch">
                                <label class="form-check-label" for="featuredCheck">Featured Post</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Image</label>
                                <div id="imageDropZone">
                                    <i class="fas fa-cloud-upload-alt fs-3 mb-2 d-block" style="color:var(--primary-light);"></i>
                                    <span class="small text-muted">Click or drag an image here (JPEG, PNG, GIF, WEBP -- max 5MB)</span>
                                    <input type="file" name="image" id="imageInput" class="form-control d-none" accept="image/*">
                                </div>
                            </div>
                            <img id="imagePreview" src="" alt="Image Preview" class="img-fluid rounded shadow-sm mb-3" style="display:none; max-height:300px;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold d-flex justify-content-between align-items-center">
                            <span>Content (Rich Editor)</span>
                            <span class="badge bg-light text-dark word-count-pill" id="wordCountBadge">0 words</span>
                        </label>
                        <textarea id="content" name="content" class="form-control" rows="10"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg" id="postSubmitBtn">Save Post</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal (read-only quick look, styled to match the live public post page) -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="previewModalLabel">Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <img id="previewImage" src="" alt="" class="img-fluid rounded shadow-sm mb-3">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <span class="badge rounded-pill" style="background:rgba(16,182,215,0.12); color:var(--primary-light);" id="previewCategory"></span>
                    <span class="badge rounded-pill" style="background:rgba(3,55,84,0.08); color:#033754; display:none;" id="previewCountry"></span>
                    <span id="previewFeaturedBadge" class="badge bg-warning text-dark" style="display:none;"><i class="fas fa-star me-1"></i>Featured</span>
                    <small class="text-muted" id="previewMeta"></small>
                </div>
                <h3 class="fw-bold" id="previewTitle"></h3>
                <div id="previewSalaryBox" class="alert alert-success rounded-4" style="display:none;">
                    <strong>Featured Package:</strong> <span id="previewSalaryText"></span>
                </div>
                <hr>
                <div id="previewBody"></div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">This is a quick look, not the live page's exact styling.</small>
                    <a href="#" target="_blank" rel="noopener" id="previewLiveLink" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-arrow-up-right-from-square me-1"></i> Open Live Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'HTML'
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.1.8/js/dataTables.bootstrap5.js"></script>
<!-- TinyMCE JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.0.0/tinymce.min.js"></script>
<script>
    // Posts data for JS (edit/preview panes read from this instead of hitting the server again)
    const posts = POSTS_JSON_PLACEHOLDER;
    console.log('[blog-admin] script loaded, posts:', posts.length);

    // Public site sits one folder above the admin panel (../blog_post.php?slug=...)
    function liveUrlFor(post) {
        return '../blog_post.php?slug=' + encodeURIComponent(post.slug);
    }

    // Small helpers so a missing element never throws and kills the caller.
    function byId(id) { return document.getElementById(id); }
    function setText(id, val) { const el = byId(id); if (el) el.textContent = val; }
    function setHtml(id, val) { const el = byId(id); if (el) el.innerHTML = val; }
    function setDisplay(id, val) { const el = byId(id); if (el) el.style.display = val; }

    // Each feature below is wrapped in its own try/catch and runs independently.
    // If one block fails, it logs to the console and every OTHER block still runs --
    // a bug in the drag-and-drop zone, for instance, can no longer take down Edit or Preview.

    // ---- DataTable ----
    let table = null;
    try {
        table = new DataTable('#postsTable', {
            responsive: true,
            paging: true,
            searching: true,
            ordering: true,
            order: [[5, 'desc']], // Date posted desc (column shifted by the new Destination column)
            columnDefs: [
                { orderable: false, searchable: false, targets: [1, 7, 8] }
            ],
            language: { search: 'Search posts:' }
        });
    } catch (err) {
        console.error('[blog-admin] DataTable init failed:', err);
    }

    // ---- Category filter pills ----
    try {
        document.querySelectorAll('.category-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                document.querySelectorAll('.category-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                const value = pill.dataset.category;
                if (table) table.column(3).search(value ? '^' + value + '$' : '', true, false).draw();
            });
        });
    } catch (err) {
        console.error('[blog-admin] Category pills failed:', err);
    }

    // ---- Destination filter pills ----
    try {
        document.querySelectorAll('.country-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                document.querySelectorAll('.country-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
                const value = pill.dataset.country;
                if (table) table.column(4).search(value ? value : '', false, false).draw();
            });
        });
    } catch (err) {
        console.error('[blog-admin] Destination pills failed:', err);
    }

    // ---- TinyMCE lifecycle tied to the Add/Edit modal ----
    // Previously this initialized TinyMCE on 'shown.bs.modal' and, separately,
    // openPostModal() ALSO listened for 'shown.bs.modal' (once) to call
    // editor.setContent(post.content). Both listeners fired off the same event,
    // but tinymce.init() is asynchronous -- so on a post's first-ever Edit click
    // in a page session, the content-setting listener could run before the
    // editor actually existed, silently leaving it blank. Saving from a blank
    // editor then wiped out that post's content on the server.
    //
    // ensureEditor() fixes this by returning one shared Promise that resolves
    // only once the editor instance is truly ready. Every caller (init on open,
    // and setContent() for both Add and Edit) awaits the SAME promise, so there
    // is no longer any ordering to race.
    const postModalEl = byId('postModal');
    let editorReadyPromise = null;

    function ensureEditor() {
        if (editorReadyPromise) return editorReadyPromise;
        editorReadyPromise = tinymce.init({
            selector: '#content',
            height: 460,
            menubar: 'file edit view insert format tools table help',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount help',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
            content_style: 'body { font-family: Poppins, sans-serif; font-size: 16px }',
            setup: function (editor) {
                editor.on('input undo redo SetContent', () => updateWordCount(editor));
            }
        }).then(editors => {
            const editor = editors[0];
            updateWordCount(editor);
            return editor;
        }).catch(err => {
            console.error('[blog-admin] TinyMCE init failed:', err);
            editorReadyPromise = null; // allow a retry the next time the modal opens
            throw err;
        });
        return editorReadyPromise;
    }

    if (postModalEl) {
        postModalEl.addEventListener('shown.bs.modal', () => {
            ensureEditor().catch(() => {}); // failure already logged inside ensureEditor
        });
        postModalEl.addEventListener('hidden.bs.modal', () => {
            try { tinymce.remove('#content'); } catch (err) { console.error('[blog-admin] TinyMCE teardown failed:', err); }
            editorReadyPromise = null; // force a clean re-init next time the modal opens
        });
    } else {
        console.error('[blog-admin] #postModal not found in the page -- Add/Edit cannot open.');
    }

    function updateWordCount(editor) {
        try {
            const text = editor.getContent({ format: 'text' }).trim();
            const words = text ? text.split(/\s+/).length : 0;
            setText('wordCountBadge', words + ' word' + (words === 1 ? '' : 's'));
        } catch (err) {
            console.error('[blog-admin] Word count failed:', err);
        }
    }

    // ---- Auto-estimate read time from word count (~200 wpm) ----
    try {
        const autoBtn = byId('autoReadTimeBtn');
        if (autoBtn) autoBtn.addEventListener('click', () => {
            ensureEditor().then(editor => {
                const text = editor.getContent({ format: 'text' }).trim();
                const words = text ? text.split(/\s+/).length : 0;
                const minutes = Math.max(1, Math.round(words / 200));
                const rt = byId('readTimeInput');
                if (rt) rt.value = minutes;
            }).catch(err => console.error('[blog-admin] Auto read-time failed:', err));
        });
    } catch (err) {
        console.error('[blog-admin] Auto read-time button failed:', err);
    }

    // ---- Title char counter + slug auto-generation ----
    const titleInput = byId('titleInput');
    const slugInput = byId('slugInput');
    const slugLockBtn = byId('slugLockBtn');
    const slugWarning = byId('slugWarning');
    let slugLocked = false;
    let editingExistingPost = false;

    function slugify(text) {
        return text.toString().toLowerCase().trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'post';
    }

    try {
        if (titleInput && slugInput) {
            titleInput.addEventListener('input', () => {
                setText('titleCount', titleInput.value.length);
                if (!slugLocked) slugInput.value = slugify(titleInput.value);
            });
        }
        if (slugLockBtn && slugInput) {
            slugLockBtn.addEventListener('click', () => {
                slugLocked = !slugLocked;
                slugInput.disabled = !slugLocked;
                slugLockBtn.innerHTML = slugLocked ? '<i class="fas fa-lock-open"></i>' : '<i class="fas fa-lock"></i>';
                if (slugWarning) slugWarning.style.display = (slugLocked && editingExistingPost) ? 'block' : 'none';
                if (slugLocked) slugInput.focus();
            });
        }
    } catch (err) {
        console.error('[blog-admin] Title/slug wiring failed:', err);
    }

    // ---- Drag-and-drop image zone ----
    try {
        const dropZone = byId('imageDropZone');
        const imageInput = byId('imageInput');
        const imagePreview = byId('imagePreview');
        if (dropZone && imageInput && imagePreview) {
            dropZone.addEventListener('click', () => imageInput.click());
            ['dragover', 'dragenter'].forEach(evt => dropZone.addEventListener(evt, e => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            }));
            ['dragleave', 'drop'].forEach(evt => dropZone.addEventListener(evt, e => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
            }));
            dropZone.addEventListener('drop', e => {
                if (e.dataTransfer.files.length) {
                    imageInput.files = e.dataTransfer.files;
                    imageInput.dispatchEvent(new Event('change'));
                }
            });
            imageInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = (ev) => {
                        imagePreview.src = ev.target.result;
                        imagePreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    } catch (err) {
        console.error('[blog-admin] Image drop zone failed:', err);
    }

    // ---- Open the Add/Edit modal for a given post (or null = new post) ----
    function openPostModal(post) {
        try {
            const form = byId('postForm');
            const imageInput = byId('imageInput');
            const imagePreview = byId('imagePreview');
            if (!form) { console.error('[blog-admin] #postForm not found.'); return; }

            editingExistingPost = !!post;
            setText('postModalLabel', post ? 'Edit Blog Post' : 'Add New Blog Post');

            if (post) {
                form.querySelector('[name="action"]').value = 'update';
                form.querySelector('[name="id"]').value = post.id;
                if (titleInput) titleInput.value = post.title;
                setText('titleCount', post.title.length);
                if (slugInput) { slugInput.value = post.slug; slugInput.disabled = true; }
                slugLocked = false;
                if (slugLockBtn) slugLockBtn.innerHTML = '<i class="fas fa-lock"></i>';
                if (slugWarning) slugWarning.style.display = 'none';
                const catField = form.querySelector('[name="category"]');
                if (catField) catField.value = post.category;
                const destField = form.querySelector('[name="destination_country"]');
                if (destField) destField.value = post.destination_country || '';
                const salField = form.querySelector('[name="salary_package"]');
                if (salField) salField.value = post.salary_package || '';
                const waField = form.querySelector('[name="whatsapp_text"]');
                if (waField) waField.value = post.whatsapp_text || '';
                const dateField = form.querySelector('[name="date_posted"]');
                if (dateField) dateField.value = post.date_posted;
                const rtField = byId('readTimeInput');
                if (rtField) rtField.value = post.read_time;
                const featField = form.querySelector('[name="featured"]');
                if (featField) featField.checked = !!post.featured;
                const existingImgField = form.querySelector('[name="existing_image"]');
                if (existingImgField) existingImgField.value = post.image;
                if (imageInput) imageInput.required = false;
                if (imagePreview) {
                    imagePreview.src = '../assets/images/' + post.image;
                    imagePreview.style.display = 'block';
                }

                const liveUrl = liveUrlFor(post);
                setDisplay('liveUrlGroup', 'block');
                const liveUrlInput = byId('liveUrlInput');
                if (liveUrlInput) liveUrlInput.value = window.location.origin + '/' + liveUrl.replace('../', '');
                const openLiveUrlBtn = byId('openLiveUrlBtn');
                if (openLiveUrlBtn) openLiveUrlBtn.href = liveUrl;
            } else {
                form.reset();
                form.querySelector('[name="action"]').value = 'add';
                form.querySelector('[name="id"]').value = '';
                form.querySelector('[name="existing_image"]').value = '';
                if (imageInput) imageInput.required = true;
                setText('titleCount', '0');
                if (slugInput) { slugInput.value = ''; slugInput.disabled = true; }
                slugLocked = false;
                if (slugLockBtn) slugLockBtn.innerHTML = '<i class="fas fa-lock"></i>';
                if (slugWarning) slugWarning.style.display = 'none';
                setDisplay('liveUrlGroup', 'none');
                if (imagePreview) { imagePreview.src = ''; imagePreview.style.display = 'none'; }
            }

            if (!postModalEl || typeof bootstrap === 'undefined') {
                console.error('[blog-admin] Cannot open modal -- postModal element or Bootstrap JS missing.');
                return;
            }
            const modal = bootstrap.Modal.getOrCreateInstance(postModalEl);
            modal.show();

            // Load the right content into the editor only once it's actually ready.
            // This is what removes the old race condition: whether this is the very
            // first time TinyMCE has ever initialized on this page, or the editor
            // was just torn down and needs a fresh init, ensureEditor() guarantees
            // setContent() only runs against a real, ready editor instance -- so an
            // edit can never silently save as blank content again.
            const desiredContent = post ? (post.content || '') : '';
            ensureEditor()
                .then(editor => editor.setContent(desiredContent))
                .catch(err => console.error('[blog-admin] Setting editor content failed:', err));
        } catch (err) {
            console.error('[blog-admin] openPostModal failed:', err);
        }
    }

    // ---- Open the Preview modal for a given post ----
    function openPreviewModal(post) {
        try {
            setText('previewTitle', post.title);
            setText('previewCategory', post.category);
            if (post.destination_country) {
                setText('previewCountry', post.destination_country);
                setDisplay('previewCountry', 'inline-block');
            } else {
                setDisplay('previewCountry', 'none');
            }
            const previewImg = byId('previewImage');
            if (previewImg) previewImg.src = '../assets/images/' + post.image;
            setText('previewMeta', post.date_posted + ' ， ' + post.read_time + ' min read');
            setDisplay('previewFeaturedBadge', post.featured ? 'inline-block' : 'none');
            setHtml('previewBody', post.content);

            if (post.salary_package) {
                setText('previewSalaryText', post.salary_package);
                setDisplay('previewSalaryBox', 'block');
            } else {
                setDisplay('previewSalaryBox', 'none');
            }

            const liveLink = byId('previewLiveLink');
            if (liveLink) liveLink.href = liveUrlFor(post);

            const previewModalEl = byId('previewModal');
            if (!previewModalEl || typeof bootstrap === 'undefined') {
                console.error('[blog-admin] Cannot open preview -- #previewModal or Bootstrap JS missing.');
                return;
            }
            const modal = bootstrap.Modal.getOrCreateInstance(previewModalEl);
            modal.show();
        } catch (err) {
            console.error('[blog-admin] openPreviewModal failed:', err);
        }
    }

    // ---- Add button ----
    try {
        const addBtn = byId('addBtn');
        if (addBtn) addBtn.addEventListener('click', () => openPostModal(null));
    } catch (err) {
        console.error('[blog-admin] Add button wiring failed:', err);
    }

    // ---- Flush the editor into the textarea right before the form submits ----
    // TinyMCE normally syncs itself back to its textarea on form submit, but
    // this is an explicit belt-and-braces flush so a save can never go out
    // with stale/empty content even in an edge case where that auto-sync
    // doesn't fire (e.g. the editor was still mid-init). Also guards against
    // accidental double-submits while the page is navigating away.
    try {
        const postForm = byId('postForm');
        const submitBtn = byId('postSubmitBtn');
        if (postForm) {
            postForm.addEventListener('submit', (e) => {
                try {
                    const editor = tinymce.get('content');
                    if (editor) editor.save();
                } catch (err) {
                    console.error('[blog-admin] Flushing editor content before submit failed:', err);
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';
                }
            });
        }
    } catch (err) {
        console.error('[blog-admin] Submit-time editor flush wiring failed:', err);
    }

    // ---- Copy live URL to clipboard ----
    try {
        const copyLiveUrlBtn = byId('copyLiveUrlBtn');
        if (copyLiveUrlBtn) {
            copyLiveUrlBtn.addEventListener('click', () => {
                const input = byId('liveUrlInput');
                if (!input) return;
                const finish = () => {
                    const original = copyLiveUrlBtn.innerHTML;
                    copyLiveUrlBtn.innerHTML = '<i class="fas fa-check"></i>';
                    setTimeout(() => { copyLiveUrlBtn.innerHTML = original; }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(input.value).then(finish).catch(() => {
                        input.select();
                        document.execCommand('copy');
                        finish();
                    });
                } else {
                    input.select();
                    document.execCommand('copy');
                    finish();
                }
            });
        }
    } catch (err) {
        console.error('[blog-admin] Copy-link button failed:', err);
    }

    // ---- DELEGATED handlers for Edit / Preview / Delete / Featured toggle ----
    // Delegation means these work even if DataTables re-orders rows, even if a
    // row is added later, and even if one row is missing an attribute -- the
    // click is caught on the table itself and routed based on what was clicked.
    try {
        const postsTable = byId('postsTable');
        if (!postsTable) {
            console.error('[blog-admin] #postsTable not found -- Edit/Preview/Delete/Featured will not work.');
        } else {
            postsTable.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.edit-btn');
                if (editBtn) {
                    const post = posts.find(p => String(p.id) === String(editBtn.dataset.id));
                    if (!post) { console.error('[blog-admin] No post found for id', editBtn.dataset.id); return; }
                    openPostModal(post);
                    return;
                }

                const previewBtn = e.target.closest('.preview-btn');
                if (previewBtn) {
                    const post = posts.find(p => String(p.id) === String(previewBtn.dataset.id));
                    if (!post) { console.error('[blog-admin] No post found for id', previewBtn.dataset.id); return; }
                    openPreviewModal(post);
                    return;
                }

                const featBtn = e.target.closest('.featured-toggle');
                if (featBtn) {
                    const id = featBtn.dataset.id;
                    featBtn.disabled = true;
                    fetch('blog.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'action=toggle_featured&id=' + encodeURIComponent(id)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            featBtn.classList.toggle('is-featured', !!data.featured);
                            const post = posts.find(p => String(p.id) === String(id));
                            if (post) post.featured = data.featured ? 1 : 0;
                        } else {
                            alert(data.error || 'Could not update this post.');
                        }
                    })
                    .catch(() => alert('Network error. Please try again.'))
                    .finally(() => { featBtn.disabled = false; });
                    return;
                }

                const deleteBtn = e.target.closest('.delete-form button[type="submit"]');
                if (deleteBtn) {
                    const title = deleteBtn.dataset.title || 'this post';
                    if (!confirm('Delete "' + title + '"? This will remove it from the database and take it off the public blog immediately. Its cover image will also be removed. This cannot be undone.')) {
                        e.preventDefault();
                    }
                    return;
                }
            });
        }
    } catch (err) {
        console.error('[blog-admin] Delegated table click handler failed to attach:', err);
    }
</script>
HTML;

$extraScripts = str_replace('POSTS_JSON_PLACEHOLDER', json_encode($posts), $extraScripts);

include 'admin_footer.php';