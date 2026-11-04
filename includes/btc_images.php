<?php
/**
 * ===========================================================================
 *  Believe Travel Consult — Image Resolution Engine
 * ===========================================================================
 *
 *  WHY THIS FILE EXISTS
 *  ---------------------------------------------------------------------
 *  offers.php used to pull every photo from LoremFlickr — a service that
 *  matches keywords against a small, unpredictable pool of tagged Flickr
 *  photos. It does NOT understand what a keyword means, so "nurse,hospital"
 *  or "Burj Khalifa" could just as easily return an unrelated photo as a
 *  correct one. That's the root cause of the mismatched images.
 *
 *  This file replaces that with a 4-tier chain, tried in order, for every
 *  single image on the site:
 *
 *   TIER 0  Real uploaded photo (BEST — always wins if present)
 *           Drop a file named exactly after an image's "slug" into
 *           /assets/images/offers/  (e.g. dubai-burj-khalifa.jpg) using
 *           the dashboard's existing drag-and-drop uploader, and that
 *           becomes the photo everywhere it's used — permanently, until
 *           you replace or remove it. This is the only tier that
 *           guarantees the photo is licensed, on-brand, and 100% correct,
 *           so it's the recommended end-state for every image on the page.
 *
 *   TIER 1  Pexels API keyword search (accurate automated placeholder)
 *           Pexels does genuine semantic search — "certified nurse caring
 *           for patient hospital ward" returns an actual nurse in a
 *           hospital, not a random tagged photo. Results are cached to
 *           disk so production traffic never waits on a live API call.
 *           Only active once BTC_PEXELS_API_KEY is set below (free key,
 *           instructions at the bottom of this file).
 *
 *   TIER 2  LoremFlickr (legacy — only used if no Pexels key is set yet,
 *           so the site behaves exactly as it did before until you add one)
 *
 *   TIER 3  Picsum seeded photo, then a soft branded CSS panel if even
 *           that fails to load client-side (unchanged from before —
 *           this is what guarantees the page can never show a broken
 *           image icon)
 *
 *  SETUP (2 minutes, free, no credit card):
 *   1. Go to https://www.pexels.com/api/ and click "Get Started" to make
 *      a free account, then copy your API key.
 *   2. Paste it below as BTC_PEXELS_API_KEY.
 *   3. Run warm_pexels_cache.php once (see that file) so the whole page
 *      loads instantly on its very first real visit instead of resolving
 *      images live.
 *   4. Whenever you have a real photo for a specific spot, drop it into
 *      /assets/images/offers/<slug>.jpg — it will immediately override
 *      the automated placeholder for that image, everywhere it's used.
 * ===========================================================================
 */

// ---- CONFIGURATION ---------------------------------------------------
// Paste your free Pexels API key here to enable Tier 1 (accurate photos).
// Leave blank ('') to keep the site running exactly as it did before —
// nothing breaks either way, this key is purely additive.
define('BTC_PEXELS_API_KEY', 'wdDjLsWdsz9nWiGJlS626XNkK8Q0XIeWWi2J4wkDrGOa4MMkAOSAgZKE'); // e.g. '563492ad6f9170000100000...'

define('BTC_IMG_UPLOAD_DIR', __DIR__ . '/../assets/images/offers');
define('BTC_IMG_UPLOAD_URL', 'assets/images/offers');
define('BTC_PEXELS_CACHE_FILE', __DIR__ . '/../cache/pexels_image_cache.json');
define('BTC_PEXELS_CACHE_TTL', 60 * 60 * 24 * 30); // 30 days
define('BTC_PEXELS_TIMEOUT_SECONDS', 4);

// ---- INTERNAL STATE (do not edit) -------------------------------------
$GLOBALS['btc_pexels_cache_writable'] = null; // resolved lazily, see below

/**
 * Confirms the cache file can actually be read/written before we ever
 * consider making a live Pexels call. If caching isn't available we
 * skip Tier 1 entirely rather than risk dozens of uncached, un-timed-out
 * API calls slowing a real page load to a crawl — speed and reliability
 * always win over trying harder to be accurate.
 */
function btc_pexels_cache_ready() {
    if ($GLOBALS['btc_pexels_cache_writable'] !== null) {
        return $GLOBALS['btc_pexels_cache_writable'];
    }
    $dir = dirname(BTC_PEXELS_CACHE_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $ok = is_dir($dir) && is_writable($dir)
        && (!file_exists(BTC_PEXELS_CACHE_FILE) || is_writable(BTC_PEXELS_CACHE_FILE));
    $GLOBALS['btc_pexels_cache_writable'] = $ok;
    return $ok;
}

function btc_load_pexels_cache() {
    if (!file_exists(BTC_PEXELS_CACHE_FILE)) return [];
    $raw = @file_get_contents(BTC_PEXELS_CACHE_FILE);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function btc_save_pexels_cache($cache) {
    @file_put_contents(BTC_PEXELS_CACHE_FILE, json_encode($cache), LOCK_EX);
}

/**
 * Resolves one keyword search against Pexels, using (and refreshing) the
 * on-disk cache. Returns a direct, hotlinkable photo URL, or null if
 * unavailable for any reason (no key, no cache, API/network failure,
 * curl missing, etc.) — every failure mode here just falls through to
 * the next tier in btc_img_tag(), it never throws or breaks the page.
 */
function btc_get_pexels_photo($query) {
    if (empty(BTC_PEXELS_API_KEY)) return null;
    if (!function_exists('curl_init')) return null;
    if (!btc_pexels_cache_ready()) return null;

    static $cache = null;
    if ($cache === null) $cache = btc_load_pexels_cache();

    $key = md5(strtolower(trim($query)));
    $now = time();

    if (isset($cache[$key]['url']) && ($now - $cache[$key]['ts']) < BTC_PEXELS_CACHE_TTL) {
        return $cache[$key]['url'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.pexels.com/v1/search?query=' . rawurlencode($query)
            . '&per_page=1&orientation=landscape',
        CURLOPT_HTTPHEADER => ['Authorization: ' . BTC_PEXELS_API_KEY],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => BTC_PEXELS_TIMEOUT_SECONDS,
        CURLOPT_CONNECTTIMEOUT => BTC_PEXELS_TIMEOUT_SECONDS,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_errno($ch);
    curl_close($ch);

    if ($err || $httpCode !== 200 || !$response) {
        // Live call failed — serve a stale cached result rather than nothing,
        // if one exists, so a temporary Pexels outage doesn't regress a
        // photo that was previously working.
        return $cache[$key]['url'] ?? null;
    }

    $data = json_decode($response, true);
    $url = $data['photos'][0]['src']['large'] ?? null;
    if (!$url) {
        return $cache[$key]['url'] ?? null;
    }

    $cache[$key] = ['url' => $url, 'ts' => $now];
    btc_save_pexels_cache($cache);
    return $url;
}

/**
 * Checks /assets/images/offers/<slug>.<ext> for a real, admin-uploaded
 * photo. This always wins over any automated source.
 */
function btc_get_uploaded_photo($slug) {
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $path = BTC_IMG_UPLOAD_DIR . '/' . $slug . '.' . $ext;
        if (is_file($path)) {
            return BTC_IMG_UPLOAD_URL . '/' . $slug . '.' . $ext . '?v=' . filemtime($path);
        }
    }
    return null;
}

/**
 * Builds one <img> tag using the full 4-tier chain.
 *
 * @param string $slug      Stable unique id for this image, used as the
 *                           override filename AND the Pexels cache key
 *                           context. Keep it short and descriptive, e.g.
 *                           'dubai-burj-khalifa', 'recruit-healthcare'.
 * @param string $keywords  Descriptive search phrase sent to Pexels /
 *                           used as the LoremFlickr fallback query. Be as
 *                           specific as possible — specificity is what
 *                           makes the automated placeholder accurate.
 * @param string $alt       Alt text.
 */
function btc_img_tag($slug, $keywords, $alt, $w = 600, $h = 400, $lock = 1, $class = '') {
    $primary = btc_get_uploaded_photo($slug);

    if (!$primary) {
        $primary = btc_get_pexels_photo($keywords);
    }

    if (!$primary) {
        // Tier 2 legacy fallback — identical to the site's old behaviour,
        // only reached if no Pexels key is configured or a lookup failed.
        $primary = "https://loremflickr.com/{$w}/{$h}/" . rawurlencode($keywords) . "?lock={$lock}";
    }

    $fallback  = "https://picsum.photos/seed/btc{$lock}/{$w}/{$h}";
    $classAttr = trim('btc-photo ' . $class);

    return '<img src="' . htmlspecialchars($primary, ENT_QUOTES) . '" data-fallback="' . $fallback . '" alt="'
        . htmlspecialchars($alt, ENT_QUOTES) . '" loading="lazy" decoding="async" class="' . $classAttr
        . '" onerror="btcImgFallback(this)">';
}