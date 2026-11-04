<?php 
require_once 'includes/db.php';
require_once 'includes/functions.php';
include 'includes/header.php'; 
?>

<div class="partners-page">
<style>
    /* ============ TYPE SYSTEM (scoped to .partners-page) ============ */
    @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700;9..144,800&family=Manrope:wght@400;500;600;700;800&display=swap');

    .partners-page {
        --navy: #033754;
        --navy-deep: #022638;
        --teal: #10b6d7;
        --lime: #89d900;
        --paper: #f8f8f4;
        --ink-soft: #5b6b74;
        --hairline: rgba(3,55,84,0.12);
        font-family: 'Manrope', system-ui, sans-serif;
        color: var(--navy);
        background: var(--paper);
        overflow-x: hidden;
    }
    .partners-page h1, .partners-page h2, .partners-page h3, .partners-page h4 {
        font-family: 'Fraunces', Georgia, serif;
        letter-spacing: -0.01em;
    }
    .partners-page .eyebrow {
        font-family: 'Manrope', sans-serif;
        font-weight: 700;
        font-size: 0.78rem;
        letter-spacing: 0.22em;
        text-transform: uppercase;
        color: var(--lime);
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
        margin-bottom: 1rem;
    }
    .partners-page .eyebrow::before {
        content: "";
        width: 28px;
        height: 2px;
        background: var(--lime);
        display: inline-block;
    }
    .partners-page .eyebrow.on-dark { color: #b7f0ff; }
    .partners-page .eyebrow.on-dark::before { background: #b7f0ff; }

    /* ============ HERO ============ */
    .p-hero {
        position: relative;
        min-height: 86vh;
        display: flex;
        align-items: center;
        color: #fff;
        overflow: hidden;
    }
    .p-hero video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; filter: brightness(0.55) saturate(1.05); z-index: 1; }
    .p-hero .p-hero-overlay {
        position: absolute; inset: 0; z-index: 2;
        background: linear-gradient(160deg, rgba(2,38,56,0.92) 0%, rgba(3,55,84,0.75) 45%, rgba(16,182,215,0.55) 100%);
    }
    .p-hero .container { position: relative; z-index: 3; }
    .p-hero h1 {
        font-weight: 700;
        font-size: clamp(2.6rem, 5.4vw, 4.6rem);
        line-height: 1.04;
        text-shadow: 0 8px 30px rgba(0,0,0,0.35);
    }
    .p-hero .lede {
        font-size: clamp(1.05rem, 1.6vw, 1.35rem);
        font-weight: 400;
        color: rgba(255,255,255,0.86);
        max-width: 640px;
    }
    .p-stat-row { border-top: 1px solid rgba(255,255,255,0.18); padding-top: 2.5rem; margin-top: 2.5rem; }
    .p-stat { text-align: left; }
    .p-stat .num { font-family: 'Fraunces', serif; font-weight: 700; font-size: clamp(1.8rem, 3vw, 2.6rem); line-height: 1; }
    .p-stat .lbl { font-size: 0.85rem; color: rgba(255,255,255,0.72); margin-top: 0.4rem; letter-spacing: 0.01em; }
    .p-btn-primary {
        background: var(--lime); color: var(--navy-deep); font-weight: 700;
        border-radius: 100px; padding: 0.95rem 2.2rem; display: inline-flex; align-items: center; gap: 0.6rem;
        transition: transform .25s ease, box-shadow .25s ease;
        box-shadow: 0 14px 30px -10px rgba(137,217,0,0.55);
    }
    .p-btn-primary:hover { transform: translateY(-3px); color: var(--navy-deep); box-shadow: 0 18px 36px -8px rgba(137,217,0,0.65); }
    .p-btn-ghost {
        border: 1.5px solid rgba(255,255,255,0.55); color: #fff; font-weight: 700;
        border-radius: 100px; padding: 0.95rem 2.2rem; display: inline-flex; align-items: center; gap: 0.6rem;
        transition: all .25s ease; background: transparent;
    }
    .p-btn-ghost:hover { background: rgba(255,255,255,0.12); color: #fff; border-color: #fff; }

    /* ============ SECTION RHYTHM ============ */
    .p-section { padding: 6.5rem 0; }
    .p-section-head { max-width: 780px; }
    .p-section-head h2 { font-weight: 700; font-size: clamp(1.9rem, 3.2vw, 2.7rem); color: var(--navy); }
    .p-section-head p { color: var(--ink-soft); font-size: 1.12rem; line-height: 1.65; }

    /* ============ TABS ============ */
    .p-tabs { display: inline-flex; background: #fff; border: 1px solid var(--hairline); border-radius: 100px; padding: 6px; box-shadow: 0 10px 30px -18px rgba(3,55,84,0.35); }
    .p-tabs .nav-link { border: none !important; border-radius: 100px !important; font-weight: 700; padding: 0.85rem 2.1rem !important; color: var(--navy); background: transparent !important; font-family: 'Manrope', sans-serif; font-size: 1.02rem; transition: all .25s ease; }
    .p-tabs .nav-link.active { background: var(--navy) !important; color: #fff !important; box-shadow: 0 10px 24px -8px rgba(3,55,84,0.55); }

    /* ============ PARTNER DOSSIER CARDS ============ */
    .p-card {
        background: #fff; border: 1px solid var(--hairline); border-radius: 20px;
        padding: 2rem; height: 100%; position: relative; overflow: hidden;
        transition: transform .35s ease, box-shadow .35s ease, border-color .35s ease;
    }
    .p-card:hover { transform: translateY(-8px); box-shadow: 0 30px 50px -24px rgba(3,55,84,0.35); border-color: transparent; }
    .p-card .p-ref {
        position: absolute; top: 0; right: 0; font-family: 'Fraunces', serif; font-weight: 600;
        font-size: 0.72rem; letter-spacing: 0.08em; color: #fff; background: var(--navy);
        padding: 0.35rem 0.9rem; border-radius: 0 20px 0 14px;
    }
    .p-card .p-badge-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.4rem; }
    .p-logo-badge {
        width: 62px; height: 62px; border-radius: 16px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        font-family: 'Fraunces', serif; font-weight: 700; font-size: 1.25rem; color: #fff;
        background: linear-gradient(135deg, var(--navy), var(--teal));
        box-shadow: 0 10px 22px -8px rgba(3,55,84,0.5);
    }
    .p-card.ghana .p-logo-badge { background: linear-gradient(135deg, #0a6b3a, var(--lime)); }
    .p-card .p-title { font-weight: 700; font-size: 1.28rem; margin-bottom: 0.15rem; color: var(--navy); line-height: 1.25; }
    .p-card .p-loc { font-size: 0.85rem; color: var(--ink-soft); font-weight: 600; display: flex; align-items: center; gap: 0.4rem; }
    .p-card .p-desc { color: var(--ink-soft); font-size: 0.97rem; line-height: 1.6; margin: 1.1rem 0 1.3rem; }
    .p-chip { display: inline-block; font-size: 0.76rem; font-weight: 700; padding: 0.4rem 0.85rem; border-radius: 100px; background: rgba(16,182,215,0.1); color: var(--navy); margin: 0 0.4rem 0.4rem 0; }
    .p-card.ghana .p-chip { background: rgba(137,217,0,0.14); }
    .p-cta { display: flex; align-items: center; justify-content: space-between; font-weight: 700; font-size: 0.92rem; color: var(--navy); border-top: 1px solid var(--hairline); padding-top: 1.1rem; margin-top: 0.4rem; text-decoration: none; }
    .p-cta:hover { color: var(--teal); }
    .p-since { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.05em; color: var(--ink-soft); text-transform: uppercase; }

    /* ============ TESTIMONIALS ============ */
    .p-quote-card { background: #fff; border-radius: 20px; padding: 2.4rem; height: 100%; border: 1px solid var(--hairline); }
    .p-quote-mark { font-family: 'Fraunces', serif; font-size: 3rem; color: var(--lime); line-height: 0.5; display: block; margin-bottom: 1rem; }
    .p-quote-card p.quote { color: var(--navy); font-size: 1.02rem; line-height: 1.6; font-weight: 500; }
    .p-quote-name { font-weight: 700; margin-top: 1rem; margin-bottom: 0; }
    .p-quote-role { font-size: 0.85rem; color: var(--ink-soft); }

    /* ============ SERVICES / MODEL / BENEFITS ============ */
    .p-icon-tile { background: #fff; border-radius: 18px; padding: 1.9rem; border: 1px solid var(--hairline); display: flex; gap: 1.3rem; align-items: flex-start; transition: box-shadow .3s ease, transform .3s ease; }
    .p-icon-tile:hover { transform: translateY(-4px); box-shadow: 0 22px 40px -22px rgba(3,55,84,0.35); }
    .p-icon-circle { width: 54px; height: 54px; border-radius: 14px; background: var(--navy); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .p-icon-circle i { color: #fff; font-size: 1.3rem; }
    .p-impact-band { background: linear-gradient(155deg, var(--navy-deep) 0%, var(--navy) 55%, #0a6c8a 100%); }
    .p-impact-tile { background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.14); border-radius: 20px; padding: 2.4rem; backdrop-filter: blur(6px); }

    /* ============ FORM ============ */
    .p-form-wrap { background: var(--navy-deep); border-radius: 26px; padding: 3rem; }
    .partners-page .form-control { border-radius: 100px; padding: 0.95rem 1.4rem; border: none; font-family: 'Manrope', sans-serif; }
    .partners-page textarea.form-control { border-radius: 18px; }
    .partners-page .form-label { color: rgba(255,255,255,0.85); font-weight: 600; font-size: 0.9rem; letter-spacing: 0.02em; }

    .hover-lift { transition: all 0.4s ease; }
    @media (max-width: 767px) { .p-section { padding: 4rem 0; } .p-form-wrap { padding: 1.8rem; } }
</style>

<!-- ============ HERO ============ -->
<section class="p-hero">
    <video autoplay muted loop playsinline>
        <source src="assets/videos/offer_video.mp4" type="video/mp4">
        <img src="assets/images/cta_dubai.jpg" alt="Dubai Skyline">
    </video>
    <div class="p-hero-overlay"></div>
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-9" data-aos="fade-up">
                <span class="eyebrow on-dark">Our Partner Network</span>
                <h1 class="mb-4">Our Esteemed<br>Value Partners</h1>
                <p class="lede mb-5">Forging strategic alliances for seamless Ghana&nbsp;&ndash;&nbsp;UAE relocations and global opportunities.</p>
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <a href="#become-partner" class="p-btn-primary">Become a Partner <i class="fas fa-handshake"></i></a>
                    <a href="contact.php" class="p-btn-ghost">Explore Collaborations <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="row p-stat-row g-4">
                    <div class="col-6 col-md-3 p-stat">
                        <div class="num" style="color:#89d900;">20+</div>
                        <div class="lbl">Trusted Global Partners</div>
                    </div>
                    <div class="col-6 col-md-3 p-stat">
                        <div class="num" style="color:#89d900;">9+</div>
                        <div class="lbl">Years of Collaborative Excellence</div>
                    </div>
                    <div class="col-6 col-md-3 p-stat">
                        <div class="num">2000+</div>
                        <div class="lbl">Successful Client Placements</div>
                    </div>
                    <div class="col-6 col-md-3 p-stat">
                        <div class="num" style="color:#89d900;">99%</div>
                        <div class="lbl">Partner Satisfaction Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ PARTNER NETWORK OVERVIEW ============ -->
<section class="p-section" id="partners-overview">
    <div class="container">
        <div class="text-center mx-auto mb-5 p-section-head" style="text-align:center;" data-aos="fade-up">
            <div class="d-flex justify-content-center"><span class="eyebrow">The Directory</span></div>
            <h2>Our Premier Partner Ecosystem</h2>
            <p class="mx-auto">At Believe Travel Consult, we partner with top-tier agencies in the UAE and Ghana to provide comprehensive, end-to-end solutions for visa processing, direct employment, freelance opportunities, and seamless relocations. Our alliances ensure compliance, efficiency, and exceptional outcomes for over 2,000 clients since 2017.</p>
        </div>

        <div class="d-flex justify-content-center mb-5">
            <ul class="nav p-tabs mb-0" id="partnersTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="uae-tab" data-bs-toggle="tab" data-bs-target="#uae-partners" type="button" role="tab" aria-controls="uae-partners" aria-selected="true">UAE Partners</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="ghana-tab" data-bs-toggle="tab" data-bs-target="#ghana-partners" type="button" role="tab" aria-controls="ghana-partners" aria-selected="false">Ghana Partners</button>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <!-- UAE Partners Tab -->
            <div class="tab-pane fade show active" id="uae-partners" role="tabpanel" aria-labelledby="uae-tab">
                <div class="row g-4">
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="p-card">
                            <span class="p-ref">UAE&nbsp;01</span>
                            <div class="p-badge-row">
                                <div class="p-logo-badge">IG</div>
                                <div>
                                    <div class="p-title">Innovation Group Ltd.</div>
                                    <div class="p-loc"><i class="fas fa-map-marker-alt"></i> Dubai, UAE</div>
                                </div>
                            </div>
                            <p class="p-desc">Founded in 1994, Innovations UAE is a pioneer in white-collar outsourcing, serving over 10,000 employees across GCC, India, and Poland. Specialties include staffing, recruitment, payroll, and HR solutions for banking, telecom, retail, and government sectors.</p>
                            <div><span class="p-chip">HR &amp; Recruitment</span><span class="p-chip">Outsourcing</span></div>
                            <a href="https://innovationuae.com/" target="_blank" class="p-cta">Visit Website <i class="fas fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="p-card">
                            <span class="p-ref">UAE&nbsp;02</span>
                            <div class="p-badge-row">
                                <div class="p-logo-badge">MV</div>
                                <div>
                                    <div class="p-title">Moativ Employment Services</div>
                                    <div class="p-loc"><i class="fas fa-map-marker-alt"></i> Dubai, UAE</div>
                                </div>
                            </div>
                            <p class="p-desc">A leading HR outsourcing partner in Dubai, Moativ offers comprehensive HR solutions including recruitment, payroll management, and employee outsourcing tailored for diverse industries to ensure operational efficiency and compliance.</p>
                            <div><span class="p-chip">HR Outsourcing</span><span class="p-chip">Payroll Services</span></div>
                            <a href="https://moativ.ae/" target="_blank" class="p-cta">Visit Website <i class="fas fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="p-card">
                            <span class="p-ref">UAE&nbsp;03</span>
                            <div class="p-badge-row">
                                <div class="p-logo-badge">LJ</div>
                                <div>
                                    <div class="p-title">LAVAJET Company</div>
                                    <div class="p-loc"><i class="fas fa-map-marker-alt"></i> Dubai, UAE</div>
                                </div>
                            </div>
                            <p class="p-desc">An international leader in environmental waste management, LAVAJET provides sustainable solid waste collection and recycling solutions, contributing to eco-friendly practices and employment in the UAE's green sector.</p>
                            <div><span class="p-chip">Environmental Services</span><span class="p-chip">Waste Management</span></div>
                            <a href="https://www.lavajet-group.com/" target="_blank" class="p-cta">Visit Website <i class="fas fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ghana Partners Tab -->
            <div class="tab-pane fade" id="ghana-partners" role="tabpanel" aria-labelledby="ghana-tab">
                <div class="row g-4">
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="p-card ghana">
                            <span class="p-ref">GH&nbsp;01</span>
                            <div class="p-badge-row">
                                <div class="p-logo-badge">JH</div>
                                <div>
                                    <div class="p-title">JobHouse Services</div>
                                    <div class="p-loc"><i class="fas fa-map-marker-alt"></i> East Legon, Accra, Ghana</div>
                                </div>
                            </div>
                            <p class="p-desc">Ghana's leading HR consultancy since 2010. JobHouse specializes in recruitment, staff outsourcing, payroll management, and career development, drawing on a candidate database of over 20,000 skilled local and foreign professionals.</p>
                            <div><span class="p-chip">Recruitment &amp; Staffing</span><span class="p-chip">HR Outsourcing</span></div>
                            <a href="https://jobhouse.com.gh/" target="_blank" class="p-cta">Visit Website <i class="fas fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                        <div class="p-card ghana">
                            <span class="p-ref">GH&nbsp;02</span>
                            <div class="p-badge-row">
                                <div class="p-logo-badge">L'A</div>
                                <div>
                                    <div class="p-title">L'AINE Services Limited</div>
                                    <div class="p-loc"><i class="fas fa-map-marker-alt"></i> Sakumono, Tema, Ghana</div>
                                </div>
                            </div>
                            <p class="p-desc">One of Ghana's most established Human Resource Development organisations, with decades of operation and branches in Accra, Kumasi, and Takoradi. ISO 9001:2015 certified and a member of SEDEX and the Ghana Club 100, L'AINE delivers recruitment, payroll, training, and labour outsourcing across Ghana and Africa.</p>
                            <div><span class="p-chip">HR Development</span><span class="p-chip">ISO 9001:2015 Certified</span></div>
                            <a href="https://laineservices.com/" target="_blank" class="p-cta">Visit Website <i class="fas fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                        <div class="p-card ghana">
                            <span class="p-ref">GH&nbsp;03</span>
                            <div class="p-badge-row">
                                <div class="p-logo-badge">CC</div>
                                <div>
                                    <div class="p-title">Career Connections Ghana</div>
                                    <div class="p-loc"><i class="fas fa-map-marker-alt"></i> Ghana</div>
                                </div>
                            </div>
                            <p class="p-desc">A dedicated career and recruitment consultancy supporting Ghanaian jobseekers and employers alike, offering structured hiring support, placement guidance, and workforce planning to help candidates move confidently into new roles.</p>
                            <div><span class="p-chip">Recruitment Consulting</span><span class="p-chip">Career Placement</span></div>
                            <a href="contact.php" class="p-cta">Contact for Details <i class="fas fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="400">
                        <div class="p-card ghana">
                            <span class="p-ref">GH&nbsp;04</span>
                            <div class="p-badge-row">
                                <div class="p-logo-badge">AB</div>
                                <div>
                                    <div class="p-title">African Bagg Recruitment</div>
                                    <div class="p-loc"><i class="fas fa-map-marker-alt"></i> Osu, Accra, Ghana</div>
                                </div>
                            </div>
                            <p class="p-desc">A subsidiary of the African Bagg group of companies and a trusted local partner for international organisations entering Ghana and the wider sub-Saharan region. Services span recruitment, executive search, assessments, payroll, and HR outsourcing across Energy, Oil &amp; Gas, and Telecommunications.</p>
                            <div><span class="p-chip">Executive Search</span><span class="p-chip">Payroll &amp; Outsourcing</span></div>
                            <a href="https://africanbaggrecruitment.com/" target="_blank" class="p-cta">Visit Website <i class="fas fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="500">
                        <div class="p-card ghana">
                            <span class="p-ref">GH&nbsp;05</span>
                            <div class="p-badge-row">
                                <div class="p-logo-badge">HR</div>
                                <div>
                                    <div class="p-title">HR Focus Africa</div>
                                    <div class="p-loc"><i class="fas fa-map-marker-alt"></i> Accra, Ghana</div>
                                </div>
                            </div>
                            <p class="p-desc">Launched as Ghana's first Human Resource magazine and now a continental HR media and events platform, HR Focus Africa (a subsidiary of L'AINE HR) links Believe Travel Consult into the wider network of HR leaders and organisations shaping the future of work across Africa.</p>
                            <div><span class="p-chip">HR Media &amp; Events</span><span class="p-chip">Industry Network</span></div>
                            <a href="https://hrfocusevents.com/" target="_blank" class="p-cta">Visit Website <i class="fas fa-arrow-up-right-from-square"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ TESTIMONIALS ============ -->
<section class="p-section" style="background:#fff;">
    <div class="container">
        <div class="text-center mx-auto mb-5 p-section-head" data-aos="fade-up">
            <div class="d-flex justify-content-center"><span class="eyebrow">In Their Words</span></div>
            <h2>Testimonials from Our Partners</h2>
            <p class="mx-auto">Discover how our collaborations have driven success and innovation in relocation and employment services.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="p-quote-card">
                    <span class="p-quote-mark">&ldquo;</span>
                    <p class="quote">Believe Travel Consult's efficient sourcing of Ghanaian talent has enhanced our outsourcing capabilities across the GCC.</p>
                    <p class="p-quote-name">Founder</p>
                    <p class="p-quote-role">Innovation Group Ltd.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="150">
                <div class="p-quote-card">
                    <span class="p-quote-mark">&ldquo;</span>
                    <p class="quote">Their seamless visa conversion and airport support have streamlined our HR processes for international hires.</p>
                    <p class="p-quote-name">HR Director</p>
                    <p class="p-quote-role">Moativ Employment Services</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="p-quote-card">
                    <span class="p-quote-mark">&ldquo;</span>
                    <p class="quote">Partnering with Believe has provided reliable talent for our sustainable waste management operations in Dubai.</p>
                    <p class="p-quote-name">Operations Manager</p>
                    <p class="p-quote-role">LAVAJET Company</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="250">
                <div class="p-quote-card">
                    <span class="p-quote-mark">&ldquo;</span>
                    <p class="quote">Believe's sponsorship letters and bank statement support have consistently strengthened our candidates' visa approvals.</p>
                    <p class="p-quote-name">HR Manager</p>
                    <p class="p-quote-role">JobHouse Services</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                <div class="p-quote-card">
                    <span class="p-quote-mark">&ldquo;</span>
                    <p class="quote">Believe's disciplined documentation and candidate vetting complement the standards we hold our own outsourcing programmes to.</p>
                    <p class="p-quote-name">HR Outsourcing Lead</p>
                    <p class="p-quote-role">L'AINE Services Limited</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="350">
                <div class="p-quote-card">
                    <span class="p-quote-mark">&ldquo;</span>
                    <p class="quote">Our candidates trust Believe's guidance through every step of the relocation and visa process.</p>
                    <p class="p-quote-name">Placement Consultant</p>
                    <p class="p-quote-role">Career Connections Ghana</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ INTEGRATED SERVICES ============ -->
<section class="p-section" style="background:var(--paper);">
    <div class="container">
        <div class="text-center mx-auto mb-5 p-section-head" data-aos="fade-up">
            <div class="d-flex justify-content-center"><span class="eyebrow">What Our Alliances Unlock</span></div>
            <h2>Integrated Services Through Partnerships</h2>
            <p class="mx-auto">Our partners enable us to offer a full spectrum of services, ensuring a smooth transition from Ghana to UAE with expert support at every step.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
                <div class="p-icon-tile"><div class="p-icon-circle"><i class="fas fa-plane-departure"></i></div>
                    <div><h5 class="fw-bold mb-1" style="color:var(--navy);">UAE Tourist to Employment Visa</h5><p class="mb-0 text-muted">Seamless conversion from tourist to employment visa with full documentation support.</p></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="150">
                <div class="p-icon-tile"><div class="p-icon-circle"><i class="fas fa-car"></i></div>
                    <div><h5 class="fw-bold mb-1" style="color:var(--navy);">Airport Pickup &amp; Accommodation</h5><p class="mb-0 text-muted">Premium meet-and-greet services upon arrival in UAE for a stress-free start.</p></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="p-icon-tile"><div class="p-icon-circle"><i class="fas fa-briefcase"></i></div>
                    <div><h5 class="fw-bold mb-1" style="color:var(--navy);">Direct Employment Placement</h5><p class="mb-0 text-muted">Guaranteed job opportunities in top UAE companies through our partner network.</p></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="250">
                <div class="p-icon-tile"><div class="p-icon-circle"><i class="fas fa-passport"></i></div>
                    <div><h5 class="fw-bold mb-1" style="color:var(--navy);">UAE Freelance Visa</h5><p class="mb-0 text-muted">2-year freelance visas with renewal support for independent professionals.</p></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
                <div class="p-icon-tile"><div class="p-icon-circle"><i class="fas fa-id-card"></i></div>
                    <div><h5 class="fw-bold mb-1" style="color:var(--navy);">Ghana Card Express (1-Day Process)</h5><p class="mb-0 text-muted">Accelerated Ghana Card issuance for quick visa applications.</p></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="350">
                <div class="p-icon-tile"><div class="p-icon-circle"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div><h5 class="fw-bold mb-1" style="color:var(--navy);">Bank Statement &amp; Sponsorship Letter</h5><p class="mb-0 text-muted">Professional financial documentation to strengthen visa approvals.</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ PARTNERSHIP IMPACT ============ -->
<section class="p-section p-impact-band text-white">
    <div class="container">
        <div class="text-center mx-auto mb-5 p-section-head" data-aos="fade-up">
            <div class="d-flex justify-content-center"><span class="eyebrow on-dark">Real-World Results</span></div>
            <h2 class="text-white">The Impact of Our Partnerships</h2>
            <p class="mx-auto text-white-50">Through our strategic alliances, we've facilitated thousands of successful relocations, boosted economies, and created lasting career opportunities.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-4 text-center" data-aos="fade-up" data-aos-delay="100">
                <div class="p-impact-tile h-100">
                    <i class="fas fa-chart-line fa-3x mb-3" style="color:var(--lime);"></i>
                    <h3 class="fw-bold fs-3">Economic Boost</h3>
                    <p class="text-white-50 mb-0">Our partnerships have contributed over $50M in remittances to Ghana's economy through placed professionals in UAE.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 text-center" data-aos="fade-up" data-aos-delay="200">
                <div class="p-impact-tile h-100">
                    <i class="fas fa-users fa-3x mb-3" style="color:var(--teal);"></i>
                    <h3 class="fw-bold fs-3">Talent Development</h3>
                    <p class="text-white-50 mb-0">Trained and placed 500+ Ghanaian youth in skilled UAE roles, enhancing global employability skills.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4 text-center" data-aos="fade-up" data-aos-delay="300">
                <div class="p-impact-tile h-100">
                    <i class="fas fa-globe-africa fa-3x mb-3" style="color:var(--lime);"></i>
                    <h3 class="fw-bold fs-3">HR Network Reach</h3>
                    <p class="text-white-50 mb-0">Collaborations with established Ghanaian HR consultancies like L'AINE and JobHouse strengthen our candidate pipeline and compliance standards.</p>
                </div>
            </div>
        </div>
        <div class="text-center mt-5">
            <a href="blog.php" class="p-btn-ghost">Read Success Stories <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- ============ PARTNERSHIP MODEL ============ -->
<section class="p-section" style="background:#fff;">
    <div class="container">
        <div class="text-center mx-auto mb-5 p-section-head" data-aos="fade-up">
            <div class="d-flex justify-content-center"><span class="eyebrow">How We Work Together</span></div>
            <h2>Our Collaborative Partnership Model</h2>
            <p class="mx-auto">Built on trust, innovation, and mutual growth, our model ensures win-win outcomes for partners, clients, and communities.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4 text-center" data-aos="fade-up" data-aos-delay="100">
                <div class="p-card h-100" style="text-align:left;">
                    <div class="p-icon-circle mb-3"><i class="fas fa-globe"></i></div>
                    <h4 class="fw-bold mb-2 fs-4" style="color:var(--navy);">Global Expertise Integration</h4>
                    <p class="text-muted mb-0">Leveraging UAE and Ghanaian insights for compliant, efficient relocation processes.</p>
                </div>
            </div>
            <div class="col-md-4 text-center" data-aos="fade-up" data-aos-delay="200">
                <div class="p-card h-100" style="text-align:left;">
                    <div class="p-icon-circle mb-3"><i class="fas fa-search-dollar"></i></div>
                    <h4 class="fw-bold mb-2 fs-4" style="color:var(--navy);">Talent Sourcing &amp; Development</h4>
                    <p class="text-muted mb-0">Identifying, training, and placing skilled professionals in high-demand UAE sectors.</p>
                </div>
            </div>
            <div class="col-md-4 text-center" data-aos="fade-up" data-aos-delay="300">
                <div class="p-card h-100" style="text-align:left;">
                    <div class="p-icon-circle mb-3"><i class="fas fa-handshake"></i></div>
                    <h4 class="fw-bold mb-2 fs-4" style="color:var(--navy);">Seamless End-to-End Placement</h4>
                    <p class="text-muted mb-0">From visa applications to job integration, with ongoing support for long-term success.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ BENEFITS ============ -->
<section class="p-section" style="background:var(--paper);">
    <div class="container">
        <div class="text-center mx-auto mb-5 p-section-head" data-aos="fade-up">
            <div class="d-flex justify-content-center"><span class="eyebrow">Why Partner With Us</span></div>
            <h2>Benefits of Our Partner Network</h2>
            <p class="mx-auto">Our alliances provide unparalleled value, ensuring reliability, speed, and success in every relocation journey.</p>
        </div>
        <div class="row g-4">
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="p-card h-100 text-center" style="text-align:center !important;">
                    <i class="fas fa-globe fa-3x mb-3" style="color:var(--lime);"></i>
                    <h4 class="fw-bold mb-2 fs-5" style="color:var(--navy);">Global Market Access</h4>
                    <p class="text-muted mb-0">Tap into UAE's booming job market with our partners' extensive networks.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="p-card h-100 text-center" style="text-align:center !important;">
                    <i class="fas fa-dollar-sign fa-3x mb-3" style="color:var(--teal);"></i>
                    <h4 class="fw-bold mb-2 fs-5" style="color:var(--navy);">Competitive Revenue Models</h4>
                    <p class="text-muted mb-0">Fair commission structures and shared growth opportunities for partners.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="p-card h-100 text-center" style="text-align:center !important;">
                    <i class="fas fa-headset fa-3x mb-3" style="color:var(--lime);"></i>
                    <h4 class="fw-bold mb-2 fs-5" style="color:var(--navy);">24/7 Expert Support</h4>
                    <p class="text-muted mb-0">Dedicated teams for visa, employment, and relocation queries.</p>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="p-card h-100 text-center" style="text-align:center !important;">
                    <i class="fas fa-shield-alt fa-3x mb-3" style="color:var(--teal);"></i>
                    <h4 class="fw-bold mb-2 fs-5" style="color:var(--navy);">Compliance &amp; Quality Assurance</h4>
                    <p class="text-muted mb-0">ISO-certified processes ensuring legal and ethical standards.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ BECOME A PARTNER FORM ============ -->
<section id="become-partner" class="p-section" style="background:linear-gradient(135deg, var(--lime), var(--teal));">
    <div class="container">
        <div class="text-center mx-auto mb-5 text-white p-section-head" data-aos="fade-up">
            <div class="d-flex justify-content-center"><span class="eyebrow on-dark">Grow With Us</span></div>
            <h2 class="text-white">Join Our Elite Partner Network</h2>
            <p class="mx-auto text-white" style="opacity:.92;">Collaborate with Believe Travel Consult to expand your reach, access premium opportunities, and drive mutual success in the global relocation industry.</p>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="p-form-wrap shadow-lg">
                    <form id="partner-form" class="row g-4 needs-validation" method="POST" action="contact.php" novalidate>
                        <div class="col-md-6">
                            <label class="form-label">Agency Name *</label>
                            <input type="text" class="form-control" name="agency_name" placeholder="Your Agency Name" required>
                            <div class="invalid-feedback">Please enter your agency name.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location *</label>
                            <input type="text" class="form-control" name="location" placeholder="City, Country" required>
                            <div class="invalid-feedback">Please enter your location.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Email *</label>
                            <input type="email" class="form-control" name="email" placeholder="email@agency.com" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone *</label>
                            <input type="tel" class="form-control" name="phone" placeholder="+233 596 204 991" required pattern="^\+?[1-9]\d{1,14}$">
                            <div class="invalid-feedback">Please enter a valid phone number.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Message / Partnership Proposal *</label>
                            <textarea class="form-control" name="message" rows="5" placeholder="Describe your agency, services, and interest in partnering with us" required></textarea>
                            <div class="invalid-feedback">Please enter your message.</div>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="p-btn-primary border-0" style="font-size:1.05rem;">
                                Submit Partnership Application <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ BRANCHES & CONTACT ============ -->
<section class="py-5 text-center text-white" style="background: var(--navy-deep);">
    <div class="container">
        <div class="d-flex justify-content-center"><span class="eyebrow on-dark">Find Us</span></div>
        <h3 class="fw-bold mb-4 fs-2">Our Strategic Branches in Ghana</h3>
        <div class="row g-4 justify-content-center">
            <div class="col-md-4">
                <p class="fs-5 mb-0"><i class="fas fa-map-marker-alt me-2" style="color:var(--lime);"></i> Tema Golf City</p>
            </div>
            <div class="col-md-4">
                <p class="fs-5 mb-0"><i class="fas fa-map-marker-alt me-2" style="color:var(--lime);"></i> Kumasi Ahodwo</p>
            </div>
            <div class="col-md-4">
                <p class="fs-5 mb-0"><i class="fas fa-map-marker-alt me-2" style="color:var(--lime);"></i> Kasoa, Adam Nana</p>
            </div>
        </div>
        <p class="mt-4 fs-5 opacity-75 mb-0">
            <i class="fas fa-envelope me-2"></i> believetravelconsult903@gmail.com
        </p>
    </div>
</section>
</div><!-- /.partners-page -->

<!-- Form Validation Script -->
<script>
    (function () {
        'use strict';
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();
</script>

<?php include 'includes/footer.php'; ?>