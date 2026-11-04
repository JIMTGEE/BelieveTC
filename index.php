<?php
/* =========================================================================
   INDEX.PHP — Believe Travel Consult — Premium Global Homepage
   -------------------------------------------------------------------------
   This page positions Believe Travel Consult as a professional international
   travel, recruitment, visa, and relocation services company serving clients
   across multiple destinations worldwide.
   ========================================================================= */

include 'includes/header.php';

/* ---------- helper: find best-guess image path, else placeholder ---------- */
function resolve_image($filename, $subfolder) {
    if (empty($filename)) {
        return "https://placehold.co/600x400/0056B3/ffffff?text=Believe+Travel";
    }
    $candidates = [
        "assets/{$subfolder}/{$filename}",
        "assets/images/{$subfolder}/{$filename}",
        "uploads/{$subfolder}/{$filename}",
        "assets/uploads/{$subfolder}/{$filename}",
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) return $path;
    }
    return $filename;
}

/* ---------- DB QUERY 1: Featured job offers/packages ---------- */
$offers = [];
if (isset($conn) && $conn instanceof mysqli) {
    $res = $conn->query("SELECT * FROM offers WHERE status = 'published' ORDER BY featured DESC, created_at DESC LIMIT 6");
    if ($res) { while ($row = $res->fetch_assoc()) { $offers[] = $row; } }
}
if (empty($offers)) {
    // Static fallback with global destinations
    $offers = [
        ['title' => 'Construction Workers - UAE', 'location' => 'Dubai, UAE', 'price' => 18000, 'currency' => 'GHS', 'duration' => '2 Year Contract', 'image' => '', 'featured' => 1],
        ['title' => 'Healthcare Professionals - UK', 'location' => 'London, UK', 'price' => 25000, 'currency' => 'GHS', 'duration' => '3 Year Contract', 'image' => '', 'featured' => 1],
        ['title' => 'Skilled Workers - Canada', 'location' => 'Toronto, Canada', 'price' => 35000, 'currency' => 'GHS', 'duration' => 'PR Pathway', 'image' => '', 'featured' => 1],
    ];
}

/* ---------- DB QUERY 2: Latest blog / success stories ---------- */
$posts = [];
if (isset($conn) && $conn instanceof mysqli) {
    $res = $conn->query("SELECT * FROM blog_posts ORDER BY featured DESC, date_posted DESC LIMIT 3");
    if ($res) { while ($row = $res->fetch_assoc()) { $posts[] = $row; } }
}

/* ---------- DB QUERY 3: Gallery preview ---------- */
$gallery = [];
if (isset($conn) && $conn instanceof mysqli) {
    $res = $conn->query("SELECT * FROM gallery WHERE type = 'image' ORDER BY uploaded_at DESC LIMIT 8");
    if ($res) { while ($row = $res->fetch_assoc()) { $gallery[] = $row; } }
}

$whatsapp_base = "https://wa.me/233596204991?text=";

/* ---------- Country Flags Data ---------- */
$country_flags = [
    'UAE' => 'ae',
    'Saudi Arabia' => 'sa',
    'Qatar' => 'qa',
    'Kuwait' => 'kw',
    'Oman' => 'om',
    'Bahrain' => 'bh',
    'United Kingdom' => 'gb',
    'Germany' => 'de',
    'Canada' => 'ca',
    'Poland' => 'pl',
    'Romania' => 'ro',
    'Croatia' => 'hr',
    'Hungary' => 'hu',
    'Turkey' => 'tr',
    'Egypt' => 'eg',
    'Thailand' => 'th',
    'Malaysia' => 'my',
    'Singapore' => 'sg',
    'Indonesia' => 'id',
    'Maldives' => 'mv',
];
?>

<!-- =========================================================
     HERO SECTION — Original Dubai Focus Restored
========================================================= -->
<div class="position-relative overflow-hidden hero-wrapper">
    <div class="home parallax-window" data-parallax="scroll" data-image-src="assets/images/hero_dubai.jpg" data-speed="0.8" style="height:94vh; min-height:640px; background-position:center top;">
        <div class="home_overlay" style="background:linear-gradient(180deg, rgba(0,86,179,0.55) 0%, rgba(0,86,179,0.85) 100%); position:absolute; top:0; left:0; width:100%; height:100%;"></div>

        <div class="container h-100 position-relative">
            <div class="row h-100 align-items-center justify-content-center text-center">
                <div class="col-lg-10" data-aos="fade-up" data-aos-delay="200">

                    <!-- Trust ribbon -->
                    <div class="d-inline-flex flex-wrap justify-content-center gap-2 mb-4 animate__animated animate__fadeInDown animate__delay-1s">
                        <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.35); backdrop-filter:blur(4px);">
                            <i class="fas fa-shield-check me-1" style="color:#F4B400;"></i> Registered Travel &amp; Recruitment Agency
                        </span>
                        <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.35); backdrop-filter:blur(4px);">
                            <i class="fas fa-globe-africa me-1" style="color:#F4B400;"></i> Serving 20+ Countries
                        </span>
                        <span class="badge rounded-pill px-3 py-2" style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.35); backdrop-filter:blur(4px);">
                            <i class="fas fa-users me-1" style="color:#F4B400;"></i> 1,500+ Placed Globally
                        </span>
                    </div>

                    <h1 class="display-3 fw-bold text-white mb-4 animate__animated animate__fadeInUp animate__delay-2s" style="text-shadow: 0 4px 20px rgba(0,0,0,0.5); line-height:1.1;">
                        Travel Beyond Borders<br><span style="color:#F4B400;">Your Trusted Global Travel & Relocation Partner</span>
                    </h1>
                    <p class="lead text-white-50 mb-5 mx-auto animate__animated animate__fadeInUp animate__delay-3s" style="font-size:1.3rem; max-width:700px;">
                        Gulf Countries &bull; European Countries &bull; Canada &bull;USA
                    </p>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3 mb-5 animate__animated animate__fadeInUp animate__delay-4s">
                      <a href="<?= $whatsapp_base ?>Hi%2C%20I%E2%80%99m%20interested%20in%20your%20services."
   class="btn btn-lg rounded-pill px-5 py-3 shadow-lg pulse-btn"
   style="background:#F4B400; color:#0056B3; font-weight:700; font-size:1.1rem;">
   <i class="fab fa-whatsapp fa-lg me-2"></i> Chat With Us Now
</a>

                        <a href="jobs.php"
                           class="btn btn-lg btn-outline-light rounded-pill px-5 py-3 shadow-lg"
                           style="border:2px solid #fff; font-weight:700;">
                            View Job Packages <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>

                  <!-- Quick eligibility mini-form -->
<div class="mx-auto p-3 p-md-4 rounded-4 shadow-lg animate__animated animate__fadeInUp animate__delay-5s" 
     style="max-width:640px; background:rgba(255,255,255,0.96);">

  <form action="jobs.php" method="get" id="eligibilityForm" class="row g-2 align-items-center justify-content-center">

    <!-- Select field -->
    <div class="col-12 col-md-5 text-start">
      <label class="form-label small fw-semibold mb-1" style="color:#0056B3;">
        I'm interested in
      </label>
      <select name="category" id="categorySelect" class="form-select rounded-pill" required>
        <option value="">Choose a category</option>
        <optgroup label="Job Opportunities">
          <option value="driver">Driver / Moto Rider</option>
          <option value="hospitality">Hospitality / Hotel Staff</option>
          <option value="construction">Construction / Labor</option>
          <option value="warehouse">Warehouse Helper</option>
          <option value="waitress">Waiter / Waitress</option>
          <option value="freelance">Freelance Visa</option>
          <option value="security">Security Agent</option>
        </optgroup>
        <optgroup label="Documents & Services">
          <option value="police-report">Dubai Police Report</option>
          <option value="passport">Ghana Passport</option>
          <option value="license">Driving License</option>
          <option value="ghana-card">Ghana Card</option>
        </optgroup>
        <option value="other">Other</option>
      </select>
    </div>

    <!-- Submit button -->
    <div class="col-12 col-md-3">
      <button type="submit" class="btn w-100 rounded-pill fw-bold py-2" 
              style="background:#0056B3; color:#fff;">
        Check <i class="fas fa-search ms-1"></i>
      </button>
    </div>

  </form>
</div>

                </div>
            </div>
        </div>
    </div>
</div>


<!-- =========================================================
     TRUST / LEGITIMACY STRIP
========================================================= -->
<section class="py-4" style="background:#0056B3;">
    <div class="container">
        <div class="row g-4 text-center align-items-center">
            <div class="col-6 col-md-3" data-aos="flip-up" data-aos-delay="100">
                <i class="fas fa-file-contract fa-2x mb-2" style="color:#F4B400;"></i>
                <p class="text-white small fw-semibold mb-0">Registered Business<br><span class="text-white-50">License No. [ADD LICENSE]</span></p>
            </div>
            <div class="col-6 col-md-3" data-aos="flip-up" data-aos-delay="200">
                <i class="fas fa-globe fa-2x mb-2" style="color:#F4B400;"></i>
                <p class="text-white small fw-semibold mb-0">Global Network<br><span class="text-white-50">Partners in 20+ Countries</span></p>
            </div>
            <div class="col-6 col-md-3" data-aos="flip-up" data-aos-delay="300">
                <i class="fas fa-user-check fa-2x mb-2" style="color:#F4B400;"></i>
                <p class="text-white small fw-semibold mb-0">Verified Employers<br><span class="text-white-50">Contracts reviewed before signing</span></p>
            </div>
            <div class="col-6 col-md-3" data-aos="flip-up" data-aos-delay="400">
                <i class="fas fa-headset fa-2x mb-2" style="color:#F4B400;"></i>
                <p class="text-white small fw-semibold mb-0">24/7 Support<br><span class="text-white-50">Before, during &amp; after travel</span></p>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     ANIMATED STATS
========================================================= -->
<section class="py-6" style="background:#f8f9fa;">
    <div class="container">
        <div class="row text-center g-5">
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="100">
                <div class="display-4 fw-bold counter" data-target="1500" style="color:#0056B3;">0</div>
                <p class="fw-semibold text-muted mb-0">People Placed Globally</p>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="200">
                <div class="display-4 fw-bold counter" data-target="20" data-suffix="+" style="color:#F4B400;">0</div>
                <p class="fw-semibold text-muted mb-0">Countries Served</p>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="300">
                <div class="display-4 fw-bold counter" data-target="98" data-suffix="%" style="color:#0056B3;">0</div>
                <p class="fw-semibold text-muted mb-0">Success Rate</p>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="400">
                <div class="display-4 fw-bold counter" data-target="8" data-suffix="+" style="color:#F4B400;">0</div>
                <p class="fw-semibold text-muted mb-0">Years of Excellence</p>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     CORE SERVICES GRID
========================================================= -->
<section class="py-6 bg-white">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(244,180,0,0.12); color:#b88a00;">WHAT WE OFFER</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Your Complete Travel &amp; Relocation Partner</h2>
            <p class="lead text-muted">From visa assistance to employment opportunities, travel planning, documentation, relocation support, and guided tourism—we simplify every step of your international journey.</p>
        </div>

        <div class="row g-4">
            <?php
            $services = [
                ['icon' => 'fa-briefcase', 'title' => 'International Recruitment', 'desc' => 'Direct employment opportunities in UAE, Europe, Canada, and beyond. Verified employers, competitive salaries.'],
                ['icon' => 'fa-passport', 'title' => 'Visa Services', 'desc' => 'Employment, freelance, visit, tourist, family, student, and business visas. End-to-end processing and support.'],
                ['icon' => 'fa-house-chimney', 'title' => 'Relocation Services', 'desc' => 'Airport pickup, accommodation assistance, document preparation, orientation, and settlement support.'],
                ['icon' => 'fa-id-card', 'title' => 'Ghana Services', 'desc' => 'Ghana Card processing, passport assistance, notary services, document authentication, and translation.'],
                ['icon' => 'fa-umbrella-beach', 'title' => 'Travel &amp; Tours', 'desc' => 'International holiday packages to Dubai, Turkey, Egypt, Europe, Maldives, Singapore, and more.'],
                ['icon' => 'fa-hand-holding-dollar', 'title' => 'Documentation Support', 'desc' => 'Bank statements, sponsorship letters, affidavits, police clearance, and embassy documentation support.'],
            ];
            foreach ($services as $i => $s): ?>
            <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?= 100 * ($i + 1) ?>">
                <div class="text-center p-5 rounded-4 shadow-sm h-100 service-card" style="background:#ffffff; border:2px solid #f1f3f5; transition:all 0.4s;">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-4 service-icon" style="width:80px; height:80px; background:rgba(0,86,179,0.06);">
                        <i class="fas <?= $s['icon'] ?> fa-2x" style="color:#0056B3;"></i>
                    </div>
                    <h4 class="fw-bold" style="color:#0056B3;"><?= $s['title'] ?></h4>
                    <p class="text-muted mb-0"><?= $s['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- =========================================================
     DESTINATIONS / COUNTRIES WE SERVE — WITH REAL FLAGS (FIXED)
========================================================= -->
<section class="py-6" style="background:#f8f9fa;">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(0,86,179,0.06); color:#0056B3;">DESTINATIONS</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Countries We Serve</h2>
            <p class="lead text-muted">Global opportunities across multiple continents</p>
        </div>

        <div class="row g-3 justify-content-center">
            <?php
            $destinations = [
                ['name' => 'UAE', 'code' => 'ae'],
                ['name' => 'Saudi Arabia', 'code' => 'sa'],
                ['name' => 'Qatar', 'code' => 'qa'],
                ['name' => 'Kuwait', 'code' => 'kw'],
                ['name' => 'Oman', 'code' => 'om'],
                ['name' => 'Bahrain', 'code' => 'bh'],
                ['name' => 'United Kingdom', 'code' => 'gb'],
                ['name' => 'Germany', 'code' => 'de'],
                ['name' => 'Canada', 'code' => 'ca'],
                ['name' => 'Poland', 'code' => 'pl'],
                ['name' => 'Romania', 'code' => 'ro'],
                ['name' => 'Croatia', 'code' => 'hr'],
                ['name' => 'Hungary', 'code' => 'hu'],
                ['name' => 'Turkey', 'code' => 'tr'],
                ['name' => 'Egypt', 'code' => 'eg'],
                ['name' => 'Thailand', 'code' => 'th'],
                ['name' => 'Malaysia', 'code' => 'my'],
                ['name' => 'Singapore', 'code' => 'sg'],
                ['name' => 'Indonesia', 'code' => 'id'],
                ['name' => 'Maldives', 'code' => 'mv'],
            ];
            foreach ($destinations as $i => $d): ?>
            <div class="col-4 col-md-2" data-aos="fade-up" data-aos-delay="<?= 20 * $i ?>">
                <div class="text-center p-3 rounded-4 bg-white shadow-sm hover-lift destination-card" style="border:1px solid #e9ecef;">
                    <div style="font-size:3rem; line-height:1.2;">
                        <span class="flag-icon flag-icon-<?= $d['code'] ?>"></span>
                    </div>
                    <p class="fw-semibold mb-0 small mt-2" style="color:#0056B3;"><?= $d['name'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="countries.php" class="btn rounded-pill px-4 py-2 btn-ripple" style="background:#0056B3; color:#fff;">
                View All Destinations <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- =========================================================
     HOW IT WORKS
========================================================= -->
<section class="py-6 bg-white">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(0,86,179,0.06); color:#0056B3;">THE PROCESS</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">How It Works</h2>
            <p class="lead text-muted">From first contact to successful relocation — six clear steps</p>
        </div>

        <div class="row g-4 text-center">
            <?php
            $steps = [
                ['num' => '01', 'icon' => 'fa-comments', 'title' => 'Free Consultation', 'desc' => 'Chat with our team about your goals, eligibility, and available opportunities.'],
                ['num' => '02', 'icon' => 'fa-folder-open', 'title' => 'Documentation', 'desc' => 'We help prepare all required documents: passport, ID, bank statements, and more.'],
                ['num' => '03', 'icon' => 'fa-stamp', 'title' => 'Visa Processing', 'desc' => 'Your visa application is submitted and tracked to approval.'],
                ['num' => '04', 'icon' => 'fa-plane', 'title' => 'Travel Arrangements', 'desc' => 'Flight booking, airport pickup, and accommodation on arrival.'],
                ['num' => '05', 'icon' => 'fa-hand-holding-dollar', 'title' => 'Relocation Support', 'desc' => 'Settlement assistance, orientation, and ongoing support.'],
                ['num' => '06', 'icon' => 'fa-flag-checkered', 'title' => 'Success & Growth', 'desc' => 'Start your new chapter with confidence and our continued support.'],
            ];
            foreach ($steps as $i => $s): ?>
            <div class="col-md-4 col-lg" data-aos="flip-up" data-aos-delay="<?= 100 * ($i + 1) ?>">
                <div class="p-4 h-100 step-card">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 mx-auto step-circle" style="width:70px; height:70px; background:#0056B3;">
                        <i class="fas <?= $s['icon'] ?> fa-lg text-white"></i>
                    </div>
                    <div class="fw-bold small mb-1" style="color:#F4B400;">STEP <?= $s['num'] ?></div>
                    <h5 class="fw-bold" style="color:#0056B3;"><?= $s['title'] ?></h5>
                    <p class="text-muted small mb-0"><?= $s['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- =========================================================
     WHY CHOOSE US
========================================================= -->
<section class="py-6" style="background:#f8f9fa;">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-aos="fade-right">
                <img src="assets/images/bt.jpeg" class="img-fluid rounded-4 shadow-lg" alt="Believe Travel Consult team" onerror="this.src='https://placehold.co/700x500/0056B3/ffffff?text=Believe+Travel+Consult'">
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(244,180,0,0.12); color:#b88a00;">WHY CHOOSE US</span>
                <h2 class="display-5 fw-bold mb-4" style="color:#0056B3;">Trusted by Travelers &amp; Professionals Worldwide</h2>
                <?php
                $why = [
                    'Transparent pricing — no hidden fees, full receipt for every transaction',
                    'Licensed partners and verified employers across 20+ countries',
                    'Dedicated support team available before, during, and after your journey',
                    'Fast-track documentation and visa processing services',
                    'Real success stories from real clients — verifiable, not stock photos',
                    'International network of travel agents, employers, and relocation partners',
                ];
                foreach ($why as $w): ?>
                <div class="d-flex align-items-start mb-3" data-aos="fade-right" data-aos-delay="100">
                    <i class="fas fa-circle-check mt-1 me-3" style="color:#F4B400; font-size:1.2rem;"></i>
                    <p class="mb-0 fs-5 text-dark"><?= $w ?></p>
                </div>
                <?php endforeach; ?>
                <a href="<?= $whatsapp_base ?>Hello%2C%20I%20have%20questions%20about%20your%20services!" class="btn btn-lg rounded-pill px-5 py-3 mt-3 pulse-btn" style="background:#F4B400; color:#0056B3; font-weight:700;">
                    <i class="fab fa-whatsapp me-2"></i> Talk to an Advisor
                </a>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     POPULAR TOUR PACKAGES — Enhanced with Online Images
========================================================= -->
<section class="py-6 bg-white">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(0,86,179,0.06); color:#0056B3;">TRAVEL &amp; TOURS</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Explore the World With Us</h2>
            <p class="lead text-muted">Carefully designed holiday packages for families, couples, solo travelers, and groups</p>
        </div>

        <div class="row g-4">
            <?php
            $packages = [
                [
                    'city' => 'Dubai, UAE', 
                    'image' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=600&h=400&fit=crop&crop=center', 
                    'price' => 'GHS 4,500', 
                    'days' => '5 Days, 4 Nights', 
                    'highlights' => 'Burj Khalifa, Desert Safari, Shopping',
                    'attractions' => ['Burj Khalifa', 'Dubai Mall', 'Desert Safari']
                ],
                [
                    'city' => 'Istanbul, Turkey', 
                    'image' => 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?w=600&h=400&fit=crop&crop=center', 
                    'price' => 'GHS 5,200', 
                    'days' => '6 Days, 5 Nights', 
                    'highlights' => 'Hagia Sophia, Bosphorus, Grand Bazaar',
                    'attractions' => ['Hagia Sophia', 'Bosphorus Cruise', 'Grand Bazaar']
                ],
                [
                    'city' => 'Cairo, Egypt', 
                    'image' => 'https://images.unsplash.com/photo-1553913861-c0fddf2619ee?w=600&h=400&fit=crop&crop=center', 
                    'price' => 'GHS 4,800', 
                    'days' => '5 Days, 4 Nights', 
                    'highlights' => 'Pyramids, Nile Cruise, Egyptian Museum',
                    'attractions' => ['Pyramids of Giza', 'Nile Cruise', 'Egyptian Museum']
                ],
                [
                    'city' => 'London, UK', 
                    'image' => 'https://images.unsplash.com/photo-1513635269975-59663e0ac1ad?w=600&h=400&fit=crop&crop=center', 
                    'price' => 'GHS 7,500', 
                    'days' => '7 Days, 6 Nights', 
                    'highlights' => 'London Eye, Buckingham Palace, Museums',
                    'attractions' => ['London Eye', 'Buckingham Palace', 'British Museum']
                ],
                [
                    'city' => 'Maldives', 
                    'image' => 'https://images.unsplash.com/photo-1514282401047-d79a71a590e8?w=600&h=400&fit=crop&crop=center', 
                    'price' => 'GHS 9,500', 
                    'days' => '6 Days, 5 Nights', 
                    'highlights' => 'Overwater Villas, Snorkeling, Beach',
                    'attractions' => ['Overwater Villas', 'Snorkeling', 'Private Beach']
                ],
                [
                    'city' => 'Singapore', 
                    'image' => 'https://images.unsplash.com/photo-1525625293386-3f8f99389edd?w=600&h=400&fit=crop&crop=center', 
                    'price' => 'GHS 6,800', 
                    'days' => '5 Days, 4 Nights', 
                    'highlights' => 'Marina Bay, Sentosa, Shopping',
                    'attractions' => ['Marina Bay Sands', 'Sentosa Island', 'Orchard Road']
                ],
                [
                    'city' => 'Bali, Indonesia', 
                    'image' => 'https://images.unsplash.com/photo-1537996194471-e657df975ab4?w=600&h=400&fit=crop&crop=center', 
                    'price' => 'GHS 6,200', 
                    'days' => '7 Days, 6 Nights', 
                    'highlights' => 'Temples, Rice Terraces, Beaches',
                    'attractions' => ['Uluwatu Temple', 'Rice Terraces', 'Kuta Beach']
                ],
                [
                    'city' => 'Cape Town, South Africa', 
                    'image' => 'https://images.unsplash.com/photo-1580060839134-75a5edca2e99?w=600&h=400&fit=crop&crop=center', 
                    'price' => 'GHS 5,500', 
                    'days' => '6 Days, 5 Nights', 
                    'highlights' => 'Table Mountain, Cape Point, Wine Tasting',
                    'attractions' => ['Table Mountain', 'Cape of Good Hope', 'Wine Tasting']
                ],
            ];
            foreach ($packages as $i => $p): ?>
            <div class="col-md-6 col-lg-3" data-aos="flip-up" data-aos-delay="<?= 100 * ($i + 1) ?>">
                <div class="rounded-4 overflow-hidden shadow-sm h-100 tour-card" style="border:1px solid #e9ecef; transition:all 0.5s;">
                    <div class="position-relative">
                        <img src="<?= $p['image'] ?>" class="w-100 tour-image" style="height:220px; object-fit:cover;" alt="<?= $p['city'] ?>" onerror="this.src='https://placehold.co/600x400/0056B3/ffffff?text=<?= urlencode($p['city']) ?>'">
                        <div class="position-absolute top-0 start-0 m-3">
                            <span class="badge rounded-pill px-3 py-2" style="background:rgba(0,86,179,0.9); color:#fff;">
                                <i class="fas fa-star me-1" style="color:#F4B400;"></i> Featured
                            </span>
                        </div>
                    </div>
                    <div class="p-4">
                        <h5 class="fw-bold" style="color:#0056B3;"><?= $p['city'] ?></h5>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-light text-dark"><i class="far fa-calendar me-1"></i> <?= $p['days'] ?></span>
                            <span class="badge" style="background:rgba(0,86,179,0.1); color:#0056B3;">
                                <i class="fas fa-utensils me-1"></i> Breakfast Included
                            </span>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-3">
                            <?php foreach ($p['attractions'] as $attraction): ?>
                            <span class="badge" style="background:rgba(244,180,0,0.1); color:#b88a00;"><?= $attraction ?></span>
                            <?php endforeach; ?>
                        </div>
                        <a href="tours.php?dest=<?= urlencode($p['city']) ?>" class="btn w-100 rounded-pill btn-ripple" style="background:#0056B3; color:#fff;">
                            View Details <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="tours.php" class="btn rounded-pill px-5 py-3 pulse-btn" style="background:#F4B400; color:#0056B3; font-weight:700;">
                View All Packages <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>

<!-- =========================================================
     TESTIMONIALS
========================================================= -->
<section class="py-6" style="background:#f8f9fa;">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(0,86,179,0.06); color:#0056B3;">SUCCESS STORIES</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Real Stories From Real Clients</h2>
            <p class="lead text-muted">Hear from people who achieved their dreams with Believe Travel Consult</p>
        </div>

        <div class="row g-4">
            <?php
            $testimonials = [
                ['name' => 'Kwame A.', 'role' => 'Taxi Driver, UAE', 'img' => 'assets/images/driver1.png', 'quote' => 'Got my job in Dubai in 2 weeks. Now earning AED 3,000/month as a taxi driver!', 'stars' => 5],
                ['name' => 'Abena M.', 'role' => 'Waitress, UK', 'img' => 'assets/images/waitress.png', 'quote' => 'They helped me get my working visa to the UK. The process was smooth and well-organized.', 'stars' => 5],
                ['name' => 'Kofi D.', 'role' => 'Freelancer, Dubai', 'img' => 'assets/images/freelancer1.png', 'quote' => 'Amazing Dubai tour package! Everything was taken care of—flights, hotels, and tours.', 'stars' => 5],
                ['name' => 'Yaa S.', 'role' => 'Relocated to Canada', 'img' => 'assets/images/gallery_1.jpg', 'quote' => 'From Ghana to Canada in 4 months. Their relocation service made the move stress-free!', 'stars' => 5],
            ];
            foreach ($testimonials as $i => $t): ?>
            <div class="col-md-6 col-lg-3" data-aos="zoom-in" data-aos-delay="<?= 100 * ($i + 1) ?>">
                <div class="p-4 rounded-4 shadow-sm h-100 testimonial-card" style="background:#fff; border:1px solid #e9ecef; transition:all 0.4s;">
                    <div class="d-flex align-items-center mb-3">
                        <img src="<?= $t['img'] ?>" class="rounded-circle" style="width:50px; height:50px; object-fit:cover;" onerror="this.src='https://placehold.co/50x50/0056B3/ffffff?text=<?= substr($t['name'],0,1) ?>'">
                        <div class="ms-3">
                            <h6 class="fw-bold mb-0" style="color:#0056B3;"><?= $t['name'] ?></h6>
                            <small class="text-muted"><?= $t['role'] ?></small>
                        </div>
                    </div>
                    <div class="text-warning mb-2"><?= str_repeat('★', $t['stars']) ?></div>
                    <p class="text-muted fst-italic mb-0">"<?= $t['quote'] ?>"</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($posts)): ?>
<!-- =========================================================
     LATEST FROM THE BLOG
========================================================= -->
<section class="py-6 bg-white">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-5" data-aos="fade-up">
            <div>
                <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(0,86,179,0.06); color:#0056B3;">BLOG</span>
                <h2 class="display-5 fw-bold mb-0" style="color:#0056B3;">Guides, Stories &amp; Insights</h2>
            </div>
            <a href="blog.php" class="btn btn-outline-dark rounded-pill px-4 py-2 mt-3 mt-md-0 btn-ripple">Read More <i class="fas fa-arrow-right ms-1"></i></a>
        </div>
        <div class="row g-4">
            <?php foreach ($posts as $i => $p): ?>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="<?= 100 * ($i + 1) ?>">
                <a href="blog-post.php?slug=<?= urlencode($p['slug'] ?? '') ?>" class="text-decoration-none">
                    <div class="rounded-4 shadow-sm overflow-hidden h-100 bg-white blog-card">
                        <img src="<?= htmlspecialchars(resolve_image($p['image'] ?? '', 'blog')) ?>" class="w-100 blog-image" style="height:200px; object-fit:cover; transition:transform 0.6s;" alt="<?= htmlspecialchars($p['title']) ?>">
                        <div class="p-4">
                            <span class="badge rounded-pill px-3 py-1 mb-2" style="background:rgba(0,86,179,0.06); color:#0056B3;"><?= htmlspecialchars($p['category'] ?? 'Story') ?></span>
                            <h5 class="fw-bold mb-2" style="color:#0056B3;"><?= htmlspecialchars($p['title']) ?></h5>
                            <small class="text-muted"><i class="fas fa-clock me-1"></i><?= (int)($p['read_time'] ?? 5) ?> min read</small>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($gallery)): ?>
<!-- =========================================================
     GALLERY PREVIEW
========================================================= -->
<section class="py-6" style="background:#f8f9fa;">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(0,86,179,0.06); color:#0056B3;">GALLERY</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Life in Photos</h2>
            <p class="lead text-muted">Real moments from our clients and travelers around the world</p>
        </div>
        <div class="row g-3">
            <?php foreach ($gallery as $i => $g): ?>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="<?= 50 * ($i + 1) ?>">
                <a href="<?= htmlspecialchars($g['file_path']) ?>" target="_blank" class="d-block rounded-4 overflow-hidden shadow-sm gallery-item" style="aspect-ratio:1/1;">
                    <img src="<?= htmlspecialchars($g['file_path']) ?>" class="w-100 h-100 gallery-image" style="object-fit:cover; transition:transform 0.6s;" alt="<?= htmlspecialchars($g['title']) ?>">
                    <div class="gallery-overlay"></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="gallery.php" class="btn rounded-pill px-4 py-2 btn-ripple" style="background:#0056B3; color:#fff;">View Full Gallery</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- =========================================================
     FAQ
========================================================= -->
<section class="py-6 bg-white">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(244,180,0,0.12); color:#b88a00;">FAQ</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Frequently Asked Questions</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="accordion" id="faqAccordion">
                    <?php
                    $faqs = [
                        ['q' => 'How long does the whole process take?', 'a' => 'Timelines vary by service and destination. Most work visas are processed within 2-6 weeks. Tourist visas can be ready in 3-5 business days. We provide specific timelines during your consultation.'],
                        ['q' => 'What documents do I need to get started?', 'a' => 'A valid passport and ID are the minimum to begin. Additional documents depend on your destination and purpose of travel. Our team will guide you through exactly what\'s needed.'],
                        ['q' => 'Do you provide accommodation on arrival?', 'a' => 'Yes — for most work and relocation packages, we provide airport pickup and 3 nights of accommodation. Tour packages include hotel stays as part of the itinerary.'],
                        ['q' => 'Can I apply if I don\'t have a Ghana Card yet?', 'a' => 'Yes. Our Ghana Card Express service can issue a new Ghana Card in as little as 1 day so it doesn\'t delay your application.'],
                        ['q' => 'Do you offer services to countries other than the UAE?', 'a' => 'Absolutely! We serve 20+ countries including Canada, UK, Germany, Turkey, Saudi Arabia, Qatar, and many more. Check our Countries page for the full list.'],
                        ['q' => 'Is there a refund if my visa is rejected?', 'a' => 'Our team reviews every application carefully to minimize rejection risk. Refund terms are outlined in your service agreement — ask your advisor for full details.'],
                    ];
                    foreach ($faqs as $i => $f): ?>
                    <div class="accordion-item rounded-4 mb-3 border-0 shadow-sm overflow-hidden faq-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?> fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" style="color:#0056B3;">
                                <?= $f['q'] ?>
                            </button>
                        </h2>
                        <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted"><?= $f['a'] ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     FINAL CALL TO ACTION
========================================================= -->
<section class="py-6 position-relative" style="background: linear-gradient(135deg, rgba(0,86,179,0.95), rgba(0,45,100,0.95)), url('https://images.unsplash.com/photo-1488085061387-422e29b40080?w=1920&h=600&fit=crop&crop=center') center/cover no-repeat;">
    <div class="container">
        <div class="row text-center">
            <div class="col-lg-9 mx-auto text-white" data-aos="zoom-in">
                <h2 class="display-4 fw-bold mb-4 animate__animated animate__pulse animate__infinite">Ready to Begin Your Global Journey?</h2>
                <p class="lead mb-5">Join thousands of Ghanaians and West Africans already traveling, working, studying, and relocating worldwide with Believe Travel Consult.</p>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                    <a href="<?= $whatsapp_base ?>Hello%2C%20I%20am%20ready%20to%20travel%20or%20relocate%20globally!"
                       class="btn btn-lg rounded-pill px-5 py-3 shadow-lg pulse-btn"
                       style="background:#F4B400; color:#0056B3; font-size:1.2rem; font-weight:700;">
                        <i class="fab fa-whatsapp fa-lg me-2"></i> Start Your Journey Today
                    </a>
                    <a href="tel:+233596204991" class="btn btn-lg btn-outline-light rounded-pill px-5 py-3 btn-ripple">
                        <i class="fas fa-phone me-2"></i> Call Us
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- =========================================================
     STICKY MOBILE CTA BAR
========================================================= -->
<div class="d-md-none position-fixed bottom-0 start-0 w-100 p-2 shadow-lg" style="background:#fff; z-index:1050; border-top:1px solid #eee;">
    <div class="d-flex gap-2">
        <a href="<?= $whatsapp_base ?>Hello%2C%20I%20want%20to%20work%20in%20Dubai!" class="btn flex-fill rounded-pill fw-bold pulse-btn" style="background:#F4B400; color:#0056B3;">
            <i class="fab fa-whatsapp me-1"></i> WhatsApp
        </a>
        <a href="tel:+233596204991" class="btn flex-fill rounded-pill fw-bold" style="background:#0056B3; color:#fff;">
            <i class="fas fa-phone me-1"></i> Call
        </a>
    </div>
</div>

<!-- floating whatsapp button (desktop) -->
<a href="<?= $whatsapp_base ?>Hi%2C%20I%E2%80%99m%20interested%20in%20your%20services."
   class="d-none d-md-flex position-fixed align-items-center justify-content-center shadow-lg floating-whatsapp"
   style="bottom:30px; right:30px; width:64px; height:64px; border-radius:50%; background:#25D366; z-index:1050;">
    <i class="fab fa-whatsapp fa-2x text-white"></i>
</a>

<?php include 'includes/footer.php'; ?>

<!-- =========================================================
     CUSTOM CSS — Enhanced Animations
========================================================= -->
<style>
/* --- Animation Imports --- */
@import url('https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css');
@import url('https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.5.0/css/flag-icon.min.css');

/* --- Base Styles --- */
.hover-lift { 
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
}
.hover-lift:hover {
    transform: translateY(-12px) scale(1.02);
    border-color: #F4B400 !important;
    box-shadow: 0 25px 50px rgba(0,86,179,0.2) !important;
}

/* --- Hero Animations --- */
.hero-wrapper {
    overflow: hidden;
}
.hero-wrapper .home {
    animation: heroZoom 20s ease-in-out infinite alternate;
}
@keyframes heroZoom {
    0% { transform: scale(1); }
    100% { transform: scale(1.05); }
}

/* --- Button Pulse Animation --- */
.pulse-btn {
    animation: pulse 2s infinite;
    transition: all 0.3s ease;
}
.pulse-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 30px rgba(244,180,0,0.4);
}
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(244,180,0,0.4); }
    70% { box-shadow: 0 0 0 15px rgba(244,180,0,0); }
    100% { box-shadow: 0 0 0 0 rgba(244,180,0,0); }
}

/* --- Ripple Button Effect --- */
.btn-ripple {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}
.btn-ripple:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(0,86,179,0.3);
}

/* --- Service Cards --- */
.service-card {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
}
.service-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(244,180,0,0.1), transparent);
    transition: left 0.6s ease;
}
.service-card:hover::before {
    left: 100%;
}
.service-card:hover {
    transform: translateY(-12px) scale(1.02);
    border-color: #F4B400 !important;
    box-shadow: 0 20px 60px rgba(0,86,179,0.15) !important;
}
.service-icon {
    transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.service-card:hover .service-icon {
    transform: rotateY(360deg) scale(1.1);
    background: rgba(244,180,0,0.15) !important;
}

/* --- Destination Cards --- */
.destination-card {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    cursor: pointer;
}
.destination-card:hover {
    transform: translateY(-8px) scale(1.05);
    border-color: #F4B400 !important;
    box-shadow: 0 15px 40px rgba(0,86,179,0.15);
}
.destination-card .flag-icon {
    transition: transform 0.6s ease;
    display: inline-block;
}
.destination-card:hover .flag-icon {
    transform: scale(1.3) rotate(5deg);
}

/* --- Step Cards --- */
.step-card {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border-radius: 20px;
    background: #fff;
}
.step-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(0,86,179,0.12);
}
.step-circle {
    transition: all 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.step-card:hover .step-circle {
    transform: scale(1.1) rotate(10deg);
    background: #F4B400 !important;
}

/* --- Tour Cards --- */
.tour-card {
    transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    overflow: hidden;
}
.tour-card:hover {
    transform: translateY(-15px) scale(1.02);
    box-shadow: 0 25px 60px rgba(0,86,179,0.2);
    border-color: #F4B400 !important;
}
.tour-image {
    transition: transform 0.7s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.tour-card:hover .tour-image {
    transform: scale(1.1);
}

/* --- Testimonial Cards --- */
.testimonial-card {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.testimonial-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 50px rgba(0,86,179,0.1);
    border-color: #F4B400 !important;
}

/* --- Blog Cards --- */
.blog-card {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.blog-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 50px rgba(0,86,179,0.12);
}
.blog-image {
    transition: transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.blog-card:hover .blog-image {
    transform: scale(1.08);
}

/* --- Gallery Items --- */
.gallery-item {
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.gallery-item:hover {
    transform: scale(1.05);
    box-shadow: 0 15px 40px rgba(0,86,179,0.2);
}
.gallery-image {
    transition: transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}
.gallery-item:hover .gallery-image {
    transform: scale(1.15);
}
.gallery-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(180deg, transparent, rgba(0,86,179,0.4));
    opacity: 0;
    transition: opacity 0.4s ease;
}
.gallery-item:hover .gallery-overlay {
    opacity: 1;
}

/* --- FAQ Items --- */
.faq-item {
    transition: all 0.3s ease;
}
.faq-item:hover {
    transform: translateX(8px);
    box-shadow: 0 10px 30px rgba(0,86,179,0.08) !important;
}

/* --- Floating WhatsApp --- */
.floating-whatsapp {
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    animation: float 3s ease-in-out infinite;
}
.floating-whatsapp:hover {
    transform: scale(1.15) rotate(5deg);
    box-shadow: 0 10px 40px rgba(37,211,102,0.4);
}
@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-15px); }
    100% { transform: translateY(0px); }
}

/* --- Scroll Animations --- */
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(15px); }
}

/* --- Accordion --- */
.accordion-button:not(.collapsed) {
    background: rgba(0,86,179,0.06);
    color: #0056B3 !important;
    box-shadow: none;
}
.accordion-button:focus { 
    box-shadow: none; 
    border-color: #F4B400;
}
.accordion-button:hover {
    background: rgba(244,180,0,0.05);
}

/* --- Mobile --- */
body { padding-bottom: 0; }
@media (max-width: 767px) { 
    body { padding-bottom: 62px; } 
    .home {
        height: 100vh !important;
        min-height: 100vh !important;
    }
    .display-3 {
        font-size: 2.2rem !important;
    }
}

/* --- AOS Override for Better Performance --- */
[data-aos] {
    pointer-events: none;
}
[data-aos].aos-animate {
    pointer-events: auto;
}

/* --- Custom Scrollbar --- */
::-webkit-scrollbar {
    width: 10px;
}
::-webkit-scrollbar-track {
    background: #f1f1f1;
}
::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #0056B3, #F4B400);
    border-radius: 5px;
}
::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, #003d80, #d4a000);
}
</style>

<!-- =========================================================
     COUNTER ANIMATION SCRIPT
========================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const counters = document.querySelectorAll('.counter');
    const options = { threshold: 0.6 };

    const runCounter = (el) => {
        const target = parseInt(el.getAttribute('data-target'), 10);
        const suffix = el.getAttribute('data-suffix') || '';
        const duration = 1500;
        const start = performance.now();

        function step(now) {
            const progress = Math.min((now - start) / duration, 1);
            const value = Math.floor(progress * target);
            el.textContent = value + suffix;
            if (progress < 1) requestAnimationFrame(step);
            else el.textContent = target + suffix;
        }
        requestAnimationFrame(step);
    };

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                runCounter(entry.target);
                obs.unobserve(entry.target);
            }
        });
    }, options);

    counters.forEach(c => observer.observe(c));
});

// --- Ripple Effect on Buttons ---
document.querySelectorAll('.btn-ripple').forEach(btn => {
    btn.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size/2;
        const y = e.clientY - rect.top - size/2;
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;
        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(ripple);
        setTimeout(() => ripple.remove(), 600);
    });
});

// Add ripple keyframes
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to { transform: scale(4); opacity: 0; }
    }
`;
document.head.appendChild(style);

// =========================================================
// ELIGIBILITY FORM ROUTING
// Job categories -> jobs.php?category=...
// Document/service categories -> offers.php?category=...
// =========================================================
document.addEventListener('DOMContentLoaded', function () {
    const eligibilityForm = document.getElementById('eligibilityForm');
    const categorySelect = document.getElementById('categorySelect');
    if (!eligibilityForm || !categorySelect) return;

    // Categories that should route to offers.php (documents & services)
    const serviceCategories = ['police-report', 'passport', 'license', 'ghana-card'];

    eligibilityForm.addEventListener('submit', function (e) {
        const value = categorySelect.value;

        if (!value) {
            e.preventDefault();
            categorySelect.focus();
            return;
        }

        // Dynamically set the form's target page based on the selected category,
        // then let the native GET submit carry the ?category= param through.
        if (serviceCategories.includes(value)) {
            eligibilityForm.setAttribute('action', 'offers.php');
        } else {
            eligibilityForm.setAttribute('action', 'jobs.php');
        }
    });
});
</script>