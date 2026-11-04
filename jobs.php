<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
include 'includes/header.php';

/* =========================================================================
   RECRUITMENT & JOB OPENINGS -- global destinations page
   Only jobs the admin has marked "published" ever reach this page -- this
   is the single point of sync between admin/offers.php and the public site.
   Offers are cross-linked by destination country AND by job category, so
   clicking a country or a category regroups the live openings that match.
   ========================================================================= */

/* =========================================================================
   Global destinations -- keep this list in sync with admin/offers.php.
   'OT' is a deliberate catch-all for brand-new destinations posted before
   this list is formally updated.
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
$currencySymbols = ['GHS' => '₵', 'AED' => 'د.إ'];

/* =========================================================================
   Job categories -- keyword-driven so every offer is auto-connected to a
   category even if the admin never picks one manually. If the `offers`
   table has an explicit `category` column populated with one of these
   slugs, that explicit value always wins over the keyword guess.
   ========================================================================= */
$categories = [
    'construction'  => ['label' => 'Construction Workers',      'icon' => 'fa-hard-hat',      'sub' => 'Mason, Steel Fixer, Carpenter, Painter', 'keywords' => ['mason','steel fixer','carpenter','painter','construction','builder','scaffold','tiler']],
    'warehouse'     => ['label' => 'Warehouse & Factory',       'icon' => 'fa-warehouse',      'sub' => 'Helpers, Packers, Loaders',              'keywords' => ['warehouse','packer','loader','factory','general helper','helpers needed']],
    'trades'        => ['label' => 'Skilled Trades',            'icon' => 'fa-tools',          'sub' => 'Electrician, Plumber, Welder',           'keywords' => ['electrician','plumber','welder','technician','fitter','mechanic']],
    'drivers'       => ['label' => 'Drivers & Logistics',       'icon' => 'fa-truck',          'sub' => 'Light/Heavy Vehicle, Delivery',          'keywords' => ['driver','rider','moto','delivery','logistics']],
    'hospitality'   => ['label' => 'Hospitality',               'icon' => 'fa-utensils',       'sub' => 'Waiters, Cleaners, Kitchen Staff',       'keywords' => ['waiter','waitress','cleaner','kitchen','chef','cook','hospitality']],
    'security'      => ['label' => 'Security Guards',           'icon' => 'fa-shield-alt',     'sub' => 'SIRA Certified, Hotel Security',         'keywords' => ['security','guard','sira']],
    'healthcare'    => ['label' => 'Healthcare',                'icon' => 'fa-notes-medical',  'sub' => 'Caregivers, Nurses, Support Staff',      'keywords' => ['nurse','caregiver','healthcare','medical','care assistant']],
    'professional'  => ['label' => 'Professional & Executive',  'icon' => 'fa-user-tie',       'sub' => 'Office, Admin, Management Roles',        'keywords' => ['admin','office','manager','executive','supervisor','professional course']],
    'domestic'      => ['label' => 'Domestic Work',             'icon' => 'fa-house-user',     'sub' => 'Housekeeping, Nannies, Cooks',           'keywords' => ['nanny','housemaid','domestic','housekeeping']],
    'other'         => ['label' => 'Other Openings',            'icon' => 'fa-briefcase',      'sub' => 'More roles across every sector',         'keywords' => []],
];

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

if (!function_exists('cleanText')) {
    /**
     * Normalizes any text pulled straight from the DB before it's ever
     * echoed to the page. Offer titles/locations/durations/descriptions
     * are entered through a rich-text admin field, so they routinely carry:
     *   - HTML entities like &nbsp; (and occasionally &amp;, &rsquo;, etc.)
     *   - Real non-breaking-space bytes (U+00A0 / \xC2\xA0 in UTF-8)
     *   - Leftover <p>, <br> and other tags from the WYSIWYG editor
     *   - Doubled-up whitespace / \r\n line endings
     * Left alone, &nbsp; either prints literally (if the value gets
     * escaped twice) or creates a long unbroken run of text that can't
     * wrap -- which is what pushes some cards' content past their
     * rounded border on certain rows. This runs once, right after the
     * DB fetch, so every place that prints offer text downstream is
     * already clean.
     */
    function cleanText($text) {
        if ($text === null || $text === '') return '';
        // Turn <p>/<br> etc. into spaces first, then strip remaining tags,
        // so paragraph breaks in rich-text descriptions become a real
        // space instead of gluing two words together.
        $text = preg_replace('/<(p|br|div|li)\b[^>]*>/i', ' ', $text);
        $text = strip_tags($text);
        // Decode entities (&nbsp; &amp; &rsquo; ...) to real characters.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Replace literal non-breaking-space bytes with a normal, breakable space.
        $text = str_replace("\xc2\xa0", ' ', $text);
        // Collapse any run of whitespace (spaces, tabs, \r\n) into one space.
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
}

if (!function_exists('inferOfferCategory')) {
    /**
     * Works out which category an offer belongs to.
     * 1) If the offer has an explicit `category` column value that matches
     *    one of our known slugs, that always wins (lets the admin override).
     * 2) Otherwise we scan the title + description for keyword matches.
     * 3) Falls back to 'other' so every offer always lands in some bucket.
     */
    function inferOfferCategory($offer, $categories) {
        if (!empty($offer['category']) && array_key_exists($offer['category'], $categories)) {
            return $offer['category'];
        }
        $haystack = mb_strtolower(($offer['title'] ?? '') . ' ' . ($offer['description'] ?? ''));
        foreach ($categories as $slug => $cat) {
            foreach ($cat['keywords'] as $kw) {
                if ($kw !== '' && str_contains($haystack, $kw)) {
                    return $slug;
                }
            }
        }
        return 'other';
    }
}

if (!function_exists('jobsUrl')) {
    // Builds a jobs.php link that keeps the current filters, letting you
    // override or clear (pass null) any one of them.
    function jobsUrl($current, $overrides = []) {
        $params = array_filter(array_merge($current, $overrides), fn($v) => $v !== '' && $v !== null);
        return 'jobs.php' . (empty($params) ? '' : '?' . http_build_query($params));
    }
}

// Fetch offers with search, destination filter, category filter & pagination
$search   = trim($_GET['search'] ?? '');
$country  = strtoupper(trim($_GET['country'] ?? ''));
if (!array_key_exists($country, $countries)) $country = '';
$categorySlug = trim($_GET['category'] ?? '');
if (!array_key_exists($categorySlug, $categories)) $categorySlug = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 9;

$currentFilters = ['search' => $search, 'country' => $country, 'category' => $categorySlug];

$sql = "SELECT * FROM offers
        WHERE status = 'published'
          AND (title LIKE :keyword
           OR location LIKE :keyword
           OR duration LIKE :keyword)";
$params = ['keyword' => "%$search%"];
if ($country !== '') {
    $sql .= " AND country = :country";
    $params['country'] = $country;
}
$sql .= " ORDER BY featured DESC, created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allOffers = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($allOffers as &$o) {
    if (empty($o['country']) || !array_key_exists($o['country'], $countries)) $o['country'] = 'AE';
    if (empty($o['currency'])) $o['currency'] = 'GHS';
    // Scrub &nbsp; / stray tags / doubled whitespace out of every text
    // field right after the fetch, so category inference, search
    // matching and every downstream echo() all see clean text.
    $o['title']       = cleanText($o['title'] ?? '');
    $o['location']    = cleanText($o['location'] ?? '');
    $o['duration']    = cleanText($o['duration'] ?? '');
    $o['description'] = cleanText($o['description'] ?? '');
    $o['category_slug'] = inferOfferCategory($o, $categories);
}
unset($o);

// Apply the category filter (done in PHP so it works whether or not the
// offers table has a real `category` column yet).
if ($categorySlug !== '') {
    $allOffers = array_values(array_filter($allOffers, fn($o) => $o['category_slug'] === $categorySlug));
}

$total = count($allOffers);
$pages = max(1, ceil($total / $per_page));
$page = min($page, $pages);
$offset = ($page - 1) * $per_page;
$offers = array_slice($allOffers, $offset, $per_page);

// Destination counts across ALL published offers (ignoring the current
// search/filter) -- powers the "Destinations Served" hero stat.
$destCountStmt = $pdo->query("SELECT country, COUNT(*) AS c FROM offers WHERE status = 'published' GROUP BY country");
$destinationCounts = [];
foreach ($destCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $code = (!empty($row['country']) && array_key_exists($row['country'], $countries)) ? $row['country'] : 'AE';
    $destinationCounts[$code] = ($destinationCounts[$code] ?? 0) + (int) $row['c'];
}
$activeDestinationCount = count($destinationCounts);

// Category counts across ALL published offers (ignoring the current
// search/filter) -- powers the live "X roles" badge on each category tile.
// NOTE: `offers` has no `category` column yet, so we only select columns
// that actually exist and let inferOfferCategory() do the classifying.
$categoryCountRows = $pdo->query("SELECT title, description FROM offers WHERE status = 'published'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($categoryCountRows as &$row) {
    $row['title']       = cleanText($row['title'] ?? '');
    $row['description'] = cleanText($row['description'] ?? '');
}
unset($row);
$categoryCounts = array_fill_keys(array_keys($categories), 0);
foreach ($categoryCountRows as $row) {
    $slug = inferOfferCategory($row, $categories);
    $categoryCounts[$slug] = ($categoryCounts[$slug] ?? 0) + 1;
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
    /* ===== Believe Travel Consult — Global Recruitment & Job Openings ===== */
    :root{
        --btc-navy:#033754;
        --btc-ink:#071c27;
        --btc-cyan:#10b6d7;
        --btc-green:#89d900;
        --btc-gold:#d4af37;
        --font-display:'Space Grotesk', sans-serif;
        --font-body:'Inter', sans-serif;
        --font-mono:'IBM Plex Mono', monospace;
    }

    #jobs-page{font-family:var(--font-body);}
    #jobs-page h1, #jobs-page h2, #jobs-page h3, #jobs-page h4, #jobs-page h5, #jobs-page h6{font-family:var(--font-display);}

    @keyframes shimmer {0%{background-position:-200% 0;}100%{background-position:200% 0;}}
    @keyframes softPulse {0%,100%{opacity:1;}50%{opacity:.55;}}
    @media (prefers-reduced-motion: reduce){
        *{animation-duration:.001ms !important; animation-iteration-count:1 !important; transition-duration:.001ms !important;}
    }

    #jobs-page .text-lime{color:var(--btc-green) !important;}
    #jobs-page .text-cyan{color:var(--btc-cyan) !important;}
    #jobs-page .grad-text{background:linear-gradient(90deg,#10b6d7,#89d900,#10b6d7); background-size:200% auto; -webkit-background-clip:text; background-clip:text; color:transparent; animation:shimmer 7s linear infinite;}
    #jobs-page .eyebrow{display:inline-flex; align-items:center; gap:8px; font-family:var(--font-mono); font-size:.72rem; font-weight:600; letter-spacing:2.5px; text-transform:uppercase; color:var(--btc-cyan); margin-bottom:.75rem;}
    #jobs-page .eyebrow::before{content:''; width:7px; height:7px; border-radius:50%; background:var(--btc-green); display:inline-block;}

    /* ---------- Page banner (slim, no video) ---------- */
    #jobs-page .page-banner{background:linear-gradient(135deg, var(--btc-navy), #0a558c 120%); padding:64px 0 44px; position:relative; overflow:hidden;}
    #jobs-page .page-banner::after{content:''; position:absolute; inset:0; background:radial-gradient(circle at 85% 20%, rgba(16,182,215,.18), transparent 55%); pointer-events:none;}
    #jobs-page .banner-title{font-size:2.35rem; line-height:1.15;}
    #jobs-page .banner-sub{opacity:.85; font-size:1rem; max-width:520px;}
    #jobs-page .banner-stats{display:flex; flex-wrap:wrap; gap:.85rem;}
    #jobs-page .stat-chip{background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.14); border-radius:14px; padding:.65rem 1rem; min-width:112px; text-align:center; transition:transform .2s ease, background .2s ease;}
    #jobs-page .stat-chip:hover{transform:translateY(-3px); background:rgba(255,255,255,.1);}
    #jobs-page .stat-chip strong{display:block; font-family:var(--font-display); font-size:1.35rem; font-variant-numeric:tabular-nums; color:var(--btc-green);}
    #jobs-page .stat-chip:nth-child(2) strong{color:var(--btc-cyan);}
    #jobs-page .stat-chip:nth-child(3) strong{color:#fff;}
    #jobs-page .stat-chip span{font-size:.66rem; text-transform:uppercase; letter-spacing:1px; opacity:.75;}

    #jobs-page .search-hero-wrap{max-width:820px;}
    #jobs-page .search-hero-wrap .form-control, #jobs-page .search-hero-wrap .form-select{color:#fff;}
    #jobs-page .search-hero-wrap .form-control::placeholder{color:rgba(255,255,255,.65);}
    #jobs-page .search-hero-wrap .form-select option{color:#033754;}
    #jobs-page .search-hero-wrap .input-group{border:1px solid rgba(255,255,255,.25); backdrop-filter:blur(8px); background:rgba(255,255,255,.06);}
    #jobs-page .search-hero-wrap .form-select{border:none; background:transparent; max-width:210px;}
    #jobs-page .search-hero-wrap .form-select:focus{box-shadow:none;}
    #jobs-page .btn-find-job{transition:transform .2s ease, box-shadow .2s ease;}
    #jobs-page .btn-find-job:hover{transform:translateY(-2px); box-shadow:0 10px 22px rgba(137,217,0,.35);}
    @media (max-width:991px){#jobs-page .banner-stats{justify-content:flex-start;}}

    /* ---------- Category roster ---------- */
    #jobs-page .roster{display:grid; grid-template-columns:1fr 1fr; gap:0 2.5rem; border-top:1px solid rgba(3,55,84,.12);}
    #jobs-page .roster-row{
        display:flex; align-items:center; gap:1rem; padding:1.1rem .15rem;
        text-decoration:none; border-bottom:1px solid rgba(3,55,84,.12);
        transition:padding-left .2s ease, background .2s ease;
    }
    #jobs-page .roster-row:hover{padding-left:.5rem; background:rgba(137,217,0,.05);}
    #jobs-page .roster-row.is-active{background:rgba(137,217,0,.08);}
    #jobs-page .roster-icon{
        width:46px; height:46px; border-radius:12px; flex-shrink:0;
        background:rgba(137,217,0,.1); color:var(--btc-green);
        display:flex; align-items:center; justify-content:center; font-size:1.1rem;
    }
    #jobs-page .roster-text{display:flex; flex-direction:column; min-width:0; flex:1;}
    #jobs-page .roster-title-row{display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;}
    #jobs-page .roster-title{font-family:var(--font-display); font-weight:600; color:var(--btc-navy); font-size:1.02rem;}
    #jobs-page .roster-count{font-family:var(--font-mono); font-size:.66rem; font-weight:600; color:var(--btc-green); background:rgba(137,217,0,.12); padding:.15rem .5rem; border-radius:50px; white-space:nowrap;}
    #jobs-page .roster-sub{font-size:.8rem; color:#7c8a92;}
    #jobs-page .roster-arrow{color:rgba(3,55,84,.25); transition:transform .2s ease, color .2s ease;}
    #jobs-page .roster-row:hover .roster-arrow{transform:translateX(4px); color:var(--btc-green);}
    @media (max-width:768px){#jobs-page .roster{grid-template-columns:1fr;}}

    /* ---------- Journey strip ---------- */
    #jobs-page .journey-strip{display:flex; flex-wrap:wrap; justify-content:center; gap:0;}
    #jobs-page .journey-step{position:relative; flex:1 1 150px; max-width:200px; text-align:center; padding:0 .5rem;}
    #jobs-page .journey-connector{position:absolute; top:28px; left:50%; width:100%; height:1px; background:rgba(255,255,255,.18); z-index:0;}
    #jobs-page .journey-icon{
        width:56px; height:56px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;
        position:relative; z-index:1; transition:transform .25s cubic-bezier(.34,1.56,.64,1);
    }
    #jobs-page .journey-step:hover .journey-icon{transform:scale(1.08);}
    #jobs-page .journey-label{font-family:var(--font-mono); font-size:.7rem; letter-spacing:1px; color:var(--btc-green);}

    /* ---------- Active filter chip ---------- */
    #jobs-page .active-filter-chip{background:rgba(16,182,215,.1); color:var(--btc-navy); border:1px solid rgba(16,182,215,.3); border-radius:50px; padding:.5rem 1.1rem; display:inline-flex; align-items:center; gap:.5rem; font-weight:600; font-size:.9rem;}
    #jobs-page .active-filter-chip a{color:#b02a37; text-decoration:none;}

    /* ---------- Job offer cards (CSS grid, self-contained sizing) ---------- */
    /* Real CSS grid instead of a Bootstrap row: grid columns stretch every
       card to match its row automatically, and nothing is pinned to a
       hard pixel height, so there is nothing for content to "overflow". */
    #jobs-page .offers-grid{
        display:grid;
        grid-template-columns:repeat(auto-fill, minmax(290px, 1fr));
        gap:1.75rem;
        align-items:stretch;
    }
    /* The AOS wrapper div is the actual grid item. It needs min-width:0 so
       it's allowed to shrink to the track width instead of growing to fit
       an unbroken run of text -- that was what pushed card content past
       its rounded border on some rows before the &nbsp; cleanup. */
    #jobs-page .offers-grid > div{min-width:0; display:flex;}
    #jobs-page .job-card{
        background:#fff;
        border:1px solid rgba(3,55,84,.08);
        border-radius:18px;
        box-shadow:0 2px 10px rgba(3,55,84,.05);
        overflow:hidden;
        display:flex; flex-direction:column;
        width:100%;
        min-width:0;
        transition:transform .25s ease, box-shadow .25s ease;
    }
    #jobs-page .job-card:hover{transform:translateY(-5px); box-shadow:0 18px 34px rgba(3,55,84,.14);}
    #jobs-page .job-card.is-featured{border-color:rgba(137,217,0,.55);}

    /* Picture is capped to a fixed aspect ratio, not the original image's
       native size, so every thumbnail is automatically the same compact
       footprint no matter how large the uploaded photo is. */
    #jobs-page .img-wrap{position:relative; aspect-ratio:16/10; overflow:hidden; background:#eef3f5; flex-shrink:0;}
    #jobs-page .img-wrap img{width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s ease;}
    #jobs-page .job-card:hover .img-wrap img{transform:scale(1.05);}
    #jobs-page .dest-flag-badge{position:absolute; bottom:10px; left:10px; background:rgba(3,55,84,.85); color:#fff; font-weight:600; font-family:var(--font-mono); font-size:.72rem; padding:.32rem .7rem; border-radius:50px; backdrop-filter:blur(4px);}
    #jobs-page .urgent-badge{animation:softPulse 2.4s ease-in-out infinite; font-family:var(--font-mono); letter-spacing:.5px; font-size:.68rem;}
    #jobs-page .btn-wa-job{transition:transform .2s ease, box-shadow .2s ease;}
    #jobs-page .btn-wa-job:hover{transform:translateY(-2px); box-shadow:0 8px 18px rgba(137,217,0,.35);}

    #jobs-page .card-body{flex:1; display:flex; flex-direction:column; padding:1.1rem 1.3rem 1.25rem; min-width:0;}
    #jobs-page .job-category-pill{
        display:inline-flex; align-items:center; gap:.35rem; align-self:flex-start;
        background:rgba(3,55,84,.06); color:var(--btc-navy); font-family:var(--font-mono);
        font-size:.63rem; font-weight:600; letter-spacing:.5px; text-transform:uppercase;
        padding:.28rem .6rem; border-radius:50px; margin-bottom:.55rem;
    }
    #jobs-page .job-title{
        font-size:1.03rem; font-weight:700; color:var(--btc-navy); line-height:1.3; margin-bottom:.4rem;
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        overflow-wrap:break-word; word-break:break-word;
    }
    #jobs-page .job-location{
        font-size:.83rem; color:#7c8a92; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-bottom:.7rem;
    }
    #jobs-page .job-price-row{display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap; margin-bottom:.6rem;}
    #jobs-page .job-price{font-family:var(--font-display); font-weight:700; font-size:1.15rem; color:var(--btc-cyan); line-height:1.1; overflow-wrap:break-word; word-break:break-word;}
    #jobs-page .job-price small{display:block; font-family:var(--font-body); font-weight:400; font-size:.66rem; color:#7c8a92; text-transform:none; letter-spacing:0;}
    #jobs-page .job-duration-pill{
        font-family:var(--font-mono); font-size:.68rem; font-weight:600; color:var(--btc-navy);
        background:rgba(137,217,0,.15); padding:.3rem .6rem; border-radius:50px; white-space:nowrap;
        max-width:150px; overflow:hidden; text-overflow:ellipsis;
    }
    #jobs-page .job-excerpt{
        font-size:.8rem; color:#8a97a0; line-height:1.45; margin-bottom:1rem;
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        overflow-wrap:break-word; word-break:break-word;
    }
    #jobs-page .job-cta-row{margin-top:auto; display:flex; gap:.6rem;}
    #jobs-page .job-cta-row .btn{flex:1; font-size:.82rem; padding:.5rem .5rem; white-space:nowrap; font-weight:600; min-width:0; overflow:hidden; text-overflow:ellipsis;}

    /* ---------- Pagination ---------- */
    /* Pagination lives in normal document flow inside the same bg-light
       section as the grid (no absolute/fixed positioning), so it can
       never visually detach into the section below. */
    #jobs-page .btc-page-link{transition:transform .2s ease;}
    #jobs-page .btc-page-link:hover{transform:translateY(-2px);}

    /* ---------- CTA ---------- */
    #jobs-page .btn-cta{transition:transform .2s ease, box-shadow .2s ease;}
    #jobs-page .btn-cta:hover{transform:translateY(-2px); box-shadow:0 12px 26px rgba(137,217,0,.4);}

    /* ---------- Floating WhatsApp ---------- */
    #jobs-page .floating-wa{position:fixed; bottom:24px; right:24px; z-index:1050; width:56px;height:56px;border-radius:50%; background:var(--btc-green); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 24px rgba(3,55,84,.35); transition:transform .2s ease;}
    #jobs-page .floating-wa:hover{transform:scale(1.08);}
    #jobs-page .floating-wa i{color:var(--btc-navy); font-size:1.5rem;}
    @media (max-width:768px){#jobs-page .floating-wa{width:48px;height:48px; bottom:16px; right:16px;} #jobs-page .search-hero-wrap .form-select{max-width:140px;}}
</style>

<div id="jobs-page">

<!-- PAGE BANNER (slim, rebranded — no video) -->
<section class="page-banner text-white">
    <div class="container position-relative" style="z-index:2;">
        <div class="row align-items-center gy-4">
            <div class="col-lg-7" data-aos="fade-up">
                <span class="eyebrow">International Recruitment &amp; Workforce Solutions</span>
                <h1 class="banner-title fw-bold mb-3">
                    Your <span class="grad-text">Dream Job Abroad</span> Starts Here
                </h1>
                <p class="banner-sub mb-0" style="font-family:var(--font-body); font-weight:400;">
                    Direct Employment &bull; Global Placements &bull; Visa &amp; Documentation Support &bull; 100% Legal
                </p>
            </div>
            <div class="col-lg-5" data-aos="fade-up" data-aos-delay="150">
                <div class="banner-stats justify-content-lg-end">
                    <div class="stat-chip">
                        <strong data-count="1500">0+</strong>
                        <span>Placements</span>
                    </div>
                    <div class="stat-chip">
                        <strong data-count="<?= max($activeDestinationCount, 1) ?>">0</strong>
                        <span>Destinations</span>
                    </div>
                    <div class="stat-chip">
                        <strong data-count="98">0%</strong>
                        <span>Success Rate</span>
                    </div>
                    <div class="stat-chip">
                        <strong data-count="7">0</strong>
                        <span>Days to Place</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search + Destination Bar -->
        <form action="jobs.php" method="get" class="mt-4" data-aos="fade-up" data-aos-delay="250">
            <div class="search-hero-wrap">
                <div class="input-group input-group-lg shadow-lg rounded-pill overflow-hidden">
                    <span class="input-group-text bg-transparent border-0 text-white"><i class="fas fa-search"></i></span>
                    <input type="text" name="search" class="form-control bg-transparent border-0 fs-5"
                           placeholder="Search: Mason, Driver, Nurse, Warehouse..." value="<?= htmlspecialchars($search) ?>">
                    <select name="country" class="form-select fs-6">
                        <option value="">All Destinations</option>
                        <?php foreach ($countries as $code => $name): if ($code === 'OT') continue; ?>
                            <option value="<?= $code ?>" <?= $country === $code ? 'selected' : '' ?>><?= countryFlagEmoji($code) . ' ' . htmlspecialchars($name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($categorySlug !== ''): ?>
                        <input type="hidden" name="category" value="<?= htmlspecialchars($categorySlug) ?>">
                    <?php endif; ?>
                    <button class="btn btn-find-job btn-lg px-5 rounded-pill" style="background:#89d900;color:#033754;font-weight:700;">
                        <i class="fas fa-briefcase me-2"></i> Find My Job
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- JOB CATEGORIES — ROSTER -->
<section class="py-7 bg-white">
    <div class="container">
        <div class="mb-4" data-aos="fade-up">
            <span class="eyebrow">Browse By Category</span>
            <h2 class="display-6 fw-bold" style="color:#033754;">Find Your Path to Success Abroad</h2>
            <p class="text-muted mb-0">Tap a category to see every open role that matches it, in any destination</p>
        </div>
        <div class="roster" data-aos="fade-up" data-aos-delay="100">
            <?php foreach ($categories as $slug => $cat):
                $count = $categoryCounts[$slug] ?? 0;
                if ($slug === 'other' && $count === 0) continue; // hide the catch-all when nothing needs it
            ?>
            <a href="<?= jobsUrl($currentFilters, ['category' => $slug, 'search' => null, 'country' => null, 'page' => null]) ?>"
               class="roster-row <?= $categorySlug === $slug ? 'is-active' : '' ?>">
                <span class="roster-icon"><i class="fas <?= $cat['icon'] ?>"></i></span>
                <span class="roster-text">
                    <span class="roster-title-row">
                        <span class="roster-title"><?= htmlspecialchars($cat['label']) ?></span>
                        <span class="roster-count"><?= $count ?> role<?= $count === 1 ? '' : 's' ?></span>
                    </span>
                    <span class="roster-sub"><?= htmlspecialchars($cat['sub']) ?></span>
                </span>
                <span class="roster-arrow"><i class="fas fa-arrow-right"></i></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- 5-STEP JOURNEY -->
<section class="py-6" style="background: linear-gradient(135deg, #033754, #0a558c 100%);">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="display-6 fw-bold text-white mb-2">Your Journey in 5 Simple Steps</h2>
            <p class="text-white opacity-75 mb-0">From Ghana to the World &ndash; Fast &amp; Stress-Free</p>
        </div>
        <div class="journey-strip">
            <?php
            $tinySteps = [
                ['icon' => 'fa-whatsapp', 'brand' => true, 'color' => '#25d366', 'title' => 'Chat With Us'],
                ['icon' => 'fa-passport', 'color' => '#10b6d7', 'title' => 'Documents Ready'],
                ['icon' => 'fa-briefcase', 'color' => '#89d900', 'title' => 'Job Match'],
                ['icon' => 'fa-plane', 'color' => '#ffffff', 'title' => 'Fly to Your Destination'],
                ['icon' => 'fa-building', 'color' => '#89d900', 'title' => 'Start Working!']
            ];
            foreach ($tinySteps as $i => $step): ?>
            <div class="journey-step" data-aos="zoom-in" data-aos-delay="<?= $i * 80 ?>">
                <?php if ($i < 4): ?><div class="d-none d-md-block journey-connector"></div><?php endif; ?>
                <div class="journey-icon shadow" style="background: <?= $step['color'] ?>; color: <?= $step['color'] === '#ffffff' ? '#033754' : '#ffffff' ?>;">
                    <i class="<?= !empty($step['brand']) ? 'fab' : 'fas' ?> <?= $step['icon'] ?> fa-lg"></i>
                </div>
                <div class="mt-2">
                    <span class="journey-label">STEP 0<?= $i + 1 ?></span>
                    <p class="text-white mb-0 fw-semibold small mt-1"><?= $step['title'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- OFFERS GRID (DYNAMIC FROM DB, PUBLISHED-ONLY) -->
<section class="py-7 bg-light">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="eyebrow">Live Openings</span>
            <h2 class="display-6 fw-bold" style="color:#033754;">Current Active Job Openings</h2>
            <p class="text-muted mb-0">Every package includes visa support, airport pickup, accommodation guidance &amp; direct employment</p>
        </div>

        <?php if ($country !== '' || $search !== '' || $categorySlug !== ''): ?>
        <div class="text-center mb-4" data-aos="fade-up">
            <?php if ($country !== ''): ?>
                <span class="active-filter-chip me-2 mb-2">
                    <?= countryFlagEmoji($country) ?> <?= htmlspecialchars($countries[$country]) ?>
                    <a href="<?= jobsUrl($currentFilters, ['country' => null]) ?>" title="Clear destination filter"><i class="fas fa-times-circle"></i></a>
                </span>
            <?php endif; ?>
            <?php if ($categorySlug !== ''): ?>
                <span class="active-filter-chip me-2 mb-2">
                    <i class="fas <?= $categories[$categorySlug]['icon'] ?>"></i> <?= htmlspecialchars($categories[$categorySlug]['label']) ?>
                    <a href="<?= jobsUrl($currentFilters, ['category' => null]) ?>" title="Clear category filter"><i class="fas fa-times-circle"></i></a>
                </span>
            <?php endif; ?>
            <?php if ($search !== ''): ?>
                <span class="active-filter-chip mb-2">
                    "<?= htmlspecialchars($search) ?>"
                    <a href="<?= jobsUrl($currentFilters, ['search' => null]) ?>" title="Clear search"><i class="fas fa-times-circle"></i></a>
                </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($offers)): ?>
            <div class="text-center py-7" data-aos="fade-up">
                <img src="assets/images/placeholder.svg" width="100" class="opacity-50 mb-4" alt="No results">
                <h4>No jobs match your search right now</h4>
                <p class="text-muted">We add new destinations and roles every week &mdash; try clearing your filters or ask us directly.</p>
                <a href="jobs.php" class="btn btn-primary rounded-pill mt-3 px-4 me-2">View All Openings</a>
                <a href="https://wa.me/233596204991?text=Hello!%20I%20am%20looking%20for%20a%20job%20opportunity%20abroad." class="btn btn-outline-success rounded-pill mt-3 px-4">
                    <i class="fab fa-whatsapp me-2"></i> Ask Us on WhatsApp
                </a>
            </div>
        <?php else: ?>
            <div class="offers-grid">
                <?php foreach ($offers as $i => $job): ?>
                <div data-aos="fade-up" data-aos-delay="<?= min($i, 8) * 60 ?>">
                    <div class="job-card <?= $job['featured'] ? 'is-featured' : '' ?>">
                        <?php
                        // Standardize image path: Assume $job['image'] is filename, stored in assets/images/
                        $imagePath = !empty($job['image'])
                            ? 'assets/images/' . $job['image']
                            : 'assets/images/placeholder.jpg';
                        $cat = $categories[$job['category_slug']] ?? $categories['other'];
                        ?>
                        <div class="img-wrap">
                            <img src="<?= $imagePath ?>"
                                 alt="<?= htmlspecialchars($job['title']) ?>"
                                 loading="lazy"
                                 onerror="this.src='assets/images/placeholder.jpg'">
                            <?php if ($job['featured']): ?>
                                <span class="urgent-badge badge bg-success position-absolute top-0 start-0 m-2 px-3 py-2 rounded-pill shadow-sm">
                                    <i class="fas fa-fire me-1"></i> URGENT
                                </span>
                            <?php endif; ?>
                            <span class="dest-flag-badge"><?= countryFlagEmoji($job['country']) ?> <?= htmlspecialchars($countries[$job['country']] ?? 'Other') ?></span>
                        </div>
                        <div class="card-body">
                            <span class="job-category-pill"><i class="fas <?= $cat['icon'] ?>"></i> <?= htmlspecialchars($cat['label']) ?></span>
                            <h4 class="job-title"><?= htmlspecialchars($job['title']) ?></h4>
                            <p class="job-location"><i class="fas fa-map-marker-alt text-info me-1"></i><?= htmlspecialchars($job['location']) ?></p>
                            <div class="job-price-row">
                                <span class="job-price"><?= htmlspecialchars($currencySymbols[$job['currency']] ?? '') ?> <?= number_format((float) $job['price']) ?>
                                    <small>Service Fee (<?= htmlspecialchars($job['currency']) ?>)</small>
                                </span>
                                <span class="job-duration-pill" title="<?= htmlspecialchars($job['duration']) ?>"><?= htmlspecialchars($job['duration']) ?></span>
                            </div>
                            <?php $excerpt = excerptText($job['description'] ?? '', 16); ?>
                            <?php if ($excerpt !== ''): ?>
                                <p class="job-excerpt"><?= htmlspecialchars($excerpt) ?></p>
                            <?php endif; ?>
                            <div class="job-cta-row">
                                <a href="job_details.php?id=<?= $job['id'] ?>" class="btn btn-outline-primary rounded-pill">Details</a>
                                <a href="https://wa.me/233596204991?text=Hello!%20I%20want%20the%20job%3A%20<?= urlencode($job['title']) ?>%20in%20<?= urlencode($countries[$job['country']] ?? '') ?>%20(<?= urlencode($job['duration']) ?>)%20-%20<?= urlencode($job['currency']) ?>%20<?= number_format((float) $job['price']) ?>"
                                   class="btn-wa-job btn rounded-pill shadow-sm"
                                   style="background:#89d900;color:#033754;">
                                    <i class="fab fa-whatsapp me-1"></i> WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Pagination -->
            <?php if ($pages > 1): ?>
            <div class="text-center" style="margin-top:3rem;" data-aos="fade-up">
                <div class="d-flex flex-wrap justify-content-center" style="gap:.5rem;">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <a href="<?= jobsUrl($currentFilters, ['page' => $i]) ?>"
                           class="btc-page-link btn <?= $i == $page ? 'btn-primary' : 'btn-outline-primary' ?> rounded-pill shadow-sm"
                           style="width:44px; height:44px; display:inline-flex; align-items:center; justify-content:center; padding:0;"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- VIDEO TESTIMONIAL SECTION -->
<section class="py-7 text-white" style="background: linear-gradient(135deg, #033754, #0a558c);">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-aos="fade-right">
                <span class="eyebrow" style="color:#89d900;">Proven Results</span>
                <h2 class="display-6 fw-bold mb-4">Real People. Real Results. Every Week.</h2>
                <p class="lead mb-4" style="font-family:var(--font-body); font-weight:400;">Over 1,500 Ghanaians are now living and working abroad because of Believe Travel Consult &mdash; across the Gulf, Europe, and beyond</p>
                <ul class="list-unstyled fs-5">
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i> Airport Meet &amp; Greet Guaranteed</li>
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i> Jobs in 7&ndash;21 Days</li>
                    <li class="mb-3"><i class="fas fa-check text-success me-3"></i> Full Documentation Support</li>
                </ul>
                <a href="https://wa.me/233596204991?text=Hello!%20I%20want%20to%20work%20abroad!"
                   class="btn-cta btn btn-lg rounded-pill px-5 py-3 shadow-lg"
                   style="background:#89d900;color:#033754;font-weight:700;">
                    <i class="fab fa-whatsapp fa-lg me-3"></i> Start Your Journey Today
                </a>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="ratio ratio-16x9 rounded-4 shadow-lg overflow-hidden">
                    <video controls
                           poster="assets/images/testimonial_poster.jpg"
                           class="w-100 h-100 rounded-4"
                           preload="metadata"
                           id="testimonial-video"
                           muted>
                        <source src="assets/videos/offer_video.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Persistent floating WhatsApp button -->
<a href="https://wa.me/233596204991?text=Hi!%20I%20have%20a%20question%20about%20a%20job" class="floating-wa" aria-label="Chat with an advisor on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

</div><!-- /#jobs-page -->

<!-- COUNTER SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('[data-count]').forEach(el => {
        let target = parseInt(el.getAttribute('data-count'));
        let suffix = el.textContent.includes('%') ? '%' : '+';
        let count = 0;
        let increment = target / 80;
        let timer = setInterval(() => {
            count += increment;
            if (count >= target) {
                el.textContent = target + suffix;
                clearInterval(timer);
            } else {
                el.textContent = Math.ceil(count) + suffix;
            }
        }, 30);
    });
});
</script>
<!-- Script for auto-playing video on scroll -->
<script>
document.addEventListener('DOMContentLoaded', () => {
  const video = document.getElementById('testimonial-video');
  if (video) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          video.play().catch(error => {
            console.log('Autoplay prevented:', error);
          });
        } else {
          video.pause();
        }
      });
    }, { threshold: 0.5 }); // Play when 50% visible
    observer.observe(video.parentElement); // Observe the container for better accuracy
  }
});
</script>
<?php include 'includes/footer.php'; ?>