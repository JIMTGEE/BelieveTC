<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
include 'includes/header.php';

/* =========================================================================
   FILTERS (category / destination / search / page) come straight from the
   query string, so every filtered view is a real, shareable, bookmarkable
   URL. Destination filtering is new -- see migration_add_destination_country.sql.
   ========================================================================= */
$categoryFilter    = isset($_GET['category']) ? trim($_GET['category']) : '';
$destinationFilter = isset($_GET['destination']) ? trim($_GET['destination']) : '';
$searchQuery       = isset($_GET['search']) ? trim($_GET['search']) : '';
$page              = max(1, (int) ($_GET['page'] ?? 1));
$perPage           = 9;

/* =========================================================================
   CATEGORIES & DESTINATIONS -- pulled live from whatever exists on posts
   right now. Add a new category or tag a new destination country in the
   admin panel and it shows up here automatically; nothing to hardcode.
   ========================================================================= */
$catStmt    = $pdo->query("SELECT DISTINCT category FROM blog_posts WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

$destStmt     = $pdo->query("SELECT DISTINCT destination_country FROM blog_posts WHERE destination_country IS NOT NULL AND destination_country != '' ORDER BY destination_country ASC");
$destinations = $destStmt->fetchAll(PDO::FETCH_COLUMN);

/* =========================================================================
   BUILD THE FILTERED QUERY
   ========================================================================= */
$where  = [];
$params = [];
if ($categoryFilter !== '') {
    $where[]  = "category = ?";
    $params[] = $categoryFilter;
}
if ($destinationFilter !== '') {
    $where[]  = "destination_country = ?";
    $params[] = $destinationFilter;
}
if ($searchQuery !== '') {
    $where[]  = "(title LIKE ? OR content LIKE ?)";
    $params[] = '%' . $searchQuery . '%';
    $params[] = '%' . $searchQuery . '%';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_posts $whereSql");
$countStmt->execute($params);
$totalPosts = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalPosts / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM blog_posts $whereSql ORDER BY date_posted DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$posts = $stmt->fetchAll();

// The big "featured story" banner is a front-page feature, not a search
// result -- only show it on the clean, unfiltered first page.
$showFeatured = ($categoryFilter === '' && $destinationFilter === '' && $searchQuery === '' && $page === 1);
$featuredPost = null;
if ($showFeatured) {
    $featuredStmt = $pdo->query("SELECT * FROM blog_posts WHERE featured=1 ORDER BY date_posted DESC LIMIT 1");
    $featuredPost = $featuredStmt->fetch();
}

// Builds a blog.php URL that preserves whichever filters are already active,
// only overriding the ones you pass in (e.g. changing just the page number).
function blogUrl($overrides = []) {
    $current = [
        'category'    => $_GET['category'] ?? '',
        'destination' => $_GET['destination'] ?? '',
        'search'      => $_GET['search'] ?? '',
        'page'        => $_GET['page'] ?? '',
    ];
    $merged = array_merge($current, $overrides);
    // Drop empty values and page=1 (the default), so URLs stay clean.
    $clean = [];
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null) continue;
        if ($k === 'page' && (int) $v <= 1) continue;
        $clean[$k] = $v;
    }
    return 'blog.php' . (count($clean) ? '?' . http_build_query($clean) : '');
}

// Trims HTML content down to a clean, word-safe excerpt (no more cutting a
// sentence off mid-word or mid-HTML-entity).
function makeExcerpt($html, $length = 150) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    $trimmed   = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($trimmed, ' ');
    if ($lastSpace !== false) {
        $trimmed = mb_substr($trimmed, 0, $lastSpace);
    }
    return $trimmed . '...';
}
?>

<!-- HERO SECTION – Global brand positioning, no longer UAE-first -->
<section class="position-relative text-white text-center overflow-hidden" 
         style="min-height:95vh; display:flex; align-items:center;">
    <!-- Background Slideshow Carousel -->
    <div id="heroCarousel" class="carousel slide position-absolute top-0 start-0 w-100 h-100" data-bs-ride="carousel" data-bs-interval="2000">
        <div class="carousel-inner h-100">
            <!-- Slide 1 -->
            <div class="carousel-item active h-100">
                <div class="h-100" style="background: linear-gradient(135deg, rgba(3,55,84,0.6) 0%, rgba(3,55,84,0.5) 100%), 
                                              url('assets/images/intro.png') center/cover no-repeat fixed;"></div>
            </div>
            <!-- Slide 2 -->
            <div class="carousel-item h-100">
                <div class="h-100" style="background: linear-gradient(135deg, rgba(3,55,84,0.6) 0%, rgba(3,55,84,0.5) 100%), 
                                              url('assets/images/97.jpg') center/cover no-repeat fixed;"></div>
            </div>
            <!-- Slide 3 -->
            <div class="carousel-item h-100">
                <div class="h-100" style="background: linear-gradient(135deg, rgba(3,55,84,0.6) 0%, rgba(3,55,84,0.5) 100%), 
                                              url('assets/images/blog_dubai_lifestyle.jpg') center/cover no-repeat fixed;"></div>
            </div>
            <!-- Slide 4 -->
            <div class="carousel-item h-100">
                <div class="h-100" style="background: linear-gradient(135deg, rgba(3,55,84,0.6) 0%, rgba(3,55,84,0.5) 100%), 
                                              url('assets/images/Facts-About-Dubai-Ramadan-676x507.jpg') center/cover no-repeat fixed;"></div>
            </div>
            <!-- Slide 5 -->
            <div class="carousel-item h-100">
                <div class="h-100" style="background: linear-gradient(135deg, rgba(3,55,84,0.6) 0%, rgba(3,55,84,0.5) 100%), 
                                              url('assets/images/OIP.webp') center/cover no-repeat fixed;"></div>
            </div>
        </div>
        <!-- Carousel Controls (Optional, hidden for clean look) -->
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev" style="opacity: 0;">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next" style="opacity: 0;">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
    
    <!-- Content Overlay -->
    <div class="container position-relative z-3">
        <div class="row justify-content-center">
            <div class="col-lg-11" data-aos="fade-up" data-aos-delay="300">
                <span class="badge bg-success fs-5 px-4 py-2 rounded-pill mb-4 shadow-lg text-uppercase">Trusted by 1,500+ Clients Worldwide</span>
                <h1 class="display-1 fw-bold mb-4" style="color:#89d900; text-shadow: 0 8px 30px rgba(0,0,0,0.7); line-height:1.1;">
                    Believe Travel Consult Blog: Your Gateway to Global Opportunities
                </h1>
                <p class="lead fs-2 mb-5 opacity-95 max-w-5xl mx-auto">
                    Real Success Stories • 2026 Visa & Immigration Guides • Exact Salaries • Jobs, Study & Relocation Across the Gulf, Europe, North America & Asia
                </p>
                <div class="d-flex flex-column flex-lg-row justify-content-center gap-4">
                    <a href="https://wa.me/233596204991?text=Hi!%20I'm%20ready%20to%20explore%20opportunities%20abroad!"
                       class="btn btn-lg rounded-pill px-6 py-4 shadow-lg" 
                       style="background:#89d900; color:#033754; font-weight:900; font-size:1.5rem;">
                        <i class="fab fa-whatsapp fa-2x me-3"></i> Start My Global Journey Now
                    </a>
                    <a href="#latest-posts" class="btn btn-lg rounded-pill px-6 py-4 btn-outline-light border-3 shadow">
                        Explore All Content ↓
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CATEGORY TABS -- generated live from the categories admins actually
     use on posts, so every tab does something real (filters the grid below). -->
<!-- Slim sticky bar: just the category pills, so it doesn't eat screen space while scrolling -->
<section class="py-3 bg-white border-bottom sticky-top" style="top:90px; z-index:1020;" id="latest-posts">
    <div class="container">
        <div class="row g-2 justify-content-center">
            <div class="col-auto">
                <a href="blog.php" class="btn btn-primary btn-sm rounded-pill px-4 py-2 <?php echo ($categoryFilter === '' && $destinationFilter === '') ? 'active' : ''; ?>">All</a>
            </div>
            <?php foreach ($categories as $cat): ?>
                <div class="col-auto">
                    <a href="<?php echo htmlspecialchars(blogUrl(['category' => $cat, 'page' => 1])); ?>"
                       class="btn btn-primary btn-sm rounded-pill px-4 py-2 <?php echo $categoryFilter === $cat ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($cat); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Jump links + search + destination filter: normal flow, scrolls away with the page -->
<section class="py-4 bg-white border-bottom">
    <div class="container">
        <?php if ($showFeatured): ?>
        <!-- Quick jump to the editorial (non-post) sections further down -->
        <div class="row g-2 justify-content-center mb-3">
            <div class="col-auto"><a href="#gulf-jobs" class="small text-muted text-decoration-none"><i class="fas fa-arrow-down me-1"></i>Gulf & Middle East</a></div>
            <div class="col-auto"><a href="#europe-opportunities" class="small text-muted text-decoration-none"><i class="fas fa-arrow-down me-1"></i>Europe</a></div>
            <div class="col-auto"><a href="#beyond-opportunities" class="small text-muted text-decoration-none"><i class="fas fa-arrow-down me-1"></i>North America, Asia & Beyond</a></div>
            <div class="col-auto"><a href="#destination-videos" class="small text-muted text-decoration-none"><i class="fas fa-arrow-down me-1"></i>Destination Videos</a></div>
        </div>
        <?php endif; ?>

        <div class="row justify-content-center g-2">
            <div class="col-lg-7">
                <form method="GET" action="blog.php" class="d-flex flex-wrap gap-2">
                    <?php if ($categoryFilter !== ''): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryFilter); ?>">
                    <?php endif; ?>
                    <input type="text" name="search" class="form-control rounded-pill flex-grow-1" placeholder="Search posts by title or content..." value="<?php echo htmlspecialchars($searchQuery); ?>" style="min-width:200px;">
                    <?php if (!empty($destinations)): ?>
                        <select name="destination" class="form-select rounded-pill" style="max-width:220px;" onchange="this.form.submit()">
                            <option value="">All Destinations</option>
                            <?php foreach ($destinations as $dest): ?>
                                <option value="<?php echo htmlspecialchars($dest); ?>" <?php echo $destinationFilter === $dest ? 'selected' : ''; ?>><?php echo htmlspecialchars($dest); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-outline-primary rounded-pill px-4"><i class="fas fa-search"></i></button>
                    <?php if ($searchQuery !== '' || $categoryFilter !== '' || $destinationFilter !== ''): ?>
                        <a href="blog.php" class="btn btn-outline-secondary rounded-pill px-4" title="Clear filters"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- FEATURED STORY -- Full Width Premium Card, Dynamic from DB -->
<?php if ($showFeatured && $featuredPost): ?>
<section class="py-7" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6 order-lg-2" data-aos="fade-left">
                <img src="assets/images/<?php echo htmlspecialchars($featuredPost['image']); ?>" alt="<?php echo htmlspecialchars($featuredPost['title']); ?>" class="img-fluid rounded-4 shadow-lg" style="height:520px; object-fit:cover;">
            </div>
            <div class="col-lg-6 order-lg-1" data-aos="fade-right">
                <span class="badge bg-success fs-5 px-4 py-2 rounded-pill mb-3 shadow">FEATURED – <?php echo date('M Y', strtotime($featuredPost['date_posted'])); ?></span>
                <?php if (!empty($featuredPost['destination_country'])): ?>
                    <span class="badge fs-6 px-3 py-2 rounded-pill mb-3 ms-2" style="background:#033754;"><i class="fas fa-location-dot me-1"></i><?php echo htmlspecialchars($featuredPost['destination_country']); ?></span>
                <?php endif; ?>
                <h2 class="display-5 fw-bold mb-4" style="color:#033754;"><?php echo htmlspecialchars($featuredPost['title']); ?></h2>
                <p class="fs-4 text-dark mb-4">
                    <?php echo htmlspecialchars(makeExcerpt($featuredPost['content'], 150)); ?>
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="blog_post.php?slug=<?php echo htmlspecialchars($featuredPost['slug']); ?>" class="btn btn-primary rounded-pill px-5 py-3">
                        Read Full Story →
                    </a>
                    <?php if ($featuredPost['whatsapp_text']): ?>
                        <a href="https://wa.me/233596204991?text=<?php echo urlencode($featuredPost['whatsapp_text']); ?>"
                           class="btn rounded-pill px-5 py-3" style="background:#89d900; color:#033754; font-weight:800;">
                            <i class="fab fa-whatsapp me-2"></i> Get This Opportunity
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- LATEST POSTS GRID -- Dynamic from DB, filterable by category, destination, and search, and paginated -->
<section class="py-7 bg-white" id="all">
    <div class="container">
        <div class="text-center mb-6">
            <h2 class="display-5 fw-bold" style="color:#033754;">
                <?php
                if ($searchQuery !== '') {
                    echo 'Search Results for "' . htmlspecialchars($searchQuery) . '"';
                } elseif ($destinationFilter !== '') {
                    echo htmlspecialchars($destinationFilter) . ' Posts';
                } elseif ($categoryFilter !== '') {
                    echo htmlspecialchars($categoryFilter) . ' Posts';
                } else {
                    echo 'Latest Blog Posts & Guides';
                }
                ?>
            </h2>
            <p class="lead text-muted">
                <?php echo $totalPosts; ?> post<?php echo $totalPosts === 1 ? '' : 's'; ?> found
                <?php if ($totalPosts > 0): ?> · Real insights on jobs, visas, relocation, and life abroad<?php endif; ?>
            </p>
        </div>

        <?php if (empty($posts)): ?>
            <div class="text-center text-muted py-6">
                <i class="fas fa-folder-open fa-3x mb-3 d-block"></i>
                <p class="fs-5">No posts found. Try a different category, destination, or search term.</p>
                <a href="blog.php" class="btn btn-outline-primary rounded-pill px-4 mt-2">View All Posts</a>
            </div>
        <?php else: ?>
        <div class="row g-5">
            <?php foreach ($posts as $index => $post): ?>
                <div class="col-lg-6 col-xl-4" data-aos="fade-up" data-aos-delay="<?php echo ($index % 3 + 1) * 100; ?>">
                    <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                        <div class="position-absolute top-0 end-0 m-3 z-3 d-flex flex-column gap-2 align-items-end">
                            <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill shadow"><?php echo htmlspecialchars($post['category']); ?></span>
                            <?php if (!empty($post['destination_country'])): ?>
                                <span class="badge fs-6 px-3 py-2 rounded-pill shadow" style="background:#033754;"><?php echo htmlspecialchars($post['destination_country']); ?></span>
                            <?php endif; ?>
                        </div>
                        <img src="assets/images/<?php echo htmlspecialchars($post['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($post['title']); ?>" style="height:280px; object-fit:cover;">
                        <div class="card-body p-4 d-flex flex-column">
                            <span class="text-muted small mb-2">
                                <i class="far fa-calendar-alt me-2"></i> <?php echo date('F d, Y', strtotime($post['date_posted'])); ?>
                                <span class="mx-1">·</span>
                                <i class="far fa-clock me-1"></i><?php echo (int) $post['read_time']; ?> min read
                            </span>
                            <h4 class="fw-bold mb-3" style="color:#033754;"><?php echo htmlspecialchars($post['title']); ?></h4>
                            <p class="text-dark flex-grow-1"><?php echo htmlspecialchars(makeExcerpt($post['content'], 100)); ?></p>
                            <a href="blog_post.php?slug=<?php echo htmlspecialchars($post['slug']); ?>" class="btn btn-outline-primary rounded-pill mt-auto">Read More →</a>
                        </div>
                        <?php if ($post['whatsapp_text']): ?>
                            <div class="card-footer bg-transparent border-0 text-end">
                                <a href="https://wa.me/233596204991?text=<?php echo urlencode($post['whatsapp_text']); ?>" 
                                   class="btn btn-success btn-sm rounded-pill px-4" style="background:#89d900; color:#033754;">
                                    <i class="fab fa-whatsapp me-2"></i> Apply Now
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="mt-6" aria-label="Blog pagination">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars(blogUrl(['page' => $page - 1])); ?>">&laquo; Previous</a>
                </li>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars(blogUrl(['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars(blogUrl(['page' => $page + 1])); ?>">Next &raquo;</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($showFeatured): ?>
<!-- GULF & MIDDLE EAST OPPORTUNITIES -->
<section class="py-7" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);" id="gulf-jobs">
    <div class="container">
        <div class="text-center mb-6">
            <h2 class="display-5 fw-bold" style="color:#033754;">Gulf & Middle East: Jobs, Visas & Guides</h2>
            <p class="lead text-muted">UAE, Saudi Arabia, Qatar, Kuwait, Oman & Bahrain – Tax-Free Salaries, Direct Employment</p>
        </div>

        <div class="row g-5">
            <!-- UAE Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <img src="assets/images/1698963123267.jpg" class="card-img-top" alt="UAE Jobs" style="height:250px; object-fit:cover;">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">United Arab Emirates</h4>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Warehouse Helpers: AED 1,300+ OT</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Bike Riders: AED 2,400 + Tips</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Nurses: AED 6,000-10,000</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Security Guards: AED 2,500 + Accom</li>
                        </ul>
                        <a href="offers.php" class="btn btn-primary rounded-pill mt-3">Explore UAE Packages →</a>
                    </div>
                </div>
            </div>

            <!-- Saudi Arabia Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <img src="assets/images/Saudi-Arabia-Employment-Visa.webp" class="card-img-top" alt="Saudi Jobs" style="height:250px; object-fit:cover;">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">Saudi Arabia</h4>
                        <p class="text-dark">Oil & Gas, Construction, Healthcare. Salaries up to SAR 15,000. Full guide to Iqama visa.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Work Visa Process: 4-6 Weeks</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Family Sponsorship Available</li>
                        </ul>
                        <a href="https://wa.me/233596204991?text=Hi!%20Tell%20me%20about%20Saudi%20jobs" class="btn btn-success rounded-pill mt-3" style="background:#89d900; color:#033754;">
                            <i class="fab fa-whatsapp me-2"></i> Inquire Now
                        </a>
                    </div>
                </div>
            </div>

            <!-- Qatar/Kuwait/Oman/Bahrain Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <img src="assets/images/New-Project-2024-07-04T095328.524.webp" class="card-img-top" alt="Qatar Jobs" style="height:250px; object-fit:cover;">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">Qatar, Kuwait, Oman & Bahrain</h4>
                        <p class="text-dark">Oil sector, hospitality, and skilled trades. QAR/KWD/OMR/BHD entry-level packages.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Free Accommodation Common</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Tourist to Work Visa Conversion</li>
                        </ul>
                        <a href="contact.php" class="btn btn-primary rounded-pill mt-3">Get Free Consultation →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- EUROPE OPPORTUNITIES -->
<section class="py-7 bg-white" id="europe-opportunities">
    <div class="container">
        <div class="text-center mb-6">
            <h2 class="display-5 fw-bold" style="color:#033754;">Europe: Visas, Jobs & Relocation Guides</h2>
            <p class="lead text-muted">UK, Germany, Poland, Romania, Croatia, Hungary – Skilled Worker Visas, Study Paths</p>
        </div>

        <div class="row g-5">
            <!-- UK Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <img src="assets/images/blog_1.jpg" class="card-img-top" alt="UK Jobs" style="height:250px; object-fit:cover;">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">UK Skilled Worker Visa 2026</h4>
                        <p class="text-dark">NHS Jobs, IT, Engineering. Salaries £25,000+. Sponsor List Included.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> COS Application Guide</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Family Relocation</li>
                        </ul>
                        <a href="https://wa.me/233596204991?text=Hi!%20UK%20visa%20help%20please" class="btn btn-success rounded-pill mt-3" style="background:#89d900; color:#033754;">
                            <i class="fab fa-whatsapp me-2"></i> Start UK Process
                        </a>
                    </div>
                </div>
            </div>

            <!-- Germany Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <img src="assets/images/blog_2.jpg" class="card-img-top" alt="Germany Jobs" style="height:250px; object-fit:cover;">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">Germany Opportunity Card</h4>
                        <p class="text-dark">Points-Based System. Nursing, Tech. €2,500+ Monthly.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Language Requirements</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Job Search Visa</li>
                        </ul>
                        <a href="contact.php?offer=germany" class="btn btn-primary rounded-pill mt-3">Explore Germany →</a>
                    </div>
                </div>
            </div>

            <!-- Poland / Romania / Croatia / Hungary -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <img src="assets/images/blog_3.jpg" class="card-img-top" alt="Central Europe Visas" style="height:250px; object-fit:cover;">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">Poland, Romania, Croatia & Hungary</h4>
                        <p class="text-dark">Work permits and EU pathway roles in manufacturing, logistics, and hospitality.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Schengen 90-Day Rule Explained</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Employer Sponsorship Support</li>
                        </ul>
                        <a href="https://wa.me/233596204991?text=Hi!%20Europe%20visa%20guide" class="btn btn-success rounded-pill mt-3" style="background:#89d900; color:#033754;">
                            <i class="fab fa-whatsapp me-2"></i> Get Europe Help
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- NORTH AMERICA, ASIA & BEYOND -->
<section class="py-7" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);" id="beyond-opportunities">
    <div class="container">
        <div class="text-center mb-6">
            <h2 class="display-5 fw-bold" style="color:#033754;">North America, Asia & Beyond</h2>
            <p class="lead text-muted">Canada, Australia, Turkey, Malaysia, Singapore, Thailand, Egypt, Georgia & Azerbaijan – our destination portfolio keeps growing</p>
        </div>

        <div class="row g-5">
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">Canada & Australia</h4>
                        <p class="text-dark">Skilled migration pathways, study permits, and permanent residency routes for qualified applicants.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Express Entry & Skilled Migration Guidance</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Student Visa Support</li>
                        </ul>
                        <a href="contact.php" class="btn btn-primary rounded-pill mt-3">Get Consultation →</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">Turkey, Malaysia, Singapore & Thailand</h4>
                        <p class="text-dark">Fast-growing hubs for hospitality, tech, and study-abroad opportunities in Asia.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> Affordable Study & Work Pathways</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Tourism & Business Visa Support</li>
                        </ul>
                        <a href="https://wa.me/233596204991?text=Hi!%20Tell%20me%20about%20Asia%20opportunities" class="btn btn-success rounded-pill mt-3" style="background:#89d900; color:#033754;">
                            <i class="fab fa-whatsapp me-2"></i> Inquire Now
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="card-body p-4">
                        <h4 class="fw-bold mb-3" style="color:#033754;">Egypt, Georgia & Azerbaijan</h4>
                        <p class="text-dark">Emerging destinations for work, study, and tourism as we expand our global network.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i> New Routes Added Regularly</li>
                            <li><i class="fas fa-check-circle text-success me-2"></i> Early Access for Subscribers</li>
                        </ul>
                        <a href="contact.php" class="btn btn-primary rounded-pill mt-3">Learn More →</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- VIDEO GALLERY SECTION -->
<section class="py-7 bg-white" id="destination-videos">
    <div class="container">
        <div class="text-center mb-6">
            <h2 class="display-5 fw-bold" style="color:#033754;">Explore Our Destinations Worldwide</h2>
            <p class="lead text-muted">From the Gulf to Europe and Asia -- silent destination guides across every region we serve.</p>
        </div>
        <div class="row g-5">
            <!-- Palm Jumeirah Beach, Dubai -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/Uwxl_L0258w?autoplay=0&mute=1&controls=1&loop=1&playlist=Uwxl_L0258w" title="Silent View of Palm Jumeirah" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Palm Jumeirah Beach, Dubai</h5>
                        <p class="text-dark">Silent aerial view of the iconic palm-shaped island and beaches.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Dubai%20Holidays" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>
            <!-- Dubai Mall -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/N0g9fjHclos?autoplay=0&mute=1&controls=1&loop=1&playlist=N0g9fjHclos" title="Silent Tour of Dubai Mall" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Dubai Mall</h5>
                        <p class="text-dark">Quiet night walk through one of the world's largest shopping malls.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Dubai%20Shopping%20Tours" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>
            <!-- Burj Khalifa, Dubai -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/L5DMlLUpXvE?autoplay=0&mute=1&controls=1&loop=1&playlist=L5DMlLUpXvE" title="Silent View of Burj Khalifa" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Burj Khalifa, Dubai</h5>
                        <p class="text-dark">Ambient silent walk around the tallest building in the world.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Dubai%20Sightseeing" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>
            <!-- Saudi Arabia Tourist Places -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/L2h7G2i3aQ4?autoplay=0&mute=1&controls=1&loop=1&playlist=L2h7G2i3aQ4" title="Silent Wonders of Saudi Arabia" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Saudi Arabia Tourist Places</h5>
                        <p class="text-dark">Silent exploration of breathtaking landscapes and historic sites.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Saudi%20Arabia%20Holidays" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>
            <!-- Qatar Tourist Places -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/WiXeyZ1khBA?autoplay=0&mute=1&controls=1&loop=1&playlist=WiXeyZ1khBA" title="Silent Beauty of Qatar" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Qatar Tourist Places</h5>
                        <p class="text-dark">Peaceful silent views of Doha's skyline and desert serenity.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Qatar%20Holidays" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>
            <!-- Brief Overview: Jobs in Gulf Countries -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/T81ocOncZFs?autoplay=0&mute=1&controls=1&loop=1&playlist=T81ocOncZFs" title="Short Film on Gulf Holidays" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Jobs in Gulf Countries</h5>
                        <p class="text-dark">Brief short: High-demand roles in media, entertainment, and more with competitive salaries.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Gulf%20Jobs" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>
            <!-- Brief Overview: Holidays in Gulf Countries -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/ol_yFrM10O0?autoplay=0&mute=1&controls=1&loop=1&playlist=ol_yFrM10O0" title="Holiday in Gulf Countries" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Holidays in Gulf Countries</h5>
                        <p class="text-dark">Short overview: Luxurious getaways in Dubai, Abu Dhabi, and beyond.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Gulf%20Holidays" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>

            <!-- ===================== EUROPE VIDEOS ===================== -->

            <!-- London, UK -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/ZSKtvO5OKvk?autoplay=0&mute=1&controls=1&loop=1&playlist=ZSKtvO5OKvk" title="Silent Aerial Flyover of London" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">London, United Kingdom</h5>
                        <p class="text-dark">Silent cinematic flyover of the Thames, Tower Bridge, and the city skyline.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20UK%20Opportunities" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>

            <!-- Berlin, Germany -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/bQo3tlj8sSs?autoplay=0&mute=1&controls=1&loop=1&playlist=bQo3tlj8sSs" title="Silent Aerial Tour of Berlin" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Berlin, Germany</h5>
                        <p class="text-dark">Quiet aerial views of the Brandenburg Gate and Germany's capital skyline.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Germany%20Opportunities" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>

            <!-- ===================== ASIA VIDEOS ===================== -->

            <!-- Istanbul, Turkey -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/N0UzSDpCRmY?autoplay=0&mute=1&controls=1&loop=1&playlist=N0UzSDpCRmY" title="Silent Aerial Tour of Istanbul" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Istanbul, Turkey</h5>
                        <p class="text-dark">Silent flyover of the Bosphorus and Istanbul's iconic skyline where Europe meets Asia.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Turkey%20Opportunities" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>

            <!-- Singapore -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift">
                    <div class="ratio ratio-16x9">
                        <iframe src="https://www.youtube.com/embed/K4EW_fVzPvg?autoplay=0&mute=1&controls=1&loop=1&playlist=K4EW_fVzPvg" title="Silent Aerial Tour of Singapore" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold" style="color:#033754;">Singapore</h5>
                        <p class="text-dark">Silent aerial views of Marina Bay Sands and the Lion City's futuristic skyline.</p>
                        <a href="https://wa.me/233596204991?text=Interested%20in%20Singapore%20Opportunities" class="btn btn-success rounded-pill" style="background:#89d900; color:#033754;">
                            Inquire Now
                        </a>
                    </div>
                </div>
            </div>

            <!-- Placeholder: coming soon card, invites subscribers instead of an empty gap -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden h-100 hover-lift d-flex align-items-center justify-content-center text-center p-4" style="min-height:100%; background: linear-gradient(135deg, #033754 0%, #0a6c8a 100%); color:#fff;">
                    <div>
                        <i class="fas fa-globe fa-2x mb-3" style="color:#89d900;"></i>
                        <h5 class="fw-bold mb-2">More Destination Videos Coming Soon</h5>
                        <p class="opacity-90 mb-3">Subscribe to be first to see new destination content.</p>
                        <a href="https://wa.me/233596204991?text=Please%20notify%20me%20about%20new%20destination%20videos" class="btn btn-sm rounded-pill" style="background:#89d900; color:#033754; font-weight:700;">
                            Notify Me
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- WHATSAPP + EMAIL SUBSCRIPTION CTA -->
<section class="py-7 text-white text-center" style="background: linear-gradient(135deg, #033754 0%, #0a6c8a 100%);">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <h2 class="display-5 fw-bold mb-3">Get Global Job & Visa Alerts Instantly</h2>
                <p class="lead mb-4 opacity-90">Gulf, Europe, North America & Asia updates plus urgent vacancies sent to your phone or email</p>
               
                <a href="https://wa.me/233596204991?text=Please%20add%20me%20to%20Believe%20Travel%20Consult%20Abroad%20Alerts!"
                   class="btn rounded-pill px-5 py-3 shadow-lg mb-5"
                   style="background:#89d900; color:#033754; font-weight:800; font-size:1.25rem;">
                    <i class="fab fa-whatsapp fa-lg me-2"></i> Add Me to WhatsApp Alerts (Free)
                </a>
                <!-- Email Subscription Form -->
                <div class="bg-white rounded-4 shadow-lg p-4 p-md-5 mx-auto" style="max-width: 560px;">
                    <h4 class="fw-bold mb-3" style="color:#033754;">Subscribe for Email Updates</h4>
                    <p class="text-muted mb-4">Weekly stories, salaries, and visa guidance for every destination we serve</p>
                    <form action="subscribe.php" method="POST" class="row g-3 justify-content-center">
                        <div class="col-12 col-md-8">
                            <input type="email" name="email" class="form-control form-control-lg rounded-pill text-center border-0 shadow-sm"
                                   placeholder="Enter your email" required
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
                        No spam • Unsubscribe anytime • Privacy guaranteed
                    </small>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<!-- Premium Hover Effects & Styles -->
<style>
.hover-lift { transition: all 0.4s ease; }
.hover-lift:hover { 
    transform: translateY(-15px); 
    box-shadow: 0 30px 60px rgba(3,55,84,0.25)!important; 
}
.z-3 { z-index: 3; }
.badge { font-weight: 700; }
.pagination .page-link { color: #033754; border-radius: 50px; margin: 0 3px; border: none; }
.pagination .page-item.active .page-link { background: #033754; color: #fff; }
.pagination .page-item.disabled .page-link { color: #adb5bd; }
</style>