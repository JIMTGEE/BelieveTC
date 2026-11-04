<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

/* =========================================================================
   Fetch the post FIRST (before the header include) so the page <title>,
   and everything else, can actually reflect this specific post.
   ========================================================================= */
$slug = $_GET['slug'] ?? '';
$id   = (int) ($_GET['id'] ?? 0);

if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
} elseif ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
} else {
    header('Location: blog.php');
    exit;
}

$post = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$post) {
    header('Location: blog.php');
    exit;
}

// Used by includes/header.php for the <title> tag, same convention as admin_header.php
$pageTitle = $post['title'];
include 'includes/header.php';

/* =========================================================================
   Categories -- pulled live from the DB, same as blog.php, so these tabs
   always match whatever categories admins are actually using right now.
   ========================================================================= */
$catStmt    = $pdo->query("SELECT DISTINCT category FROM blog_posts WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

// Related posts: same category, exclude current
$related_stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE category = ? AND id != ? ORDER BY RAND() LIMIT 3");
$related_stmt->execute([$post['category'], $post['id']]);
$related_posts = $related_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent posts for the sidebar -- prepared statement instead of splicing the id into the SQL string
$recent_stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id != ? ORDER BY date_posted DESC LIMIT 3");
$recent_stmt->execute([$post['id']]);
$recent_posts = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- CATEGORY TABS -- slim sticky bar, generated live from real categories (matches blog.php) -->
<section class="py-3 bg-white border-bottom sticky-top" style="top:90px; z-index:1020;" id="post-content">
    <div class="container">
        <div class="row g-2 justify-content-center">
            <div class="col-auto"><a href="blog.php" class="btn btn-outline-primary btn-sm rounded-pill px-4 py-2">All Posts</a></div>
            <?php foreach ($categories as $cat): ?>
                <div class="col-auto">
                    <a href="blog.php?category=<?= urlencode($cat) ?>"
                       class="btn btn-sm rounded-pill px-4 py-2 <?= $cat === $post['category'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                        <?= htmlspecialchars($cat) ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- MAIN POST CONTENT – everything below comes straight from what the admin entered for THIS post -->
<section class="py-7 bg-white">
    <div class="container">
        <!-- Breadcrumb back to the blog + this post's category -->
        <nav class="mb-4" aria-label="breadcrumb">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="blog.php" class="text-decoration-none">Blog</a></li>
                <li class="breadcrumb-item"><a href="blog.php?category=<?= urlencode($post['category']) ?>" class="text-decoration-none"><?= htmlspecialchars($post['category']) ?></a></li>
                <li class="breadcrumb-item active text-muted" aria-current="page"><?= htmlspecialchars($post['title']) ?></li>
            </ol>
        </nav>

        <div class="row g-5">
            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="bg-white shadow-lg rounded-4 p-5">
                    <img src="assets/images/<?= htmlspecialchars($post['image']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" 
                         class="img-fluid rounded-4 shadow-lg mb-5" style="height:520px; object-fit:cover; width:100%;">

                    <!-- The admin's full rich-text content for this post -- headings, lists, tables,
                         FAQs, quotes, anything they built in the editor shows up here exactly as written. -->
                    <div class="fs-5 text-dark lh-lg post-body">
                        <?= $post['content'] ?>
                    </div>

                    <?php if ($post['salary_package']): ?>
                        <div class="alert alert-success mt-5 rounded-4 fs-5">
                            <strong>Featured Package:</strong> <?= htmlspecialchars($post['salary_package']) ?>
                        </div>
                    <?php endif; ?>

                    <hr class="my-5">

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <span class="text-muted fs-5">
                            <i class="far fa-calendar-alt me-2"></i> <?= htmlspecialchars(date('F d, Y', strtotime($post['date_posted']))) ?>
                        </span>
                        <div>
                            <a href="https://wa.me/233596204991?text=<?= urlencode('Great post! I have a question about ' . $post['title']) ?>"
                               class="btn btn-outline-success rounded-pill px-4 me-2">
                                <i class="fab fa-whatsapp me-2"></i> Ask Question
                            </a>
                            <a href="https://wa.me/?text=<?= urlencode($post['title'] . ' - ' . ('https://' . $_SERVER['HTTP_HOST'] . '/blog_post.php?slug=' . $post['slug'])) ?>"
                               class="btn btn-outline-primary rounded-pill px-4">
                                <i class="fas fa-share-alt me-2"></i> Share
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar: Recent Posts & CTA -->
            <div class="col-lg-4">
                <div class="sticky-top" style="top:120px;">
                    <!-- Recent Posts Widget -->
                    <?php if (!empty($recent_posts)): ?>
                    <div class="bg-white shadow-lg rounded-4 p-4 mb-5">
                        <h4 class="fw-bold mb-4" style="color:#033754;">Recent Posts</h4>
                        <?php foreach ($recent_posts as $recent): ?>
                            <div class="d-flex align-items-center mb-4">
                                <img src="assets/images/<?= htmlspecialchars($recent['image']) ?>" alt="<?= htmlspecialchars($recent['title']) ?>" 
                                     class="rounded-3 me-3" style="width:80px; height:80px; object-fit:cover;">
                                <div>
                                    <a href="blog_post.php?slug=<?= htmlspecialchars($recent['slug']) ?>" class="fw-bold text-dark fs-6 text-decoration-none">
                                        <?= htmlspecialchars($recent['title']) ?>
                                    </a>
                                    <small class="d-block text-muted"><?= htmlspecialchars(date('M d, Y', strtotime($recent['date_posted']))) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- WhatsApp CTA Widget -->
                    <div class="bg-light shadow-lg rounded-4 p-4 text-center">
                        <h4 class="fw-bold mb-3" style="color:#033754;">Get Dubai Job Alerts</h4>
                        <p class="text-muted mb-4">New posts + vacancies direct to your phone</p>
                        <a href="https://wa.me/233596204991?text=Please%20add%20me%20to%20Believe%20Travel%20Dubai%20Job%20Alerts!"
                           class="btn btn-lg w-100 rounded-pill" 
                           style="background:#89d900; color:#033754; font-weight:900;">
                            <i class="fab fa-whatsapp fa-2x me-3"></i> Add Me (Free)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- RELATED POSTS – Grid like blog.php -->
<?php if (!empty($related_posts)): ?>
<section class="py-7" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
    <div class="container">
        <h2 class="text-center display-5 fw-bold mb-6" style="color:#033754;">More Stories Like This</h2>
        <div class="row g-5">
            <?php foreach ($related_posts as $index => $rel): ?>
                <div class="col-lg-4" data-aos="fade-up" data-aos-delay="<?= $index * 100 + 100 ?>">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                        <img src="assets/images/<?= htmlspecialchars($rel['image']) ?>" class="card-img-top" alt="<?= htmlspecialchars($rel['title']) ?>" style="height:280px; object-fit:cover;">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="text-muted small mb-2"><i class="far fa-calendar-alt me-2"></i> <?= htmlspecialchars(date('F d, Y', strtotime($rel['date_posted']))) ?></span>
                            <h4 class="fw-bold mb-3" style="color:#033754;"><?= htmlspecialchars($rel['title']) ?></h4>
                            <a href="blog_post.php?slug=<?= htmlspecialchars($rel['slug']) ?>" class="btn btn-outline-primary rounded-pill mt-auto">Read More →</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- WHATSAPP + EMAIL SUBSCRIPTION CTA -->
<section class="py-7 text-white text-center" style="background: linear-gradient(135deg, #033754 0%, #0a6c8a 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">

                <h2 class="display-5 fw-bold mb-3">Get Dubai Job Alerts Instantly</h2>
                <p class="lead mb-4 opacity-90">New blog posts + urgent vacancies sent directly to your phone</p>
                
                <a href="https://wa.me/233596204991?text=Please%20add%20me%20to%20Believe%20Travel%20Dubai%20Job%20Alerts!"
                   class="btn rounded-pill px-5 py-3 shadow-lg mb-5"
                   style="background:#89d900; color:#033754; font-weight:800; font-size:1.25rem;">
                    <i class="fab fa-whatsapp fa-lg me-2"></i> Add Me to WhatsApp Alerts (Free)
                </a>

                <!-- Email Subscription Form -->
                <div class="bg-white rounded-4 shadow-lg p-4 p-md-5 mx-auto" style="max-width: 560px;">
                    <h4 class="fw-bold mb-3" style="color:#033754;">Or Get Updates by Email</h4>
                    <p class="text-muted mb-4">Receive new success stories, salary updates & visa tips weekly</p>

                    <form action="subscribe.php" method="POST" class="row g-3 justify-content-center">
                        <div class="col-12 col-md-8">
                            <input type="email" name="email" class="form-control form-control-lg rounded-pill text-center border-0 shadow-sm" 
                                   placeholder="Enter your email address" required 
                                   style="height: 56px; font-size: 1.1rem;">
                        </div>
                        <div class="col-12 col-md-4">
                            <button type="submit" class="btn btn-lg w-100 rounded-pill shadow" 
                                    style="background:#10b6d7; color:white; font-weight:700; height: 56px;">
                                Subscribe
                            </button>
                        </div>
                    </form>

                    <small class="text-muted d-block mt-3 opacity-75">
                        No spam • Unsubscribe anytime • 100% private
                    </small>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- Premium Hover Effects – Same as blog.php -->
<style>
.hover-lift { transition: all 0.4s ease; }
.hover-lift:hover { 
    transform: translateY(-15px); 
    box-shadow: 0 30px 60px rgba(3,55,84,0.25)!important; 
}
.z-3 { z-index: 3; }
.badge { font-weight: 700; }
.post-body img { max-width: 100%; height: auto; border-radius: 12px; }
.post-body table { width: 100%; margin: 1.5rem 0; }
.post-body blockquote { border-left: 4px solid #10b6d7; padding-left: 1.5rem; font-style: italic; color: #555; }
</style>