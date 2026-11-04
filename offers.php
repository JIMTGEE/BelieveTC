<?php include 'includes/header.php'; ?>
<?php require_once 'includes/btc_images.php'; ?>

<style>
    /* ===== Believe Travel Consult — Offers Page Enhancements ===== */
    :root{
        --btc-navy:#033754;
        --btc-cyan:#10b6d7;
        --btc-green:#89d900;
        --btc-gold:#d4af37;
    }

    /* Motion library */
    @keyframes floatY {0%,100%{transform:translateY(0) translateX(0) rotate(0deg);}50%{transform:translateY(-22px) translateX(12px) rotate(6deg);}}
    @keyframes floatYSlow {0%,100%{transform:translateY(0);}50%{transform:translateY(18px);}}
    @keyframes pulseRing {0%{transform:scale(1);opacity:.55;}100%{transform:scale(1.22);opacity:0;}}
    @keyframes shimmer {0%{background-position:-200% 0;}100%{background-position:200% 0;}}
    @keyframes badgeIn {from{opacity:0;transform:translateY(10px) scale(.9);}to{opacity:1;transform:translateY(0) scale(1);}}

    @media (prefers-reduced-motion: reduce){
        *{animation-duration:.001ms !important; animation-iteration-count:1 !important; transition-duration:.001ms !important;}
    }

    .grad-text{background:linear-gradient(90deg,#10b6d7,#89d900,#10b6d7); background-size:200% auto; -webkit-background-clip:text; background-clip:text; color:transparent; animation:shimmer 6s linear infinite;}

    .pulse-cta{position:relative;}
    .pulse-cta::after{content:'';position:absolute;inset:0;border-radius:50px;border:2px solid #89d900;animation:pulseRing 2.2s ease-out infinite;}

    /* Section eyebrow */
    .eyebrow{display:inline-block; font-size:.75rem; font-weight:800; letter-spacing:2.5px; text-transform:uppercase; color:var(--btc-cyan); margin-bottom:.75rem;}
    .eyebrow::before{content:'●'; color:var(--btc-green); margin-right:8px; font-size:.6rem;}

    /* ===== Hero: split headline + floating service showcase ===== */
    .offers-hero{
        position:relative; overflow:hidden; padding:5.5rem 0 4.5rem;
        background:linear-gradient(160deg, #042a42 0%, #033754 50%, #054866 100%);
    }
    .offers-hero .hero-orb{position:absolute; border-radius:50%; filter:blur(60px); opacity:.5; pointer-events:none;}
    .offers-hero .hero-orb-1{width:420px;height:420px; top:-140px; left:-120px; background:radial-gradient(circle, rgba(16,182,215,.55), transparent 70%); animation:floatYSlow 9s ease-in-out infinite;}
    .offers-hero .hero-orb-2{width:360px;height:360px; bottom:-160px; right:-100px; background:radial-gradient(circle, rgba(137,217,0,.4), transparent 70%); animation:floatYSlow 11s ease-in-out infinite reverse;}
    .offers-hero::after{
        content:''; position:absolute; inset:0; pointer-events:none;
        background-image:
            linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
        background-size:46px 46px;
        -webkit-mask-image:radial-gradient(circle at 30% 30%, rgba(0,0,0,.5), transparent 70%);
        mask-image:radial-gradient(circle at 30% 30%, rgba(0,0,0,.5), transparent 70%);
    }
    .hero-kicker{
        display:inline-flex; align-items:center; gap:.5rem; font-size:.72rem; font-weight:700; letter-spacing:3px;
        text-transform:uppercase; color:var(--btc-gold); background:rgba(212,175,55,.12);
        border:1px solid rgba(212,175,55,.35); padding:.5rem 1rem; border-radius:50px; margin-bottom:1.4rem;
    }
    .hero-kicker i{font-size:.65rem;}
    .hero-title{
        font-size:clamp(2.1rem, 4vw, 3.4rem); font-weight:800; line-height:1.14; color:#fff; margin-bottom:1.2rem;
    }
    .hero-title .accent{color:var(--btc-cyan);}
    .hero-sub{color:rgba(255,255,255,.78); font-size:1.1rem; max-width:520px; margin-bottom:2rem;}
    .hero-cta-row{display:flex; flex-wrap:wrap; gap:.9rem; margin-bottom:2.6rem;}
    .btn-hero-primary{
        display:inline-flex; align-items:center; gap:.6rem; background:var(--btc-green); color:var(--btc-navy);
        font-weight:800; padding:.95rem 2rem; border-radius:50px; text-decoration:none;
        box-shadow:0 16px 32px rgba(3,55,84,.32); transition:transform .3s ease, box-shadow .3s ease;
    }
    .btn-hero-primary:hover, .btn-hero-primary:focus-visible{transform:translateY(-2px); box-shadow:0 20px 40px rgba(3,55,84,.4); color:var(--btc-navy);}
    .btn-hero-ghost{
        display:inline-flex; align-items:center; gap:.6rem; background:rgba(255,255,255,.08); color:#fff;
        font-weight:700; padding:.95rem 1.8rem; border-radius:50px; text-decoration:none; border:1.5px solid rgba(255,255,255,.28);
        transition:all .3s ease;
    }
    .btn-hero-ghost:hover, .btn-hero-ghost:focus-visible{background:rgba(255,255,255,.16); color:#fff; border-color:rgba(255,255,255,.5);}
    .hero-trust-row{display:flex; flex-wrap:wrap; gap:1.6rem;}
    .trust-chip{display:flex; flex-direction:column;}
    .trust-chip strong{font-size:1.4rem; font-weight:800; color:#fff; line-height:1;}
    .trust-chip span{font-size:.72rem; color:rgba(255,255,255,.6); letter-spacing:.5px; text-transform:uppercase; margin-top:.25rem;}

    /* Floating service showcase */
    .hero-card-grid{display:grid; grid-template-columns:1fr 1fr; gap:1rem;}
    .hero-service-card{
        display:flex; flex-direction:column; align-items:flex-start; gap:.7rem;
        background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.16); border-radius:1.1rem;
        padding:1.4rem 1.2rem; text-decoration:none; backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px);
        transition:transform .35s cubic-bezier(.22,1,.36,1), background .35s ease, border-color .35s ease, box-shadow .35s ease;
        animation:floatYSlow 6s ease-in-out infinite; animation-delay:var(--d, 0s);
    }
    .hero-service-card:nth-child(even){animation-direction:alternate-reverse;}
    .hero-service-card .hsc-icon{
        width:44px;height:44px;border-radius:12px; display:flex; align-items:center; justify-content:center;
        background:linear-gradient(135deg, rgba(16,182,215,.25), rgba(137,217,0,.25)); color:#fff; font-size:1.15rem;
    }
    .hero-service-card span{color:#fff; font-weight:700; font-size:.92rem;}
    .hero-service-card small{color:rgba(255,255,255,.55); font-size:.72rem;}
    .hero-service-card:hover, .hero-service-card:focus-visible{
        transform:translateY(-6px); background:rgba(255,255,255,.13); border-color:rgba(137,217,0,.55);
        box-shadow:0 18px 34px rgba(3,55,84,.35);
    }
    @media (max-width:575px){.hero-card-grid{gap:.75rem;} .hero-service-card{padding:1.1rem .9rem;}}

    /* Offer cards */
    .offer-card{transition:transform .35s cubic-bezier(.22,1,.36,1), box-shadow .35s ease, border-color .35s ease; border:1.5px solid transparent; overflow:hidden;}
    .offer-card:hover{transform:translateY(-8px); box-shadow:0 22px 44px rgba(3,55,84,.16); border-color:rgba(137,217,0,.4);}
    .icon-wrap{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem; background:linear-gradient(135deg, rgba(16,182,215,.12), rgba(137,217,0,.14)); transition:transform .4s cubic-bezier(.34,1.56,.64,1);}
    .offer-card:hover .icon-wrap{transform:scale(1.14) rotate(-6deg);}

    /* Card top photo, image mode */
    .card-photo-wrap{position:relative; margin:-1px -1px 0; overflow:hidden; border-radius:1rem 1rem 0 0; height:170px;}
    .card-photo-wrap img{width:100%;height:100%;object-fit:cover; display:block; transition:transform .6s cubic-bezier(.22,1,.36,1);}
    .offer-card:hover .card-photo-wrap img{transform:scale(1.1);}
    .card-photo-wrap .icon-badge{position:absolute; bottom:10px; right:10px; width:38px;height:38px;border-radius:12px; background:rgba(255,255,255,.92); display:flex;align-items:center;justify-content:center; box-shadow:0 6px 16px rgba(3,55,84,.25);}

    /* Thumbnail photo used inline next to a title (rows/compact cards) */
    .thumb-photo{width:56px;height:56px;border-radius:14px;object-fit:cover;flex:0 0 auto;box-shadow:0 6px 16px rgba(3,55,84,.18);}
    .thumb-wrap{position:relative; flex:0 0 auto; width:56px; height:56px;}
    .thumb-wrap .mini-badge{position:absolute; bottom:-6px; right:-6px; width:22px;height:22px;border-radius:50%; background:var(--btc-green); display:flex;align-items:center;justify-content:center; font-size:.6rem; color:var(--btc-navy); box-shadow:0 4px 10px rgba(3,55,84,.3);}

    /* Compact per-item CTA button used across every offer */
    .btn-offer{font-size:.78rem; font-weight:700; padding:.4rem 1rem; border-radius:50px; border:1.5px solid var(--btc-green); color:var(--btc-navy); background:#fff; transition:all .3s ease; display:inline-flex; align-items:center; gap:.4rem; white-space:nowrap;}
    .btn-offer:hover, .btn-offer:focus-visible{background:var(--btc-green); color:var(--btc-navy); transform:translateX(3px); box-shadow:0 8px 18px rgba(137,217,0,.35); text-decoration:none;}
    .btn-offer i{transition:transform .3s ease;}
    .btn-offer:hover i{transform:translateX(3px);}

    /* Compact list-row CTA (for dense lists like training courses) */
    .offer-row{display:flex; align-items:center; justify-content:space-between; gap:.75rem; transition:background .25s ease, padding-left .25s ease;}
    .offer-row:hover{background:rgba(16,182,215,.06); padding-left:.5rem; border-radius:.5rem;}
    .offer-row .row-cta{opacity:.55; transition:opacity .25s ease, transform .25s ease; color:var(--btc-cyan); flex:0 0 auto;}
    .offer-row:hover .row-cta{opacity:1; transform:translateX(3px);}

    /* How-it-works steps */
    .step-circle{width:58px;height:58px;border-radius:50%;background:linear-gradient(135deg,var(--btc-cyan),var(--btc-navy));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.25rem;margin:0 auto 1rem;box-shadow:0 10px 22px rgba(3,55,84,.28); position:relative; z-index:2;}

    /* Service quick-nav pills */
    .quicknav-link{position:relative; transition:transform .25s ease;}
    .quicknav-link::after{content:''; position:absolute; left:50%; bottom:-6px; width:0; height:2px; background:var(--btc-green); transition:all .3s ease; transform:translateX(-50%);}
    .quicknav-link:hover{transform:translateY(-3px);}
    .quicknav-link:hover::after{width:70%;}

    /* Dubai spotlight photo tiles */
    .spot-tile{position:relative;border-radius:1rem;overflow:hidden;height:150px; box-shadow:0 10px 24px rgba(3,55,84,.14);}
    .spot-tile img{width:100%;height:100%;object-fit:cover; display:block; transition:transform .6s ease;}
    .spot-tile:hover img{transform:scale(1.14);}
    .spot-tile .cap{position:absolute;bottom:0;left:0;right:0;padding:.6rem .8rem;background:linear-gradient(to top, rgba(3,55,84,.92), transparent);color:#fff;font-weight:700;font-size:.82rem;display:flex;align-items:center;gap:.4rem;}

    /* Destination photo cards (Turkey, Egypt, etc.) */
    .dest-photo-card{position:relative;border-radius:1.25rem;overflow:hidden; min-height:290px; color:#fff;}
    .dest-photo-card img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover; z-index:0; transition:transform .6s ease;}
    .dest-photo-card:hover img{transform:scale(1.1);}
    .dest-photo-card .overlay{position:absolute;inset:0;background:linear-gradient(to top, rgba(3,55,84,.94) 8%, rgba(3,55,84,.35) 55%, rgba(3,55,84,.05));z-index:1;}
    .dest-photo-card .content{position:relative;z-index:2;padding:1.5rem;height:100%;display:flex;flex-direction:column;justify-content:flex-end;}

    /* Graceful degradation when BOTH remote photo sources fail to load */
    .card-photo-wrap.img-broken,
    .spot-tile.img-broken,
    .dest-photo-card.img-broken{
        background:linear-gradient(135deg, rgba(16,182,215,.16), rgba(137,217,0,.18));
        display:flex; align-items:center; justify-content:center;
    }
    .card-photo-wrap.img-broken .icon-badge{
        position:static; transform:none; width:54px; height:54px; box-shadow:0 8px 20px rgba(3,55,84,.18);
    }
    .dest-photo-card.img-broken .overlay{display:none;}
    .dest-photo-card.img-broken .content{justify-content:center; text-align:center; align-items:center;}
    .thumb-wrap.img-broken{
        border-radius:14px; background:linear-gradient(135deg, rgba(16,182,215,.2), rgba(137,217,0,.22));
        display:flex; align-items:center; justify-content:center;
    }
    .thumb-wrap.img-broken .mini-badge{position:static; width:26px; height:26px; font-size:.7rem;}

    /* Floating persistent WhatsApp button */
    .floating-wa{position:fixed; bottom:24px; right:24px; z-index:1050; width:58px;height:58px;border-radius:50%; background:var(--btc-green); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 26px rgba(3,55,84,.35); transition:transform .25s ease;}
    .floating-wa:hover{transform:scale(1.1);}
    .floating-wa i{color:var(--btc-navy); font-size:1.6rem;}
    @media (max-width:768px){.floating-wa{width:50px;height:50px; bottom:16px; right:16px;}}
</style>

<script>
    /**
     * Robust image loading for every photo built with btc_img_tag().
     * Stage 1: primary source fails -> try data-fallback (Picsum).
     * Stage 2: fallback also fails -> hide the <img> and let its card
     *          degrade to the .img-broken CSS treatment instead of
     *          showing a browser broken-image icon.
     */
    function btcImgFallback(img) {
        if (img.dataset.stage !== '1' && img.dataset.fallback) {
            img.dataset.stage = '1';
            img.src = img.dataset.fallback;
            return;
        }
        img.onerror = null;
        img.style.display = 'none';
        var frame = img.closest('.card-photo-wrap, .spot-tile, .dest-photo-card, .thumb-wrap');
        if (frame) frame.classList.add('img-broken');
    }
</script>

<!-- HERO: split headline + floating service showcase (no photo dependency at all) -->
<section class="offers-hero text-white">
    <span class="hero-orb hero-orb-1"></span>
    <span class="hero-orb hero-orb-2"></span>
    <div class="container position-relative">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6" data-aos="fade-right">
                <span class="hero-kicker"><i class="fas fa-globe-africa"></i> Recruitment &nbsp;·&nbsp; Travel &nbsp;·&nbsp; Relocation &nbsp;·&nbsp; Worldwide</span>
                <h1 class="hero-title">Every Offer We Run &mdash; <span class="accent">In One Place</span></h1>
                <p class="hero-sub">Browse recruitment, visa, travel, relocation, Ghana services and training offers below, then speak to an advisor to get started.</p>
                <div class="hero-cta-row">
                    <a href="#offer-nav" class="btn-hero-ghost">
                        Browse All Offers <i class="fas fa-arrow-down"></i>
                    </a>
                </div>
                <div class="hero-trust-row">
                    <div class="trust-chip"><strong>21+</strong><span>Destinations</span></div>
                    <div class="trust-chip"><strong>6</strong><span>Service Areas</span></div>
                    <div class="trust-chip"><strong>24/7</strong><span>Advisor Support</span></div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="hero-card-grid">
                    <a href="#recruitment" class="hero-service-card" style="--d:0s">
                        <span class="hsc-icon"><i class="fas fa-briefcase"></i></span>
                        <span>Recruitment</span><small>Skilled &amp; professional roles</small>
                    </a>
                    <a href="#visa-immigration" class="hero-service-card" style="--d:.4s">
                        <span class="hsc-icon"><i class="fas fa-passport"></i></span>
                        <span>Visa &amp; Immigration</span><small>Every stage covered</small>
                    </a>
                    <a href="#travel-tourism" class="hero-service-card" style="--d:.8s">
                        <span class="hsc-icon"><i class="fas fa-plane-departure"></i></span>
                        <span>Travel &amp; Tourism</span><small>21+ destinations</small>
                    </a>
                    <a href="#relocation" class="hero-service-card" style="--d:1.2s">
                        <span class="hsc-icon"><i class="fas fa-suitcase-rolling"></i></span>
                        <span>Relocation</span><small>Settle in with ease</small>
                    </a>
                    <a href="#ghana-services" class="hero-service-card" style="--d:1.6s">
                        <span class="hsc-icon"><i class="fas fa-flag"></i></span>
                        <span>Ghana Services</span><small>Documentation, handled</small>
                    </a>
                    <a href="#training-courses" class="hero-service-card" style="--d:2s">
                        <span class="hsc-icon"><i class="fas fa-graduation-cap"></i></span>
                        <span>Training Courses</span><small>Certified &amp; job-ready</small>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW TO GET AN OFFER -->
<section class="py-7 bg-white">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="eyebrow">How It Works</span>
            <h2 class="display-5 fw-bold" style="color:#033754;">Four Steps From Enquiry to <span class="grad-text">Arrival</span></h2>
            <p class="lead text-muted col-lg-8 mx-auto">Every offer on this page &mdash; recruitment, visas, travel, relocation, Ghana services or training &mdash; runs through the same simple process.</p>
        </div>
        <div class="row g-4 position-relative">
            <?php
            $steps = [
                ['num'=>'1','title'=>'Chat With an Advisor','desc'=>'Tap any "Get This Offer" button and tell us what you need on WhatsApp.'],
                ['num'=>'2','title'=>'Share Your Details','desc'=>'We review your goals, documents and eligibility for the right offer.'],
                ['num'=>'3','title'=>'We Handle the Process','desc'=>'Applications, employer matching, visa or documentation &mdash; managed for you.'],
                ['num'=>'4','title'=>'You Travel or Receive Your Service','desc'=>'Arrival support, document delivery or course start &mdash; we stay with you.'],
            ];
            foreach ($steps as $i => $s) {
                echo '<div class="col-6 col-lg-3 text-center" data-aos="fade-up" data-aos-delay="' . ($i*100) . '">
                        <div class="step-circle">' . $s['num'] . '</div>
                        <h6 class="fw-bold mb-2" style="color:#033754;">' . $s['title'] . '</h6>
                        <p class="text-muted small mb-0">' . $s['desc'] . '</p>
                      </div>';
            }
            ?>
        </div>
        <div class="text-center mt-6" data-aos="fade-up">
            <a href="https://wa.me/233596204991?text=Hi!%20I'd%20like%20to%20start%20-%20please%20guide%20me%20through%20the%20process"
               class="btn btn-lg rounded-pill px-6 py-3 shadow-lg pulse-cta" style="background:#89d900; color:#033754; font-weight:900;">
                <i class="fab fa-whatsapp me-2"></i> Start My Offer Now
            </a>
        </div>
    </div>
</section>

<!-- COUNTRIES WE SERVE -->
<section class="py-6 bg-light border-bottom">
    <div class="container text-center" data-aos="fade-up">
        <span class="eyebrow">Destinations We Serve</span>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <?php
            $countries = [
                'United Arab Emirates','Saudi Arabia','Qatar','Kuwait','Oman','Bahrain',
                'United Kingdom','Germany','Poland','Romania','Croatia','Hungary',
                'Canada','Australia','Turkey','Malaysia','Singapore','Thailand',
                'Egypt','Georgia','Azerbaijan'
            ];
            foreach ($countries as $i => $country) {
                echo '<span class="badge rounded-pill px-4 py-2 fs-6 dest-badge" data-aos="zoom-in" data-aos-delay="' . (($i%8)*40) . '" style="background:#fff; color:#033754; border:1px solid #d0e4ea;">' . htmlspecialchars($country) . '</span>';
            }
            ?>
            <span class="badge rounded-pill px-4 py-2 fs-6" style="background:#033754; color:#fff;">+ Growing Every Month</span>
        </div>
    </div>
</section>

<!-- SERVICE OVERVIEW NAV -->
<section class="py-5" style="background:#033754;" id="offer-nav">
    <div class="container">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-2"><a href="#recruitment" class="quicknav-link text-white text-decoration-none fw-semibold d-inline-block"><i class="fas fa-briefcase d-block mb-2 fs-3" style="color:#89d900;"></i>Recruitment</a></div>
            <div class="col-6 col-md-2"><a href="#visa-immigration" class="quicknav-link text-white text-decoration-none fw-semibold d-inline-block"><i class="fas fa-passport d-block mb-2 fs-3" style="color:#89d900;"></i>Visa &amp; Immigration</a></div>
            <div class="col-6 col-md-2"><a href="#travel-tourism" class="quicknav-link text-white text-decoration-none fw-semibold d-inline-block"><i class="fas fa-plane-departure d-block mb-2 fs-3" style="color:#89d900;"></i>Travel &amp; Tourism</a></div>
            <div class="col-6 col-md-2"><a href="#relocation" class="quicknav-link text-white text-decoration-none fw-semibold d-inline-block"><i class="fas fa-suitcase-rolling d-block mb-2 fs-3" style="color:#89d900;"></i>Relocation</a></div>
            <div class="col-6 col-md-2"><a href="#ghana-services" class="quicknav-link text-white text-decoration-none fw-semibold d-inline-block"><i class="fas fa-flag d-block mb-2 fs-3" style="color:#89d900;"></i>Ghana Services</a></div>
            <div class="col-6 col-md-2"><a href="#training-courses" class="quicknav-link text-white text-decoration-none fw-semibold d-inline-block"><i class="fas fa-graduation-cap d-block mb-2 fs-3" style="color:#89d900;"></i>Training Courses</a></div>
        </div>
    </div>
</section>

<!-- SECTION 1: INTERNATIONAL RECRUITMENT -->
<section class="py-7 bg-light" id="recruitment">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="eyebrow">Offer Category</span>
            <h2 class="display-5 fw-bold" style="color:#033754;">International Recruitment &amp; Workforce Offers</h2>
            <p class="lead text-muted">Tap any offer below to get matched with a verified employer in our partner destinations</p>
        </div>
        <div class="row g-4">
            <?php
            $recruitment = [
                ['icon'=>'fa-user-hard-hat','title'=>'Skilled Workforce Recruitment','desc'=>'Technicians, tradespeople and skilled operatives for a range of industries.','slug'=>'recruit-skilled-workforce','kw'=>'factory technician working on industrial machine'],
                ['icon'=>'fa-concierge-bell','title'=>'Hospitality Recruitment','desc'=>'Hotel, restaurant and guest-services roles with leading hospitality brands.','slug'=>'recruit-hospitality','kw'=>'hotel receptionist welcoming guest smiling'],
                ['icon'=>'fa-hard-hat','title'=>'Construction Recruitment','desc'=>'Site staff and construction professionals for major regional projects.','slug'=>'recruit-construction','kw'=>'construction worker on site wearing hard hat'],
                ['icon'=>'fa-user-nurse','title'=>'Healthcare Recruitment','desc'=>'Nurses, caregivers and allied health professionals for hospitals and clinics.','slug'=>'recruit-healthcare','kw'=>'nurse caring for patient in hospital ward'],
                ['icon'=>'fa-shield-alt','title'=>'Security Recruitment','desc'=>'Licensed security personnel for corporate, residential and event roles.','slug'=>'recruit-security','kw'=>'professional security guard in uniform'],
                ['icon'=>'fa-home','title'=>'Domestic Worker Recruitment','desc'=>'Vetted domestic staff placed with families through a transparent process.','slug'=>'recruit-domestic','kw'=>'housekeeper cleaning modern home'],
                ['icon'=>'fa-truck-loading','title'=>'Logistics &amp; Warehouse Recruitment','desc'=>'Warehouse, supply chain and logistics roles across growing markets.','slug'=>'recruit-logistics','kw'=>'warehouse worker with forklift and pallets'],
                ['icon'=>'fa-user-tie','title'=>'Professional &amp; Executive Recruitment','desc'=>'Mid-to-senior professional placements for qualified candidates.','slug'=>'recruit-professional','kw'=>'business professional working in modern office'],
            ];
            foreach ($recruitment as $i => $r) {
                $wa = 'https://wa.me/233596204991?text=' . rawurlencode("Hi! I'm interested in the " . strip_tags($r['title']) . " offer");
                echo '<div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="' . (($i%4)*80) . '">
                        <div class="offer-card bg-white shadow-lg rounded-4 h-100 d-flex flex-column">
                            <div class="card-photo-wrap">
                                ' . btc_img_tag($r['slug'], $r['kw'], strip_tags($r['title']), 500, 340, 200+$i) . '
                                <span class="icon-badge"><i class="fas ' . $r['icon'] . '" style="color:#10b6d7;"></i></span>
                            </div>
                            <div class="p-4 d-flex flex-column flex-grow-1 text-center">
                                <h5 class="fw-bold" style="color:#033754;">' . $r['title'] . '</h5>
                                <p class="text-muted small mb-3 flex-grow-1">' . $r['desc'] . '</p>
                                <a href="' . $wa . '" class="btn-offer mx-auto"><i class="fab fa-whatsapp"></i> Get This Offer <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                      </div>';
            }
            ?>
        </div>
    </div>
</section>

<!-- SECTION 2: VISA & IMMIGRATION -->
<section class="py-7 bg-white" id="visa-immigration">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="eyebrow">Offer Category</span>
            <h2 class="display-5 fw-bold" style="color:#033754;">Visa &amp; Immigration Offers</h2>
            <p class="lead text-muted">Clear, compliant guidance through every stage of the visa process</p>
        </div>
        <div class="row g-3">
            <?php
            $visas = [
                ['fa-briefcase','Employment Visa Assistance','visa-employment','work visa passport stamp office desk'],
                ['fa-umbrella-beach','Tourist Visa Assistance','visa-tourist','tourist relaxing on tropical beach vacation'],
                ['fa-handshake','Business Visa Support','visa-business','business handshake meeting agreement'],
                ['fa-user-graduate','Student Visa Guidance','visa-student','university student on campus with books'],
                ['fa-users','Family Visa Support','visa-family','happy family together at home'],
                ['fa-id-card','Residence Visa Assistance','visa-residence','identity card and passport documents on desk'],
                ['fa-sync-alt','Visa Renewal &amp; Extension Support','visa-renewal','passport with visa stamps close up'],
            ];
            foreach ($visas as $i => $v) {
                $wa = 'https://wa.me/233596204991?text=' . rawurlencode("Hi! I need help with " . strip_tags($v[1]));
                echo '<div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="' . (($i%3)*80) . '">
                        <a href="' . $wa . '" class="text-decoration-none">
                        <div class="offer-row bg-light rounded-4 p-3 h-100">
                            <div class="d-flex align-items-center gap-3">
                                <div class="thumb-wrap">
                                    ' . btc_img_tag($v[2], $v[3], strip_tags($v[1]), 160, 160, 300+$i, 'thumb-photo') . '
                                    <span class="mini-badge"><i class="fas ' . $v[0] . '"></i></span>
                                </div>
                                <h6 class="fw-bold mb-0" style="color:#033754;">' . $v[1] . '</h6>
                            </div>
                            <i class="fab fa-whatsapp row-cta fa-lg"></i>
                        </div>
                        </a>
                      </div>';
            }
            ?>
        </div>
        <div class="text-center mt-5" data-aos="fade-up">
            <a href="https://wa.me/233596204991?text=Hi!%20I%20need%20Visa%20%26%20Immigration%20guidance"
               class="btn btn-lg rounded-pill px-6 py-3 shadow-lg" style="background:#10b6d7; color:#fff; font-weight:800;">
                <i class="fab fa-whatsapp me-2"></i> Get Visa Guidance
            </a>
        </div>
    </div>
</section>

<!-- SECTION 3: TRAVEL & TOURISM -->
<section class="py-7 bg-light" id="travel-tourism">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="eyebrow">Offer Category</span>
            <h2 class="display-5 fw-bold" style="color:#033754;">Global Travel &amp; Tourism Offers</h2>
            <p class="lead text-muted">Curated trip types &mdash; tap one to start planning</p>
        </div>
        <div class="row g-3 mb-6">
            <?php
            $travel = [
                ['fa-suitcase','International Holiday Packages','travel-holiday-packages','beach resort with palm trees and pool'],
                ['fa-users','Group Tours','travel-group-tours','tour group of travelers walking city street'],
                ['fa-child','Family Vacations','travel-family-vacations','family with children on vacation together'],
                ['fa-heart','Honeymoon Packages','travel-honeymoon','romantic couple walking on beach at sunset'],
                ['fa-building','Corporate Travel','travel-corporate','business traveler with suitcase at airport'],
                ['fa-gem','Luxury Travel Experiences','travel-luxury','luxury hotel resort infinity pool'],
                ['fa-ship','Cruise Packages','travel-cruise','cruise ship sailing on open ocean'],
                ['fa-mountain','Adventure Tours','travel-adventure','hikers on mountain trail adventure'],
                ['fa-landmark','Cultural &amp; Heritage Tours','travel-cultural-heritage','ancient historical landmark ruins'],
            ];
            foreach ($travel as $i => $t) {
                $wa = 'https://wa.me/233596204991?text=' . rawurlencode("Hi! I'm interested in " . strip_tags($t[1]));
                echo '<div class="col-6 col-md-4 col-lg-3" data-aos="zoom-in" data-aos-delay="' . (($i%4)*70) . '">
                        <a href="' . $wa . '" class="text-decoration-none">
                        <div class="offer-card bg-white rounded-4 h-100">
                            <div class="card-photo-wrap" style="height:120px;">
                                ' . btc_img_tag($t[2], $t[3], strip_tags($t[1]), 400, 260, 400+$i) . '
                                <span class="icon-badge" style="width:32px;height:32px;"><i class="fas ' . $t[0] . '" style="color:#10b6d7;font-size:.85rem;"></i></span>
                            </div>
                            <p class="fw-semibold mb-0 small text-center p-3" style="color:#033754;">' . $t[1] . '</p>
                        </div>
                        </a>
                      </div>';
            }
            ?>
        </div>

        <!-- Destination Spotlight: Dubai -->
        <div class="mb-6">
            <h3 class="fw-bold text-center mb-4" style="color:#10b6d7;" data-aos="fade-up">Destination Spotlight: Dubai, UAE</h3>
            <div class="row g-3">
                <?php
                $dubai = [
                    ['Burj Khalifa','dubai-burj-khalifa','Burj Khalifa skyscraper Dubai skyline'],
                    ['Dubai Mall','dubai-mall','Dubai Mall shopping interior'],
                    ['Museum of the Future','dubai-museum-of-future','Museum of the Future Dubai building'],
                    ['Palm Jumeirah','dubai-palm-jumeirah','Palm Jumeirah aerial island Dubai'],
                    ['Dubai Marina','dubai-marina','Dubai Marina skyline waterfront at night'],
                    ['Desert Safari','dubai-desert-safari','desert safari dunes jeep sunset UAE'],
                    ['Atlantis Aquaventure','dubai-aquaventure','Atlantis Aquaventure waterpark Dubai'],
                    ['Global Village','dubai-global-village','Global Village Dubai cultural park lights'],
                    ['Miracle Garden','dubai-miracle-garden','Dubai Miracle Garden flowers landscape'],
                    ['Dubai Frame','dubai-frame','Dubai Frame landmark structure'],
                    ['Gold Souk','dubai-gold-souk','Gold Souk Dubai market jewellery'],
                    ['Dhow Cruise','dubai-dhow-cruise','traditional dhow boat cruise Dubai creek'],
                    ['IMG Worlds of Adventure','dubai-img-worlds','indoor theme park adventure rides'],
                    ['Ski Dubai','dubai-ski-dubai','Ski Dubai indoor snow slope'],
                ];
                foreach ($dubai as $i => $spot) {
                    echo '<div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="' . (($i%7)*40) . '">
                            <div class="spot-tile">
                                ' . btc_img_tag($spot[1], $spot[2], htmlspecialchars($spot[0]), 400, 300, 500+$i) . '
                                <div class="cap"><i class="fas fa-map-marker-alt" style="color:#89d900;"></i> ' . $spot[0] . '</div>
                            </div>
                          </div>';
                }
                ?>
            </div>
            <div class="text-center mt-4" data-aos="fade-up">
                <a href="https://wa.me/233596204991?text=Hi!%20I'm%20interested%20in%20a%20Dubai%20travel%20package" class="btn-offer"><i class="fab fa-whatsapp"></i> Get a Dubai Offer <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>

        <!-- Other Destinations -->
        <div>
            <h3 class="fw-bold text-center mb-4" style="color:#10b6d7;" data-aos="fade-up">More Destinations to Explore</h3>
            <div class="row g-4">
                <?php
                $destinations = [
                    ['Turkey','Istanbul\'s bazaars, Cappadocia balloon rides, Mediterranean coastlines','dest-turkey','Istanbul Turkey skyline and bazaar'],
                    ['Egypt','The Pyramids of Giza, the Nile, Red Sea diving resorts','dest-egypt','Pyramids of Giza Egypt desert'],
                    ['Malaysia','Kuala Lumpur skyline, Langkawi beaches, rainforest adventures','dest-malaysia','Kuala Lumpur Petronas Towers skyline'],
                    ['Singapore','Marina Bay Sands, Gardens by the Bay, world-class city living','dest-singapore','Marina Bay Sands Singapore skyline'],
                    ['Thailand','Bangkok temples, Phuket beaches, Chiang Mai culture','dest-thailand','Bangkok Thailand temple architecture'],
                    ['Georgia','Tbilisi\'s old town, Caucasus mountain scenery, wine country','dest-georgia','Tbilisi Georgia old town skyline'],
                    ['Azerbaijan','Baku\'s modern skyline, Flame Towers, Caspian coastline','dest-azerbaijan','Baku Azerbaijan Flame Towers skyline'],
                    ['Europe','City breaks and holiday packages across the UK, Germany, Poland, Romania, Croatia and Hungary','dest-europe','European city street architecture'],
                ];
                foreach ($destinations as $i => $d) {
                    $wa = 'https://wa.me/233596204991?text=' . rawurlencode("Hi! I'm interested in a travel package to " . $d[0]);
                    echo '<div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="' . (($i%4)*80) . '">
                            <div class="dest-photo-card shadow-lg h-100">
                                ' . btc_img_tag($d[2], $d[3], htmlspecialchars($d[0]), 500, 600, 600+$i) . '
                                <div class="overlay"></div>
                                <div class="content">
                                    <h6 class="fw-bold mb-1">' . $d[0] . '</h6>
                                    <p class="small mb-3 opacity-90">' . $d[1] . '</p>
                                    <a href="' . $wa . '" class="btn-offer">Get This Offer <i class="fas fa-arrow-right"></i></a>
                                </div>
                            </div>
                          </div>';
                }
                ?>
            </div>
        </div>

        <div class="text-center mt-6" data-aos="fade-up">
            <a href="https://wa.me/233596204991?text=Hi!%20I'm%20interested%20in%20a%20Travel%20%26%20Tourism%20package"
               class="btn btn-lg rounded-pill px-6 py-3 shadow-lg pulse-cta" style="background:#89d900; color:#033754; font-weight:900;">
                <i class="fab fa-whatsapp me-2"></i> Plan My Trip
            </a>
        </div>
    </div>
</section>

<!-- SECTION 4: RELOCATION SERVICES -->
<section class="py-7 bg-white" id="relocation">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="eyebrow">Offer Category</span>
            <h2 class="display-5 fw-bold" style="color:#033754;">Relocation Offers</h2>
            <p class="lead text-muted">Practical, on-the-ground support so your first weeks abroad go smoothly</p>
        </div>
        <div class="row g-4">
            <?php
            $relocation = [
                ['fa-plane-arrival','Airport Pickup','relocation-airport-pickup','airport arrival hall travelers with luggage'],
                ['fa-bed','Accommodation Assistance','relocation-accommodation','modern furnished apartment bedroom'],
                ['fa-file-medical','Travel Insurance Guidance','relocation-insurance','insurance policy document and pen'],
                ['fa-hands-helping','Settlement Support','relocation-settlement','helping hands support assistance'],
                ['fa-compass','Local Orientation','relocation-orientation','city map and compass navigation'],
                ['fa-sim-card','SIM Card Assistance','relocation-sim-card','mobile phone with sim card'],
                ['fa-university','Banking Guidance','relocation-banking','modern bank building exterior'],
                ['fa-bus','Transportation Information','relocation-transport','city public bus transportation'],
            ];
            foreach ($relocation as $i => $r) {
                $wa = 'https://wa.me/233596204991?text=' . rawurlencode("Hi! I need " . strip_tags($r[1]));
                echo '<div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="' . (($i%4)*80) . '">
                        <a href="' . $wa . '" class="text-decoration-none">
                        <div class="offer-card bg-light rounded-4 h-100 text-center">
                            <div class="card-photo-wrap" style="height:110px;">
                                ' . btc_img_tag($r[2], $r[3], strip_tags($r[1]), 380, 260, 700+$i) . '
                                <span class="icon-badge" style="width:32px;height:32px;"><i class="fas ' . $r[0] . '" style="color:#10b6d7;font-size:.85rem;"></i></span>
                            </div>
                            <p class="fw-semibold mb-0 small p-3" style="color:#033754;">' . $r[1] . '</p>
                        </div>
                        </a>
                      </div>';
            }
            ?>
        </div>
        <div class="text-center mt-5" data-aos="fade-up">
            <a href="https://wa.me/233596204991?text=Hi!%20I%20need%20Relocation%20Support"
               class="btn btn-lg rounded-pill px-6 py-3 shadow-lg" style="background:#10b6d7; color:#fff; font-weight:800;">
                <i class="fab fa-whatsapp me-2"></i> Get Relocation Support
            </a>
        </div>
    </div>
</section>

<!-- SECTION 5: GHANA SERVICES -->
<section class="py-7" style="background:#f8f9fa;" id="ghana-services">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="eyebrow">Offer Category</span>
            <h2 class="display-5 fw-bold" style="color:#033754;">Ghana Services</h2>
            <p class="lead text-muted">Local documentation support, handled from application to collection &mdash; every service below has a direct link to get started</p>
        </div>
        <div class="row g-4">
            <?php
            $ghana = [
                ['icon'=>'fa-id-card','title'=>'Ghana Card Processing','desc'=>'Assistance with new applications, corrections and re-collection.','href'=>null,'slug'=>'ghana-id-card','kw'=>'national identification smart card close up'],
                ['icon'=>'fa-passport','title'=>'Passport Assistance','desc'=>'Online booking, document checks and application follow-up.','href'=>'passport_form.php','label'=>'Start Application','slug'=>'ghana-passport','kw'=>'passport booklet document close up'],
                ['icon'=>'fa-file-alt','title'=>'Birth Certificate Assistance','desc'=>'Biometric birth certificate processing via the Births &amp; Deaths Registry.','href'=>'birth_certificate_form.php','label'=>'Start Application','slug'=>'ghana-birth-certificate','kw'=>'official birth certificate document with seal'],
                ['icon'=>'fa-gavel','title'=>'Police Clearance Certificate','desc'=>'Support securing clearance certificates for travel and employment.','href'=>null,'slug'=>'ghana-police-clearance','kw'=>'official certificate document with stamp and seal on paper'],
                ['icon'=>'fa-stamp','title'=>'Document Authentication','desc'=>'Authentication of certificates and records for official use abroad.','href'=>null,'slug'=>'ghana-authentication','kw'=>'official stamp being pressed on document'],
                ['icon'=>'fa-language','title'=>'Document Translation','desc'=>'Certified translation of official documents.','href'=>null,'slug'=>'ghana-translation','kw'=>'dictionary and translation documents'],
                ['icon'=>'fa-signature','title'=>'Affidavits &amp; Notarization','desc'=>'Sworn statements and notarized documents prepared correctly the first time.','href'=>null,'slug'=>'ghana-affidavit','kw'=>'signing legal document signature'],
                ['icon'=>'fa-university','title'=>'Embassy Documentation Support','desc'=>'Guidance preparing documentation for embassy submissions.','href'=>null,'slug'=>'ghana-embassy','kw'=>'embassy building with flag at entrance'],
                ['icon'=>'fa-truck','title'=>'Courier &amp; Document Delivery','desc'=>'Secure delivery of documents locally and internationally.','href'=>null,'slug'=>'ghana-courier','kw'=>'courier delivery van package'],
            ];
            foreach ($ghana as $i => $g) {
                $ctaHref = $g['href'] ?? ('https://wa.me/233596204991?text=' . rawurlencode("Hi! I need help with " . strip_tags($g['title'])));
                $ctaLabel = $g['href'] ? ($g['label'] ?? 'Start Application') : 'Ask About This';
                $ctaIcon = $g['href'] ? 'fa-arrow-right' : 'fa-whatsapp fab';
                echo '<div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="' . (($i%3)*80) . '">
                        <div class="offer-card bg-white shadow-lg rounded-4 h-100 d-flex flex-column">
                            <div class="card-photo-wrap" style="height:140px;">
                                ' . btc_img_tag($g['slug'], $g['kw'], strip_tags($g['title']), 460, 300, 800+$i) . '
                                <span class="icon-badge"><i class="fas ' . $g['icon'] . '" style="color:#89d900;"></i></span>
                            </div>
                            <div class="p-4 d-flex flex-column flex-grow-1">
                                <h5 class="fw-bold mb-2" style="color:#033754;">' . $g['title'] . '</h5>
                                <p class="text-muted small flex-grow-1">' . $g['desc'] . '</p>
                                <a href="' . $ctaHref . '" class="btn btn-sm btn-outline-primary rounded-pill mt-2 align-self-start">' . $ctaLabel . ' <i class="fas ' . $ctaIcon . ' ms-1"></i></a>
                            </div>
                        </div>
                      </div>';
            }
            ?>
        </div>
        <div class="text-center mt-5" data-aos="fade-up">
            <a href="https://wa.me/233596204991?text=Hi!%20I%20need%20a%20Ghana%20Service"
               class="btn btn-lg rounded-pill px-6 py-3 shadow-lg" style="background:#89d900; color:#033754; font-weight:900;">
                <i class="fab fa-whatsapp me-2"></i> Ask About Ghana Services
            </a>
        </div>
    </div>
</section>

<!-- SECTION 6: TRAINING & PROFESSIONAL COURSES -->
<section class="py-7 bg-white" id="training-courses">
    <div class="container">
        <div class="text-center mb-6" data-aos="fade-up">
            <span class="eyebrow">Offer Category</span>
            <h2 class="display-5 fw-bold" style="color:#033754;">Professional &amp; Aviation Training Offers</h2>
            <p class="lead text-muted">
                Certified training that strengthens a candidate's profile ahead of recruitment or relocation.
                Availability, eligibility and pricing vary by destination and course &mdash; ask your advisor for
                the current options for your destination of interest.
            </p>
        </div>

        <div class="mb-6">
            <h3 class="fw-bold mb-4 text-center" style="color:#10b6d7;" data-aos="fade-up">Featured Courses</h3>
            <div class="row g-4">
                <?php
                $featured = [
                    ['title'=>'Aviation Management','desc'=>'3&ndash;4 week course. Attested certificate and job placement assistance.','slug'=>'training-aviation-mgmt','kw'=>'commercial airplane on runway aviation'],
                    ['title'=>'Airport Operations Course','desc'=>'2 week course. Approved &amp; attested certificate, plus free CV editing.','slug'=>'training-airport-ops','kw'=>'airport terminal departures interior'],
                    ['title'=>'Air Cargo Marketing Basics','desc'=>'1 week intensive. Professional training with certificate of participation.','slug'=>'training-air-cargo','kw'=>'air cargo freight logistics airplane'],
                ];
                foreach ($featured as $i => $f) {
                    $wa = 'https://wa.me/233596204991?text=' . rawurlencode("Hi! I'm interested in " . strip_tags($f['title']) . " Course");
                    echo '<div class="col-md-4" data-aos="fade-up" data-aos-delay="' . ($i*100) . '">
                            <div class="offer-card bg-light shadow-lg rounded-4 h-100 d-flex flex-column">
                                <div class="card-photo-wrap" style="height:170px;">
                                    ' . btc_img_tag($f['slug'], $f['kw'], strip_tags($f['title']), 500, 340, 900+$i) . '
                                </div>
                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <h4 class="fw-bold" style="color:#033754;">' . $f['title'] . '</h4>
                                    <p class="text-muted flex-grow-1">' . $f['desc'] . '</p>
                                    <a href="' . $wa . '" class="btn btn-outline-primary rounded-pill w-100">Ask for Details</a>
                                </div>
                            </div>
                          </div>';
                }
                ?>
            </div>
        </div>

        <div class="row g-4">
            <?php
            $courseColumns = [
                ['Fashion Merchandising','FMCG Retail Merchandising','Train The Trainer','Hotel Management'],
                ['Healthcare Marketing','Real Estate Marketing','Luxury Brand Management','Business Events Management'],
                ['Aircraft Sales &amp; Leasing','Airline &amp; Airport Management Fundamentals','Airport Business Management','Women in Aviation Leadership'],
            ];
            foreach ($courseColumns as $ci => $col) {
                echo '<div class="col-md-4" data-aos="fade-up" data-aos-delay="' . ($ci*80) . '"><ul class="list-group list-group-flush fs-6">';
                foreach ($col as $course) {
                    $wa = 'https://wa.me/233596204991?text=' . rawurlencode("Hi! I'm interested in the " . strip_tags($course) . " course");
                    echo '<li class="list-group-item p-0">
                            <a href="' . $wa . '" class="offer-row text-decoration-none py-3 px-2 d-flex">
                                <span><i class="fas fa-graduation-cap me-2" style="color:#10b6d7;"></i>' . $course . '</span>
                                <i class="fab fa-whatsapp row-cta"></i>
                            </a>
                          </li>';
                }
                echo '</ul></div>';
            }
            ?>
        </div>

        <div class="text-center mt-5" data-aos="fade-up">
            <div class="d-flex justify-content-center flex-wrap gap-3 mb-4">
                <span class="badge bg-primary fs-6 px-4 py-3 rounded-pill">Attested Certificate</span>
                <span class="badge bg-success fs-6 px-4 py-3 rounded-pill">Internship</span>
                <span class="badge bg-info fs-6 px-4 py-3 rounded-pill">Job Placement</span>
            </div>
            <a href="https://wa.me/233596204991?text=Hi!%20I'd%20like%20course%20details%20and%20fees"
               class="btn btn-lg rounded-pill px-6 py-3 shadow-lg" style="background:#89d900; color:#033754; font-weight:900;">
                <i class="fab fa-whatsapp me-2"></i> Ask About Course Fees
            </a>
        </div>
    </div>
</section>



<!-- Persistent floating WhatsApp button -->
<a href="https://wa.me/233596204991?text=Hi!%20I'd%20like%20to%20know%20more%20about%20your%20services" class="floating-wa" aria-label="Chat with an advisor on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<?php include 'includes/footer.php'; ?>