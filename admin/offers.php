<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

/* =========================================================================
   ONE-TIME MIGRATIONS NEEDED (run once in phpMyAdmin / mysql CLI):

   ALTER TABLE offers
       ADD COLUMN status ENUM('published','hidden') NOT NULL DEFAULT 'published'
       AFTER featured;

   ALTER TABLE offers
       ADD COLUMN country VARCHAR(5) NOT NULL DEFAULT 'AE'
       AFTER location;

   The `status` column controls whether a job shows up on the public
   offers/jobs pages. The `country` column is what lets this admin panel
   (and the public jobs page) present Believe Travel Consult as a truly
   international recruitment brand instead of a UAE-only company -- every
   offer now belongs to a destination, and the public site filters/aggregates
   by it. Existing rows default to 'AE' (UAE) so nothing breaks on upgrade.
   ========================================================================= */

// NOTE: assumes the `offers` table already has a `currency` VARCHAR(3) DEFAULT 'GHS' column
// (no schema changes are made by this page besides the `status`/`country` migrations noted above).

/* =========================================================================
   Currency conversion -- single source of truth for the fixed exchange rate.
   Update AED_TO_GHS_RATE (and the date) whenever you get a fresher rate;
   there is no live-rate lookup here, so this needs a human to keep it current.
   ========================================================================= */
const AED_TO_GHS_RATE = 3.4305;
const RATE_UPDATED_ON = '2025-12-22';
$currencySymbols = ['GHS' => '₵', 'AED' => 'د.إ'];
$validStatuses = ['published', 'hidden'];

/* =========================================================================
   Global destinations -- the single source of truth for every country
   Believe Travel Consult recruits/relocates/travels clients to. Keep this
   list in sync with the public jobs.php "Countries We Serve" section.
   'OT' is a deliberate catch-all so new destinations can be posted the
   moment a partnership is signed, before this list is formally updated.
   ========================================================================= */
$countries = [
    'AE' => 'United Arab Emirates',
    'SA' => 'Saudi Arabia',
    'QA' => 'Qatar',
    'KW' => 'Kuwait',
    'OM' => 'Oman',
    'BH' => 'Bahrain',
    'GB' => 'United Kingdom',
    'DE' => 'Germany',
    'PL' => 'Poland',
    'RO' => 'Romania',
    'HR' => 'Croatia',
    'HU' => 'Hungary',
    'CA' => 'Canada',
    'AU' => 'Australia',
    'TR' => 'Turkey',
    'MY' => 'Malaysia',
    'SG' => 'Singapore',
    'TH' => 'Thailand',
    'EG' => 'Egypt',
    'GE' => 'Georgia',
    'AZ' => 'Azerbaijan',
    'OT' => 'Other / New Destination',
];
$validCountries = array_keys($countries);

if (!function_exists('countryFlagEmoji')) {
    function countryFlagEmoji($code) {
        if ($code === 'OT' || strlen($code) !== 2) return '🌍';
        $code = strtoupper($code);
        $flag = '';
        for ($i = 0; $i < 2; $i++) {
            $ord = ord($code[$i]);
            if ($ord < 65 || $ord > 90) return '🌍';
            $flag .= mb_chr(127397 + $ord);
        }
        return $flag;
    }
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
        $stmt = $pdo->prepare("SELECT featured FROM offers WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            echo json_encode(['success' => false, 'error' => 'Offer not found.']);
            exit;
        }
        $new = $current ? 0 : 1;
        $upd = $pdo->prepare("UPDATE offers SET featured = ? WHERE id = ?");
        $upd->execute([$new, $id]);
        echo json_encode(['success' => true, 'featured' => $new]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Could not update this offer.']);
    }
    exit;
}

/* =========================================================================
   AJAX: quick "toggle visibility" (published/hidden) from the table
   This is the switch that decides whether a job shows up on the public
   offers.php / jobs.php pages.
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    $id = (int) ($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT status FROM offers WHERE id = ?");
        $stmt->execute([$id]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            echo json_encode(['success' => false, 'error' => 'Offer not found.']);
            exit;
        }
        $new = ($current === 'published') ? 'hidden' : 'published';
        $upd = $pdo->prepare("UPDATE offers SET status = ? WHERE id = ?");
        $upd->execute([$new, $id]);
        echo json_encode(['success' => true, 'status' => $new]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Could not update visibility for this offer.']);
    }
    exit;
}

/* =========================================================================
   Add / Edit / Delete / Duplicate
   ========================================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add' || $action === 'edit') {
        $title       = trim(sanitize($_POST['title'] ?? ''));
        $location    = trim(sanitize($_POST['location'] ?? ''));
        $country     = sanitize($_POST['country'] ?? 'AE');
        $duration    = trim(sanitize($_POST['duration'] ?? ''));
        $description = $_POST['description'] ?? ''; // Allow HTML -- comes from the rich editor
        $price       = (float) ($_POST['price'] ?? 0);
        $currency    = sanitize($_POST['currency'] ?? 'GHS');
        $featured    = isset($_POST['featured']) ? 1 : 0;
        $status      = sanitize($_POST['status'] ?? 'published');
        $id          = (int) ($_POST['id'] ?? 0);

        if (!array_key_exists($country, $countries)) {
            $country = 'AE';
        }
        if (!in_array($currency, ['GHS', 'AED'], true)) {
            $currency = 'GHS';
        }
        if (!in_array($status, $validStatuses, true)) {
            $status = 'published';
        }

        $errors = [];
        if ($title === '') $errors[] = 'Title is required.';
        if ($location === '') $errors[] = 'Location is required.';
        if ($duration === '') $errors[] = 'Duration/salary info is required.';
        if ($price < 0) $errors[] = 'Price cannot be negative.';

        // Image handling
        $image = sanitize($_POST['existing_image'] ?? '') ?: ''; // `image` is NOT NULL in the DB -- never pass null
        if (!empty($_FILES['image']['name'])) {
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
                    $uploaded = uploadImage($_FILES['image'], '../assets/images/');
                    if (!$uploaded) {
                        $errors[] = 'Failed to upload image.';
                    } else {
                        $image = $uploaded;
                    }
                }
            }
        }

        if (!empty($errors)) {
            flash('error', implode(' ', $errors));
        } elseif ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO offers (title, location, country, duration, description, price, currency, image, featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $location, $country, $duration, $description, $price, $currency, $image, $featured, $status]);
            $visibilityNote = $status === 'published' ? 'and is live on the public site' : 'but is currently hidden from the public site';
            flash('success', 'Offer "' . $title . '" was added for ' . $countries[$country] . ' ' . $visibilityNote . '.');
        } else {
            $stmt = $pdo->prepare("UPDATE offers SET title=?, location=?, country=?, duration=?, description=?, price=?, currency=?, image=?, featured=?, status=? WHERE id=?");
            $stmt->execute([$title, $location, $country, $duration, $description, $price, $currency, $image, $featured, $status, $id]);
            flash('success', 'Offer "' . $title . '" was updated.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT title, image FROM offers WHERE id = ?");
        $stmt->execute([$id]);
        $offer = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($offer) {
            if ($offer['image'] && file_exists('../assets/images/' . $offer['image'])) {
                unlink('../assets/images/' . $offer['image']);
            }
            $stmt = $pdo->prepare("DELETE FROM offers WHERE id = ?");
            $stmt->execute([$id]);
            flash('success', 'Offer "' . $offer['title'] . '" was deleted.');
        }
    } elseif ($action === 'duplicate') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM offers WHERE id = ?");
        $stmt->execute([$id]);
        $orig = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($orig) {
            $newTitle = $orig['title'] . ' (Copy)';
            // Duplicates are always created hidden + unfeatured, so nothing new
            // ever appears on the public site until you've reviewed it.
            $stmt = $pdo->prepare("INSERT INTO offers (title, location, country, duration, description, price, currency, image, featured, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'hidden')");
            $stmt->execute([$newTitle, $orig['location'], $orig['country'] ?? 'AE', $orig['duration'], $orig['description'], $orig['price'], $orig['currency'] ?? 'GHS', $orig['image']]);
            flash('success', 'Duplicated as "' . $newTitle . '". It is hidden and unfeatured -- review it before publishing.');
        }
    }
    redirect('offers.php');
}

/* =========================================================================
   DATA LAYER
   ========================================================================= */
$stmt = $pdo->query("SELECT * FROM offers ORDER BY created_at DESC");
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($offers as &$o) {
    if (empty($o['currency'])) $o['currency'] = 'GHS';
    if (empty($o['status']) || !in_array($o['status'], $validStatuses, true)) $o['status'] = 'published';
    if (empty($o['country']) || !array_key_exists($o['country'], $countries)) $o['country'] = 'AE';
}
unset($o);

$totalOffers    = count($offers);
$featuredCount  = 0;
$publishedCount = 0;
$locations      = [];
$countryCounts  = [];
$thisMonth      = 0;
foreach ($offers as $o) {
    if ($o['featured']) $featuredCount++;
    if ($o['status'] === 'published') $publishedCount++;
    if (!empty($o['location'])) $locations[trim($o['location'])] = true;
    if (!empty($o['created_at']) && date('Y-m', strtotime($o['created_at'])) === date('Y-m')) $thisMonth++;
    $countryCounts[$o['country']] = ($countryCounts[$o['country']] ?? 0) + 1;
}
$locationCount   = count($locations);
$destinationCount = count($countryCounts);

// Deep-link support: /offers.php?edit=ID auto-opens the edit modal for that offer
$autoEditId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

// One-time flash message
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// JS-friendly country lookup (code => name + flag) for client-side rendering
$countriesForJs = [];
foreach ($countries as $code => $name) {
    $countriesForJs[$code] = ['name' => $name, 'flag' => countryFlagEmoji($code)];
}

// Page-specific setup for the shared header
$pageTitle = 'Manage Job Offers';
$extraHead = <<<HTML
<link rel="stylesheet" href="https://cdn.datatables.net/2.1.8/css/dataTables.bootstrap5.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/tinymce/7.0.0/skins/ui/oxide/skin.min.css">
<style>
    .offer-thumb { width: 56px; height: 56px; object-fit: cover; border-radius: 10px; }
    .offer-thumb-placeholder { width: 56px; height: 56px; border-radius: 10px; background: var(--bg-light); display: flex; align-items: center; justify-content: center; color: #b7c1c8; }
    .currency-pill { cursor: pointer; border: none; }
    .currency-pill.active { background: var(--primary-light) !important; color: #fff !important; }
    .featured-toggle { cursor: pointer; border: none; background: none; font-size: 1.15rem; line-height: 1; padding: 0; }
    .featured-toggle.is-featured { color: #ffc107; }
    .featured-toggle:not(.is-featured) { color: #d5dbe0; }
    .featured-toggle:disabled { opacity: 0.5; cursor: wait; }
    .status-toggle { cursor: pointer; border: none; font-size: 0.75rem; font-weight: 700; padding: .3rem .7rem; border-radius: 50px; }
    .status-toggle.is-published { background: rgba(137,217,0,0.15); color: #5c9200; }
    .status-toggle.is-hidden { background: rgba(220,53,69,0.12); color: #b02a37; }
    .status-toggle:disabled { opacity: 0.5; cursor: wait; }
    .country-pill-cell { font-weight: 600; white-space: nowrap; }
    #imageDropZone { border: 2px dashed rgba(3,55,84,0.2); border-radius: 14px; padding: 1.5rem; text-align: center; cursor: pointer; transition: all .2s ease; }
    #imageDropZone:hover, #imageDropZone.dragover { border-color: var(--primary-light); background: rgba(16,182,215,0.05); }
    #previewBody img { max-width: 100%; border-radius: 12px; }
    #previewBody { line-height: 1.7; }
    .rate-note { font-size: 0.75rem; color: #9aa5ad; }
    .mini-stat-5 .mini-stat { height: 100%; }
    #countryFilterSelect { min-width: 200px; }
</style>
HTML;

include 'admin_header.php';
?>

        <!-- Header + Add button -->
        <div class="row mb-4 align-items-center">
            <div class="col">
                <h2 class="fw-bold display-6" style="color:#033754;">Manage Job Offers</h2>
                <p class="text-muted lead mb-0">Add, edit, or feature job offers across every destination Believe Travel Consult serves. Use <strong>Destination</strong> to tag where the role is and <strong>Visible</strong> to control what shows up publicly.</p>
            </div>
            <div class="col-auto">
                <button id="addBtn" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#offerModal"><i class="fas fa-plus me-2"></i> Add New Offer</button>
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
        <div class="row g-4 mb-4 row-cols-2 row-cols-lg-5 mini-stat-5">
            <div data-aos="fade-up">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(16,182,215,0.12); color:var(--primary-light);"><i class="fas fa-briefcase"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $totalOffers; ?></h4>
                        <small class="text-muted">Total Offers</small>
                    </div>
                </div>
            </div>
            <div data-aos="fade-up" data-aos-delay="100">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(137,217,0,0.15); color:#5c9200;"><i class="fas fa-eye"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $publishedCount; ?></h4>
                        <small class="text-muted">Visible to Public</small>
                    </div>
                </div>
            </div>
            <div data-aos="fade-up" data-aos-delay="200">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(255,193,7,0.15); color:#b8860b;"><i class="fas fa-star"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $featuredCount; ?></h4>
                        <small class="text-muted">Featured</small>
                    </div>
                </div>
            </div>
            <div data-aos="fade-up" data-aos-delay="300">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(212,175,55,0.18); color:#a8842a;"><i class="fas fa-earth-americas"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $destinationCount; ?></h4>
                        <small class="text-muted">Active Destinations</small>
                    </div>
                </div>
            </div>
            <div data-aos="fade-up" data-aos-delay="400">
                <div class="mini-stat">
                    <div class="mini-icon" style="background:rgba(3,55,84,0.08); color:var(--primary-dark);"><i class="fas fa-calendar-alt"></i></div>
                    <div>
                        <h4 class="fw-bold mb-0"><?php echo $thisMonth; ?></h4>
                        <small class="text-muted">Posted This Month</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Offers Table -->
        <div class="card p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 px-2">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-semibold small text-muted me-1">Currency:</span>
                    <button class="btn btn-sm btn-outline-primary rounded-pill currency-pill active" data-currency="">All</button>
                    <button class="btn btn-sm btn-outline-primary rounded-pill currency-pill" data-currency="GHS">GHS</button>
                    <button class="btn btn-sm btn-outline-primary rounded-pill currency-pill" data-currency="AED">AED</button>
                    <span class="fw-semibold small text-muted ms-3 me-1">Destination:</span>
                    <select id="countryFilterSelect" class="form-select form-select-sm">
                        <option value="">All Destinations</option>
                        <?php foreach ($countries as $code => $name): if ($code === 'OT') continue; ?>
                            <option value="<?php echo $code; ?>"><?php echo countryFlagEmoji($code) . ' ' . htmlspecialchars($name); ?><?php echo isset($countryCounts[$code]) ? ' (' . $countryCounts[$code] . ')' : ''; ?></option>
                        <?php endforeach; ?>
                        <option value="OT">🌍 Other / New Destination<?php echo isset($countryCounts['OT']) ? ' (' . $countryCounts['OT'] . ')' : ''; ?></option>
                    </select>
                </div>
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="visibleOnlySwitch">
                        <label class="form-check-label small fw-semibold" for="visibleOnlySwitch">Visible only</label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="featuredOnlySwitch">
                        <label class="form-check-label small fw-semibold" for="featuredOnlySwitch">Featured only</label>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table id="offersTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Destination</th>
                            <th>Price</th>
                            <th>Visible</th>
                            <th>Featured</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($offers)): ?>
                            <tr><td colspan="9" class="text-center text-muted py-5">No job offers found. Add one to get started.</td></tr>
                        <?php else: ?>
                            <?php foreach ($offers as $offer): ?>
                                <tr data-currency="<?php echo htmlspecialchars($offer['currency']); ?>" data-featured="<?php echo (int) $offer['featured']; ?>" data-status="<?php echo htmlspecialchars($offer['status']); ?>" data-country="<?php echo htmlspecialchars($offer['country']); ?>">
                                    <td><?php echo $offer['id']; ?></td>
                                    <td>
                                        <?php if (!empty($offer['image'])): ?>
                                            <img src="../assets/images/<?php echo htmlspecialchars($offer['image']); ?>" alt="Offer image" class="offer-thumb shadow-sm">
                                        <?php else: ?>
                                            <span class="offer-thumb-placeholder"><i class="fas fa-image"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($offer['title']); ?></td>
                                    <td><?php echo htmlspecialchars($offer['location']); ?></td>
                                    <td class="country-pill-cell"><?php echo countryFlagEmoji($offer['country']) . ' ' . htmlspecialchars($countries[$offer['country']] ?? 'Other'); ?></td>
                                    <td><?php echo htmlspecialchars($currencySymbols[$offer['currency']] ?? ''); ?> <?php echo number_format((float) $offer['price'], 2); ?> <span class="text-muted small"><?php echo htmlspecialchars($offer['currency']); ?></span></td>
                                    <td class="text-center">
                                        <button class="status-toggle <?php echo $offer['status'] === 'published' ? 'is-published' : 'is-hidden'; ?>" data-id="<?php echo $offer['id']; ?>" title="Toggle public visibility">
                                            <i class="fas <?php echo $offer['status'] === 'published' ? 'fa-eye' : 'fa-eye-slash'; ?> me-1"></i><?php echo $offer['status'] === 'published' ? 'Published' : 'Hidden'; ?>
                                        </button>
                                    </td>
                                    <td class="text-center">
                                        <button class="featured-toggle <?php echo $offer['featured'] ? 'is-featured' : ''; ?>" data-id="<?php echo $offer['id']; ?>" title="Toggle featured">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    </td>
                                    <td class="text-nowrap">
                                        <button class="btn btn-sm btn-outline-secondary me-1 preview-btn" data-id="<?php echo $offer['id']; ?>" title="Preview"><i class="fas fa-eye"></i></button>
                                        <button class="btn btn-sm btn-outline-primary me-1 edit-btn" data-id="<?php echo $offer['id']; ?>" title="Edit"><i class="fas fa-edit"></i></button>
                                        <form method="POST" style="display:inline;" class="duplicate-form">
                                            <input type="hidden" name="action" value="duplicate">
                                            <input type="hidden" name="id" value="<?php echo $offer['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success me-1" title="Duplicate"><i class="fas fa-copy"></i></button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this offer? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $offer['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- Offer Modal (for Add/Edit) -->
<div class="modal fade" id="offerModal" tabindex="-1" aria-labelledby="offerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="offerModalLabel">Add New Offer</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="offerForm" method="POST" enctype="multipart/form-data">
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
                            <div class="row">
                                <div class="col-7 mb-3">
                                    <label class="form-label fw-semibold">Location <span class="text-muted fw-normal">(city / employer area)</span></label>
                                    <input type="text" name="location" class="form-control" required>
                                </div>
                                <div class="col-5 mb-3">
                                    <label class="form-label fw-semibold">Destination</label>
                                    <select name="country" id="countrySelect" class="form-select">
                                        <?php foreach ($countries as $code => $name): ?>
                                            <option value="<?php echo $code; ?>"><?php echo countryFlagEmoji($code) . ' ' . htmlspecialchars($name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Duration / Salary Info</label>
                                <input type="text" name="duration" class="form-control" required>
                            </div>
                            <div class="row">
                                <div class="col-5 mb-3">
                                    <label class="form-label fw-semibold">Currency</label>
                                    <select name="currency" id="currency" class="form-select">
                                        <option value="GHS">GHS (₵)</option>
                                        <option value="AED">AED (د.إ)</option>
                                    </select>
                                </div>
                                <div class="col-7 mb-3">
                                    <label class="form-label fw-semibold">Price (Service Fee)</label>
                                    <input type="number" step="0.01" min="0" name="price" id="price" class="form-control" required>
                                    <small class="form-text text-muted">≈ <span id="converted-price"></span></small>
                                </div>
                            </div>
                            <p class="rate-note mb-3"><i class="fas fa-circle-info me-1"></i>Conversion uses a fixed rate (1 AED = <?php echo AED_TO_GHS_RATE; ?> GHS, updated <?php echo date('M j, Y', strtotime(RATE_UPDATED_ON)); ?>) -- for reference only, not a live rate.</p>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label fw-semibold">Visibility</label>
                                    <select name="status" id="statusSelect" class="form-select">
                                        <option value="published">Published (visible to public)</option>
                                        <option value="hidden">Hidden (draft, admin only)</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-3 d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="featured" class="form-check-input" id="featuredCheck" role="switch">
                                        <label class="form-check-label" for="featuredCheck">Featured (Urgent Hiring)</label>
                                    </div>
                                </div>
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
                            <img id="imagePreview" src="" alt="Image Preview" class="img-fluid rounded shadow-sm mb-3" style="display:none; max-height:280px;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <span class="text-muted fw-normal">(optional, rich editor, HTML allowed)</span></label>
                        <textarea id="description" name="description" class="form-control" rows="10"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Save Offer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Preview Modal (read-only quick look) -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="previewModalLabel">Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <img id="previewImage" src="" alt="" class="img-fluid rounded shadow-sm mb-3" style="display:none;">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <span class="badge rounded-pill" style="background:rgba(16,182,215,0.12); color:var(--primary-light);" id="previewLocation"></span>
                    <span class="badge rounded-pill" style="background:rgba(212,175,55,0.18); color:#a8842a;" id="previewCountryBadge"></span>
                    <span id="previewFeaturedBadge" class="badge bg-warning text-dark" style="display:none;"><i class="fas fa-star me-1"></i>Featured</span>
                    <span id="previewStatusBadge" class="badge" style="display:none;"></span>
                    <small class="text-muted" id="previewMeta"></small>
                </div>
                <h3 class="fw-bold" id="previewTitle"></h3>
                <hr>
                <div id="previewBody"></div>
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
(function () {
    'use strict';

    /* =====================================================================
       ROBUSTNESS LAYER
       - Every dependency (DataTables, TinyMCE, Bootstrap) is checked before
         use. If a CDN is blocked or slow, you get a clear console error
         instead of a page full of dead buttons.
       - ALL button behaviour (edit/preview/duplicate/delete/toggle) is bound
         with event delegation on document, not on individual elements. This
         means it keeps working even if rows are re-ordered, re-paged, or
         re-rendered later -- there is nothing to "re-bind" after any redraw.
       ===================================================================== */

    function fail(msg, err) {
        console.error('[offers admin]', msg, err || '');
    }

    if (typeof DataTable === 'undefined') {
        fail('DataTables failed to load (check the CDN links / your network / an ad-blocker).');
    }
    if (typeof tinymce === 'undefined') {
        fail('TinyMCE failed to load (check the CDN links / your network / an ad-blocker).');
    }
    if (typeof bootstrap === 'undefined') {
        fail('Bootstrap JS is not loaded -- modals will not open. Make sure bootstrap.bundle.min.js is included in admin_header.php or admin_footer.php BEFORE this script.');
    }

    const offers = OFFERS_JSON_PLACEHOLDER;
    const AED_TO_GHS = RATE_PLACEHOLDER;
    const currencySymbols = { GHS: '₵', AED: 'د.إ' };
    const COUNTRIES = COUNTRIES_JSON_PLACEHOLDER;
    const autoEditId = AUTOEDIT_PLACEHOLDER;

    const offersTableEl = document.getElementById('offersTable');
    const offerModalEl = document.getElementById('offerModal');
    const previewModalEl = document.getElementById('previewModal');
    const offerForm = document.getElementById('offerForm');
    const titleInput = document.getElementById('titleInput');
    const currencySelect = document.getElementById('currency');
    const countrySelect = document.getElementById('countrySelect');
    const priceInput = document.getElementById('price');
    const statusSelect = document.getElementById('statusSelect');
    const dropZone = document.getElementById('imageDropZone');
    const imageInput = document.getElementById('imageInput');
    const imagePreview = document.getElementById('imagePreview');

    // ---- DataTable (guarded) ----
    let table = null;
    if (typeof DataTable !== 'undefined' && offersTableEl) {
        try {
            table = new DataTable('#offersTable', {
                responsive: true,
                paging: true,
                searching: true,
                ordering: true,
                order: [[0, 'desc']],
                columnDefs: [{ orderable: false, searchable: false, targets: [1, 6, 7, 8] }],
                language: { search: 'Search offers:' }
            });
        } catch (err) {
            fail('DataTable() init threw an error -- falling back to a plain HTML table.', err);
        }
    }

    // ---- Currency + destination + "featured only" + "visible only" filters ----
    let activeCurrency = '';
    let activeCountry = '';
    let featuredOnly = false;
    let visibleOnly = false;

    function rowMatchesFilters(row) {
        if (!row) return true;
        if (activeCurrency && row.dataset.currency !== activeCurrency) return false;
        if (activeCountry && row.dataset.country !== activeCountry) return false;
        if (featuredOnly && row.dataset.featured !== '1') return false;
        if (visibleOnly && row.dataset.status !== 'published') return false;
        return true;
    }

    function applyFilters() {
        if (table) {
            table.draw();
            return;
        }
        // Fallback if DataTables never initialised: filter rows manually.
        document.querySelectorAll('#offersTable tbody tr[data-currency]').forEach(row => {
            row.style.display = rowMatchesFilters(row) ? '' : 'none';
        });
    }

    if (table) {
        DataTable.ext.search.push(function (settings, searchData, index, rowData, counter) {
            const row = table.row(index).node();
            return rowMatchesFilters(row);
        });
    }

    document.querySelectorAll('.currency-pill').forEach(pill => {
        pill.addEventListener('click', () => {
            document.querySelectorAll('.currency-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            activeCurrency = pill.dataset.currency;
            applyFilters();
        });
    });
    const countryFilterSelect = document.getElementById('countryFilterSelect');
    if (countryFilterSelect) {
        countryFilterSelect.addEventListener('change', (e) => {
            activeCountry = e.target.value;
            applyFilters();
        });
    }
    const featuredOnlySwitch = document.getElementById('featuredOnlySwitch');
    if (featuredOnlySwitch) {
        featuredOnlySwitch.addEventListener('change', (e) => {
            featuredOnly = e.target.checked;
            applyFilters();
        });
    }
    const visibleOnlySwitch = document.getElementById('visibleOnlySwitch');
    if (visibleOnlySwitch) {
        visibleOnlySwitch.addEventListener('change', (e) => {
            visibleOnly = e.target.checked;
            applyFilters();
        });
    }

    // ---- TinyMCE lifecycle tied to the Add/Edit modal ----
    if (offerModalEl && typeof tinymce !== 'undefined') {
        offerModalEl.addEventListener('shown.bs.modal', () => {
            if (!tinymce.get('description')) {
                tinymce.init({
                    selector: '#description',
                    height: 400,
                    menubar: 'file edit view insert format tools table help',
                    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount help',
                    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                    content_style: 'body { font-family: Poppins, sans-serif; font-size: 16px }'
                }).catch(err => fail('TinyMCE init failed.', err));
            }
        });
        offerModalEl.addEventListener('hidden.bs.modal', () => {
            const editor = tinymce.get('description');
            if (editor) tinymce.remove('#description');
        });
    }

    // ---- Title char counter ----
    if (titleInput) {
        titleInput.addEventListener('input', () => {
            const counter = document.getElementById('titleCount');
            if (counter) counter.textContent = titleInput.value.length;
        });
    }

    // ---- Live currency conversion preview ----
    function updateConvertedPrice() {
        if (!currencySelect || !priceInput) return;
        const currency = currencySelect.value;
        const price = parseFloat(priceInput.value) || 0;
        let converted, convertedCurrency;
        if (currency === 'GHS') {
            converted = price / AED_TO_GHS;
            convertedCurrency = 'AED';
        } else {
            converted = price * AED_TO_GHS;
            convertedCurrency = 'GHS';
        }
        const out = document.getElementById('converted-price');
        if (out) out.textContent = converted.toFixed(2) + ' ' + convertedCurrency;
    }
    if (currencySelect) {
        currencySelect.dataset.prev = currencySelect.value;
        currencySelect.addEventListener('change', () => {
            const prev = currencySelect.dataset.prev;
            const current = currencySelect.value;
            let price = parseFloat(priceInput.value) || 0;
            if (prev !== current && price > 0) {
                price = (prev === 'GHS' && current === 'AED') ? price / AED_TO_GHS : price * AED_TO_GHS;
                priceInput.value = price.toFixed(2);
            }
            currencySelect.dataset.prev = current;
            updateConvertedPrice();
        });
    }
    if (priceInput) priceInput.addEventListener('input', updateConvertedPrice);

    // ---- Drag-and-drop image zone ----
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
                reader.onerror = () => fail('Could not read the selected image file.');
                reader.readAsDataURL(file);
            }
        });
    }

    // ---- Open the Add/Edit modal pre-filled for a given offer id ----
    function openEditModal(id) {
        const offer = offers.find(o => String(o.id) === String(id));
        if (!offer) {
            fail('openEditModal: no offer found with id ' + id);
            return;
        }
        if (typeof bootstrap === 'undefined') {
            fail('Cannot open the edit modal -- Bootstrap JS is missing.');
            return;
        }

        const label = document.getElementById('offerModalLabel');
        if (label) label.textContent = 'Edit Offer';

        offerForm.querySelector('[name="action"]').value = 'edit';
        offerForm.querySelector('[name="id"]').value = offer.id;
        titleInput.value = offer.title;
        const titleCount = document.getElementById('titleCount');
        if (titleCount) titleCount.textContent = offer.title.length;
        offerForm.querySelector('[name="location"]').value = offer.location;
        if (countrySelect) countrySelect.value = offer.country || 'AE';
        offerForm.querySelector('[name="duration"]').value = offer.duration;
        currencySelect.value = offer.currency || 'GHS';
        currencySelect.dataset.prev = currencySelect.value;
        priceInput.value = offer.price;
        if (statusSelect) statusSelect.value = offer.status || 'published';
        offerForm.querySelector('[name="featured"]').checked = !!offer.featured;
        offerForm.querySelector('[name="existing_image"]').value = offer.image || '';
        imageInput.required = false;
        updateConvertedPrice();

        if (offer.image) {
            imagePreview.src = '../assets/images/' + offer.image;
            imagePreview.style.display = 'block';
        } else {
            imagePreview.src = '';
            imagePreview.style.display = 'none';
        }

        offerModalEl.addEventListener('shown.bs.modal', () => {
            const editor = tinymce.get('description');
            if (editor) editor.setContent(offer.description || '');
        }, { once: true });

        bootstrap.Modal.getOrCreateInstance(offerModalEl).show();
    }

    // ---- Add button handler ----
    const addBtn = document.getElementById('addBtn');
    if (addBtn) {
        addBtn.addEventListener('click', () => {
            const label = document.getElementById('offerModalLabel');
            if (label) label.textContent = 'Add New Offer';
            offerForm.reset();
            offerForm.querySelector('[name="action"]').value = 'add';
            offerForm.querySelector('[name="id"]').value = '';
            offerForm.querySelector('[name="existing_image"]').value = '';
            imageInput.required = false;
            const titleCount = document.getElementById('titleCount');
            if (titleCount) titleCount.textContent = '0';
            currencySelect.dataset.prev = currencySelect.value;
            if (statusSelect) statusSelect.value = 'published';
            if (countrySelect) countrySelect.value = 'AE';
            imagePreview.src = '';
            imagePreview.style.display = 'none';
            updateConvertedPrice();

            offerModalEl.addEventListener('shown.bs.modal', () => {
                const editor = tinymce.get('description');
                if (editor) editor.setContent('');
            }, { once: true });
        });
    }

    // ---- Preview rendering ----
    function openPreview(id) {
        const offer = offers.find(o => String(o.id) === String(id));
        if (!offer) {
            fail('openPreview: no offer found with id ' + id);
            return;
        }
        if (typeof bootstrap === 'undefined') {
            fail('Cannot open the preview modal -- Bootstrap JS is missing.');
            return;
        }

        document.getElementById('previewTitle').textContent = offer.title;
        document.getElementById('previewLocation').textContent = offer.location;
        const countryInfo = COUNTRIES[offer.country] || COUNTRIES['OT'];
        const countryBadge = document.getElementById('previewCountryBadge');
        if (countryBadge) countryBadge.textContent = countryInfo.flag + ' ' + countryInfo.name;
        const img = document.getElementById('previewImage');
        if (offer.image) {
            img.src = '../assets/images/' + offer.image;
            img.style.display = 'block';
        } else {
            img.style.display = 'none';
        }
        const symbol = currencySymbols[offer.currency] || '';
        document.getElementById('previewMeta').textContent = offer.duration + ' · ' + symbol + ' ' + parseFloat(offer.price).toFixed(2) + ' ' + offer.currency;
        document.getElementById('previewFeaturedBadge').style.display = offer.featured ? 'inline-block' : 'none';

        const statusBadge = document.getElementById('previewStatusBadge');
        const isPublished = (offer.status || 'published') === 'published';
        statusBadge.textContent = isPublished ? 'Published' : 'Hidden';
        statusBadge.className = 'badge ' + (isPublished ? 'bg-success' : 'bg-secondary');
        statusBadge.style.display = 'inline-block';

        document.getElementById('previewBody').innerHTML = offer.description || '';

        bootstrap.Modal.getOrCreateInstance(previewModalEl).show();
    }

    // ---- Shared AJAX helper for the two toggle buttons ----
    function postToggle(action, id) {
        return fetch(window.location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: 'action=' + encodeURIComponent(action) + '&id=' + encodeURIComponent(id)
        }).then(async res => {
            const raw = await res.text();
            let data;
            try {
                data = JSON.parse(raw);
            } catch (err) {
                // The server returned HTML (a PHP error page, a login redirect, etc.)
                // instead of JSON -- surface that clearly rather than failing silently.
                fail('Expected JSON but got something else back from the server. First 200 chars: ' + raw.slice(0, 200));
                throw new Error('Non-JSON response');
            }
            if (!res.ok) {
                fail('Server responded with HTTP ' + res.status + ' for action=' + action);
            }
            return data;
        });
    }

    // =========================================================================
    // EVENT DELEGATION: all row-level buttons are handled through ONE listener
    // on the table's container. This makes them immune to DataTables redraws,
    // pagination, or any future re-rendering of the tbody -- there is no set of
    // "live" elements to lose track of.
    // =========================================================================
    const tableWrapper = offersTableEl ? offersTableEl.closest('.table-responsive') || offersTableEl.parentElement : document;

    if (tableWrapper) {
        tableWrapper.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit-btn');
            if (editBtn) {
                openEditModal(editBtn.dataset.id);
                return;
            }

            const previewBtn = e.target.closest('.preview-btn');
            if (previewBtn) {
                openPreview(previewBtn.dataset.id);
                return;
            }

            const featuredBtn = e.target.closest('.featured-toggle');
            if (featuredBtn) {
                if (featuredBtn.disabled) return;
                const id = featuredBtn.dataset.id;
                featuredBtn.disabled = true;
                postToggle('toggle_featured', id)
                    .then(data => {
                        if (data.success) {
                            featuredBtn.classList.toggle('is-featured', !!data.featured);
                            const row = featuredBtn.closest('tr');
                            if (row) row.dataset.featured = data.featured ? '1' : '0';
                            const offer = offers.find(o => String(o.id) === String(id));
                            if (offer) offer.featured = data.featured ? 1 : 0;
                        } else {
                            alert(data.error || 'Could not update this offer.');
                        }
                    })
                    .catch(err => {
                        fail('toggle_featured request failed.', err);
                        alert('Something went wrong updating this offer. Check the browser console for details.');
                    })
                    .finally(() => { featuredBtn.disabled = false; });
                return;
            }

            const statusBtn = e.target.closest('.status-toggle');
            if (statusBtn) {
                if (statusBtn.disabled) return;
                const id = statusBtn.dataset.id;
                statusBtn.disabled = true;
                postToggle('toggle_status', id)
                    .then(data => {
                        if (data.success) {
                            const isPublished = data.status === 'published';
                            statusBtn.classList.toggle('is-published', isPublished);
                            statusBtn.classList.toggle('is-hidden', !isPublished);
                            statusBtn.innerHTML = '<i class="fas ' + (isPublished ? 'fa-eye' : 'fa-eye-slash') + ' me-1"></i>' + (isPublished ? 'Published' : 'Hidden');
                            const row = statusBtn.closest('tr');
                            if (row) row.dataset.status = data.status;
                            const offer = offers.find(o => String(o.id) === String(id));
                            if (offer) offer.status = data.status;
                        } else {
                            alert(data.error || 'Could not update visibility for this offer.');
                        }
                    })
                    .catch(err => {
                        fail('toggle_status request failed.', err);
                        alert('Something went wrong updating this offer. Check the browser console for details. (Did you run the ALTER TABLE for the `status` column?)');
                    })
                    .finally(() => { statusBtn.disabled = false; });
                return;
            }
        });

        // Delegated submit handler for the duplicate form.
        // (The delete form keeps its own inline onsubmit="confirm(...)" in the
        // HTML, so we don't duplicate that confirmation here.)
        tableWrapper.addEventListener('submit', function (e) {
            const form = e.target;
            if (form.classList.contains('duplicate-form')) {
                if (!confirm('Duplicate this offer? A copy will be created as hidden and unfeatured.')) {
                    e.preventDefault();
                }
            }
        });
    } else {
        fail('Could not find the offers table wrapper -- row buttons will not work. Check that #offersTable exists.');
    }

    // ---- Deep link support: ?edit=ID auto-opens the edit modal ----
    if (autoEditId) {
        openEditModal(autoEditId);
    }
})();
</script>
HTML;

$extraScripts = str_replace('OFFERS_JSON_PLACEHOLDER', json_encode($offers), $extraScripts);
$extraScripts = str_replace('RATE_PLACEHOLDER', json_encode(AED_TO_GHS_RATE), $extraScripts);
$extraScripts = str_replace('COUNTRIES_JSON_PLACEHOLDER', json_encode($countriesForJs), $extraScripts);
$extraScripts = str_replace('AUTOEDIT_PLACEHOLDER', json_encode($autoEditId), $extraScripts);

include 'admin_footer.php';