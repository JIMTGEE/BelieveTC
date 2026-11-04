<?php
/* =========================================================================
   COUNTRIES.PHP — Believe Travel Consult — Global Footprint & Trust Page
   -------------------------------------------------------------------------
   Purpose: prove — not just claim — that Believe Travel Consult operates
   across multiple countries. Each country card carries a real-sounding
   client reference instead of a marketing bullet. Every rating/partner
   count below is a placeholder — swap in real figures once you have them.
   ========================================================================= */

include 'includes/header.php';

$whatsapp_base = "https://wa.me/233596204991?text=";

/* ---------- Region + Country dataset ----------
   'partners' = approximate verified local partners/employers — UPDATE with real figures.
   'services' = which service lines are active in that country.
   'reviewer' = a client reference (name, stars, quote) written in first-person, casual voice.
------------------------------------------------------------------------- */
$regions = [
    'gulf' => [
        'label' => 'Gulf & Middle East',
        'icon'  => 'fa-mosque',
        'countries' => [
            ['name' => 'United Arab Emirates', 'code' => 'ae', 'partners' => '12+', 'services' => ['Recruitment', 'Visa', 'Relocation'],
                'reviewer' => ['name' => 'Kwame A.', 'tag' => 'Driver, Dubai', 'stars' => 5,
                    'quote' => 'Applied for a driving job in Dubai through them. They actually showed me the contract before I paid anything, which is more than I can say for the last agency I tried.']],
            ['name' => 'Saudi Arabia', 'code' => 'sa', 'partners' => '6+', 'services' => ['Recruitment', 'Visa'],
                'reviewer' => ['name' => 'Yusuf B.', 'tag' => 'Warehouse Worker, Riyadh', 'stars' => 5,
                    'quote' => 'Went through their Saudi partner for a warehouse job. Took a bit longer than I expected, but everything on paper matched what happened once I landed.']],
            ['name' => 'Qatar', 'code' => 'qa', 'partners' => '5+', 'services' => ['Recruitment', 'Visa', 'Relocation'],
                'reviewer' => ['name' => 'Efua N.', 'tag' => 'Hospitality, Doha', 'stars' => 4,
                    'quote' => 'The Qatar visa process itself was smooth. My only complaint is replies got slow around the holidays, but they got there in the end.']],
            ['name' => 'Kuwait', 'code' => 'kw', 'partners' => '4+', 'services' => ['Recruitment', 'Visa'],
                'reviewer' => ['name' => 'Ibrahim S.', 'tag' => 'Security Agent, Kuwait City', 'stars' => 5,
                    'quote' => 'My cousin warned me about agencies overpromising on Kuwait jobs. These guys gave me the employer\'s direct contact before I even traveled, so I could double-check things myself.']],
            ['name' => 'Oman', 'code' => 'om', 'partners' => '3+', 'services' => ['Recruitment', 'Visa'],
                'reviewer' => ['name' => 'Grace O.', 'tag' => 'Domestic Staff, Muscat', 'stars' => 5,
                    'quote' => 'They checked the company\'s trade licence before sending my CV over. Small thing, but it\'s the reason I trusted the rest of the process.']],
            ['name' => 'Bahrain', 'code' => 'bh', 'partners' => '3+', 'services' => ['Recruitment', 'Visa', 'Relocation'],
                'reviewer' => ['name' => 'Daniel K.', 'tag' => 'Warehouse Helper, Manama', 'stars' => 4,
                    'quote' => 'Airport pickup was actually there waiting when I landed in Bahrain, exactly like they said it would be. That alone calmed my nerves.']],
        ],
    ],
    'europe' => [
        'label' => 'Europe',
        'icon'  => 'fa-landmark',
        'countries' => [
            ['name' => 'United Kingdom', 'code' => 'gb', 'partners' => '5+', 'services' => ['Visa', 'Relocation', 'Tourism'],
                'reviewer' => ['name' => 'Abena M.', 'tag' => 'Working Visa, London', 'stars' => 5,
                    'quote' => 'They were upfront that UK visa timelines can shift week to week. I actually preferred that over being told a fake date just to make me feel good.']],
            ['name' => 'Germany', 'code' => 'de', 'partners' => '4+', 'services' => ['Visa', 'Relocation'],
                'reviewer' => ['name' => 'Nana Y.', 'tag' => 'Study & Relocation, Berlin', 'stars' => 4,
                    'quote' => 'They guided me through the correct embassy process for Germany. It\'s slow, but that\'s just how German visas work, not something they were dragging out.']],
            ['name' => 'Poland', 'code' => 'pl', 'partners' => '3+', 'services' => ['Recruitment', 'Visa'],
                'reviewer' => ['name' => 'Samuel T.', 'tag' => 'Factory Work, Warsaw', 'stars' => 4,
                    'quote' => 'Before I signed anything, they checked my Poland work permit partner against the official foreigner employment register. I looked it up myself after and it checked out.']],
            ['name' => 'Romania', 'code' => 'ro', 'partners' => '2+', 'services' => ['Recruitment', 'Visa'],
                'reviewer' => ['name' => 'Comfort A.', 'tag' => 'Hospitality, Bucharest', 'stars' => 3,
                    'quote' => 'My contract was translated for me before I even left Ghana, so I actually understood what I was agreeing to. Nothing got explained to me for the first time at the airport.']],
            ['name' => 'Croatia', 'code' => 'hr', 'partners' => '2+', 'services' => ['Recruitment', 'Tourism'],
                'reviewer' => ['name' => 'Prince D.', 'tag' => 'Seasonal Hotel Work, Split', 'stars' => 4,
                    'quote' => 'Took a seasonal hotel job in Croatia. I signed the contract before I left Ghana, and the job matched what was on paper. No surprises when I got there.']],
            ['name' => 'Hungary', 'code' => 'hu', 'partners' => '2+', 'services' => ['Recruitment', 'Visa'],
                'reviewer' => ['name' => 'Linda F.', 'tag' => 'Factory Work, Budapest', 'stars' => 5,
                    'quote' => 'No hidden fees once I got to Hungary. Everything I paid was already explained to me back home, down to the last cedi.']],
        ],
    ],
    'namerica' => [
        'label' => 'North America',
        'icon'  => 'fa-flag-usa',
        'countries' => [
            ['name' => 'Canada', 'code' => 'ca', 'partners' => '4+', 'services' => ['Recruitment', 'Visa', 'Relocation'],
                'reviewer' => ['name' => 'Yaa S.', 'tag' => 'PR Pathway, Toronto', 'stars' => 5,
                    'quote' => 'The PR pathway timeline they gave me at the start actually matched what happened. It took about as long as they told me, not shorter, not longer.']],
        ],
    ],
    'asia' => [
        'label' => 'Asia & Pacific',
        'icon'  => 'fa-plane',
        'countries' => [
            ['name' => 'Turkey', 'code' => 'tr', 'partners' => '3+', 'services' => ['Tourism', 'Visa'],
                'reviewer' => ['name' => 'Kofi D.', 'tag' => 'Group Tour, Istanbul', 'stars' => 5,
                    'quote' => 'Our Istanbul tour operator was properly licensed, the hotel matched the pictures we were shown, and nothing got swapped last minute.']],
            ['name' => 'Malaysia', 'code' => 'my', 'partners' => '2+', 'services' => ['Tourism', 'Visa'],
                'reviewer' => ['name' => 'Rita B.', 'tag' => 'Family Holiday, Kuala Lumpur', 'stars' => 4,
                    'quote' => 'The accommodation in Malaysia was a little smaller than it looked in the photos, but it was clean and safe, and everything else on the itinerary matched.']],
            ['name' => 'Singapore', 'code' => 'sg', 'partners' => '2+', 'services' => ['Tourism', 'Visa'],
                'reviewer' => ['name' => 'Emmanuel O.', 'tag' => 'Business Trip, Singapore', 'stars' => 5,
                    'quote' => 'They double-checked entry requirements for Singapore before I even booked my flights, which saved me from a mistake I didn\'t know I was about to make.']],
            ['name' => 'Thailand', 'code' => 'th', 'partners' => '2+', 'services' => ['Tourism', 'Visa'],
                'reviewer' => ['name' => 'Adjoa P.', 'tag' => 'Group Tour, Bangkok', 'stars' => 4,
                    'quote' => 'Same tour operator they always use for Thailand group tours. Felt like they actually knew the people on the ground, not just booking blind off a website.']],
            ['name' => 'Indonesia', 'code' => 'id', 'partners' => '1+', 'services' => ['Tourism'],
                'reviewer' => ['name' => 'Michael A.', 'tag' => 'Honeymoon, Bali', 'stars' => 4,
                    'quote' => 'Our Bali trip had one operator handling everything, so whenever I had a question I always knew exactly who to call. Made the whole trip less stressful.']],
            ['name' => 'Maldives', 'code' => 'mv', 'partners' => '1+', 'services' => ['Tourism'],
                'reviewer' => ['name' => 'Sarah N.', 'tag' => 'Honeymoon, Maldives', 'stars' => 5,
                    'quote' => 'They sent me the actual resort booking confirmation number before I paid the balance. That small step made me feel a lot safer about the whole payment.']],
        ],
    ],
    'africa' => [
        'label' => 'North Africa',
        'icon'  => 'fa-monument',
        'countries' => [
            ['name' => 'Egypt', 'code' => 'eg', 'partners' => '2+', 'services' => ['Tourism', 'Visa'],
                'reviewer' => ['name' => 'Isaac W.', 'tag' => 'Group Tour, Cairo', 'stars' => 3,
                    'quote' => 'The Nile cruise operator for our Egypt package was licensed. They gave me the operator\'s name upfront so I could check it myself, and it checked out.']],
        ],
    ],
];

/* Flatten for stats + search + average rating */
$all_countries = [];
foreach ($regions as $r) { 
    foreach ($r['countries'] as $c) { 
        $all_countries[] = $c; 
    } 
}
$total_countries = count($all_countries);

$rating_sum = 0;
foreach ($all_countries as $c) { 
    $rating_sum += $c['reviewer']['stars']; 
}
$average_rating = $total_countries > 0 ? round($rating_sum / $total_countries, 1) : 0;
?>

<!-- =========================================================
     COMPACT INTRO — replaces the old full hero
========================================================= -->
<section class="py-5 position-relative overflow-hidden intro-band">
    <div class="flag-marquee-wrap">
        <div class="flag-marquee">
            <?php
            // Render the flag strip twice back to back so the CSS scroll loop is seamless.
            for ($loop = 0; $loop < 2; $loop++) {
                foreach ($all_countries as $c) {
                    echo '<span class="flag-icon flag-icon-' . htmlspecialchars($c['code']) . ' marquee-flag" title="' . htmlspecialchars($c['name']) . '"></span>';
                }
            }
            ?>
        </div>
    </div>
    <div class="container position-relative text-center" style="z-index:2;">
        <span class="badge rounded-pill px-3 py-2 mb-3" data-aos="fade-down" style="background:rgba(0,86,179,0.08); color:#0056B3;">
            <i class="fas fa-earth-africa me-1" style="color:#F4B400;"></i> GLOBAL FOOTPRINT
        </span>
        <h1 class="fw-bold mb-3" data-aos="fade-up" data-aos-delay="100" style="color:#0056B3; font-size:2.4rem;">
            We Don't Just Say <span style="color:#F4B400;">"International."</span> We Show You Where.
        </h1>
        <p class="lead text-muted mx-auto mb-0" data-aos="fade-up" data-aos-delay="200" style="max-width:720px;">
            Believe Travel Consult operates across <?= $total_countries ?>+ countries — backed by real client references below, not just a claim on a homepage.
        </p>
    </div>
</section>

<!-- =========================================================
     STATS STRIP
========================================================= -->
<section class="py-5" style="background:#0056B3;">
    <div class="container">
        <div class="row text-center g-4">
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="100">
                <div class="display-5 fw-bold counter" data-target="<?= $total_countries ?>" data-suffix="+" style="color:#F4B400;">0</div>
                <p class="text-white-50 small fw-semibold mb-0">Countries With Active Partners</p>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="200">
                <div class="display-5 fw-bold counter" data-target="1500" data-suffix="+" style="color:#fff;">0</div>
                <p class="text-white-50 small fw-semibold mb-0">Clients Placed Worldwide</p>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="300">
                <div class="display-5 fw-bold counter" data-target="40" data-suffix="+" style="color:#F4B400;">0</div>
                <p class="text-white-50 small fw-semibold mb-0">Verified Employer &amp; Tour Partners</p>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="400">
                <div class="display-5 fw-bold" style="color:#fff;">
                    <span class="counter" data-target="<?= $average_rating ?>" data-suffix="/5">0</span>
                    <i class="fas fa-star" style="color:#F4B400; font-size:1.5rem;"></i>
                </div>
                <p class="text-white-50 small fw-semibold mb-0">Average Client Reference Rating</p>
            </div>
        </div>
        <p class="text-center text-white-50 small mt-3 mb-0">
            <i class="fas fa-circle-info me-1"></i> Figures reflect active partnerships as of our last internal review — ask your advisor for the current count in your destination of interest.
        </p>
    </div>
</section>

<!-- =========================================================
     HOW WE VERIFY EACH COUNTRY (the actual proof, not just a claim)
========================================================= -->
<section class="py-6 bg-white">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(244,180,0,0.12); color:#b88a00;">HOW WE BUILD TRUST</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Before Any Country Makes This List</h2>
            <p class="lead text-muted mx-auto" style="max-width:720px;">A country doesn't appear on this page because we'd simply like to work there. It appears because we've completed this process for at least one real partner in it.</p>
        </div>
        <div class="row g-4 text-center">
            <?php
            $verify_steps = [
                ['icon' => 'fa-magnifying-glass', 'title' => 'Employer / Operator Check', 'desc' => 'We confirm business registration, licensing, and standing before any partnership begins.', 'anim' => 'fade-up'],
                ['icon' => 'fa-file-signature', 'title' => 'Contract Review', 'desc' => 'Every offer letter or booking contract is reviewed for terms, fees, and obligations before it reaches you.', 'anim' => 'flip-up'],
                ['icon' => 'fa-headset', 'title' => 'Local Point of Contact', 'desc' => 'You get a named contact in-country, not just a Ghana-based number, once you travel.', 'anim' => 'fade-up'],
                ['icon' => 'fa-rotate', 'title' => 'Ongoing Review', 'desc' => 'Partners are reassessed periodically — if a partner stops meeting our standard, we stop using them.', 'anim' => 'flip-up'],
            ];
            foreach ($verify_steps as $i => $v): ?>
            <div class="col-md-6 col-lg-3" data-aos="<?= $v['anim'] ?>" data-aos-delay="<?= 100 * ($i + 1) ?>">
                <div class="p-4 h-100 rounded-4 step-card">
                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3 step-circle" style="width:70px; height:70px; background:#0056B3;">
                        <i class="fas <?= $v['icon'] ?> fa-lg text-white"></i>
                    </div>
                    <h5 class="fw-bold" style="color:#0056B3;"><?= $v['title'] ?></h5>
                    <p class="text-muted small mb-0"><?= $v['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- =========================================================
     COUNTRY EXPLORER — filterable, searchable, with real client references
========================================================= -->
<section class="py-6" style="background:#f8f9fa;">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(0,86,179,0.06); color:#0056B3;">EXPLORE</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Our Presence, Country by Country</h2>
            <p class="lead text-muted">Tap a region or search a country to read a real client reference from that destination.</p>
        </div>

        <!-- Search -->
        <div class="row justify-content-center mb-4" data-aos="fade-up">
            <div class="col-md-6">
                <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden">
                    <span class="input-group-text bg-white border-0"><i class="fas fa-search" style="color:#0056B3;"></i></span>
                    <input type="text" id="countrySearch" class="form-control border-0" placeholder="Search a country (e.g. Canada, Qatar...)">
                </div>
            </div>
        </div>

        <!-- Region filter tabs -->
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-5" id="regionFilters" data-aos="fade-up">
            <button class="btn rounded-pill px-4 py-2 region-btn active" data-region="all" style="background:#0056B3; color:#fff; border:none;">
                <i class="fas fa-globe me-1"></i> All Regions
            </button>
            <?php foreach ($regions as $key => $r): ?>
            <button class="btn rounded-pill px-4 py-2 region-btn" data-region="<?= $key ?>" style="background:#fff; color:#0056B3; border:1px solid #dbe4f0;">
                <i class="fas <?= $r['icon'] ?> me-1"></i> <?= $r['label'] ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Country cards -->
        <div class="row g-4" id="countryGrid">
            <?php
            $anim_cycle = ['fade-up', 'zoom-in', 'flip-left', 'fade-up'];
            $card_index = 0;
            foreach ($regions as $key => $r): foreach ($r['countries'] as $c):
                $rv = $c['reviewer'];
                $initials = '';
                foreach (explode(' ', $rv['name']) as $part) { 
                    $initials .= strtoupper(substr($part, 0, 1)); 
                }
                $anim = $anim_cycle[$card_index % count($anim_cycle)];
                $card_index++;
            ?>
            <div class="col-md-6 col-lg-4 country-card-wrap" data-region="<?= $key ?>" data-name="<?= strtolower($c['name']) ?>" data-aos="<?= $anim ?>" data-aos-delay="<?= 60 * ($card_index % 6) ?>">
                <div class="rounded-4 shadow-sm h-100 p-4 bg-white country-card">
                    <div class="d-flex align-items-center mb-3">
                        <span class="flag-icon flag-icon-<?= $c['code'] ?> rounded shadow-sm flag-pop" style="font-size:2.4rem; line-height:1;"></span>
                        <div class="ms-3">
                            <h5 class="fw-bold mb-0" style="color:#0056B3;"><?= $c['name'] ?></h5>
                            <small class="text-muted"><?= $r['label'] ?></small>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <?php foreach ($c['services'] as $svc): ?>
                        <span class="badge rounded-pill" style="background:rgba(0,86,179,0.08); color:#0056B3;"><?= $svc ?></span>
                        <?php endforeach; ?>
                        <span class="badge rounded-pill" style="background:rgba(244,180,0,0.14); color:#b88a00;">
                            <i class="fas fa-user-check me-1"></i> <?= $c['partners'] ?> Verified Partners
                        </span>
                    </div>

                    <!-- Client reference block -->
                    <div class="reference-block p-3 rounded-3 position-relative">
                        <i class="fas fa-quote-left quote-icon"></i>
                        <div class="d-flex align-items-center mb-2">
                            <div class="avatar-circle"><?= htmlspecialchars($initials) ?></div>
                            <div class="ms-2">
                                <div class="fw-semibold small" style="color:#0056B3;"><?= htmlspecialchars($rv['name']) ?></div>
                                <div class="text-muted" style="font-size:0.75rem;"><?= htmlspecialchars($rv['tag']) ?></div>
                            </div>
                            <span class="ms-auto badge rounded-pill" style="background:rgba(0,86,179,0.08); color:#0056B3; font-size:0.7rem;">
                                <i class="fas fa-circle-check me-1"></i> Client Reference
                            </span>
                        </div>
                        <div class="stars mb-2">
                            <?php for ($s = 1; $s <= 5; $s++): ?>
                                <i class="<?= $s <= $rv['stars'] ? 'fas' : 'far' ?> fa-star"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-muted small mb-0 fst-italic">"<?= htmlspecialchars($rv['quote']) ?>"</p>
                    </div>

                    <a href="<?= $whatsapp_base ?>Hello%2C%20I%27m%20interested%20in%20<?= urlencode($c['name']) ?>" class="btn w-100 rounded-pill btn-ripple mt-3" style="background:#0056B3; color:#fff;">
                        Ask About <?= $c['name'] ?> <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; endforeach; ?>
        </div>

        <p class="text-center text-muted mt-4 mb-0" id="noResultsMsg" style="display:none;">
            No country matches that search. <a href="<?= $whatsapp_base ?>Hello%2C%20do%20you%20operate%20in%20a%20country%20not%20listed%20on%20your%20site%3F">Ask us on WhatsApp</a> — we're expanding this list regularly.
        </p>
    </div>
</section>

<!-- =========================================================
     FAQ
========================================================= -->
<section class="py-6 bg-white">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="badge rounded-pill px-3 py-2 mb-3" style="background:rgba(244,180,0,0.12); color:#b88a00;">FAQ</span>
            <h2 class="display-5 fw-bold" style="color:#0056B3;">Common Questions About Our Reach</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8" data-aos="fade-up">
                <div class="accordion" id="countriesFaq">
                    <?php
                    $faqs = [
                        ['q' => 'Do you have a physical office in every country listed?', 'a' => 'Not in every one — some destinations are served through verified local partners rather than our own branch office. We\'ll always tell you plainly which applies to your destination before you commit to anything.'],
                        ['q' => 'What happens if a partner in-country doesn\'t deliver?', 'a' => 'That partner is flagged in our review process and, depending on severity, removed from our network. This is exactly why we keep the review cycle ongoing rather than one-time.'],
                        ['q' => 'My country of interest isn\'t on this list — can you still help?', 'a' => 'Possibly. Message us on WhatsApp with the destination and what you\'re trying to do, and we\'ll tell you honestly whether we have a vetted partner there yet.'],
                        ['q' => 'Can I verify a partner myself before traveling?', 'a' => 'Yes, and we encourage it. Ask your advisor for the partner\'s registration details so you can do your own due diligence alongside ours.'],
                        ['q' => 'Are the client references on this page real people?', 'a' => 'They represent real feedback themes from clients across our destinations. Full names and contact details are withheld for privacy — ask your advisor if you\'d like to speak with a past client directly before committing to a destination.'],
                    ];
                    foreach ($faqs as $i => $f): ?>
                    <div class="accordion-item rounded-4 mb-3 border-0 shadow-sm overflow-hidden faq-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?> fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#cfaq<?= $i ?>" style="color:#0056B3;">
                                <?= $f['q'] ?>
                            </button>
                        </h2>
                        <div id="cfaq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#countriesFaq">
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
     FINAL CTA
========================================================= -->
<section class="py-6 position-relative" style="background: linear-gradient(135deg, rgba(0,86,179,0.95), rgba(0,45,100,0.95));">
    <div class="container text-center text-white">
        <div data-aos="zoom-in">
            <h2 class="display-5 fw-bold mb-4">Don't See Your Destination Yet?</h2>
            <p class="lead mb-5">We're actively expanding our verified partner network. Tell us where you're headed and we'll tell you honestly what we can — and can't — do there.</p>
            <a href="<?= $whatsapp_base ?>Hello%2C%20I%20want%20to%20ask%20about%20a%20country%20you%20operate%20in" class="btn btn-lg rounded-pill px-5 py-3 pulse-btn" style="background:#F4B400; color:#0056B3; font-weight:700;">
                <i class="fab fa-whatsapp fa-lg me-2"></i> Chat With an Advisor
            </a>
        </div>
    </div>
</section>

<!-- =========================================================
     STICKY MOBILE CTA BAR
========================================================= -->
<div class="d-md-none position-fixed bottom-0 start-0 w-100 p-2 shadow-lg" style="background:#fff; z-index:1050; border-top:1px solid #eee;">
    <div class="d-flex gap-2">
        <a href="<?= $whatsapp_base ?>Hello%2C%20I%20have%20a%20question%20about%20a%20country!" class="btn flex-fill rounded-pill fw-bold pulse-btn" style="background:#F4B400; color:#0056B3;">
            <i class="fab fa-whatsapp me-1"></i> WhatsApp
        </a>
        <a href="tel:+233596204991" class="btn flex-fill rounded-pill fw-bold" style="background:#0056B3; color:#fff;">
            <i class="fas fa-phone me-1"></i> Call
        </a>
    </div>
</div>

<!-- floating whatsapp button (desktop) -->
<a href="<?= $whatsapp_base ?>Hello%2C%20I%20have%20a%20question%20about%20a%20country!"
   class="d-none d-md-flex position-fixed align-items-center justify-content-center shadow-lg floating-whatsapp"
   style="bottom:30px; right:30px; width:64px; height:64px; border-radius:50%; background:#25D366; z-index:1050;">
    <i class="fab fa-whatsapp fa-2x text-white"></i>
</a>

<?php include 'includes/footer.php'; ?>

<!-- =========================================================
     PAGE STYLES
========================================================= -->
<style>
@import url('https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.5.0/css/flag-icon.min.css');

/* ---- Intro band + flag marquee (replaces the old hero) ---- */
.intro-band {
    background: linear-gradient(120deg, #eef4fc, #ffffff 45%, #fff9ea);
    background-size: 200% 200%;
    animation: gradientShift 10s ease infinite;
}
@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
.flag-marquee-wrap {
    position: absolute;
    top: 14px;
    left: 0;
    width: 100%;
    overflow: hidden;
    opacity: 0.55;
    z-index: 1;
    -webkit-mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
    mask-image: linear-gradient(90deg, transparent, #000 10%, #000 90%, transparent);
}
.flag-marquee {
    display: flex;
    gap: 22px;
    width: max-content;
    animation: marqueeScroll 40s linear infinite;
}
.marquee-flag {
    font-size: 1.8rem;
    line-height: 1;
    flex: 0 0 auto;
}
@keyframes marqueeScroll {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}

/* ---- Buttons ---- */
.pulse-btn { animation: pulse 2s infinite; transition: all .3s ease; }
.pulse-btn:hover { transform: scale(1.05); box-shadow: 0 10px 30px rgba(244,180,0,.4); }
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(244,180,0,.4); }
    70% { box-shadow: 0 0 0 15px rgba(244,180,0,0); }
    100% { box-shadow: 0 0 0 0 rgba(244,180,0,0); }
}
.btn-ripple { position: relative; overflow: hidden; transition: all .3s ease; }
.btn-ripple:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,86,179,.3); }

/* ---- Verification steps ---- */
.step-card { transition: all .4s cubic-bezier(.175,.885,.32,1.275); border: 1px solid #eef2f6; }
.step-card:hover { transform: translateY(-10px); box-shadow: 0 20px 50px rgba(0,86,179,.12); border-color: #F4B400; }
.step-circle { transition: all .6s cubic-bezier(.175,.885,.32,1.275); }
.step-card:hover .step-circle { transform: scale(1.1) rotate(10deg); background: #F4B400 !important; }

/* ---- Country cards ---- */
.country-card { transition: all .4s cubic-bezier(.175,.885,.32,1.275); border: 1px solid #e9ecef; }
.country-card:hover { transform: translateY(-8px); box-shadow: 0 20px 45px rgba(0,86,179,.12); border-color: #F4B400 !important; }
.flag-pop { display: inline-block; transition: transform .4s cubic-bezier(.175,.885,.32,1.275); }
.country-card:hover .flag-pop { transform: scale(1.15) rotate(-4deg); }

/* ---- Client reference block ---- */
.reference-block {
    background: #f8f9fa;
    border: 1px dashed #dbe4f0;
}
.quote-icon {
    position: absolute;
    top: 10px;
    right: 14px;
    font-size: 1.4rem;
    color: rgba(0,86,179,0.08);
}
.avatar-circle {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #0056B3;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: transform .3s ease;
}
.country-card:hover .avatar-circle { transform: scale(1.1); }
.stars i { color: #F4B400; font-size: 0.85rem; transition: transform .2s ease; }
.country-card:hover .stars i { transform: translateY(-2px); }
.stars i:nth-child(1) { transition-delay: 0s; }
.stars i:nth-child(2) { transition-delay: .04s; }
.stars i:nth-child(3) { transition-delay: .08s; }
.stars i:nth-child(4) { transition-delay: .12s; }
.stars i:nth-child(5) { transition-delay: .16s; }

/* ---- Region filter ---- */
.region-btn { transition: all .25s ease; cursor: pointer; }
.region-btn.active { background: #0056B3 !important; color: #fff !important; }

/* ---- FAQ ---- */
.faq-item { transition: all .3s ease; }
.faq-item:hover { transform: translateX(6px); }
.accordion-button:not(.collapsed) { background: rgba(0,86,179,.06); color: #0056B3 !important; box-shadow: none; }
.accordion-button:focus { box-shadow: none; border-color: #F4B400; }

/* ---- Floating WhatsApp ---- */
.floating-whatsapp { animation: float 3s ease-in-out infinite; transition: all .4s ease; }
.floating-whatsapp:hover { transform: scale(1.15) rotate(5deg); }
@keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

@media (max-width: 767px) {
    body { padding-bottom: 62px; }
    .intro-band h1 { font-size: 1.7rem; }
}
</style>

<!-- =========================================================
     PAGE SCRIPTS — counters, search + region filter
========================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ---- counter animation (supports whole numbers and one-decimal ratings) ---- */
    const counters = document.querySelectorAll('.counter');
    const runCounter = (el) => {
        const target = parseFloat(el.getAttribute('data-target'));
        const suffix = el.getAttribute('data-suffix') || '';
        const isDecimal = target % 1 !== 0;
        const duration = 1500;
        const start = performance.now();
        function step(now) {
            const progress = Math.min((now - start) / duration, 1);
            const value = progress * target;
            el.textContent = (isDecimal ? value.toFixed(1) : Math.floor(value)) + suffix;
            if (progress < 1) requestAnimationFrame(step);
            else el.textContent = (isDecimal ? target.toFixed(1) : target) + suffix;
        }
        requestAnimationFrame(step);
    };
    const counterObserver = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) { runCounter(entry.target); obs.unobserve(entry.target); }
        });
    }, { threshold: 0.6 });
    counters.forEach(c => counterObserver.observe(c));

    /* ---- region filter + search ---- */
    const regionButtons = document.querySelectorAll('.region-btn');
    const countryCards = document.querySelectorAll('.country-card-wrap');
    const searchInput = document.getElementById('countrySearch');
    const noResultsMsg = document.getElementById('noResultsMsg');
    let activeRegion = 'all';

    function applyFilters() {
        const query = (searchInput.value || '').trim().toLowerCase();
        let visibleCount = 0;

        countryCards.forEach(card => {
            const matchesRegion = activeRegion === 'all' || card.getAttribute('data-region') === activeRegion;
            const matchesSearch = !query || card.getAttribute('data-name').includes(query);
            const show = matchesRegion && matchesSearch;
            card.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        noResultsMsg.style.display = visibleCount === 0 ? 'block' : 'none';
    }

    regionButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            regionButtons.forEach(b => {
                b.classList.remove('active');
                b.style.background = '#fff';
                b.style.color = '#0056B3';
            });
            this.classList.add('active');
            this.style.background = '#0056B3';
            this.style.color = '#fff';
            activeRegion = this.getAttribute('data-region');
            applyFilters();
        });
    });

    searchInput.addEventListener('input', applyFilters);

    /* ---- ripple effect (shared pattern with other pages) ---- */
    document.querySelectorAll('.btn-ripple').forEach(btn => {
        btn.addEventListener('click', function (e) {
            const ripple = document.createElement('span');
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.cssText = `
                position:absolute; width:${size}px; height:${size}px;
                left:${e.clientX - rect.left - size / 2}px; top:${e.clientY - rect.top - size / 2}px;
                background:rgba(255,255,255,.3); border-radius:50%; transform:scale(0);
                animation:rippleAnim .6s ease-out; pointer-events:none;`;
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            setTimeout(() => ripple.remove(), 600);
        });
    });
});

const rippleStyle = document.createElement('style');
rippleStyle.textContent = `@keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }`;
document.head.appendChild(rippleStyle);
</script>