<?php include 'includes/header.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@600;700;800;900&family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400;1,6..72,500&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<style>
    /* ===========================================================
       ABOUT PAGE — DESIGN TOKENS
       Visual identity: the travel document itself — boarding
       passes, departure boards, passport stamps. Concrete objects
       from the client's own world, not decoration borrowed from
       elsewhere.
       =========================================================== */
    :root {
        --navy:        #06304a;
        --navy-soft:   #0a6c8a;
        --ink:         #0b1c26;
        --blue:        #10b6d7;
        --green:       #7cc300;
        --gold:        #c99a2e;
        --cream:       #f6f2e7;
        --cream-line:  #e4dcc8;
        --paper:       #fffdf8;
        --font-display:'Big Shoulders Display', sans-serif;
        --font-body:   'Newsreader', Georgia, serif;
        --font-mono:   'IBM Plex Mono', 'Courier New', monospace;
        --ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
        --section-pad: clamp(3.5rem, 6vw, 7rem);
    }

    body { padding-top: 90px; }
    @media (max-width: 991.98px) { body { padding-top: 70px; } }
    html { scroll-padding-top: 100px; }

    .about-page { font-family: var(--font-body); color: var(--ink); }
    .about-page h1, .about-page h2, .about-page h3, .about-page h4 { font-family: var(--font-display); letter-spacing: .3px; }
    .about-page .eyebrow {
        font-family: var(--font-mono);
        font-weight: 600;
        letter-spacing: 3px;
        text-transform: uppercase;
        font-size: .78rem;
    }
    .about-page a:focus-visible,
    .about-page button:focus-visible {
        outline: 3px solid var(--blue);
        outline-offset: 3px;
    }

    .about-section { padding-block: var(--section-pad); }
    .about-section.bg-paper  { background: var(--paper); }
    .about-section.bg-cream  { background: var(--cream); }
    .about-section.bg-ink    { background: var(--ink); }
    .about-section.bg-navy-gradient {
        background: linear-gradient(150deg, var(--navy) 0%, var(--navy-soft) 100%);
    }
    .about-section.bg-navy { background: var(--navy); }

    /* ===========================================================
       MOTION SYSTEM — opt in via [data-reveal]; respects
       prefers-reduced-motion at the source.
       =========================================================== */
    @media (prefers-reduced-motion: no-preference) {
        [data-reveal] {
            opacity: 0;
            transform: translateY(26px);
            transition: opacity .8s var(--ease-out-expo), transform .8s var(--ease-out-expo);
            transition-delay: calc(var(--reveal-delay, 0) * 1ms);
        }
        [data-reveal].is-visible { opacity: 1; transform: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        [data-reveal] { opacity: 1 !important; transform: none !important; }
        .route-line::before, .flap-tile { animation: none !important; }
    }

    /* ===========================================================
       ROUTE LINE — a quiet flight path stitched down the page,
       tying the sections together. Deliberately restrained; the
       boarding pass and stamp carry the visual weight.
       =========================================================== */
    .route-line { position: relative; }
    .route-line::before {
        content: "";
        position: absolute;
        left: 50%; top: 0;
        width: 1px; height: 100%;
        background: repeating-linear-gradient(to bottom, var(--gold) 0 6px, transparent 6px 14px);
        transform: translateX(-50%) scaleY(0);
        transform-origin: top;
        opacity: .35;
        z-index: 0;
    }
    .route-line.is-visible::before {
        transform: translateX(-50%) scaleY(1);
        transition: transform 1.8s var(--ease-out-expo);
    }
    @media (max-width: 767.98px) { .route-line::before { display: none; } }

    /* ===========================================================
       BUTTONS
       =========================================================== */
    .btn-btc-primary {
        background: var(--navy); color: #fff; font-weight: 700;
        font-family: var(--font-mono); font-size: .95rem; letter-spacing: .5px;
        transition: transform .25s var(--ease-out-expo), box-shadow .25s var(--ease-out-expo);
    }
    .btn-btc-primary:hover { transform: translateY(-3px); box-shadow: 0 .75rem 1.5rem rgba(6,48,74,.25); color:#fff; }
    .btn-btc-whatsapp {
        background: var(--green); color: var(--ink); font-weight: 700;
        font-family: var(--font-mono); font-size: .95rem; letter-spacing: .5px;
        transition: transform .25s var(--ease-out-expo), box-shadow .25s var(--ease-out-expo);
        animation: pulse-glow 3.5s ease-in-out infinite;
    }
    .btn-btc-whatsapp:hover { transform: translateY(-3px); box-shadow: 0 .75rem 1.5rem rgba(124,195,0,.35); color: var(--ink); }
    @keyframes pulse-glow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(124,195,0,.35); }
        50% { box-shadow: 0 0 0 10px rgba(124,195,0,0); }
    }
    @media (prefers-reduced-motion: reduce) { .btn-btc-whatsapp { animation: none; } }

    /* ===========================================================
       OPENING — copy left, boarding pass right
       =========================================================== */
    .opening-headline {
        font-weight: 800;
        color: var(--navy);
        line-height: 1.05;
        font-size: clamp(2.4rem, 5vw, 4rem);
        text-transform: uppercase;
    }
    .opening-headline em {
        font-style: normal;
        font-family: var(--font-body);
        font-weight: 500;
        font-style: italic;
        text-transform: none;
        display: block;
        color: var(--navy-soft);
        font-size: .42em;
        letter-spacing: 0;
        margin-top: .3em;
    }
    .opening-lede {
        font-size: clamp(1.1rem, 1.4vw, 1.3rem);
        color: #3c4a52;
        max-width: 48ch;
        font-style: italic;
    }

    /* Boarding pass card — the signature element */
    .boarding-pass {
        position: relative;
        background: var(--paper);
        border-radius: 1.1rem;
        box-shadow: 0 2rem 4rem rgba(6,48,74,.18);
        overflow: hidden;
        max-width: 420px;
        margin-inline: auto;
        transform: rotate(2deg);
        transition: transform .5s var(--ease-out-expo);
    }
    .boarding-pass:hover { transform: rotate(0deg); }
    .bp-strip { height: 10px; background: repeating-linear-gradient(-45deg, var(--blue) 0 14px, var(--navy) 14px 28px); }
    .bp-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1.1rem 1.4rem 0;
    }
    .bp-logo {
        font-family: var(--font-display); font-weight: 800; font-size: 1.4rem;
        color: var(--navy); letter-spacing: 1px;
        border: 2px solid var(--navy); border-radius: 50%;
        width: 2.6rem; height: 2.6rem; display: inline-flex; align-items: center; justify-content: center;
    }
    .bp-doc { font-family: var(--font-mono); font-size: .72rem; letter-spacing: 2.5px; color: #6a7780; text-transform: uppercase; }
    .bp-photo { margin: 1.1rem 1.4rem 0; border-radius: .6rem; overflow: hidden; position: relative; }
    .bp-photo img { width: 100%; height: 230px; object-fit: cover; display: block; filter: saturate(1.05); }
    .bp-photo::after {
        content: "";
        position: absolute; inset: 0;
        background: linear-gradient(200deg, transparent 50%, rgba(6,48,74,.5));
    }
    .bp-fields {
        display: grid; grid-template-columns: 1fr 1fr; gap: .9rem 1rem;
        padding: 1.1rem 1.4rem;
    }
    .bp-field span {
        display: block; font-family: var(--font-mono); font-size: .65rem;
        letter-spacing: 1.5px; color: #8b968d; text-transform: uppercase;
    }
    .bp-field strong { font-family: var(--font-mono); font-size: 1rem; color: var(--navy); font-weight: 600; }
    .bp-field strong.ok { color: var(--green); }
    .bp-perf {
        position: relative;
        border-top: 2px dashed var(--cream-line);
        margin: 0 1.4rem;
    }
    .bp-perf::before, .bp-perf::after {
        content: ""; position: absolute; top: -10px; width: 20px; height: 20px;
        border-radius: 50%; background: var(--cream);
    }
    .bp-perf::before { left: -1.9rem; }
    .bp-perf::after { right: -1.9rem; }
    .bp-stub {
        display: flex; align-items: baseline; justify-content: space-between; gap: .75rem;
        padding: 1.1rem 1.4rem 1.4rem;
    }
    .bp-stub strong { font-family: var(--font-display); font-size: 2rem; font-weight: 800; color: var(--navy); }
    .bp-stub span { font-family: var(--font-mono); font-size: .68rem; letter-spacing: 1px; color: #6a7780; max-width: 15ch; text-align: right; }

    /* ===========================================================
       OUR STORY — passport-stamp accent on the image
       =========================================================== */
    .story-photo-wrap { position: relative; }
    .story-photo-wrap img {
        width: 100%; border-radius: 1rem; box-shadow: 0 1.5rem 3rem rgba(6,48,74,.16);
        max-height: 520px; object-fit: cover;
    }
    .story-stamp {
        position: absolute;
        width: clamp(120px, 16vw, 170px); height: auto;
        color: var(--gold);
        mix-blend-mode: multiply;
        opacity: .92;
        transform: rotate(-14deg);
        top: -1.5rem; right: -1.2rem;
        pointer-events: none;
    }
    @media (max-width: 767.98px) { .story-stamp { top: -1rem; right: .5rem; width: 110px; } }

    .story-pull {
        font-family: var(--font-body); font-style: italic; font-size: 1.5rem;
        color: var(--navy); border-left: 3px solid var(--gold); padding-left: 1.2rem;
        margin: 1.8rem 0;
    }

    /* ===========================================================
       MOTTO
       =========================================================== */
    .motto-banner { border-radius: 1.5rem; position: relative; overflow: hidden; }
    .motto-banner::after {
        content: ""; position: absolute; inset: -40% -10% auto -10%; height: 220%;
        background: radial-gradient(circle, rgba(255,255,255,.08), transparent 60%);
        animation: drift 12s ease-in-out infinite;
    }
    @keyframes drift { 0%,100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(4%) rotate(6deg); } }
    @media (prefers-reduced-motion: reduce) { .motto-banner::after { animation: none; } }
    .motto-banner h2 { font-size: clamp(1.8rem, 4vw, 3rem); font-style: italic; font-family: var(--font-body); font-weight: 500; }

    /* ===========================================================
       DEPARTURE BOARD — accomplishments, split-flap styling
       =========================================================== */
    .board-panel { background: var(--ink); border-radius: 1.25rem; padding: clamp(1.5rem, 4vw, 3rem); }
    .board-row-label {
        font-family: var(--font-mono); color: #7f9aa8; font-size: .72rem;
        letter-spacing: 2px; text-transform: uppercase; display: flex; align-items: center; gap: .6rem;
    }
    .board-row-label::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--green); box-shadow: 0 0 8px var(--green); }
    .flap-tile {
        border: 1px solid rgba(255,255,255,.08);
        border-radius: .6rem;
        background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(255,255,255,0));
        padding: 1.6rem 1.2rem;
        height: 100%;
    }
    @media (prefers-reduced-motion: no-preference) {
        .flap-tile { transform-origin: top center; }
        .flap-tile.is-visible { animation: flap-in .6s var(--ease-out-expo) both; }
    }
    @keyframes flap-in { from { transform: rotateX(60deg); opacity: 0; } to { transform: rotateX(0deg); opacity: 1; } }
    .flap-tile .stat-count {
        font-family: var(--font-mono); font-weight: 600; font-size: clamp(2.2rem, 4vw, 3rem);
        color: var(--paper); letter-spacing: 1px;
    }
    .flap-tile p.flap-label { color: #cdd8dd; font-weight: 600; margin-bottom: .15rem; font-family: var(--font-body); font-size: 1.05rem; }
    .flap-tile p.flap-sub { color: #7f9aa8; font-family: var(--font-mono); font-size: .72rem; letter-spacing: .5px; margin-bottom: 0; }

    /* ===========================================================
       VISION / MISSION / VALUES
       =========================================================== */
    .principle-card {
        border: 1px solid var(--cream-line);
        border-radius: 1.25rem;
        background: var(--paper);
        padding: 2.6rem 2.2rem;
        height: 100%;
        transition: transform .35s var(--ease-out-expo), box-shadow .35s var(--ease-out-expo);
    }
    .principle-card:hover { transform: translateY(-8px); box-shadow: 0 1.5rem 3rem rgba(6,48,74,.12); }
    .principle-num {
        font-family: var(--font-mono); font-size: .75rem; letter-spacing: 2px;
        color: var(--gold); display: block; margin-bottom: .9rem;
    }
    .principle-card i { color: var(--blue); }

    /* ===========================================================
       HOW WE VALUE CLIENTS
       =========================================================== */
    .value-tile {
        background: var(--paper); border: 1px solid var(--cream-line);
        border-radius: 1rem;
        transition: transform .3s var(--ease-out-expo), box-shadow .3s var(--ease-out-expo);
    }
    .value-tile:hover { transform: translateY(-6px); box-shadow: 0 1rem 2rem rgba(6,48,74,.1); }

    /* ===========================================================
       COUNTRIES — flight-departure listing
       =========================================================== */
    .region-label {
        font-family: var(--font-mono); color: var(--gold); letter-spacing: 2.5px;
        font-size: .72rem; text-transform: uppercase; margin-bottom: 1rem; display: block;
    }
    .dest-row {
        display: flex; align-items: baseline; gap: .5rem;
        padding: .55rem 0;
        border-bottom: 1px solid rgba(255,255,255,.1);
        font-family: var(--font-body); font-size: 1.05rem;
    }
    .dest-row .dest-name { white-space: nowrap; }
    .dest-row .dest-leader { flex: 1; border-bottom: 1px dotted rgba(255,255,255,.3); transform: translateY(-.3rem); }
    .dest-row .dest-status { font-family: var(--font-mono); font-size: .7rem; letter-spacing: 1.5px; color: var(--green); white-space: nowrap; }

    /* ===========================================================
       BRANCHES
       =========================================================== */
    .branch-card {
        border: 1px solid rgba(255,255,255,.12);
        border-radius: 1rem;
        padding: 1.75rem 1.25rem;
        transition: transform .3s var(--ease-out-expo), background .3s var(--ease-out-expo);
        height: 100%;
    }
    .branch-card:hover { transform: translateY(-4px); background: rgba(255,255,255,.05); }
    .branch-tag { font-family: var(--font-mono); font-size: .68rem; letter-spacing: 2px; color: var(--gold); text-transform: uppercase; }

    /* ===========================================================
       FINAL CTA
       =========================================================== */
    .final-cta-btn {
        font-family: var(--font-mono);
        transition: transform .3s var(--ease-out-expo), box-shadow .3s var(--ease-out-expo);
        animation: pulse-glow-strong 3s ease-in-out infinite;
    }
    .final-cta-btn:hover { transform: translateY(-4px) scale(1.02); }
    @keyframes pulse-glow-strong {
        0%, 100% { box-shadow: 0 .5rem 1.5rem rgba(124,195,0,.25); }
        50% { box-shadow: 0 .5rem 2.5rem rgba(124,195,0,.5); }
    }
    @media (prefers-reduced-motion: reduce) { .final-cta-btn { animation: none; } }

    @media (max-width: 767.98px) {
        .about-section { padding-block: clamp(2.5rem, 8vw, 4rem); }
        .boarding-pass { margin-top: 2.5rem; transform: rotate(0deg); }
    }
</style>

<div class="about-page">

<!-- OPENING -->
<section class="about-section bg-cream">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6" data-reveal>
                <p class="eyebrow gold-text mb-3" style="color:var(--gold);">
                    <span style="display:inline-block;width:28px;height:2px;background:var(--gold);vertical-align:middle;margin-right:.6rem;"></span>
                    Since 2017 &middot; Ghana to the World
                </p>
                <h1 class="opening-headline mb-4">
                    Somewhere out there
                    <em>your next chapter is waiting for you to show up.</em>
                </h1>
                <p class="opening-lede mb-4">
                    A visa approved. A flight booked. A phone call home that starts with
                    "I made it." For eight years, that moment has been our entire business —
                    we just call it Tuesday.
                </p>
                <p class="fs-5 mb-4" style="color:#2e3a41;">
                    Believe Travel Consult exists for the people behind the paperwork: the
                    job seeker supporting a family, the student chasing a degree abroad, the
                    professional ready to build a life on new ground. We handle the
                    documentation, the logistics, and the details — so the only thing left
                    for you to feel is the excitement.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="contact.php" class="btn btn-lg rounded-pill px-4 py-3 btn-btc-primary">
                        Start Your Journey <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                    <a href="https://wa.me/233596204991?text=Hello%2C%20I'd%20like%20to%20learn%20more%20about%20Believe%20Travel%20Consult"
                       class="btn btn-lg rounded-pill px-4 py-3 btn-btc-whatsapp">
                        <i class="fab fa-whatsapp fa-lg me-2"></i> Chat With Us
                    </a>
                </div>
            </div>
            <div class="col-lg-6" data-reveal style="--reveal-delay:150">
                <div class="boarding-pass">
                    <div class="bp-strip"></div>
                    <div class="bp-header">
                        <span class="bp-logo">BTC</span>
                        <span class="bp-doc">Boarding Pass</span>
                    </div>
                    <div class="bp-photo">
                        <img src="assets/images/about_handshake.jpg" alt="A client and consultant shaking hands after a successful placement">
                    </div>
                    <div class="bp-fields">
                        <div class="bp-field"><span>Passenger</span><strong>You</strong></div>
                        <div class="bp-field"><span>Status</span><strong class="ok">Confirmed</strong></div>
                        <div class="bp-field"><span>From</span><strong>Accra, GH</strong></div>
                        <div class="bp-field"><span>To</span><strong>Anywhere</strong></div>
                    </div>
                    <div class="bp-perf"></div>
                    <div class="bp-stub">
                        <strong>1,500+</strong>
                        <span>Lives relocated since 2017</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="route-line" data-reveal-parent>

<!-- OUR STORY -->
<section class="about-section bg-paper">
    <div class="container">
        <div class="row align-items-center g-6">
            <div class="col-lg-6" data-reveal>
                <p class="eyebrow mb-2" style="color:var(--gold);">Our Story</p>
                <h2 class="display-6 fw-bold mb-4" style="color:var(--navy); text-transform:uppercase;">
                    Empowering Dreams
                </h2>
                <p class="lead mb-4" style="color:#556069;">
                    For more than eight years, Believe Travel Consult has grown steadily from
                    strength to strength, establishing itself as a respectable, premium brand
                    in international travel, recruitment, and relocation consultancy.
                </p>
                <p class="fs-5 mb-4" style="color:#2e3a41;">
                    What began in 2017 as a Ghana-based travel and employment consultancy has
                    grown into an international gateway connecting clients to opportunities
                    across the Gulf, Europe, North America, and Asia. Our success is built on
                    the concerted efforts and hard work of our experienced, dynamic, and
                    dedicated team. We've successfully conducted over 150 premium activities,
                    including recruitment drives, interview preparations, corporate training,
                    and professional workshops, benefiting more than 35 leading companies
                    across multiple industries.
                </p>
                <p class="story-pull">
                    "As a premium service provider, we specialize in seamless international
                    relocations — from direct employment placement to 1-day Ghana Card
                    processing to airport meet-and-greet on arrival."
                </p>
                <p class="fs-5 mb-5" style="color:#2e3a41;">
                    With branches in Tema Golf City, Kumasi Ahodwo, and Kasoa Adam Nana, we
                    remain firmly rooted in Ghana while operating with an international
                    outlook — committed to reliability, exceptional value for money, quality,
                    and fairness in every interaction.
                </p>
                <a href="contact.php" class="btn btn-lg rounded-pill px-5 py-3 btn-btc-primary">
                    Contact Our Experts <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
            <div class="col-lg-6" data-reveal style="--reveal-delay:150">
                <div class="story-photo-wrap">
                    <img src="assets/images/booking.jpeg" alt="Premium partnership and trust">
                    <svg class="story-stamp" viewBox="0 0 200 200" aria-hidden="true">
                        <defs>
                            <path id="stampTop" d="M 20,100 A 80,80 0 0 1 180,100" />
                            <path id="stampBottom" d="M 30,120 A 70,70 0 0 0 170,120" />
                        </defs>
                        <circle cx="100" cy="100" r="88" fill="none" stroke="currentColor" stroke-width="3"/>
                        <circle cx="100" cy="100" r="78" fill="none" stroke="currentColor" stroke-width="1.5"/>
                        <text font-size="13" letter-spacing="3" fill="currentColor">
                            <textPath href="#stampTop" startOffset="50%" text-anchor="middle">BELIEVE TRAVEL CONSULT</textPath>
                        </text>
                        <text font-size="12" letter-spacing="2" fill="currentColor">
                            <textPath href="#stampBottom" startOffset="50%" text-anchor="middle">GHANA &#183; EST 2017</textPath>
                        </text>
                        <text x="100" y="108" font-family="'Big Shoulders Display', sans-serif" font-weight="800" font-size="30" fill="currentColor" text-anchor="middle">APPROVED</text>
                    </svg>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- OUR MOTTO -->
<section class="about-section bg-paper pt-0">
    <div class="container">
        <div class="motto-banner bg-navy-gradient text-white text-center p-5 p-md-6" data-reveal>
            <i class="fas fa-quote-left fa-2x mb-3 position-relative" style="color:var(--gold); z-index:1;"></i>
            <h2 class="mb-2 position-relative" style="z-index:1;">"Your Dreams. Our Gateway."</h2>
            <p class="lead mb-0 opacity-90 position-relative" style="z-index:1;">
                Every journey we support — whether across town or across continents — is
                guided by one simple promise: your dream is our responsibility until it
                becomes your reality.
            </p>
        </div>
    </div>
</section>

<!-- WHAT WE'VE ACCOMPLISHED — DEPARTURE BOARD -->
<section class="about-section bg-ink">
    <div class="container">
        <div class="text-center mb-6" data-reveal>
            <p class="eyebrow mb-2" style="color:var(--gold);">Our Track Record</p>
            <h2 class="display-6 fw-bold text-white">What We've Accomplished</h2>
            <p class="lead" style="color:#9fb2bb;">Eight years of measurable results, built one client relationship at a time.</p>
        </div>
        <div class="board-row-label justify-content-center mb-4 d-flex" data-reveal>Live Status Board</div>
        <div class="row g-4" id="stats-grid">
            <div class="col-md-6 col-lg-3" data-reveal>
                <div class="flap-tile">
                    <div class="stat-count" data-target="8" data-suffix="+">0</div>
                    <p class="flap-label mt-2">Years in Operation</p>
                    <p class="flap-sub">Serving clients since 2017</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-reveal style="--reveal-delay:100">
                <div class="flap-tile">
                    <div class="stat-count" data-target="1500" data-suffix="+">0</div>
                    <p class="flap-label mt-2">Clients Placed &amp; Relocated</p>
                    <p class="flap-sub">Across multiple destinations</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-reveal style="--reveal-delay:200">
                <div class="flap-tile">
                    <div class="stat-count" data-target="150" data-suffix="+">0</div>
                    <p class="flap-label mt-2">Drives &amp; Workshops</p>
                    <p class="flap-sub">Interview prep &amp; corporate training</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3" data-reveal style="--reveal-delay:300">
                <div class="flap-tile">
                    <div class="stat-count" data-target="35" data-suffix="+">0</div>
                    <p class="flap-label mt-2">Partner Companies</p>
                    <p class="flap-sub">Hospitality, construction, health &amp; more</p>
                </div>
            </div>
        </div>
        <div class="row mt-5">
            <div class="col-lg-8 mx-auto text-center" data-reveal>
                <p class="fs-5" style="color:#cdd8dd;">
                    We've also earned a <strong style="color:#fff;">98% visa and employment success rate</strong>,
                    opened three branches across Ghana — Tema Golf City, Kumasi Ahodwo, and
                    Kasoa Adam Nana — and built a growing network of trusted recruitment and
                    travel partners in the Gulf, Europe, and beyond.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- VISION / MISSION / VALUES -->
<section class="about-section bg-paper">
    <div class="container">
        <div class="text-center mb-6" data-reveal>
            <p class="eyebrow mb-2" style="color:var(--gold);">Guiding Principles</p>
            <h2 class="display-6 fw-bold" style="color:var(--navy);">Our Vision, Mission &amp; Values</h2>
        </div>
        <div class="row g-5">
            <div class="col-lg-4" data-reveal>
                <div class="principle-card text-center">
                    <span class="principle-num">I.</span>
                    <i class="fas fa-eye fa-3x mb-4"></i>
                    <h3 class="fw-bold mb-3" style="color:var(--navy);">Vision</h3>
                    <p style="color:#556069;" class="fs-5">To emerge as West Africa's leading one-stop global
                        travel, recruitment, and relocation consultancy, bringing memorable and
                        transformative experiences to our clients. We envision a world where every
                        client — whether pursuing work, education, tourism, or a new life abroad —
                        receives a full spectrum of tailored, high-end services under one roof, ensuring
                        seamless transitions and lifelong success.</p>
                </div>
            </div>
            <div class="col-lg-4" data-reveal style="--reveal-delay:150">
                <div class="principle-card text-center">
                    <span class="principle-num">II.</span>
                    <i class="fas fa-handshake fa-3x mb-4" style="color:var(--green);"></i>
                    <h3 class="fw-bold mb-3" style="color:var(--navy);">Mission</h3>
                    <p style="color:#556069;" class="fs-5">To forge close, enduring relationships with our clients
                        while continuously innovating to provide flexible, transparent solutions
                        devoted to resolving their unique challenges. We are dedicated to delivering
                        true personalized service, ensuring client satisfaction through premium
                        assurance, efficiency, and a client-first approach that turns travel and
                        relocation dreams into reality — anywhere in the world.</p>
                </div>
            </div>
            <div class="col-lg-4" data-reveal style="--reveal-delay:300">
                <div class="principle-card text-center">
                    <span class="principle-num">III.</span>
                    <i class="fas fa-heart fa-3x mb-4" style="color:var(--navy);"></i>
                    <h3 class="fw-bold mb-3" style="color:var(--navy);">Values</h3>
                    <ul class="list-unstyled text-start mx-auto" style="max-width:280px; line-height:1.9; color:#2e3a41;">
                        <li class="mb-2"><i class="fas fa-check me-2" style="color:var(--gold);"></i> <strong>Consistency:</strong> Reliable, premium service every time.</li>
                        <li class="mb-2"><i class="fas fa-check me-2" style="color:var(--gold);"></i> <strong>Initiative:</strong> Proactively innovating for client success.</li>
                        <li class="mb-2"><i class="fas fa-check me-2" style="color:var(--gold);"></i> <strong>Teamwork:</strong> Collaborating for exceptional results.</li>
                        <li class="mb-2"><i class="fas fa-check me-2" style="color:var(--gold);"></i> <strong>Passion:</strong> Driven by genuine care for our clients.</li>
                        <li class="mb-2"><i class="fas fa-check me-2" style="color:var(--gold);"></i> <strong>Integrity:</strong> Upholding honesty and ethical standards.</li>
                        <li class="mb-2"><i class="fas fa-check me-2" style="color:var(--gold);"></i> <strong>Efficiency:</strong> Streamlining processes for swift outcomes.</li>
                        <li><i class="fas fa-check me-2" style="color:var(--gold);"></i> <strong>Responsibility:</strong> Committed to sustainable, impactful service.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- HOW WE VALUE OUR CLIENTS -->
<section class="about-section bg-cream">
    <div class="container">
        <div class="row align-items-center g-6">
            <div class="col-lg-6" data-reveal>
                <p class="eyebrow mb-2" style="color:var(--gold);">Our Commitment</p>
                <h2 class="display-6 fw-bold mb-4" style="color:var(--navy);">How We Value Our Clients</h2>
                <p class="fs-5 mb-4" style="color:#2e3a41;">
                    We know that behind every application, every visa, and every ticket is a real
                    person with real hopes — a job seeker supporting their family, a student chasing
                    an education abroad, a family planning the trip of a lifetime, or a business
                    coordinating travel for its team. We treat every client relationship with that
                    weight of responsibility.
                </p>
                <p class="fs-5 mb-0" style="color:#2e3a41;">
                    That means clear communication at every stage, honest guidance even when it isn't
                    what a client hopes to hear, fair and transparent pricing with no hidden costs, and
                    support that doesn't end once a visa is approved or a ticket is booked — from
                    airport pickup to settling in at your destination, we stay with you.
                </p>
            </div>
            <div class="col-lg-6" data-reveal style="--reveal-delay:150">
                <div class="row g-4">
                    <div class="col-6">
                        <div class="value-tile shadow-sm p-4 h-100 text-center">
                            <i class="fas fa-comments fa-2x mb-2" style="color:var(--blue);"></i>
                            <h6 class="fw-bold mb-1" style="color:var(--navy);">Clear Communication</h6>
                            <p class="text-muted small mb-0">Regular updates, no guesswork</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="value-tile shadow-sm p-4 h-100 text-center">
                            <i class="fas fa-balance-scale fa-2x mb-2" style="color:var(--green);"></i>
                            <h6 class="fw-bold mb-1" style="color:var(--navy);">Fair Pricing</h6>
                            <p class="text-muted small mb-0">Transparent costs, no surprises</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="value-tile shadow-sm p-4 h-100 text-center">
                            <i class="fas fa-user-shield fa-2x mb-2" style="color:var(--gold);"></i>
                            <h6 class="fw-bold mb-1" style="color:var(--navy);">Honest Guidance</h6>
                            <p class="text-muted small mb-0">Advice that protects your interests</p>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="value-tile shadow-sm p-4 h-100 text-center">
                            <i class="fas fa-hands-helping fa-2x mb-2" style="color:var(--navy);"></i>
                            <h6 class="fw-bold mb-1" style="color:var(--navy);">Ongoing Support</h6>
                            <p class="text-muted small mb-0">From booking to settling in</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- COUNTRIES WE SERVE — DEPARTURE LISTING -->
<section class="about-section bg-navy-gradient">
    <div class="container text-white">
        <div class="text-center mb-6">
            <p class="eyebrow mb-2" data-reveal style="color:var(--gold);">Our Global Reach</p>
            <h2 class="display-6 fw-bold mb-3" data-reveal>Countries We Serve</h2>
            <p class="lead opacity-90" data-reveal>Our destination network continues to grow across the Gulf,
                Europe, the Americas, and Asia.</p>
        </div>

        <div class="row g-5">
            <div class="col-md-4" data-reveal>
                <span class="region-label">Gulf</span>
                <div class="dest-row"><span class="dest-name">United Arab Emirates</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Saudi Arabia</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Qatar</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Kuwait</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Oman</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Bahrain</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
            </div>
            <div class="col-md-4" data-reveal style="--reveal-delay:100">
                <span class="region-label">Europe</span>
                <div class="dest-row"><span class="dest-name">United Kingdom</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Germany</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Poland</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Romania</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Croatia</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Hungary</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
            </div>
            <div class="col-md-4" data-reveal style="--reveal-delay:200">
                <span class="region-label">Americas &amp; Asia</span>
                <div class="dest-row"><span class="dest-name">Canada</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Australia</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Turkey</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Malaysia</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Singapore</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Thailand</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Egypt</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Georgia</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
                <div class="dest-row"><span class="dest-name">Azerbaijan</span><span class="dest-leader"></span><span class="dest-status">Open</span></div>
            </div>
        </div>
        <p class="text-center mt-5 mb-0" data-reveal style="font-family:var(--font-mono); font-size:.78rem; letter-spacing:1.5px; color:#9fb2bb;">
            + More destinations added regularly
        </p>
    </div>
</section>

<!-- BRANCHES -->
<section class="about-section bg-navy text-white text-center">
    <div class="container">
        <p class="eyebrow mb-2" data-reveal style="color:var(--gold);">Find Us</p>
        <h3 class="fw-bold mb-5" data-reveal>Visit Us At Any Of Our Ghana Branches</h3>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4" data-reveal>
                <div class="branch-card">
                    <span class="branch-tag">Branch 01</span>
                    <p class="fs-4 mb-0 mt-2"><i class="fas fa-map-marker-alt me-2" style="color:var(--gold);"></i> Tema Golf City</p>
                </div>
            </div>
            <div class="col-md-4" data-reveal style="--reveal-delay:100">
                <div class="branch-card">
                    <span class="branch-tag">Branch 02</span>
                    <p class="fs-4 mb-0 mt-2"><i class="fas fa-map-marker-alt me-2" style="color:var(--gold);"></i> Kumasi Ahodwo</p>
                </div>
            </div>
            <div class="col-md-4" data-reveal style="--reveal-delay:200">
                <div class="branch-card">
                    <span class="branch-tag">Branch 03</span>
                    <p class="fs-4 mb-0 mt-2"><i class="fas fa-map-marker-alt me-2" style="color:var(--gold);"></i> Kasoa, Adam Nana</p>
                </div>
            </div>
        </div>
        <p class="mt-5 fs-5 opacity-90" data-reveal>
            <i class="fas fa-envelope me-2"></i> believetravelconsult903@gmail.com
        </p>
    </div>
</section>

</div><!-- /.route-line -->

<!-- FINAL CTA -->
<section class="about-section bg-cream text-center">
    <div class="container">
        <p class="eyebrow mb-2" data-reveal style="color:var(--gold);">Final Call</p>
        <h2 class="display-5 fw-bold mb-4" style="color:var(--navy); text-transform:uppercase;" data-reveal>Your Journey Starts Here</h2>
        <p class="lead mb-5" data-reveal style="--reveal-delay:100; color:#556069;">Wherever you want to work, study, travel, or relocate — Believe Travel Consult is your gateway.</p>
        <a href="https://wa.me/233596204991?text=Hello%2C%20I'd%20like%20to%20learn%20more%20about%20Believe%20Travel%20Consult"
           class="btn btn-lg rounded-pill px-5 py-4 shadow-lg final-cta-btn"
           data-reveal style="--reveal-delay:200; background:var(--green); color:var(--ink); font-weight:800; font-size:1.3rem;">
            <i class="fab fa-whatsapp fa-2x me-3"></i> Chat With Us Now
        </a>
    </div>
</section>

</div><!-- /.about-page -->

<script>
(function () {
    "use strict";

    // --- Scroll reveal for every [data-reveal] element -------------------
    var revealTargets = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window && revealTargets.length) {
        var revealObserver = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' });
        revealTargets.forEach(function (el) { revealObserver.observe(el); });
    } else {
        revealTargets.forEach(function (el) { el.classList.add('is-visible'); });
    }

    // --- Route line grows in once its section enters view -----------------
    var thread = document.querySelector('.route-line');
    if (thread && 'IntersectionObserver' in window) {
        var threadObserver = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05 });
        threadObserver.observe(thread);
    } else if (thread) {
        thread.classList.add('is-visible');
    }

    // --- Flap-tiles get their own visibility class for the CSS animation --
    var flapTiles = document.querySelectorAll('.flap-tile');
    if ('IntersectionObserver' in window && flapTiles.length) {
        var flapObserver = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        flapTiles.forEach(function (el) { flapObserver.observe(el); });
    } else {
        flapTiles.forEach(function (el) { el.classList.add('is-visible'); });
    }

    // --- Count-up animation for the track-record stats --------------------
    var counters = document.querySelectorAll('.stat-count');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function animateCount(el) {
        var target = parseInt(el.getAttribute('data-target'), 10) || 0;
        var suffix = el.getAttribute('data-suffix') || '';

        if (reduceMotion) {
            el.textContent = target + suffix;
            return;
        }

        var duration = 1400;
        var startTime = null;

        function step(timestamp) {
            if (!startTime) { startTime = timestamp; }
            var progress = Math.min((timestamp - startTime) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3); // ease-out-cubic
            el.textContent = Math.floor(eased * target) + suffix;
            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                el.textContent = target + suffix;
            }
        }
        window.requestAnimationFrame(step);
    }

    if (counters.length) {
        if ('IntersectionObserver' in window) {
            var statObserver = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        animateCount(entry.target);
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });
            counters.forEach(function (el) { statObserver.observe(el); });
        } else {
            counters.forEach(animateCount);
        }
    }
})();
</script>

<?php include 'includes/footer.php'; ?>