<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

/* =========================================================================
   DATA LAYER
   Everything below is wrapped defensively so a missing table/column never
   takes the whole dashboard down -- it just quietly shows zero/empty.
   ========================================================================= */

// ---- Basic counts -------------------------------------------------------
$offersCount   = (int) $pdo->query("SELECT COUNT(*) FROM offers")->fetchColumn();
$blogCount     = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
$messagesCount = (int) $pdo->query("SELECT COUNT(*) FROM contact_messages")->fetchColumn();

$featuredOffers = 0;
$featuredBlogs  = 0;
$galleryCount   = 0;
$pendingCommentsTotal = 0;
try { $featuredOffers = (int) $pdo->query("SELECT COUNT(*) FROM offers WHERE featured = 1")->fetchColumn(); } catch (Exception $e) {}
try { $featuredBlogs  = (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE featured = 1")->fetchColumn(); } catch (Exception $e) {}
try { $galleryCount   = (int) $pdo->query("SELECT COUNT(*) FROM gallery")->fetchColumn(); } catch (Exception $e) {}
try { $pendingCommentsTotal = (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE approved = 0")->fetchColumn(); } catch (Exception $e) {}

// ---- Month-over-month trend for the 3 headline stats --------------------
function calcTrend(PDO $pdo, string $table, string $dateColumn): array {
    try {
        $sql = "SELECT
                    SUM(CASE WHEN $dateColumn >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS this_month,
                    SUM(CASE WHEN $dateColumn >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
                              AND $dateColumn < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 ELSE 0 END) AS last_month
                FROM $table";
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        $thisMonth = (int) ($row['this_month'] ?? 0);
        $lastMonth = (int) ($row['last_month'] ?? 0);
        if ($lastMonth === 0) {
            $pct = $thisMonth > 0 ? 100 : 0;
        } else {
            $pct = (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100);
        }
        return ['pct' => $pct, 'up' => $pct >= 0, 'this_month' => $thisMonth];
    } catch (Exception $e) {
        return ['pct' => 0, 'up' => true, 'this_month' => 0];
    }
}
$offersTrend   = calcTrend($pdo, 'offers', 'created_at');
$blogTrend     = calcTrend($pdo, 'blog_posts', 'date_posted');
$messagesTrend = calcTrend($pdo, 'contact_messages', 'created_at');

// ---- 6-month growth chart (real counts, grouped by month) ---------------
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[] = date('Y-m', strtotime("-$i months"));
}
$monthLabels = array_map(fn($m) => date('M', strtotime($m . '-01')), $months);

function monthlyCounts(PDO $pdo, string $table, string $dateColumn, array $months): array {
    $counts = array_fill_keys($months, 0);
    try {
        $stmt = $pdo->query("SELECT DATE_FORMAT($dateColumn, '%Y-%m') AS ym, COUNT(*) AS cnt FROM $table GROUP BY ym");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($counts[$row['ym']])) {
                $counts[$row['ym']] = (int) $row['cnt'];
            }
        }
    } catch (Exception $e) {}
    return array_values($counts);
}
$offersMonthly   = monthlyCounts($pdo, 'offers', 'created_at', $months);
$blogMonthly     = monthlyCounts($pdo, 'blog_posts', 'date_posted', $months);
$messagesMonthly = monthlyCounts($pdo, 'contact_messages', 'created_at', $months);

// ---- Blog category distribution ------------------------------------------
$categoryDist = [];
try {
    $categoryDist = $pdo->query(
        "SELECT COALESCE(NULLIF(category,''), 'Uncategorized') AS category, COUNT(*) AS cnt
         FROM blog_posts GROUP BY category ORDER BY cnt DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ---- Top destinations (offers grouped by location) -----------------------
$topDestinations = [];
try {
    $topDestinations = $pdo->query(
        "SELECT location, COUNT(*) AS cnt FROM offers
         WHERE location IS NOT NULL AND location <> ''
         GROUP BY location ORDER BY cnt DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$maxDestinationCount = 0;
foreach ($topDestinations as $d) { $maxDestinationCount = max($maxDestinationCount, (int) $d['cnt']); }

// ---- Pending comments (moderation queue) ----------------------------------
$pendingComments = [];
try {
    $pendingComments = $pdo->query(
        "SELECT c.id, c.name, c.email, c.comment, c.created_at, b.title AS post_title
         FROM comments c LEFT JOIN blog_posts b ON b.id = c.post_id
         WHERE c.approved = 0 ORDER BY c.created_at DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ---- Recent offers / blog / messages (existing "quick edit" lists) -------
$recentOffers = $pdo->query("SELECT id, title, location, price, created_at FROM offers ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentBlogs  = $pdo->query("SELECT id, title, category, date_posted FROM blog_posts ORDER BY date_posted DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$recentMessages = $pdo->query("SELECT id, name, email, subject, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// ---- Unified activity feed --------------------------------------------
$activity = [];
try {
    foreach ($pdo->query("SELECT title, created_at FROM offers ORDER BY created_at DESC LIMIT 5") as $r) {
        $activity[] = ['icon' => 'fa-briefcase', 'color' => '#10b6d7', 'text' => "New job offer posted: <strong>" . htmlspecialchars($r['title']) . "</strong>", 'time' => $r['created_at']];
    }
} catch (Exception $e) {}
try {
    foreach ($pdo->query("SELECT title, date_posted FROM blog_posts ORDER BY date_posted DESC LIMIT 5") as $r) {
        $activity[] = ['icon' => 'fa-blog', 'color' => '#033754', 'text' => "Blog post published: <strong>" . htmlspecialchars($r['title']) . "</strong>", 'time' => $r['date_posted']];
    }
} catch (Exception $e) {}
try {
    foreach ($pdo->query("SELECT name, subject, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 5") as $r) {
        $activity[] = ['icon' => 'fa-envelope', 'color' => '#89d900', 'text' => htmlspecialchars($r['name']) . " sent an inquiry: <strong>" . htmlspecialchars($r['subject']) . "</strong>", 'time' => $r['created_at']];
    }
} catch (Exception $e) {}
try {
    foreach ($pdo->query("SELECT c.name, c.created_at, b.title AS post_title FROM comments c LEFT JOIN blog_posts b ON b.id = c.post_id ORDER BY c.created_at DESC LIMIT 5") as $r) {
        $activity[] = ['icon' => 'fa-comment-dots', 'color' => '#ffc107', 'text' => htmlspecialchars($r['name']) . ' commented on "' . htmlspecialchars($r['post_title'] ?? 'a post') . '"', 'time' => $r['created_at']];
    }
} catch (Exception $e) {}
usort($activity, fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));
$activity = array_slice($activity, 0, 8);

// Page-specific setup for the shared header
$pageTitle = 'Admin Dashboard';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';

include 'admin_header.php';
?>

        <!-- Header + Quick Actions -->
        <div class="row mb-4 align-items-center">
            <div class="col-lg-7" data-aos="fade-down">
                <h2 class="fw-bold display-6" style="color:#033754;">Dashboard Overview</h2>
                <p class="text-muted lead mb-0">Welcome back, here's what's happening with Believe Travel Consult today.</p>
            </div>
            <div class="col-lg-5 mt-3 mt-lg-0 text-lg-end" data-aos="fade-down">
                <a href="offers.php" class="btn btn-primary btn-sm me-1 mb-2 mb-lg-0"><i class="fas fa-plus me-1"></i>New Offer</a>
                <a href="blog.php" class="btn btn-outline-primary btn-sm me-1 mb-2 mb-lg-0" style="border-radius:50px;"><i class="fas fa-pen me-1"></i>New Post</a>
                <a href="gallery.php" class="btn btn-outline-primary btn-sm mb-2 mb-lg-0" style="border-radius:50px;"><i class="fas fa-image me-1"></i>Upload Media</a>
            </div>
        </div>

        <!-- Headline Stats -->
        <div class="row g-4 mb-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-card position-relative">
                    <i class="fas fa-briefcase"></i>
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h1 class="fw-bold display-4 mb-1"><?php echo $offersCount; ?></h1>
                            <p class="fs-5 mb-2 opacity-90">Job Offers</p>
                        </div>
                    </div>
                    <span class="trend-badge <?php echo $offersTrend['up'] ? 'trend-up' : 'trend-down'; ?>">
                        <i class="fas fa-arrow-<?php echo $offersTrend['up'] ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($offersTrend['pct']); ?>% <span class="opacity-75 fw-normal">vs last month</span>
                    </span>
                    <a href="offers.php" class="btn btn-sm btn-light mt-3 rounded-pill d-block" style="width:fit-content;">Manage Offers</a>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-card position-relative">
                    <i class="fas fa-blog"></i>
                    <h1 class="fw-bold display-4 mb-1"><?php echo $blogCount; ?></h1>
                    <p class="fs-5 mb-2 opacity-90">Blog Posts</p>
                    <span class="trend-badge <?php echo $blogTrend['up'] ? 'trend-up' : 'trend-down'; ?>">
                        <i class="fas fa-arrow-<?php echo $blogTrend['up'] ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($blogTrend['pct']); ?>% <span class="opacity-75 fw-normal">vs last month</span>
                    </span>
                    <a href="blog.php" class="btn btn-sm btn-light mt-3 rounded-pill d-block" style="width:fit-content;">Manage Blog</a>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-card position-relative">
                    <i class="fas fa-envelope"></i>
                    <h1 class="fw-bold display-4 mb-1"><?php echo $messagesCount; ?></h1>
                    <p class="fs-5 mb-2 opacity-90">Total Inquiries</p>
                    <span class="trend-badge <?php echo $messagesTrend['up'] ? 'trend-up' : 'trend-down'; ?>">
                        <i class="fas fa-arrow-<?php echo $messagesTrend['up'] ? 'up' : 'down'; ?>"></i>
                        <?php echo abs($messagesTrend['pct']); ?>% <span class="opacity-75 fw-normal">vs last month</span>
                    </span>
                    <a href="messages.php" class="btn btn-sm btn-light mt-3 rounded-pill d-block" style="width:fit-content;">View Messages</a>
                </div>
            </div>
        </div>

        <!-- Secondary mini-stats -->
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3" data-aos="fade-up">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(16,182,215,0.12); color:var(--primary-light);"><i class="fas fa-star"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $featuredOffers; ?></h4>
                        <small class="text-muted">Featured Offers</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(3,55,84,0.08); color:var(--primary-dark);"><i class="fas fa-award"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $featuredBlogs; ?></h4>
                        <small class="text-muted">Featured Stories</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(137,217,0,0.15); color:#5c9200;"><i class="fas fa-images"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $galleryCount; ?></h4>
                        <small class="text-muted">Gallery Items</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(255,77,79,0.12); color:#c0392b;"><i class="fas fa-comment-dots"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $pendingCommentsTotal; ?></h4>
                        <small class="text-muted">Pending Comments</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="card p-5 h-100">
                    <h4 class="fw-bold mb-1" style="color:#033754;">Growth Overview</h4>
                    <p class="text-muted mb-4">Job offers, blog posts, and inquiries over the last 6 months.</p>
                    <canvas id="growthChart" height="220"></canvas>
                </div>
            </div>
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card p-5 h-100">
                    <h4 class="fw-bold mb-1" style="color:#033754;">Blog Categories</h4>
                    <p class="text-muted mb-4">Content mix across your blog.</p>
                    <?php if (empty($categoryDist)): ?>
                        <div class="empty-state"><i class="fas fa-chart-pie"></i><p class="mb-0">No blog posts yet</p></div>
                    <?php else: ?>
                        <canvas id="categoryChart" height="220"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Destinations + Comment Moderation -->
        <div class="row g-4 mb-4">
            <div class="col-lg-5" data-aos="fade-up">
                <div class="card p-5 h-100">
                    <h4 class="fw-bold mb-4" style="color:#033754;">Top Destinations</h4>
                    <?php if (empty($topDestinations)): ?>
                        <div class="empty-state"><i class="fas fa-map-marked-alt"></i><p class="mb-0">No destination data yet</p></div>
                    <?php else: ?>
                        <?php foreach ($topDestinations as $dest): ?>
                            <div class="progress-row">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="fw-semibold small"><i class="fas fa-map-marker-alt me-1" style="color:var(--primary-light);"></i><?php echo htmlspecialchars($dest['location']); ?></span>
                                    <span class="small text-muted"><?php echo (int) $dest['cnt']; ?> offer<?php echo $dest['cnt'] == 1 ? '' : 's'; ?></span>
                                </div>
                                <div class="progress">
                                    <div class="progress-bar" style="width: <?php echo $maxDestinationCount ? round(($dest['cnt'] / $maxDestinationCount) * 100) : 0; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100" id="pending-comments">
                <div class="card p-5 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0" style="color:#033754;">Comment Moderation</h4>
                        <?php if (count($pendingComments) > 0): ?>
                            <span class="badge badge-soft-warning rounded-pill px-3 py-2"><?php echo count($pendingComments); ?> pending</span>
                        <?php endif; ?>
                    </div>
                    <div id="commentModerationList">
                        <?php if (empty($pendingComments)): ?>
                            <div class="empty-state"><i class="fas fa-check-circle"></i><p class="mb-0">No comments waiting for review</p></div>
                        <?php else: ?>
                            <?php foreach ($pendingComments as $c): ?>
                                <div class="comment-card" id="comment-<?php echo (int) $c['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                        <div>
                                            <span class="fw-bold"><?php echo htmlspecialchars($c['name']); ?></span>
                                            <span class="text-muted small"> on "<?php echo htmlspecialchars($c['post_title'] ?? 'a post'); ?>"</span>
                                        </div>
                                        <small class="text-muted"><?php echo timeAgo($c['created_at']); ?></small>
                                    </div>
                                    <p class="mb-2 small"><?php echo htmlspecialchars(substr($c['comment'], 0, 140)) . (strlen($c['comment']) > 140 ? '...' : ''); ?></p>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-success rounded-pill px-3" onclick="moderateComment(<?php echo (int) $c['id']; ?>, 'approve', this)"><i class="fas fa-check me-1"></i>Approve</button>
                                        <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="moderateComment(<?php echo (int) $c['id']; ?>, 'delete', this)"><i class="fas fa-trash me-1"></i>Delete</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Feed + Recent Lists -->
        <div class="row g-4 mb-4">
            <div class="col-lg-4" data-aos="fade-up">
                <div class="card p-5 h-100">
                    <h4 class="fw-bold mb-4" style="color:#033754;">Recent Activity</h4>
                    <?php if (empty($activity)): ?>
                        <div class="empty-state"><i class="fas fa-stream"></i><p class="mb-0">Nothing has happened yet</p></div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($activity as $item): ?>
                                <div class="timeline-item">
                                    <div class="timeline-icon" style="background: <?php echo $item['color']; ?>;">
                                        <i class="fas <?php echo $item['icon']; ?>"></i>
                                    </div>
                                    <p class="mb-1 small"><?php echo $item['text']; ?></p>
                                    <small class="text-muted"><?php echo timeAgo($item['time']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Offers -->
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card p-5 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0" style="color:#033754;">Recent Offers</h4>
                        <a href="offers.php" class="btn btn-sm btn-primary">Add New</a>
                    </div>
                    <?php if (empty($recentOffers)): ?>
                        <p class="text-muted text-center py-3">No recent offers yet</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentOffers as $offer): ?>
                                <a href="offers.php?edit=<?php echo (int) $offer['id']; ?>" class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($offer['title']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($offer['location'] ?? ''); ?> · GH₵ <?php echo number_format($offer['price'], 2); ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo date('M d', strtotime($offer['created_at'])); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Messages -->
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="card p-5 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0" style="color:#033754;">Recent Inquiries</h4>
                        <a href="messages.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <?php if (empty($recentMessages)): ?>
                        <p class="text-muted text-center py-3">No recent inquiries yet</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentMessages as $msg): ?>
                                <a href="messages.php?view=<?php echo (int) $msg['id']; ?>" class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($msg['name']); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars(substr($msg['subject'], 0, 30)); ?><?php echo strlen($msg['subject']) > 30 ? '...' : ''; ?></small>
                                        </div>
                                        <small class="text-muted"><?php echo date('M d', strtotime($msg['created_at'])); ?></small>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Blog Posts (full width strip) -->
        <div class="row g-4">
            <div class="col-12" data-aos="fade-up">
                <div class="card p-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="fw-bold mb-0" style="color:#033754;">Recent Blog Posts</h4>
                        <a href="blog.php" class="btn btn-sm btn-primary">Add New</a>
                    </div>
                    <?php if (empty($recentBlogs)): ?>
                        <p class="text-muted text-center py-3">No recent posts yet</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Date</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentBlogs as $blog): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($blog['title']); ?></td>
                                            <td><span class="badge rounded-pill" style="background:rgba(16,182,215,0.12); color:var(--primary-light);"><?php echo htmlspecialchars($blog['category'] ?? 'Uncategorized'); ?></span></td>
                                            <td class="text-muted"><?php echo date('M d, Y', strtotime($blog['date_posted'])); ?></td>
                                            <td class="text-end"><a href="blog.php?edit=<?php echo (int) $blog['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill">Edit</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

<?php
$monthLabelsJson   = json_encode($monthLabels);
$offersMonthlyJson = json_encode($offersMonthly);
$blogMonthlyJson   = json_encode($blogMonthly);
$messagesMonthlyJson = json_encode($messagesMonthly);
$categoryLabelsJson = json_encode(array_column($categoryDist, 'category'));
$categoryDataJson   = json_encode(array_map('intval', array_column($categoryDist, 'cnt')));

$extraScripts = <<<HTML
<script>
    // ---- Growth chart (real data) ----
    const growthCtx = document.getElementById('growthChart').getContext('2d');
    new Chart(growthCtx, {
        type: 'line',
        data: {
            labels: {$monthLabelsJson},
            datasets: [
                {
                    label: 'Job Offers',
                    data: {$offersMonthlyJson},
                    borderColor: '#10b6d7',
                    backgroundColor: 'rgba(16,182,215,0.15)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#10b6d7'
                },
                {
                    label: 'Blog Posts',
                    data: {$blogMonthlyJson},
                    borderColor: '#033754',
                    backgroundColor: 'rgba(3,55,84,0.08)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#033754'
                },
                {
                    label: 'Inquiries',
                    data: {$messagesMonthlyJson},
                    borderColor: '#89d900',
                    backgroundColor: 'rgba(137,217,0,0.15)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#89d900'
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'top', labels: { font: { size: 13, weight: '600' } } },
                tooltip: { backgroundColor: 'rgba(3,55,84,0.9)', titleFont: { weight: 'bold' }, bodyFont: { size: 13 } }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(3,55,84,0.08)' } },
                x: { grid: { display: false } }
            }
        }
    });

HTML;

if (!empty($categoryDist)) {
    $extraScripts .= <<<HTML
    // ---- Category doughnut chart (real data) ----
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: {$categoryLabelsJson},
            datasets: [{
                data: {$categoryDataJson},
                backgroundColor: ['#10b6d7', '#033754', '#89d900', '#ffc107', '#c0392b', '#8e44ad'],
                borderWidth: 3,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 12 } }
            },
            cutout: '65%'
        }
    });

HTML;
}

$extraScripts .= <<<'HTML'
    // ---- Inline comment moderation (approve / delete via AJAX) ----
    function moderateComment(id, action, btn) {
        const card = document.getElementById('comment-' + id);
        const buttons = card.querySelectorAll('button');
        buttons.forEach(b => b.disabled = true);
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('ajax_comment_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + encodeURIComponent(id) + '&action=' + encodeURIComponent(action)
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                card.style.transition = 'opacity 0.3s ease';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    const list = document.getElementById('commentModerationList');
                    if (!list.querySelector('.comment-card')) {
                        list.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i><p class="mb-0">No comments waiting for review</p></div>';
                    }
                }, 300);
            } else {
                alert(data.error || 'Something went wrong. Please try again.');
                buttons.forEach(b => b.disabled = false);
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            buttons.forEach(b => b.disabled = false);
        });
    }
</script>
HTML;

include 'admin_footer.php';