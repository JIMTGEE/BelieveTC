<?php
/**
 * ===========================================================================
 *  Pexels Cache Warmer
 * ===========================================================================
 *  Run this ONCE after you set BTC_PEXELS_API_KEY in includes/btc_images.php
 *  (and again any time you add new offers with new keywords). It resolves
 *  and caches every image used on offers.php up front, so real visitors
 *  never trigger a live Pexels lookup — every page load after this is
 *  served instantly from the tier-0/tier-1 result, exactly like a normal
 *  static image.
 *
 *  HOW TO RUN IT
 *   • Command line (recommended):  php warm_pexels_cache.php
 *   • Or visit it in a browser once, then delete it / block it via
 *     .htaccess — it has no destructive effect, but there's no reason to
 *     leave a public URL that makes ~65 outbound API calls exposed.
 *
 *  It is safe to re-run any time — already-cached, non-expired entries
 *  are skipped automatically by btc_get_pexels_photo().
 * ===========================================================================
 */

require_once __DIR__ . '/includes/btc_images.php';

if (empty(BTC_PEXELS_API_KEY)) {
    echo "No BTC_PEXELS_API_KEY set in includes/btc_images.php — nothing to warm.\n";
    echo "Get a free key at https://www.pexels.com/api/ and paste it in, then re-run this.\n";
    exit(1);
}

/**
 * IMPORTANT: keep this list in sync with the $slug => $keywords pairs used
 * in offers.php. If you add a new offer there, add its slug + keywords
 * here too so it gets pre-warmed instead of resolving on a live visitor's
 * page load.
 */
$images = [
    // Recruitment
    'recruit-skilled-workforce'   => 'factory technician working on industrial machine',
    'recruit-hospitality'         => 'hotel receptionist welcoming guest smiling',
    'recruit-construction'        => 'construction worker on site wearing hard hat',
    'recruit-healthcare'          => 'nurse caring for patient in hospital ward',
    'recruit-security'            => 'professional security guard in uniform',
    'recruit-domestic'            => 'housekeeper cleaning modern home',
    'recruit-logistics'           => 'warehouse worker with forklift and pallets',
    'recruit-professional'        => 'business professional working in modern office',

    // Visa & Immigration
    'visa-employment'             => 'work visa passport stamp office desk',
    'visa-tourist'                => 'tourist relaxing on tropical beach vacation',
    'visa-business'                => 'business handshake meeting agreement',
    'visa-student'                => 'university student on campus with books',
    'visa-family'                 => 'happy family together at home',
    'visa-residence'               => 'identity card and passport documents on desk',
    'visa-renewal'                => 'passport with visa stamps close up',

    // Travel & Tourism categories
    'travel-holiday-packages'     => 'beach resort with palm trees and pool',
    'travel-group-tours'          => 'tour group of travelers walking city street',
    'travel-family-vacations'     => 'family with children on vacation together',
    'travel-honeymoon'            => 'romantic couple walking on beach at sunset',
    'travel-corporate'            => 'business traveler with suitcase at airport',
    'travel-luxury'               => 'luxury hotel resort infinity pool',
    'travel-cruise'               => 'cruise ship sailing on open ocean',
    'travel-adventure'            => 'hikers on mountain trail adventure',
    'travel-cultural-heritage'    => 'ancient historical landmark ruins',

    // Dubai spotlight
    'dubai-burj-khalifa'          => 'Burj Khalifa skyscraper Dubai skyline',
    'dubai-mall'                  => 'Dubai Mall shopping interior',
    'dubai-museum-of-future'      => 'Museum of the Future Dubai building',
    'dubai-palm-jumeirah'         => 'Palm Jumeirah aerial island Dubai',
    'dubai-marina'                => 'Dubai Marina skyline waterfront at night',
    'dubai-desert-safari'         => 'desert safari dunes jeep sunset UAE',
    'dubai-aquaventure'           => 'Atlantis Aquaventure waterpark Dubai',
    'dubai-global-village'        => 'Global Village Dubai cultural park lights',
    'dubai-miracle-garden'        => 'Dubai Miracle Garden flowers landscape',
    'dubai-frame'                 => 'Dubai Frame landmark structure',
    'dubai-gold-souk'             => 'Gold Souk Dubai market jewellery',
    'dubai-dhow-cruise'           => 'traditional dhow boat cruise Dubai creek',
    'dubai-img-worlds'            => 'indoor theme park adventure rides',
    'dubai-ski-dubai'             => 'Ski Dubai indoor snow slope',

    // Destinations
    'dest-turkey'                 => 'Istanbul Turkey skyline and bazaar',
    'dest-egypt'                  => 'Pyramids of Giza Egypt desert',
    'dest-malaysia'               => 'Kuala Lumpur Petronas Towers skyline',
    'dest-singapore'              => 'Marina Bay Sands Singapore skyline',
    'dest-thailand'               => 'Bangkok Thailand temple architecture',
    'dest-georgia'                => 'Tbilisi Georgia old town skyline',
    'dest-azerbaijan'             => 'Baku Azerbaijan Flame Towers skyline',
    'dest-europe'                 => 'European city street architecture',

    // Relocation
    'relocation-airport-pickup'   => 'airport arrival hall travelers with luggage',
    'relocation-accommodation'    => 'modern furnished apartment bedroom',
    'relocation-insurance'        => 'insurance policy document and pen',
    'relocation-settlement'       => 'helping hands support assistance',
    'relocation-orientation'      => 'city map and compass navigation',
    'relocation-sim-card'         => 'mobile phone with sim card',
    'relocation-banking'          => 'modern bank building exterior',
    'relocation-transport'        => 'city public bus transportation',

    // Ghana services
    // NOTE: ghana-id-card, ghana-passport, ghana-birth-certificate,
    // ghana-police-clearance and ghana-embassy are the 5 items where a
    // stock photo can only ever be a generic placeholder — Pexels has no
    // photo of the actual Ghana Card, an actual Ghana passport booklet, an
    // actual Ghana birth certificate, an actual issued Ghana police
    // clearance certificate, or your actual embassy contact abroad. These
    // are the strongest candidates on the whole page for the Tier-0
    // upload override in includes/btc_images.php — drop a real photo of
    // each into assets/images/offers/<slug>.jpg and it will replace this
    // placeholder immediately, everywhere it's used.
    'ghana-id-card'               => 'national identification smart card close up',
    'ghana-passport'              => 'passport booklet document close up',
    'ghana-birth-certificate'     => 'official birth certificate document with seal',
    'ghana-police-clearance'      => 'official certificate document with stamp and seal on paper',
    'ghana-authentication'        => 'official stamp being pressed on document',
    'ghana-translation'           => 'dictionary and translation documents',
    'ghana-affidavit'             => 'signing legal document signature',
    'ghana-embassy'               => 'embassy building with flag at entrance',
    'ghana-courier'               => 'courier delivery van package',

    // Training
    'training-aviation-mgmt'      => 'commercial airplane on runway aviation',
    'training-airport-ops'        => 'airport terminal departures interior',
    'training-air-cargo'          => 'air cargo freight logistics airplane',
];

$total = count($images);
$done = 0;
$resolved = 0;

foreach ($images as $slug => $keywords) {
    $done++;
    $url = btc_get_pexels_photo($keywords);
    $status = $url ? "OK" : "FAILED (will use fallback chain)";
    echo sprintf("[%d/%d] %-28s -> %s\n", $done, $total, $slug, $status);
    if ($url) $resolved++;
    usleep(150000); // be polite to the free-tier rate limit (200/hr)
}

echo "\nDone. $resolved / $total images cached successfully.\n";
if ($resolved < $total) {
    echo "Any that failed will automatically use LoremFlickr, then Picsum, then the branded panel — the page still won't break.\n";
}