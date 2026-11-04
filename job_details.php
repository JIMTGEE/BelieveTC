<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
include 'includes/header.php';

if (!function_exists('cleanText')) {
    /**
     * Normalizes short plain-text DB fields (title, location, duration)
     * before they're ever displayed. These are entered through the same
     * rich-text-capable admin field as `description`, so they often carry
     * stray &nbsp; entities, real non-breaking-space bytes, or leftover
     * tags. Left alone, &nbsp; shows up as literal "&nbsp;" text once
     * htmlspecialchars() escapes the & -- and it also pollutes the
     * WhatsApp deep-link message text below.
     *
     * NOTE: this is intentionally NOT applied to `description` on this
     * page -- that field is rendered unescaped as rich HTML further down
     * ("Full Package Details"), so its &nbsp; entities are already valid
     * HTML and render as real spaces in the browser as-is.
     */
    function cleanText($text) {
        if ($text === null || $text === '') return '';
        $text = preg_replace('/<(p|br|div|li)\b[^>]*>/i', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
}

$currencySymbols = ['GHS' => '₵', 'AED' => 'د.إ'];

// Only published jobs are ever visible here -- this keeps job_details.php in
// sync with offers.php and the admin panel: hide a job in admin/offers.php
// and its direct-link detail page disappears too, no separate flag to manage.
$offer_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($offer_id <= 0) {
    header('Location: offers.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM offers WHERE id = ? AND status = 'published'");
$stmt->execute([$offer_id]);
$offer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$offer) {
    header('Location: offers.php');
    exit;
}

// Clean the plain-text fields only -- `description` is left untouched
// since it's rendered as raw HTML further down this page.
$offer['title']    = cleanText($offer['title'] ?? '');
$offer['location'] = cleanText($offer['location'] ?? '');
$offer['duration'] = cleanText($offer['duration'] ?? '');

$currency       = $offer['currency'] ?? 'GHS';
$currencySymbol = $currencySymbols[$currency] ?? '';
$hasDescription = trim(strip_tags($offer['description'] ?? '')) !== '';
$imagePath      = !empty($offer['image']) ? 'assets/images/' . $offer['image'] : 'assets/images/placeholder.jpg';
$postedDate     = !empty($offer['created_at']) ? date('F j, Y', strtotime($offer['created_at'])) : null;

// Pull a handful of other currently-published offers to suggest at the bottom.
$otherStmt = $pdo->prepare("SELECT id, title, location, price, currency, image, featured FROM offers WHERE status = 'published' AND id != ? ORDER BY featured DESC, created_at DESC LIMIT 3");
$otherStmt->execute([$offer_id]);
$otherOffers = $otherStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($otherOffers as &$other) {
    $other['title']    = cleanText($other['title'] ?? '');
    $other['location'] = cleanText($other['location'] ?? '');
}
unset($other);

$waMessage = 'URGENT! I want the job: ' . $offer['title'] . ' (' . $offer['duration'] . ') - ' . $currencySymbol . ' ' . number_format((float) $offer['price'], 2) . ' ' . $currency;
?>

<!-- HERO WITH JOB IMAGE -->
<section class="position-relative text-white text-center overflow-hidden" style="min-height:78vh; display:flex; align-items:center;">
    <div class="position-absolute w-100 h-100" style="background: linear-gradient(rgba(3,55,84,0.92), rgba(3,55,84,0.88)), url('<?= htmlspecialchars($imagePath) ?>') center/cover no-repeat; top:0; left:0; z-index:1;"></div>

    <div class="container position-relative" style="z-index:2;">
        <div class="row justify-content-center">
            <div class="col-lg-11" data-aos="fade-up">
                <h1 class="display-2 fw-bold mb-4 lh-1"><?= htmlspecialchars($offer['title']) ?></h1>

                <p class="fs-2 mb-4 opacity-90">
                    <i class="fas fa-map-marker-alt me-3"></i><?= htmlspecialchars($offer['location']) ?>
                </p>

                <div class="display-3 fw-bold mb-4" style="color:#89d900; text-shadow: 0 4px 20px rgba(0,0,0,0.6);">
                    <?= htmlspecialchars($offer['duration']) ?>
                </div>

                <p class="display-5 fw-bold text-white mb-5">
                    Service Fee: <span style="color:#89d900;"><?= htmlspecialchars($currencySymbol) ?> <?= number_format((float) $offer['price'], 2) ?> <small class="fs-4"><?= htmlspecialchars($currency) ?></small></span>
                </p>

                <?php if ($offer['featured']): ?>
                    <span class="badge bg-warning text-dark fs-4 px-5 py-3 rounded-pill shadow-lg mb-4">
                        <i class="fas fa-fire me-2"></i> URGENT HIRING – LIMITED SPOTS
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- MAIN CONTENT + VIDEO -->
<section class="py-7 bg-light">
    <div class="container">
        <div class="row g-5">
            <!-- Left Column: Quick Facts + Full Details + Video -->
            <div class="col-lg-8">

                <!-- Job Overview / Quick Facts -->
                <div class="bg-white rounded-4 shadow-lg p-5 mb-5">
                    <h2 class="fw-bold mb-4" style="color:#033754;">Job Overview</h2>
                    <div class="row g-4">
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width:48px; height:48px; background:rgba(16,182,215,0.12);">
                                    <i class="fas fa-map-marker-alt" style="color:#10b6d7;"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Location</small>
                                    <span class="fw-semibold"><?= htmlspecialchars($offer['location']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width:48px; height:48px; background:rgba(137,217,0,0.15);">
                                    <i class="fas fa-clock" style="color:#5c9200;"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Duration / Salary</small>
                                    <span class="fw-semibold"><?= htmlspecialchars($offer['duration']) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width:48px; height:48px; background:rgba(255,193,7,0.15);">
                                    <i class="fas fa-money-bill-wave" style="color:#b8860b;"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Service Fee</small>
                                    <span class="fw-semibold"><?= htmlspecialchars($currencySymbol) ?> <?= number_format((float) $offer['price'], 2) ?> <?= htmlspecialchars($currency) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php if ($postedDate): ?>
                        <div class="col-sm-6">
                            <div class="d-flex align-items-start">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 me-3" style="width:48px; height:48px; background:rgba(3,55,84,0.08);">
                                    <i class="fas fa-calendar-alt" style="color:#033754;"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Posted</small>
                                    <span class="fw-semibold"><?= htmlspecialchars($postedDate) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Full Description: requirements, benefits, accommodation, everything the -->
                <!-- admin entered in the rich editor lives here as formatted HTML.          -->
                <?php if ($hasDescription): ?>
                <div class="bg-white rounded-4 shadow-lg p-5 mb-5">
                    <h2 class="fw-bold mb-4" style="color:#033754;">Full Package Details</h2>
                    <div class="fs-5 text-dark lh-lg job-description-body">
                        <?= $offer['description'] ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-4 shadow-lg p-5 mb-5 text-center text-muted">
                    <i class="fas fa-circle-info fs-3 mb-3 d-block"></i>
                    Full requirements and benefits for this job haven't been added yet -- message us on WhatsApp and we'll walk you through everything.
                </div>
                <?php endif; ?>

                <!-- Your Real Testimonial Video -->
                <div class="bg-white rounded-4 shadow-lg overflow-hidden">
                    <div class="p-4 text-center text-white" style="background:#033754;">
                        <h3 class="fw-bold mb-0">Watch Someone Who Took This Same Package</h3>
                    </div>
                    <div class="ratio ratio-16x9">
                        <video controls poster="<?= htmlspecialchars($imagePath) ?>" preload="metadata" class="w-100 h-100 rounded-bottom-4">
                            <source src="assets/videos/offer_video.mp4" type="video/mp4">
                            Your browser does not support video.
                        </video>
                    </div>
                </div>
            </div>

            <!-- Right Column: Booking Card -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden sticky-top" style="top:100px;">
                    <div class="card-header text-center py-5 text-white" style="background:#033754;">
                        <h3 class="fw-bold fs-3 mb-3">Book This Job Now</h3>
                        <div class="display-5 fw-bold" style="color:#89d900;"><?= htmlspecialchars($currencySymbol) ?> <?= number_format((float) $offer['price'], 2) ?></div>
                        <small><?= htmlspecialchars($currency) ?> · Includes visa, flight, pickup &amp; job</small>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-grid gap-4">
                            <a href="https://wa.me/233596204991?text=<?= urlencode($waMessage) ?>"
                               class="btn btn-lg rounded-pill shadow-lg"
                               style="background:#89d900; color:#033754; font-weight:800; font-size:1.4rem;">
                                <i class="fab fa-whatsapp fa-2x me-3"></i> Book via WhatsApp
                            </a>

                            <a href="tel:+233596204991" class="btn btn-lg btn-outline-primary rounded-pill">
                                <i class="fas fa-phone fa-lg me-2"></i> Call Now: +233 596 204 991
                            </a>

                            <a href="contact.php" class="btn btn-lg btn-primary rounded-pill">
                                <i class="fas fa-envelope me-2"></i> Send Inquiry
                            </a>
                        </div>

                        <div class="mt-4 p-3 bg-success text-white text-center rounded">
                            <p class="mb-0 fw-bold">100% Guaranteed Placement or Full Refund</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 p-4 bg-white shadow rounded-4 text-center border border-success">
                    <img src="assets/images/logo_new.png" alt="Logo" width="90" class="mb-3">
                    <p class="fw-bold mb-1" style="color:#033754;">Believe Travel Consult</p>
                    <p class="small text-muted">Trusted by 1,500+ Ghanaians</p>
                    <p><i class="fas fa-envelope text-primary"></i> believetravelconsult903@gmail.com</p>
                </div>

                <?php if (!empty($otherOffers)): ?>
                <div class="mt-4 p-4 bg-white shadow rounded-4">
                    <h5 class="fw-bold mb-3" style="color:#033754;">Other Jobs You Might Like</h5>
                    <?php foreach ($otherOffers as $other): ?>
                        <a href="job_details.php?id=<?= (int) $other['id'] ?>" class="d-flex align-items-center text-decoration-none mb-3 pb-3 border-bottom">
                            <?php
                            $otherImg = !empty($other['image']) ? 'assets/images/' . $other['image'] : 'assets/images/placeholder.jpg';
                            $otherSym = $currencySymbols[$other['currency'] ?? 'GHS'] ?? '';
                            ?>
                            <img src="<?= htmlspecialchars($otherImg) ?>" alt="" class="rounded-3 me-3" style="width:56px; height:56px; object-fit:cover;">
                            <div>
                                <span class="d-block fw-semibold small" style="color:#033754;"><?= htmlspecialchars($other['title']) ?></span>
                                <span class="d-block text-muted small"><?= htmlspecialchars($other['location']) ?> · <?= htmlspecialchars($otherSym) ?> <?= number_format((float) $other['price']) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <a href="offers.php" class="btn btn-outline-primary btn-sm rounded-pill w-100">View All Offers</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- FINAL CTA -->
<section class="py-7 text-white text-center" style="background: linear-gradient(135deg, #033754, #0a6c8a);">
    <div class="container">
        <h2 class="display-4 fw-bold mb-4">Don't Miss This Opportunity</h2>
        <p class="lead fs-3 mb-5">Spots fill fast — message us NOW before it's gone!</p>
        <a href="https://wa.me/233596204991?text=<?= urlencode("I'm ready for the " . $offer['title'] . ' job!') ?>"
           class="btn btn-lg rounded-pill px-6 py-5 shadow-lg"
           style="background:#89d900; color:#033754; font-weight:800; font-size:1.5rem;">
            <i class="fab fa-whatsapp fa-2x me-3"></i> WhatsApp Us Immediately
        </a>
    </div>
</section>

<style>
    .job-description-body img { max-width: 100%; border-radius: 12px; }
    .job-description-body { line-height: 1.8; }
</style>

<?php include 'includes/footer.php'; ?>