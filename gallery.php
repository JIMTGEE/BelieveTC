<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
include 'includes/header.php';

// ——— AUTO CREATE GALLERY TABLE IF NOT EXISTS (SAFE) ———
try {
    $pdo->query("SELECT 1 FROM gallery LIMIT 1");
} catch (Exception $e) {
    // Table doesn't exist → create it
    $sql = "CREATE TABLE gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        file_path VARCHAR(500) NOT NULL,
        type ENUM('image', 'video') NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $pdo->exec($sql);

    // Optional: Insert a welcome image so gallery is never empty
    $welcome = "assets/images/about_handshake.jpg";
    if (file_exists("../" . $welcome)) {
        $stmt = $pdo->prepare("INSERT INTO gallery (title, description, file_path, type) VALUES (?, ?, ?, 'image')");
        $stmt->execute(['Welcome to Our Gallery', 'Real clients, real results – Believe Travel Consult', $welcome]);
    }
}

// ——— FETCH ALL GALLERY ITEMS ———
$stmt = $pdo->prepare("SELECT * FROM gallery ORDER BY uploaded_at DESC");
$stmt->execute();
$gallery_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_count = count($gallery_items);
$photo_count = count(array_filter($gallery_items, fn($i) => $i['type'] === 'image'));
$video_count = count(array_filter($gallery_items, fn($i) => $i['type'] === 'video'));
?>

<style>
    :root {
        --btc-navy: #033754;
        --btc-navy-deep: #022639;
        --btc-green: #89d900;
        --btc-gold: #d4af37;
    }

    /* ——— PAGE HEADER (minimal, text-only) ——— */
    .gallery-page-header {
        padding: 2.25rem 0 1.75rem;
        border-bottom: 1px solid #e7ecef;
        background: #fff;
    }
    .gallery-page-header .eyebrow {
        color: var(--btc-green);
        letter-spacing: .12em;
        text-transform: uppercase;
        font-size: .78rem;
        font-weight: 700;
    }
    .gallery-page-header h1 {
        color: var(--btc-navy);
        font-weight: 800;
        font-size: clamp(1.6rem, 2.4vw, 2.1rem);
        margin: .3rem 0 .4rem;
        position: relative;
        padding-bottom: .6rem;
    }
    .gallery-page-header h1::after {
        content: "";
        position: absolute;
        left: 0; bottom: 0;
        width: 46px; height: 3px;
        background: var(--btc-green);
        border-radius: 3px;
    }
    .gallery-page-header p {
        color: #5b6b74;
        max-width: 640px;
        margin-bottom: 0;
        font-size: .98rem;
    }

    /* ——— FILTER BAR ——— */
    .gallery-filter-bar {
        background: #fff;
        border-bottom: 1px solid #eef1f3;
        position: sticky;
        top: 0;
        z-index: 20;
    }
    .gallery-filter-bar .filter-btn {
        border: 1.5px solid #dfe6ea;
        background: #fff;
        color: var(--btc-navy);
        font-weight: 600;
        font-size: .9rem;
        padding: .5rem 1.15rem;
        border-radius: 999px;
        transition: all .2s ease;
    }
    .gallery-filter-bar .filter-btn i { margin-right: .4rem; }
    .gallery-filter-bar .filter-btn:hover {
        border-color: var(--btc-green);
        color: var(--btc-navy);
    }
    .gallery-filter-bar .filter-btn.active {
        background: var(--btc-navy);
        border-color: var(--btc-navy);
        color: #fff;
    }

    /* ——— GALLERY GRID / CARDS ——— */
    .gallery-section { background: #f6f8f9; }

    .gallery-item {
        transition: opacity .25s ease, transform .25s ease;
    }
    .gallery-item.is-hidden { display: none !important; }

    .gmedia-card {
        position: relative;
        border-radius: 16px;
        overflow: hidden;
        background: #0d1b24;
        box-shadow: 0 6px 20px rgba(3,55,84,.10);
        transition: transform .3s ease, box-shadow .3s ease;
    }
    .gmedia-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 18px 36px rgba(3,55,84,.20);
    }

    .gmedia-frame {
        position: relative;
        width: 100%;
        aspect-ratio: 4 / 3;
        overflow: hidden;
        display: block;
    }
    .gmedia-frame img,
    .gmedia-frame video {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        transition: transform .5s ease;
    }
    .gmedia-card:hover .gmedia-frame img,
    .gmedia-card:hover .gmedia-frame video {
        transform: scale(1.06);
    }

    .gmedia-type-badge {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 3;
        background: rgba(3,55,84,.82);
        color: #fff;
        font-size: .72rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        padding: .3rem .65rem;
        border-radius: 999px;
        backdrop-filter: blur(3px);
    }
    .gmedia-type-badge.video { background: rgba(137,217,0,.92); color: #033754; }
    .gmedia-type-badge i { margin-right: .3rem; }

    .gmedia-play-overlay {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(to top, rgba(0,0,0,.35), transparent 45%);
        pointer-events: none;
        z-index: 2;
    }
    .gmedia-play-overlay i {
        width: 56px; height: 56px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,.92);
        color: var(--btc-navy);
        border-radius: 50%;
        font-size: 1.25rem;
        box-shadow: 0 6px 18px rgba(0,0,0,.25);
    }

    .gmedia-caption-overlay {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        z-index: 2;
        padding: 1rem 1rem .85rem;
        background: linear-gradient(to top, rgba(3,20,30,.92), rgba(3,20,30,0));
        pointer-events: none;
    }
    .gmedia-caption-overlay h5 {
        color: #fff;
        font-size: 1rem;
        font-weight: 700;
        margin: 0 0 .15rem;
        text-shadow: 0 2px 8px rgba(0,0,0,.4);
    }
    .gmedia-caption-overlay .gmedia-date {
        color: rgba(255,255,255,.7);
        font-size: .78rem;
    }
    .gmedia-caption-overlay .gmedia-date i { margin-right: .3rem; }

    .gallery-empty-state {
        text-align: center;
        padding: 5rem 1rem;
    }
    .gallery-empty-state img { width: 100px; opacity: .4; margin-bottom: 1.25rem; }
    .gallery-empty-state h3 { color: #5b6b74; font-weight: 700; }

    @media (max-width: 576px) {
        .gallery-filter-bar { overflow-x: auto; white-space: nowrap; }
        .gallery-filter-bar .filter-scroll { display: inline-flex; gap: .5rem; padding: .75rem 1rem; }
    }
</style>

<!-- ——— PAGE HEADER (minimal, text-only) ——— -->
<section class="gallery-page-header">
    <div class="container">
        <div class="row">
            <div class="col-lg-9">
                <div class="eyebrow">Believe Travel Consult</div>
                <h1>Gallery of Success</h1>
                <p>Real clients, real placements, real life abroad — a look at the people and moments behind every success story.</p>
            </div>
        </div>
    </div>
</section>

<!-- ——— FILTER BAR ——— -->
<?php if (!empty($gallery_items)): ?>
<div class="gallery-filter-bar shadow-sm">
    <div class="container">
        <div class="d-flex align-items-center gap-2 py-3 filter-scroll">
            <button class="filter-btn active" data-filter="all">
                <i class="fas fa-th-large"></i>All (<?= (int)$total_count ?>)
            </button>
            <button class="filter-btn" data-filter="image">
                <i class="fas fa-image"></i>Photos (<?= (int)$photo_count ?>)
            </button>
            <button class="filter-btn" data-filter="video">
                <i class="fas fa-video"></i>Videos (<?= (int)$video_count ?>)
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ——— GALLERY GRID ——— -->
<section class="py-6 gallery-section" id="gallery-grid">
    <div class="container">
        <?php if (empty($gallery_items)): ?>
            <div class="gallery-empty-state">
                <img src="assets/images/placeholder.svg" alt="Empty">
                <h3>Gallery is being updated...</h3>
                <p class="text-muted">Amazing content coming very soon!</p>
            </div>
        <?php else: ?>
            <div class="row g-4" id="light-gallery">
                <?php foreach ($gallery_items as $index => $item): ?>
                    <div class="col-6 col-md-4 col-lg-3 gallery-item" data-type="<?= htmlspecialchars($item['type']) ?>" data-aos="fade-up" data-aos-delay="<?= min($index * 60, 400) ?>">
                        <div class="gmedia-card h-100">
                            <?php if ($item['type'] === 'image'): ?>
                                <a href="<?= htmlspecialchars($item['file_path']) ?>"
                                   data-lg-size="1600-1067"
                                   data-sub-html="<h5>#<?= htmlspecialchars(addslashes($item['title'])) ?></h5><p>#<?= htmlspecialchars(addslashes($item['description'] ?? '')) ?></p>"
                                   class="gmedia-frame">
                                    <span class="gmedia-type-badge"><i class="fas fa-image"></i>Photo</span>
                                    <img src="<?= htmlspecialchars($item['file_path']) ?>"
                                         alt="<?= htmlspecialchars($item['title']) ?>"
                                         loading="lazy">
                                    <div class="gmedia-caption-overlay">
                                        <h5><?= htmlspecialchars($item['title']) ?></h5>
                                        <div class="gmedia-date">
                                            <i class="fas fa-calendar"></i><?= date('d M Y', strtotime($item['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                </a>
                            <?php elseif ($item['type'] === 'video'): ?>
                                <a href="<?= htmlspecialchars($item['file_path']) ?>"
                                   data-lg-size="1280-720"
                                   data-poster=""
                                   data-video='{"source": [{"src":"<?= htmlspecialchars($item['file_path']) ?>", "type":"video/mp4"}], "attributes": {"preload": "none", "controls": true}}'
                                   data-sub-html="<h5>#<?= htmlspecialchars(addslashes($item['title'])) ?></h5><p>#<?= htmlspecialchars(addslashes($item['description'] ?? '')) ?></p>"
                                   class="gmedia-frame gallery-video-trigger">
                                    <span class="gmedia-type-badge video"><i class="fas fa-video"></i>Video</span>
                                    <video preload="metadata" muted playsinline>
                                        <source src="<?= htmlspecialchars($item['file_path']) ?>#t=0.5" type="video/mp4">
                                    </video>
                                    <div class="gmedia-play-overlay"><i class="fas fa-play"></i></div>
                                    <div class="gmedia-caption-overlay">
                                        <h5><?= htmlspecialchars($item['title']) ?></h5>
                                        <div class="gmedia-date">
                                            <i class="fas fa-calendar"></i><?= date('d M Y', strtotime($item['uploaded_at'])) ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endif; ?>

                            <?php if ($item['description']): ?>
                                <div class="p-3 pt-2 d-none d-md-block">
                                    <p class="text-muted small mb-0"><?= htmlspecialchars(mb_strimwidth($item['description'], 0, 90, '…')) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>





<!-- Light Gallery CDN (beautiful popups for images & videos) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lightgallery.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lg-zoom.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/css/lg-video.css">
<script src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/lightgallery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/plugins/zoom/lg-zoom.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lightgallery@2.7.2/plugins/video/lg-video.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('light-gallery');
        if (!container) return;

        // Initialize LightGallery for both images and videos
        const lg = lightGallery(container, {
            selector: '.gmedia-frame',
            plugins: [lgZoom, lgVideo],
            speed: 500,
            thumbnail: true,
            animateThumb: true,
            zoom: true,
            actualSize: true,
            download: false
        });

        // ——— FILTER LOGIC ———
        const filterButtons = document.querySelectorAll('.filter-btn');
        const items = document.querySelectorAll('.gallery-item');

        filterButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.dataset.filter;
                items.forEach(item => {
                    const matches = (filter === 'all' || item.dataset.type === filter);
                    item.classList.toggle('is-hidden', !matches);
                });
            });
        });
    });
</script>