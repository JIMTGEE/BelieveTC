<?php
/* =====================================================================
 * services.php — Admin Services Dashboard (Passport / Birth Cert / Pre-Screening)
 * Believe Travel Consult
 *
 * ASSUMPTIONS (each is a one-line fix if wrong):
 *   1. Lives in /admin/ alongside admin_header.php, login.php.
 *   2. Shared PDO connection at ../includes/db.php, defines $pdo.
 *      PDO must be configured with:
 *        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
 *      (if it isn't, add it — this file relies on DB errors throwing).
 *   3. Logged-in admin marked by $_SESSION['admin_id'].
 *   4. admin_header.php prints the full page shell (no admin_footer.php
 *      expected) — same as before.
 *   5. Upload root is ../uploads/{table_name}/{ref_code}/ — same as before.
 * ===================================================================== */

declare(strict_types=1);
session_start();

require_once __DIR__ . '/../includes/db.php'; // must define $pdo (PDO)

// ---- ADMIN AUTH CHECK ----
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Make sure PDO actually throws on error, and always returns clean
// associative arrays — if db.php didn't set these, force them here so
// (a) a bad query never silently returns false / gets treated as "no
// rows", and (b) fetch() never returns the FETCH_BOTH default, which
// mixes in numeric-indexed duplicates of every column and breaks any
// code that iterates over column names (a real bug class if these
// aren't pinned explicitly).
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pageTitle = 'Manage Services';

/* =====================================================================
 * CONSTANTS / WHITELISTS  — every enum-like value in the DB has a
 * matching whitelist here. Nothing gets written to the DB unless it
 * passes through one of these lists.
 * ===================================================================== */
const STATUS_OPTIONS       = ['Pending', 'Processing', 'Approved', 'Rejected'];
const FINAL_RESULT_OPTIONS = ['Hired', 'Rejected', 'Not Fit', 'Hold'];
const SEX_OPTIONS           = ['Male', 'Female'];
const YES_NO_OPTIONS        = ['Yes', 'No'];
const SERVICE_TYPES         = ['passport', 'birth', 'prescreen'];

const TABLE_MAP = [
    'passport'  => 'passport_applications',
    'birth'     => 'birth_certificate_applications',
    'prescreen' => 'pre_screening_applications',
];

/*
 * UPLOAD_FOLDER_MAP — the folder each type's files actually live under
 * inside public_html/uploads/. Confirmed against real saved paths:
 *   uploads/passport_applications/{ref_code}/{hash}.{ext}
 *   uploads/birth_certificate_applications/{ref_code}/{hash}.{ext}
 * Pre-screening's public form saves into uploads/pre_screening/{ref_code}/
 * (a different, shorter folder name than its table name) — kept as its
 * own map so the two naming schemes never get confused.
 */
const UPLOAD_FOLDER_MAP = [
    'passport'  => 'passport_applications',
    'birth'     => 'birth_certificate_applications',
    'prescreen' => 'pre_screening',
];
const TYPE_LABELS = [
    'passport'  => 'Passport',
    'birth'     => 'Birth Certificate',
    'prescreen' => 'Pre-Screening',
];
const TYPE_ICONS = [
    'passport'  => 'fa-passport',
    'birth'     => 'fa-baby',
    'prescreen' => 'fa-user-check',
];
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5MB
const ALLOWED_UPLOAD_MIME = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'application/pdf' => 'pdf',
];
// Extensions we'll treat as previewable images vs. PDF vs. unknown/other,
// purely for icon/thumbnail choice — not a security allowlist (that's
// still enforced at upload time via finfo in admin_store_upload()).
const PREVIEWABLE_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

const PER_PAGE = 20;
const LETTERHEAD_ADDRESS = 'Tema Golf City &middot; Kumasi Ahodwo &middot; Kasoa Adam Nana';
const LETTERHEAD_PHONE   = '+233 596 204 991';
const PAPER_FORM_TITLES = [
    'passport'  => ['Ghana Passport Application Form', 'Express Processing'],
    'birth'     => ['Official Birth Certificate Biometric Form', ''],
    'prescreen' => ['Pre-Screening Application Form', ''],
];

/* =====================================================================
 * CSRF
 * ===================================================================== */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function csrf_token(): string { return $_SESSION['csrf_token']; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">'; }
function csrf_verify(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        throw new RuntimeException('Your session expired or the form was resubmitted. Please try again.');
    }
}

/* =====================================================================
 * HELPERS
 * ===================================================================== */
function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

/** Restrict a value to a whitelist; returns $default (nullable) if not present. */
function pick_enum(array $allowed, $value, ?string $default = null): ?string {
    return (is_string($value) && in_array($value, $allowed, true)) ? $value : $default;
}

/** Only ever redirect to a same-file relative URL — blocks open-redirect via redirect_to. */
function redirect_back(string $fallback = 'services.php'): void {
    $target = $_POST['redirect_to'] ?? $fallback;
    if (!is_string($target) || !preg_match('~^services\.php(\?[A-Za-z0-9_=&%.\-]*)?$~', $target)) {
        $target = $fallback;
    }
    header('Location: ' . $target);
    exit;
}

function generate_ref_code(PDO $pdo, string $table, string $prefix): string {
    $check = $pdo->prepare("SELECT id FROM {$table} WHERE ref_code = ? LIMIT 1");
    do {
        $ref = $prefix . date('Y') . '-' . random_int(100000, 999999);
        $check->execute([$ref]);
    } while ($check->fetch());
    return $ref;
}

/**
 * Securely store an uploaded file.
 *  - Verifies real file content (not just extension) via finfo.
 *  - Enforces size limit.
 *  - Writes with a random filename (no user-controlled name ever hits disk).
 *  - Drops a .htaccess in the target folder disabling script execution.
 * Returns the stored relative path, or $existing on failure (with a message
 * appended to $errors so failures are never silent).
 */
function admin_store_upload(
    string $fileKey,
    string $uploadRoot,
    string $refCode,
    string $folder,
    ?string $existing,
    array &$errors
): ?string {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existing;
    }
    $file = $_FILES[$fileKey];
    $label = ucwords(str_replace('_', ' ', $fileKey));

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "{$label}: upload failed (error code {$file['error']}).";
        return $existing;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $errors[] = "{$label}: invalid upload.";
        return $existing;
    }
    if ($file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) {
        $errors[] = "{$label}: file must be under 5MB.";
        return $existing;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']) ?: '';
    if (!isset(ALLOWED_UPLOAD_MIME[$mime])) {
        $errors[] = "{$label}: only JPG, PNG, or PDF files are allowed.";
        return $existing;
    }
    $ext = ALLOWED_UPLOAD_MIME[$mime];

    if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0755, true) && !is_dir($uploadRoot)) {
        $errors[] = "{$label}: could not create the upload folder.";
        return $existing;
    }
    @chmod($uploadRoot, 0755);
    ensure_uploads_htaccess($uploadRoot);

    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destPath = $uploadRoot . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        $errors[] = "{$label}: could not save the file.";
        return $existing;
    }
    // move_uploaded_file() leaves permissions to the server's umask, which on
    // some hosts (PHP-FPM running as a different user than the webserver, or
    // a restrictive umask) produces a file the *webserver* can't read even
    // though PHP itself can. That shows up as "found on disk" server-side
    // but a broken <img> in the browser. Force it world-readable explicitly.
    @chmod($destPath, 0644);
    return $folder . '/' . $refCode . '/' . $safeName;
}

/** Multi-file variant for passport photos — returns JSON array of paths, merged with existing. */
function admin_store_multi_upload(
    string $fileKey,
    string $uploadRoot,
    string $refCode,
    string $folder,
    ?string $existingJson,
    array &$errors
): ?string {
    $existingPaths = [];
    if ($existingJson) {
        $decoded = json_decode($existingJson, true);
        if (is_array($decoded)) $existingPaths = $decoded;
    }
    if (empty($_FILES[$fileKey]) || !is_array($_FILES[$fileKey]['name'] ?? null)) {
        return $existingJson;
    }
    $count = count($_FILES[$fileKey]['name']);
    $anyNew = false;
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES[$fileKey]['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $single = [
            'name'     => $_FILES[$fileKey]['name'][$i],
            'type'     => $_FILES[$fileKey]['type'][$i],
            'tmp_name' => $_FILES[$fileKey]['tmp_name'][$i],
            'error'    => $_FILES[$fileKey]['error'][$i],
            'size'     => $_FILES[$fileKey]['size'][$i],
        ];
        $_FILES['__single_tmp__'] = $single;
        $path = admin_store_upload('__single_tmp__', $uploadRoot, $refCode, $folder, null, $errors);
        if ($path !== null) {
            $existingPaths[] = $path;
            $anyNew = true;
        }
    }
    unset($_FILES['__single_tmp__']);
    if (!$anyNew && empty($existingPaths)) return null;
    return json_encode(array_values($existingPaths));
}

function pretty_json_entries(?string $json): array {
    if (!$json) return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function json_or_null(string $raw) {
    $raw = trim($raw);
    if ($raw === '' || $raw === '[]') return null;
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) return '__INVALID__';
    return json_encode(array_values($decoded));
}

function clamp_rating($val): ?int {
    $val = trim((string)($val ?? ''));
    if ($val === '' || !ctype_digit($val)) return null;
    $n = (int)$val;
    return ($n >= 1 && $n <= 5) ? $n : null;
}

function valid_date_ymd(string $val): bool {
    $d = DateTime::createFromFormat('Y-m-d', $val);
    return $d && $d->format('Y-m-d') === $val;
}

/** Builds one branch of the UNION list query with its own uniquely-named params. */
function build_type_query(
    string $type,
    string $table,
    string $nameExpr,
    string $contactExpr,
    string $statusExpr,
    array $searchCols,
    string $q,
    string $statusFilter,
    array &$params,
    string $suffix
): string {
    $where = ['1=1'];
    if ($q !== '') {
        $ors = [];
        foreach ($searchCols as $i => $col) {
            $ph = ":q{$suffix}{$i}";
            $ors[] = "{$col} LIKE {$ph}";
            $params[$ph] = '%' . $q . '%';
        }
        $where[] = '(' . implode(' OR ', $ors) . ')';
    }
    if ($statusFilter !== '') {
        $ph = ":s{$suffix}";
        $where[] = "{$statusExpr} = {$ph}";
        $params[$ph] = $statusFilter;
    }
    return "SELECT '{$type}' AS type, id, ref_code, {$nameExpr} AS name, {$contactExpr} AS contact, "
         . "{$statusExpr} AS status, created_at FROM {$table} WHERE " . implode(' AND ', $where);
}

/* =====================================================================
 * DOCUMENT RESOLUTION — turns whatever is stored in the DB (a bare
 * filename, a full "uploads/xxx/ref/file.ext" path, or a JSON array of
 * either) into verified, existing files on disk, with the correct
 * public URL. Nothing is ever displayed unless file_exists() confirms
 * it's actually there — no more broken-image icons.
 *
 * HARDENED: the previous version assumed exactly one upload root
 * (__DIR__ . '/../uploads') and exactly one relative layout
 * (folder/ref_code/file). On real hosting, either of those can be
 * slightly off (admin/ nested deeper, uploads/ symlinked, an old file
 * saved flat without a ref subfolder, etc.) and the old code would
 * silently show "file not found" with no way to tell why. This version
 * tries several plausible roots and layouts, and — critically — logs
 * every miss with the exact paths it tried, so a real problem shows up
 * in the PHP error log instead of just a blank thumbnail.
 * ===================================================================== */

/**
 * Writes a *safe*, cross-server .htaccess into an uploads subfolder that
 * blocks script execution without breaking normal file serving.
 *
 * The previous version used `php_flag engine off`. That directive only
 * works when Apache is running mod_php. On PHP-FPM, LiteSpeed/LSAPI, or
 * suPHP setups (very common on shared cPanel hosting) an unsupported
 * php_flag/php_value in .htaccess causes Apache to return a hard 500 for
 * EVERY request to that folder — including plain image requests. That
 * produces exactly this symptom: the file genuinely exists and PHP's own
 * is_file() check (server-side) confirms it, but the browser's direct
 * request for the image fails outright.
 *
 * `<FilesMatch>` + `Require all denied` (with an Apache-2.2-compatible
 * fallback) only blocks the listed script extensions and works
 * identically under mod_php, PHP-FPM, and LiteSpeed — images and PDFs are
 * untouched.
 */
function ensure_uploads_htaccess(string $uploadRoot): void {
    $htaccess = $uploadRoot . '/.htaccess';
    $safeRules = <<<HT
# Block execution of scripts in this uploads folder — images/PDFs are unaffected.
<FilesMatch "\\.(php|php\\d?|phtml|pl|py|cgi|asp|aspx|sh)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>
Options -ExecCGI -Indexes
HT;
    $existing = file_exists($htaccess) ? @file_get_contents($htaccess) : null;
    // Rewrite if missing OR if it's the old, unsafe php_flag-based version —
    // this self-heals folders created by an earlier deploy of this file.
    if ($existing === null || strpos($existing, 'php_flag') !== false) {
        @file_put_contents($htaccess, $safeRules);
        @chmod($htaccess, 0644);
    }
}

/**
 * All plausible filesystem roots for the uploads/ folder, in priority
 * order. We no longer trust a single assumption about where admin/
 * sits relative to uploads/ — we try the documented layout first, then
 * fall back to the web server's document root, then to walking a
 * couple of levels up from this file looking for an uploads/ directory.
 * Returns real, existing directory paths only (deduplicated).
 */
function uploads_fs_roots(): array {
    $candidates = [];

    // 1) Documented layout: admin/ sits directly beside uploads/.
    $candidates[] = __DIR__ . '/../uploads';

    // 2) Web server document root + /uploads (covers admin/ nested
    //    deeper than one level, e.g. admin/services/ or similar).
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/uploads';
    }

    // 3) Walk up from this file (up to 4 levels) looking for the first
    //    "uploads" directory we find — a self-healing fallback for
    //    layouts nobody documented.
    $walk = __DIR__;
    for ($i = 0; $i < 4; $i++) {
        $walk = dirname($walk);
        $candidates[] = $walk . '/uploads';
    }

    $resolved = [];
    foreach ($candidates as $c) {
        $real = realpath($c);
        if ($real !== false && is_dir($real) && !in_array($real, $resolved, true)) {
            $resolved[] = $real;
        }
    }
    return $resolved;
}

/** Kept for any external callers — returns the first (documented) root, resolved if possible. */
function uploads_fs_root(): string {
    $roots = uploads_fs_roots();
    if (!empty($roots)) return $roots[0];
    return realpath(__DIR__ . '/../uploads') ?: (__DIR__ . '/../uploads');
}

/**
 * Resolve ONE stored value (bare filename OR a path that may already
 * include "uploads/...") against a given type's upload folder + ref
 * code, trying every plausible on-disk location across every plausible
 * uploads root. Returns null if no candidate actually exists on disk —
 * and logs exactly what it tried, so a genuine mismatch (wrong root,
 * wrong folder name, file actually missing) is visible in the PHP
 * error log instead of silently showing "not found" in the UI.
 *
 * Also self-heals two very common "found on disk but browser can't load
 * it" causes the moment a document is viewed:
 *   - Unreadable file permissions (public-form uploads never chmod()'d
 *     the file — depending on server umask that can leave it at 0600).
 *   - A missing/unsafe .htaccess in the containing folder.
 *
 * Returns an array: ['rel' => 'uploads/xxx/ref/file.ext', 'ext' => 'jpg']
 */
function resolve_one_document(string $storedValue, string $folder, string $refCode): ?array {
    $storedValue = trim($storedValue);
    if ($storedValue === '') return null;

    // Never trust a stored value as a filesystem path directly (could contain
    // ../ traversal) — always rebuild from a whitelisted folder + basename.
    $bareName = basename(str_replace('\\', '/', $storedValue));
    if ($bareName === '' || $bareName === '.' || $bareName === '..') return null;

    // The stored value might itself carry a ref-code-looking directory
    // segment that differs from the one we were given (e.g. an older
    // record, or a value entered by hand in the edit form). Pull that
    // out too and try it as a secondary ref code, in case it's more
    // accurate than what the DB row's own ref_code column says.
    $storedDir = dirname(str_replace('\\', '/', $storedValue));
    $storedRefGuess = ($storedDir !== '.' && $storedDir !== '/') ? basename($storedDir) : null;

    $relCandidates = [
        // Standard convention: uploads/{folder}/{ref_code}/{file}
        $folder . '/' . $refCode . '/' . $bareName,
        // Fallback: file saved flat in the folder without a ref subfolder
        $folder . '/' . $bareName,
    ];
    if ($storedRefGuess && $storedRefGuess !== $refCode) {
        $relCandidates[] = $folder . '/' . $storedRefGuess . '/' . $bareName;
    }

    $tried = [];
    foreach (uploads_fs_roots() as $root) {
        foreach ($relCandidates as $rel) {
            $fsPath = $root . '/' . $rel;
            $tried[] = $fsPath;
            if (is_file($fsPath)) {
                // Self-heal: make sure the webserver can actually read this
                // file and that its folder has a safe (non-500-inducing)
                // .htaccess, so a document that resolves server-side never
                // silently fails to load in the browser.
                if (!is_readable($fsPath)) {
                    @chmod($fsPath, 0644);
                    if (!is_readable($fsPath)) {
                        error_log('[services.php] document found but NOT READABLE by webserver after chmod attempt: ' . $fsPath);
                    }
                }
                ensure_uploads_htaccess(dirname($fsPath));

                return [
                    'rel' => 'uploads/' . $rel,
                    'ext' => strtolower(pathinfo($bareName, PATHINFO_EXTENSION)),
                ];
            }
        }
    }

    // Nothing matched anywhere we know to look — log it so this is
    // diagnosable from the server side instead of a silent blank.
    error_log('[services.php] document not found on disk. stored="' . $storedValue . '" tried: ' . implode(' | ', $tried));
    return null;
}

/**
 * Resolve a DB column's value — which may be a single stored path/filename
 * OR a JSON array of them (multi-upload fields like passport_photos) —
 * into a list of verified, existing documents.
 * Returns a list of ['url' => '../uploads/...', 'ext' => 'jpg', 'is_pdf' => bool].
 */
function resolve_document_field(?string $dbValue, string $folder, string $refCode, string $baseUrl): array {
    if (!$dbValue) return [];
    $decoded = json_decode($dbValue, true);
    $values = is_array($decoded) ? $decoded : [$dbValue];

    $out = [];
    foreach ($values as $v) {
        if (!is_string($v) || $v === '') continue;
        $resolved = resolve_one_document($v, $folder, $refCode);
        if ($resolved === null) continue;
        $out[] = [
            'url'    => $baseUrl . $resolved['rel'],
            'ext'    => $resolved['ext'],
            'is_pdf' => $resolved['ext'] === 'pdf',
        ];
    }
    return $out;
}

/**
 * Pre-screening has no known document columns in the current schema, and
 * the exact column names aren't guessable — so instead of hard-coding
 * columns that might not exist, this scans every string-valued column on
 * the record and keeps only the ones that actually resolve to a real file
 * under uploads/pre_screening/{ref_code}/. A column can only match if its
 * value is genuinely the name of a file that exists on disk, so this is
 * safe even though it's schema-agnostic — it can never mistake ordinary
 * text for a document.
 * Returns [ ['key' => 'ghana_card_photo', 'label' => 'Ghana Card Photo', 'url' => ..., 'is_pdf' => bool], ... ]
 */
function discover_prescreen_documents(array $record, string $refCode, string $baseUrl): array {
    $folder = UPLOAD_FOLDER_MAP['prescreen'];
    // Columns that are never document paths even though they're strings —
    // skip them so we don't waste file_exists() calls on obviously-textual data.
    $skipKeys = [
        'id', 'ref_code', 'status', 'final_result', 'created_at', 'updated_at',
        'last_name', 'first_name', 'middle_name', 'sex', 'dob', 'ghana_card',
        'phone', 'work_phone', 'email', 'disability', 'criminal_record',
        'address', 'city', 'country', 'gps',
        'emergency_name', 'emergency_relationship', 'emergency_address', 'emergency_city',
        'emergency_country', 'emergency_ghana_card', 'emergency_home_phone',
        'emergency_work_phone', 'emergency_email', 'emergency_gps',
        'medical_history', 'education_history', 'former_employers', 'references',
        'rating_neatness', 'rating_character', 'rating_personality', 'rating_ability',
        'rating_communication', 'rating_confidence',
        'position_applied', 'auth_name', 'signature', 'interviewed_by', 'interview_date',
        'remark', 'approved_by',
    ];

    $found = [];
    foreach ($record as $key => $value) {
        if (in_array($key, $skipKeys, true)) continue;
        if (!is_string($value) || $value === '') continue;

        $resolved = resolve_one_document($value, $folder, $refCode);
        if ($resolved === null) continue;

        $found[] = [
            'key'    => $key,
            'label'  => ucwords(str_replace('_', ' ', $key)),
            'url'    => $baseUrl . $resolved['rel'],
            'is_pdf' => $resolved['ext'] === 'pdf',
        ];
    }
    return $found;
}

/* =====================================================================
 * AJAX ENDPOINT — server-verified document previews.
 * GET services.php?ajax=doc_preview&type=passport|birth|prescreen&id=123
 * Returns JSON describing every document field for that record, each
 * already checked against the filesystem, so the front-end never has
 * to guess a path or show a broken-image icon.
 * ===================================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'doc_preview') {
    header('Content-Type: application/json');

    $ajaxType = pick_enum(SERVICE_TYPES, $_GET['type'] ?? null);
    $ajaxId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (!$ajaxType || $ajaxId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request.']);
        exit;
    }

    try {
        $table = TABLE_MAP[$ajaxType];
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$ajaxId]);
        $record = $stmt->fetch();

        if (!$record) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Record not found.']);
            exit;
        }

        $refCode = (string) $record['ref_code'];
        $folder  = UPLOAD_FOLDER_MAP[$ajaxType];
        $baseUrl = '../'; // admin/ -> public_html/

        $groups = [];

        if ($ajaxType === 'passport') {
            $fieldLabels = [
                'birth_certificate' => 'Birth Certificate',
                'guarantor_id'      => "Guarantor's ID",
                'proof_address'     => 'Proof of Address',
            ];
            foreach ($fieldLabels as $col => $label) {
                $groups[] = [
                    'key'   => $col,
                    'label' => $label,
                    'items' => resolve_document_field($record[$col] ?? null, $folder, $refCode, $baseUrl),
                ];
            }
            $groups[] = [
                'key'   => 'passport_photos',
                'label' => 'Passport Photos',
                'items' => resolve_document_field($record['passport_photos'] ?? null, $folder, $refCode, $baseUrl),
            ];
        } elseif ($ajaxType === 'birth') {
            $fieldLabels = [
                'father_id' => "Father's ID",
                'mother_id' => "Mother's ID",
            ];
            foreach ($fieldLabels as $col => $label) {
                $groups[] = [
                    'key'   => $col,
                    'label' => $label,
                    'items' => resolve_document_field($record[$col] ?? null, $folder, $refCode, $baseUrl),
                ];
            }
        } else { // prescreen — auto-discovered, one group per matched column
            $discovered = discover_prescreen_documents($record, $refCode, $baseUrl);
            foreach ($discovered as $doc) {
                $groups[] = [
                    'key'   => $doc['key'],
                    'label' => $doc['label'],
                    'items' => [['url' => $doc['url'], 'ext' => pathinfo($doc['url'], PATHINFO_EXTENSION), 'is_pdf' => $doc['is_pdf']]],
                ];
            }
        }

        echo json_encode(['success' => true, 'type' => $ajaxType, 'id' => $ajaxId, 'groups' => $groups]);
    } catch (Throwable $e) {
        error_log('[services.php] doc_preview ajax failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Could not load documents right now.']);
    }
    exit;
}

/* =====================================================================
 * AJAX DIAGNOSTIC ENDPOINT — for when a document genuinely isn't
 * showing and you need to see WHY without SSH/log access.
 * GET services.php?ajax=doc_debug&type=passport|birth|prescreen&id=123
 * Shows: every raw DB value that might be a document, every uploads
 * root this server actually found on disk, and every candidate path
 * tried for each value plus whether it existed. Same admin-only auth
 * as the rest of the file (session check already ran at the top).
 * ===================================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'doc_debug') {
    header('Content-Type: application/json');

    $ajaxType = pick_enum(SERVICE_TYPES, $_GET['type'] ?? null);
    $ajaxId   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if (!$ajaxType || $ajaxId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request. Pass ?ajax=doc_debug&type=passport&id=123']);
        exit;
    }

    try {
        $table = TABLE_MAP[$ajaxType];
        $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$ajaxId]);
        $record = $stmt->fetch();

        if (!$record) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Record not found.']);
            exit;
        }

        $refCode = (string) $record['ref_code'];
        $folder  = UPLOAD_FOLDER_MAP[$ajaxType];
        $roots   = uploads_fs_roots();

        // Which raw columns we consider "documents" for this type.
        $docCols = $ajaxType === 'passport'
            ? ['birth_certificate', 'guarantor_id', 'proof_address', 'passport_photos']
            : ($ajaxType === 'birth' ? ['father_id', 'mother_id'] : array_keys($record));

        $report = [];
        foreach ($docCols as $col) {
            if (!array_key_exists($col, $record)) continue;
            $rawValue = $record[$col];
            if ($rawValue === null || $rawValue === '') continue;
            if (!is_string($rawValue)) continue;

            $decoded = json_decode($rawValue, true);
            $values = is_array($decoded) ? $decoded : [$rawValue];

            $entries = [];
            foreach ($values as $v) {
                if (!is_string($v) || $v === '') continue;
                $bareName = basename(str_replace('\\', '/', $v));
                $candidates = [];
                foreach ($roots as $root) {
                    foreach ([$folder . '/' . $refCode . '/' . $bareName, $folder . '/' . $bareName] as $rel) {
                        $fsPath = $root . '/' . $rel;
                        $exists = is_file($fsPath);
                        $candidates[] = [
                            'path'        => $fsPath,
                            'exists'      => $exists,
                            'readable'    => $exists ? is_readable($fsPath) : null,
                            'perms'       => $exists ? substr(sprintf('%o', @fileperms($fsPath)), -4) : null,
                            'owner_uid'   => $exists ? @fileowner($fsPath) : null,
                            'php_uid'     => function_exists('posix_geteuid') ? @posix_geteuid() : null,
                        ];
                    }
                }
                $entries[] = ['stored_value' => $v, 'basename_used' => $bareName, 'candidates_tried' => $candidates];
            }
            if ($ajaxType === 'prescreen' && empty($entries)) continue; // skip plain-text columns for prescreen scan
            $report[] = ['column' => $col, 'raw_db_value' => $rawValue, 'resolved_attempts' => $entries];
        }

        echo json_encode([
            'success'          => true,
            'type'             => $ajaxType,
            'id'               => $ajaxId,
            'ref_code'         => $refCode,
            'upload_folder'    => $folder,
            'uploads_roots_found_on_disk' => $roots,
            'columns_checked'  => $report,
            'note'             => 'readable:false or a perms value that is not 644/755 means the webserver user likely cannot read this file even though PHP found it — that produces "found on disk" here but a broken <img> in the browser.',
        ], JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        error_log('[services.php] doc_debug ajax failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

/* =====================================================================
 * POST HANDLERS (create / update / delete / quick status)
 * ===================================================================== */
$formError   = null;
$formErrors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    $formType = $_POST['form_type'];

    try {
        csrf_verify();

        // ---------------- PASSPORT ----------------
        if ($formType === 'save_passport') {
            $isEdit = !empty($_POST['id']);
            $id = $isEdit ? (int) $_POST['id'] : null;

            $requiredFields = ['applicant_name', 'dob', 'place_of_birth', 'phone_primary', 'residential_address',
                'father_name', 'father_hometown', 'father_residential_address', 'father_phone',
                'mother_name', 'mother_hometown', 'mother_residential_address',
                'grandmother_name', 'grandmother_hometown', 'grandfather_name', 'grandfather_hometown',
                'guarantor_name', 'guarantor_occupation', 'guarantor_phone', 'guarantor_residential_address'];
            $optionalFields = ['package', 'remarks'];

            $data = [];
            foreach (array_merge($requiredFields, $optionalFields) as $f) {
                $data[$f] = trim((string)($_POST[$f] ?? ''));
            }
            $data['declaration_accepted'] = isset($_POST['declaration_accepted']) ? 1 : 0;
            $data['status'] = pick_enum(STATUS_OPTIONS, $_POST['status'] ?? null, 'Pending');

            $missing = [];
            foreach ($requiredFields as $f) {
                if ($data[$f] === '') $missing[] = ucwords(str_replace('_', ' ', $f));
            }
            if (!valid_date_ymd($data['dob'])) {
                $missing[] = 'a valid Date of Birth (YYYY-MM-DD)';
            }
            if ($missing) {
                throw new RuntimeException('Please provide: ' . implode(', ', $missing) . '.');
            }
            if (!preg_match('/^[0-9+\-\s()]{6,20}$/', $data['phone_primary'])) {
                throw new RuntimeException('Primary phone number looks invalid.');
            }

            if ($isEdit) {
                $existing = $pdo->prepare("SELECT ref_code, birth_certificate, passport_photos, guarantor_id, proof_address FROM passport_applications WHERE id = ?");
                $existing->execute([$id]);
                $row = $existing->fetch();
                if (!$row) throw new RuntimeException('Record not found.');
                $refCode = $row['ref_code'];
            } else {
                $refCode = generate_ref_code($pdo, 'passport_applications', 'BTC-PASSPORT-');
                $row = ['birth_certificate' => null, 'passport_photos' => null, 'guarantor_id' => null, 'proof_address' => null];
            }

            $uploadErrors = [];
            $uploadRoot = __DIR__ . '/../uploads/passport_applications/' . $refCode;
            $data['birth_certificate'] = admin_store_upload('birth_certificate', $uploadRoot, $refCode, 'uploads/passport_applications', $row['birth_certificate'], $uploadErrors);
            $data['guarantor_id']      = admin_store_upload('guarantor_id', $uploadRoot, $refCode, 'uploads/passport_applications', $row['guarantor_id'], $uploadErrors);
            $data['proof_address']     = admin_store_upload('proof_address', $uploadRoot, $refCode, 'uploads/passport_applications', $row['proof_address'], $uploadErrors);
            $data['passport_photos']   = admin_store_multi_upload('passport_photos', $uploadRoot, $refCode, 'uploads/passport_applications', $row['passport_photos'], $uploadErrors);
            if ($uploadErrors) $formErrors = array_merge($formErrors, $uploadErrors);

            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE passport_applications SET
                    applicant_name=:applicant_name, dob=:dob, place_of_birth=:place_of_birth, phone_primary=:phone_primary,
                    residential_address=:residential_address, father_name=:father_name, father_hometown=:father_hometown,
                    father_residential_address=:father_residential_address, father_phone=:father_phone,
                    mother_name=:mother_name, mother_hometown=:mother_hometown, mother_residential_address=:mother_residential_address,
                    grandmother_name=:grandmother_name, grandmother_hometown=:grandmother_hometown,
                    grandfather_name=:grandfather_name, grandfather_hometown=:grandfather_hometown,
                    guarantor_name=:guarantor_name, guarantor_occupation=:guarantor_occupation,
                    guarantor_phone=:guarantor_phone, guarantor_residential_address=:guarantor_residential_address,
                    declaration_accepted=:declaration_accepted, package=:package,
                    birth_certificate=:birth_certificate, passport_photos=:passport_photos,
                    guarantor_id=:guarantor_id, proof_address=:proof_address,
                    status=:status, remarks=:remarks
                    WHERE id=:id");
                $data['id'] = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO passport_applications (
                    ref_code, applicant_name, dob, place_of_birth, phone_primary, residential_address,
                    father_name, father_hometown, father_residential_address, father_phone,
                    mother_name, mother_hometown, mother_residential_address,
                    grandmother_name, grandmother_hometown, grandfather_name, grandfather_hometown,
                    guarantor_name, guarantor_occupation, guarantor_phone, guarantor_residential_address,
                    declaration_accepted, package, birth_certificate, passport_photos, guarantor_id, proof_address,
                    status, remarks
                ) VALUES (
                    :ref_code, :applicant_name, :dob, :place_of_birth, :phone_primary, :residential_address,
                    :father_name, :father_hometown, :father_residential_address, :father_phone,
                    :mother_name, :mother_hometown, :mother_residential_address,
                    :grandmother_name, :grandmother_hometown, :grandfather_name, :grandfather_hometown,
                    :guarantor_name, :guarantor_occupation, :guarantor_phone, :guarantor_residential_address,
                    :declaration_accepted, :package, :birth_certificate, :passport_photos, :guarantor_id, :proof_address,
                    :status, :remarks
                )");
                $data['ref_code'] = $refCode;
            }
            $stmt->execute($data);
            $newId = $isEdit ? $id : (int)$pdo->lastInsertId();

            if ($formErrors) {
                $_SESSION['flash_warning'] = implode(' ', $formErrors);
            }
            redirect_back("services.php?action=view&type=passport&id={$newId}&saved=1");
        }

        // ---------------- BIRTH CERTIFICATE ----------------
        if ($formType === 'save_birth') {
            $isEdit = !empty($_POST['id']);
            $id = $isEdit ? (int) $_POST['id'] : null;

            $requiredFields = ['child_name', 'dob', 'place_of_birth', 'father_name', 'father_occupation', 'father_religion',
                'mother_maiden_name', 'informant', 'telephone_numbers'];
            $optionalFields = ['package', 'remarks'];
            $data = [];
            foreach (array_merge($requiredFields, $optionalFields) as $f) {
                $data[$f] = trim((string)($_POST[$f] ?? ''));
            }
            $data['declaration_accepted'] = isset($_POST['declaration_accepted']) ? 1 : 0;
            $data['status'] = pick_enum(STATUS_OPTIONS, $_POST['status'] ?? null, 'Pending');
            $data['parents_alive'] = pick_enum(YES_NO_OPTIONS, $_POST['parents_alive'] ?? null);

            $missing = [];
            foreach ($requiredFields as $f) {
                if ($data[$f] === '') $missing[] = ucwords(str_replace('_', ' ', $f));
            }
            if (!valid_date_ymd($data['dob'])) $missing[] = 'a valid Date of Birth (YYYY-MM-DD)';
            if ($data['parents_alive'] === null) $missing[] = 'Parents Alive (Yes/No)';
            if ($missing) {
                throw new RuntimeException('Please provide: ' . implode(', ', $missing) . '.');
            }

            if ($isEdit) {
                $existing = $pdo->prepare("SELECT ref_code, father_id, mother_id FROM birth_certificate_applications WHERE id = ?");
                $existing->execute([$id]);
                $row = $existing->fetch();
                if (!$row) throw new RuntimeException('Record not found.');
                $refCode = $row['ref_code'];
            } else {
                $refCode = generate_ref_code($pdo, 'birth_certificate_applications', 'BTC-BC-');
                $row = ['father_id' => null, 'mother_id' => null];
            }

            $uploadErrors = [];
            $uploadRoot = __DIR__ . '/../uploads/birth_certificate_applications/' . $refCode;
            $data['father_id'] = admin_store_upload('father_id', $uploadRoot, $refCode, 'uploads/birth_certificate_applications', $row['father_id'], $uploadErrors);
            $data['mother_id'] = admin_store_upload('mother_id', $uploadRoot, $refCode, 'uploads/birth_certificate_applications', $row['mother_id'], $uploadErrors);
            if ($uploadErrors) $formErrors = array_merge($formErrors, $uploadErrors);

            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE birth_certificate_applications SET
                    child_name=:child_name, dob=:dob, place_of_birth=:place_of_birth,
                    father_name=:father_name, father_occupation=:father_occupation, father_religion=:father_religion,
                    mother_maiden_name=:mother_maiden_name, informant=:informant, parents_alive=:parents_alive,
                    telephone_numbers=:telephone_numbers, declaration_accepted=:declaration_accepted, package=:package,
                    father_id=:father_id, mother_id=:mother_id, status=:status, remarks=:remarks
                    WHERE id=:id");
                $data['id'] = $id;
            } else {
                $stmt = $pdo->prepare("INSERT INTO birth_certificate_applications (
                    ref_code, child_name, dob, place_of_birth, father_name, father_occupation, father_religion,
                    mother_maiden_name, informant, parents_alive, telephone_numbers, declaration_accepted, package,
                    father_id, mother_id, status, remarks
                ) VALUES (
                    :ref_code, :child_name, :dob, :place_of_birth, :father_name, :father_occupation, :father_religion,
                    :mother_maiden_name, :informant, :parents_alive, :telephone_numbers, :declaration_accepted, :package,
                    :father_id, :mother_id, :status, :remarks
                )");
                $data['ref_code'] = $refCode;
            }
            $stmt->execute($data);
            $newId = $isEdit ? $id : (int)$pdo->lastInsertId();

            if ($formErrors) {
                $_SESSION['flash_warning'] = implode(' ', $formErrors);
            }
            redirect_back("services.php?action=view&type=birth&id={$newId}&saved=1");
        }

        // ---------------- PRE-SCREENING ----------------
        if ($formType === 'save_prescreen') {
            $isEdit = !empty($_POST['id']);
            $id = $isEdit ? (int) $_POST['id'] : null;

            $requiredFields = ['last_name', 'first_name', 'address', 'city', 'country', 'phone', 'email',
                'dob', 'ghana_card', 'emergency_name', 'emergency_relationship', 'position_applied', 'signature'];
            $optionalFields = ['middle_name', 'work_phone', 'gps', 'emergency_address', 'emergency_city',
                'emergency_country', 'emergency_home_phone', 'emergency_work_phone', 'emergency_email',
                'emergency_ghana_card', 'emergency_gps', 'auth_name', 'interviewed_by', 'interview_date',
                'remark', 'approved_by'];

            $data = [];
            foreach (array_merge($requiredFields, $optionalFields) as $f) {
                $val = trim((string)($_POST[$f] ?? ''));
                $data[$f] = $val === '' ? null : $val;
            }

            $data['sex']             = pick_enum(SEX_OPTIONS, $_POST['sex'] ?? null);
            $data['disability']      = pick_enum(YES_NO_OPTIONS, $_POST['disability'] ?? null, 'No');
            $data['criminal_record'] = pick_enum(YES_NO_OPTIONS, $_POST['criminal_record'] ?? null, 'No');

            $missing = [];
            foreach ($requiredFields as $f) {
                if (($data[$f] ?? '') === null || $data[$f] === '') $missing[] = ucwords(str_replace('_', ' ', $f));
            }
            if ($data['sex'] === null) $missing[] = 'Sex';
            if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid email address.');
            }
            if ($data['emergency_email'] && !filter_var($data['emergency_email'], FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Please enter a valid emergency contact email address.');
            }
            if ($missing) {
                throw new RuntimeException('Please provide: ' . implode(', ', $missing) . '.');
            }

            // JSON fields — admin edits them as raw JSON text
            foreach (['medical_history', 'education_history', 'former_employers', 'references'] as $jf) {
                $raw = json_or_null(trim((string)($_POST[$jf] ?? '')));
                if ($raw === '__INVALID__') {
                    throw new RuntimeException(ucwords(str_replace('_', ' ', $jf)) . ' is not valid JSON — check the format and try again.');
                }
                $data[$jf] = $raw;
            }

            foreach (['rating_neatness', 'rating_character', 'rating_personality', 'rating_ability', 'rating_communication', 'rating_confidence'] as $rf) {
                $data[$rf] = clamp_rating($_POST[$rf] ?? null);
            }

            $data['final_result'] = pick_enum(FINAL_RESULT_OPTIONS, $_POST['final_result'] ?? null);
            // NOTE: pre_screening_applications has no declaration_accepted column in the
            // current schema (unlike passport/birth), so it's intentionally not set here.
            // If you want a declaration checkbox on this form, add the column first:
            //   ALTER TABLE pre_screening_applications
            //     ADD COLUMN declaration_accepted TINYINT(1) NOT NULL DEFAULT 0;
            // then re-add it to $otherCols below and the checkbox in the form.

            if ($isEdit) {
                $existing = $pdo->prepare("SELECT ref_code FROM pre_screening_applications WHERE id = ?");
                $existing->execute([$id]);
                $row = $existing->fetch();
                if (!$row) throw new RuntimeException('Record not found.');
                $refCode = $row['ref_code'];
            } else {
                $refCode = generate_ref_code($pdo, 'pre_screening_applications', 'BTC-PS-');
            }

            $allTextFields = array_merge($requiredFields, $optionalFields, ['sex', 'disability', 'criminal_record']);
            $otherCols = ['medical_history', 'education_history', 'former_employers',
                'rating_neatness', 'rating_character', 'rating_personality', 'rating_ability',
                'rating_communication', 'rating_confidence', 'final_result'];

            if ($isEdit) {
                $setParts = [];
                foreach (array_merge($allTextFields, $otherCols) as $col) {
                    $setParts[] = "{$col} = :{$col}";
                }
                $setParts[] = "`references` = :references";
                $sql = "UPDATE pre_screening_applications SET " . implode(', ', $setParts) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $data['id'] = $id;
                $data['references'] = $data['references'] ?? null;
            } else {
                $insertCols = array_merge(['ref_code'], $allTextFields, $otherCols, ['references']);
                $placeholders = array_map(fn($c) => $c === 'references' ? ':references' : ":{$c}", $insertCols);
                $colsSql = implode(', ', array_map(fn($c) => $c === 'references' ? '`references`' : $c, $insertCols));
                $sql = "INSERT INTO pre_screening_applications (" . $colsSql . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $data['ref_code'] = $refCode;
                $data['references'] = $data['references'] ?? null;
            }
            $stmt->execute($data);
            $newId = $isEdit ? $id : (int)$pdo->lastInsertId();

            redirect_back("services.php?action=view&type=prescreen&id={$newId}&saved=1");
        }

        // ---------------- QUICK STATUS (passport / birth only) ----------------
        if ($formType === 'quick_status') {
            $type = pick_enum(['passport', 'birth'], $_POST['type'] ?? null);
            $id = (int) ($_POST['id'] ?? 0);
            $status = pick_enum(STATUS_OPTIONS, $_POST['status'] ?? null);
            if (!$type || !$status || $id <= 0) {
                throw new RuntimeException('Invalid status update request.');
            }
            $table = TABLE_MAP[$type];
            $stmt = $pdo->prepare("UPDATE {$table} SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            redirect_back();
        }

        // ---------------- DELETE ----------------
        if ($formType === 'delete') {
            $type = pick_enum(SERVICE_TYPES, $_POST['type'] ?? null);
            $id = (int) ($_POST['id'] ?? 0);
            if (!$type || $id <= 0) {
                throw new RuntimeException('Invalid delete request.');
            }
            $table = TABLE_MAP[$type];
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmt->execute([$id]);
            redirect_back('services.php?deleted=1');
        }

    } catch (Throwable $e) {
        // RuntimeException messages are safe/intended for the admin to see.
        // Anything else (DB errors etc.) gets a generic message — the real
        // error should go to your server's PHP error log, not the browser.
        if ($e instanceof RuntimeException) {
            $formError = $e->getMessage();
        } else {
            error_log('[services.php] ' . $e->getMessage());
            $formError = 'Something went wrong while saving. Please check your input and try again.';
        }
    }
}

/* =====================================================================
 * GET ROUTING
 * ===================================================================== */
$action = in_array($_GET['action'] ?? '', ['list', 'view', 'edit', 'create'], true) ? $_GET['action'] : 'list';
$type   = pick_enum(SERVICE_TYPES, $_GET['type'] ?? null) ?? '';
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

/* -------------------- PRINT VIEW (standalone, no admin chrome) -------------------- */
if (isset($_GET['print']) && isset(TABLE_MAP[$type]) && $id) {
    $table = TABLE_MAP[$type];
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record) { http_response_code(404); die('Record not found.'); }
    [$titleText, $subtitleText] = PAPER_FORM_TITLES[$type];
    $subtitleText = $subtitleText !== '' ? $subtitleText : 'Submitted ' . date('d M Y, g:i A', strtotime($record['created_at']));
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Print — <?php echo h($record['ref_code']); ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
        <style>
            body { background:#eef1f3; padding: 2rem 0; }
            .paper-sheet { max-width: 900px; margin: 0 auto; background:#fff; border-radius: 14px; overflow: hidden; box-shadow: 0 20px 60px rgba(3,55,84,0.15); }
            .print-actions { max-width: 900px; margin: 0 auto 1rem; text-align: right; }
            <?php echo paper_css(); ?>
        </style>
    </head>
    <body>
        <div class="print-actions no-print">
            <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-2"></i>Print / Save as PDF</button>
        </div>
        <div class="paper-sheet paper-doc" id="paperPrintRoot" data-doc-type="<?php echo h($type); ?>" data-doc-id="<?php echo (int) $id; ?>">
            <?php echo paper_letterhead($titleText, $subtitleText, $record['ref_code'], '../'); ?>
            <div class="paper-body">
                <?php echo render_paper_form($type, $record, '../'); ?>
            </div>
        </div>
        <?php echo doc_preview_js($type, (int) $id, false); ?>
    </body>
    </html>
    <?php
    exit;
}

include 'admin_header.php';

/* =====================================================================
 * DATA FOR LIST VIEW — filtered & paginated at the SQL level (not
 * fetch-500-then-filter-in-PHP, which silently drops matches beyond
 * the fetch limit).
 * ===================================================================== */
$stypeRaw     = $_GET['stype'] ?? 'all';
$stype        = in_array($stypeRaw, array_merge(['all'], SERVICE_TYPES), true) ? $stypeRaw : 'all';
$q            = trim((string)($_GET['q'] ?? ''));
$statusFilter = pick_enum(array_unique(array_merge(STATUS_OPTIONS, FINAL_RESULT_OPTIONS)), $_GET['status'] ?? null, '') ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));

$rows = [];
$totalRows = 0;
$counts = ['all' => 0, 'passport' => 0, 'birth' => 0, 'prescreen' => 0];

try {
    $params = [];
    $branches = [];
    if ($stype === 'all' || $stype === 'passport') {
        $branches[] = build_type_query('passport', 'passport_applications', 'applicant_name', 'phone_primary', 'status',
            ['ref_code', 'applicant_name', 'phone_primary'], $q, $statusFilter, $params, 'p');
    }
    if ($stype === 'all' || $stype === 'birth') {
        $branches[] = build_type_query('birth', 'birth_certificate_applications', 'child_name', 'telephone_numbers', 'status',
            ['ref_code', 'child_name', 'telephone_numbers'], $q, $statusFilter, $params, 'b');
    }
    if ($stype === 'all' || $stype === 'prescreen') {
        $branches[] = build_type_query('prescreen', 'pre_screening_applications', "CONCAT(first_name, ' ', last_name)", 'phone', 'final_result',
            ['ref_code', "CONCAT(first_name, ' ', last_name)", 'phone'], $q, $statusFilter, $params, 'r');
    }
    $unionSql = implode(' UNION ALL ', $branches);

    // Total count (for pagination)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ({$unionSql}) x");
    foreach ($params as $ph => $val) $countStmt->bindValue($ph, $val, PDO::PARAM_STR);
    $countStmt->execute();
    $totalRows = (int) $countStmt->fetchColumn();

    // Per-type counts (unfiltered, so the tab badges always show true totals)
    $counts['passport']  = (int) $pdo->query("SELECT COUNT(*) FROM passport_applications")->fetchColumn();
    $counts['birth']     = (int) $pdo->query("SELECT COUNT(*) FROM birth_certificate_applications")->fetchColumn();
    $counts['prescreen'] = (int) $pdo->query("SELECT COUNT(*) FROM pre_screening_applications")->fetchColumn();
    $counts['all']       = $counts['passport'] + $counts['birth'] + $counts['prescreen'];

    // Paginated page of rows
    $totalPages = max(1, (int) ceil($totalRows / PER_PAGE));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * PER_PAGE;

    $pageSql = "SELECT * FROM ({$unionSql}) x ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $pageStmt = $pdo->prepare($pageSql);
    foreach ($params as $ph => $val) $pageStmt->bindValue($ph, $val, PDO::PARAM_STR);
    $pageStmt->bindValue(':limit', PER_PAGE, PDO::PARAM_INT);
    $pageStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $pageStmt->execute();
    $rows = $pageStmt->fetchAll();

} catch (Throwable $e) {
    error_log('[services.php] list query failed: ' . $e->getMessage());
    $rows = [];
    $totalPages = 1;
    $formError = $formError ?? 'Could not load the applications list. Please refresh the page.';
}

// Dashboard stat-card numbers are informational only — if these fail for
// any reason, the list above must still render, so they get their own
// isolated try/catch rather than being able to take down the whole page.
$newThisWeek = 0;
try {
    $weekStmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM passport_applications WHERE created_at >= NOW() - INTERVAL 7 DAY) +
            (SELECT COUNT(*) FROM birth_certificate_applications WHERE created_at >= NOW() - INTERVAL 7 DAY) +
            (SELECT COUNT(*) FROM pre_screening_applications WHERE created_at >= NOW() - INTERVAL 7 DAY) AS wk
    ");
    $newThisWeek = (int) $weekStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('[services.php] week-stat query failed: ' . $e->getMessage());
}

$pendingCount = 0;
try {
    $pendingStmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM passport_applications WHERE status = 'Pending') +
            (SELECT COUNT(*) FROM birth_certificate_applications WHERE status = 'Pending') AS pend
    ");
    $pendingCount = (int) $pendingStmt->fetchColumn();
} catch (Throwable $e) {
    error_log('[services.php] pending-stat query failed: ' . $e->getMessage());
}

$flashWarning = $_SESSION['flash_warning'] ?? null;
unset($_SESSION['flash_warning']);

$STATUS_BADGE_CLASS = [
    'Pending'    => 'bg-warning text-dark',
    'Processing' => 'bg-info text-dark',
    'Approved'   => 'bg-success',
    'Hired'      => 'bg-success',
    'Rejected'   => 'bg-danger',
    'Not Fit'    => 'bg-danger',
    'Hold'       => 'bg-secondary',
];
function status_badge(?string $status): string {
    global $STATUS_BADGE_CLASS;
    $status = $status ?: 'Pending Review';
    $cls = $STATUS_BADGE_CLASS[$status] ?? 'bg-secondary';
    return '<span class="badge rounded-pill ' . $cls . ' px-3 py-2">' . h($status) . '</span>';
}

/* =====================================================================
 * "FILLED PAPER FORM" RENDERER
 * Mirrors the letterhead/section layout of the public-facing forms
 * (passport_form.php / birth_certificate_form.php / pre_screening_form.php)
 * so a saved application looks like a completed paper form rather than
 * a generic key/value dump. Used by both the standalone print page and
 * the in-app "view" screen.
 * ===================================================================== */

function paper_css(): string {
    return <<<CSS
        .paper-doc { font-family: 'Poppins', Arial, sans-serif; color: var(--navy, #033754); }
        .paper-top-accent { height: 5px; background: linear-gradient(90deg, #033754 0%, #10b6d7 55%, #89d900 100%); border-radius: 3px; margin-bottom: 0; }
        .paper-letterhead { padding: 1.75rem 2rem 1.25rem; background: #fff; }
        .paper-letterhead-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; }
        .paper-logo { height: 46px; width: auto; }
        .paper-brand-name { font-weight: 800; font-size: 1rem; color: #033754; line-height: 1.3; }
        .paper-brand-tagline { font-size: .68rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: #10b6d7; margin-top: 2px; }
        .paper-contact { font-size: .76rem; color: #5b6b74; line-height: 1.7; text-align: right; }
        .paper-contact i { color: #10b6d7; width: 14px; text-align: center; margin-right: 4px; }
        .paper-divider { height: 3px; border-radius: 2px; margin: 1.25rem 0; background: linear-gradient(90deg, #033754 0%, #10b6d7 60%, #89d900 100%); opacity: .9; }
        .paper-title { text-align: center; }
        .paper-title h1 { font-weight: 800; font-size: 1.5rem; color: #033754; letter-spacing: .3px; text-transform: uppercase; margin: 0 0 .4rem; }
        .paper-title p { font-size: .85rem; color: #6c7a82; margin: 0; }
        .paper-ref-line { text-align: right; font-size: .8rem; color: #5b6b74; margin: 1rem 2rem 0; }
        .paper-ref-line strong { color: #033754; font-size: 1rem; }
        .paper-body { padding: 1.5rem 2rem 2rem; }
        .paper-section { margin-bottom: 2.25rem; }
        .paper-section-title { color: #033754; border-bottom: 2px solid #89d900; display: inline-flex; align-items: center; gap: .6rem; padding-bottom: 6px; font-size: 1.1rem; font-weight: 700; margin-bottom: 1.1rem; }
        .paper-step-badge { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #033754; color: #fff; font-size: .8rem; flex-shrink: 0; }
        .paper-field { margin-bottom: 1.15rem; }
        .paper-field .lbl { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #8a99a1; font-weight: 700; margin-bottom: 3px; }
        .paper-field .val { font-size: .98rem; font-weight: 600; color: #1c2b33; border-bottom: 1.5px dotted #c7d0d4; padding-bottom: 6px; min-height: 1.5em; word-break: break-word; }
        .paper-field .val.is-empty { color: #b7c0c4; font-weight: 400; font-style: italic; }
        .paper-entry-card { background: #f8fafb; border: 1px solid #eef1f3; border-radius: 10px; padding: .75rem .9rem; margin-bottom: .6rem; font-size: .88rem; }
        .paper-entry-card .k { color: #8a99a1; font-weight: 700; font-size: .68rem; text-transform: uppercase; letter-spacing: .03em; }
        .paper-doc-grid { display: flex; flex-wrap: wrap; gap: .9rem; min-height: 1px; }
        .paper-doc-thumb-wrap { text-align: center; }
        .paper-doc-thumb { width: 108px; height: 108px; object-fit: cover; border-radius: 10px; border: 2px solid #eef1f3; cursor: pointer; display: block; transition: border-color .15s ease, transform .15s ease; background: #f8fafb; }
        .paper-doc-thumb:hover { border-color: #10b6d7; transform: translateY(-2px); }
        .paper-doc-caption { font-size: .68rem; color: #8a99a1; margin-top: 4px; max-width: 108px; }
        .paper-pdf-card { width: 108px; height: 108px; border-radius: 10px; border: 2px solid #eef1f3; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; color: #c9302c; text-decoration: none; transition: border-color .15s ease; }
        .paper-pdf-card:hover { border-color: #10b6d7; color: #c9302c; }
        .paper-pdf-card i { font-size: 1.6rem; }
        .paper-pdf-card span { font-size: .65rem; color: #5b6b74; font-weight: 600; }
        .paper-not-uploaded { color: #b7c0c4; font-style: italic; font-size: .85rem; }
        .paper-declaration-box { background: #f8fafb; border: 1px solid #dfe4e8; border-radius: 12px; padding: 1.1rem 1.3rem; }
        .paper-doc-skel { width: 108px; height: 108px; border-radius: 10px; background: linear-gradient(100deg, #eef1f3 30%, #f6f8f9 50%, #eef1f3 70%); background-size: 200% 100%; animation: paperDocPulse 1.3s ease-in-out infinite; }
        @keyframes paperDocPulse { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        .paper-doc-error { color: #c9302c; font-size: .78rem; display: flex; align-items: center; gap: 6px; }
        .paper-doc-error i { font-size: .85rem; }
        .paper-doc-retry { background: none; border: none; color: #10b6d7; font-size: .78rem; text-decoration: underline; cursor: pointer; padding: 0; }
        @media print {
            .no-print { display: none !important; }
            .paper-doc { padding: 0; }
            .paper-doc-thumb, .paper-pdf-card { cursor: default; }
        }
        CSS;
}

/** One label/value line styled like a filled-in paper field. */
function paper_field(string $label, $value): string {
    $isEmpty = ($value === null || $value === '');
    $displayValue = $isEmpty ? '—' : nl2br(h((string)$value));
    $cls = $isEmpty ? 'val is-empty' : 'val';
    return '<div class="paper-field"><div class="lbl">' . h($label) . '</div><div class="' . $cls . '">' . $displayValue . '</div></div>';
}

/** Wraps a block of paper_field() output in a numbered section header, matching the public form's style. */
function paper_section(int $num, string $title, string $innerHtml, string $suffix = ''): string {
    return '<div class="paper-section">'
        . '<div class="paper-section-title"><span class="paper-step-badge">' . $num . '</span> ' . h($title) . ($suffix !== '' ? ' <span class="fs-6 fw-normal text-muted">' . h($suffix) . '</span>' : '') . '</div>'
        . '<div class="row g-3">' . $innerHtml . '</div>'
        . '</div>';
}

function paper_col(string $width, string $fieldHtml): string {
    return '<div class="col-md-' . $width . '">' . $fieldHtml . '</div>';
}

/** Renders a JSON-array column (medical history, references, etc.) as a stack of read-only entry cards. */
function paper_json_section(int $num, string $title, ?string $json, string $suffix = ''): string {
    $entries = pretty_json_entries($json);
    if (empty($entries)) {
        $inner = '<div class="col-12"><span class="paper-not-uploaded">None provided</span></div>';
    } else {
        $cards = '';
        foreach ($entries as $entry) {
            if (is_array($entry)) {
                $parts = [];
                foreach ($entry as $ek => $ev) {
                    if ($ev === '' || $ev === null) continue;
                    $parts[] = '<span class="me-3"><span class="k">' . h(ucwords(str_replace('_', ' ', (string)$ek))) . ':</span> ' . h((string)$ev) . '</span>';
                }
                $cards .= '<div class="col-12"><div class="paper-entry-card">' . implode(' ', $parts) . '</div></div>';
            } else {
                $cards .= '<div class="col-12"><div class="paper-entry-card">' . h((string)$entry) . '</div></div>';
            }
        }
        $inner = $cards;
    }
    return paper_section($num, $title, $inner, $suffix);
}

/**
 * Renders an AJAX-driven placeholder for one document field. Instead of
 * embedding a (possibly broken) <img src> directly, this outputs a
 * skeleton loader tagged with data-doc-key — the doc_preview AJAX script
 * (see doc_preview_js()) finds it after fetching the verified document
 * list for this record and swaps it for real thumbnails, a "not
 * uploaded" note, or a "couldn't load" retry state.
 *
 * $hasValue lets us skip the network round-trip entirely when the DB
 * column is already known (server-side, synchronously) to be empty —
 * only fields that might actually have a file get a skeleton + fetch.
 */
function paper_doc_placeholder(string $label, string $docKey, bool $hasValue): string {
    if (!$hasValue) {
        return '<div class="paper-field"><div class="lbl">' . h($label) . '</div><span class="paper-not-uploaded">Not uploaded</span></div>';
    }
    return '<div class="paper-field mb-2"><div class="lbl">' . h($label) . '</div></div>'
        . '<div class="paper-doc-grid mb-2" data-doc-key="' . h($docKey) . '">'
        . '<div class="paper-doc-skel"></div>'
        . '</div>';
}

/**
 * Auto-discovered pre-screening documents don't have known keys up
 * front, so their whole section is one placeholder that the AJAX
 * script fills in with as many document groups as it actually finds
 * (each with its own label) — or a single "no documents on file" line
 * if the discovery scan comes back empty.
 */
function paper_prescreen_doc_section(int $num): string {
    $inner = '<div class="col-12" data-doc-key="__prescreen_all__"><div class="d-flex flex-wrap gap-3"><div class="paper-doc-skel"></div><div class="paper-doc-skel"></div></div></div>';
    return paper_section($num, 'Uploaded Documents', $inner);
}

function paper_letterhead(string $title, string $subtitle, string $refCode, string $logoPrefix): string {
    $logoSrc = $logoPrefix . 'assets/images/logo_new.png';
    return '
    <div class="paper-top-accent"></div>
    <div class="paper-letterhead">
        <div class="paper-letterhead-top">
            <div class="d-flex align-items-center gap-3">
                <img src="' . h($logoSrc) . '" alt="Believe Travel Consult" class="paper-logo" onerror="this.style.display=\'none\'">
                <div>
                    <div class="paper-brand-name">Believe Travel Consult</div>
                    <div class="paper-brand-tagline">Authorized Passport Processing Agent</div>
                </div>
            </div>
            <div class="paper-contact no-print">
                <div><i class="fas fa-location-dot"></i> ' . LETTERHEAD_ADDRESS . '</div>
                <div><i class="fas fa-phone"></i> ' . LETTERHEAD_PHONE . '</div>
            </div>
        </div>
        <div class="paper-divider"></div>
        <div class="paper-title">
            <h1>' . h($title) . '</h1>
            <p>' . h($subtitle) . '</p>
        </div>
    </div>
    <div class="paper-ref-line">Ref: <strong>' . h($refCode) . '</strong></div>';
}

function render_passport_paper(array $r, string $baseUrl): string {
    $html = paper_section(1, 'Applicant Information', implode('', [
        paper_col('6', paper_field('Full Name (As in Birth Certificate)', $r['applicant_name'])),
        paper_col('6', paper_field('Date of Birth', !empty($r['dob']) ? date('d M Y', strtotime($r['dob'])) : null)),
        paper_col('6', paper_field('Place of Birth', $r['place_of_birth'])),
        paper_col('6', paper_field('Primary Phone', $r['phone_primary'])),
        paper_col('12', paper_field('Residential Address in Ghana', $r['residential_address'])),
    ]));
    $html .= paper_section(2, "Father's Information", implode('', [
        paper_col('6', paper_field('Full Name', $r['father_name'])),
        paper_col('6', paper_field('Hometown', $r['father_hometown'])),
        paper_col('8', paper_field('Residential Address', $r['father_residential_address'])),
        paper_col('4', paper_field('Telephone', $r['father_phone'])),
    ]));
    $html .= paper_section(3, "Mother's &amp; Grandparents' Information", implode('', [
        paper_col('6', paper_field("Mother's Full Name (Maiden)", $r['mother_name'])),
        paper_col('6', paper_field("Mother's Hometown", $r['mother_hometown'])),
        paper_col('12', paper_field("Mother's Residential Address", $r['mother_residential_address'])),
        paper_col('6', paper_field("Grandmother's Name", $r['grandmother_name'])),
        paper_col('6', paper_field("Grandmother's Hometown", $r['grandmother_hometown'])),
        paper_col('6', paper_field("Grandfather's Name", $r['grandfather_name'])),
        paper_col('6', paper_field("Grandfather's Hometown", $r['grandfather_hometown'])),
    ]));
    $html .= paper_section(4, 'Guarantor Information', implode('', [
        paper_col('6', paper_field('Full Name', $r['guarantor_name'])),
        paper_col('6', paper_field('Occupation', $r['guarantor_occupation'])),
        paper_col('6', paper_field('Telephone', $r['guarantor_phone'])),
        paper_col('12', paper_field('Residential Address', $r['guarantor_residential_address'])),
    ]));
    $html .= paper_section(5, 'Required Documents', implode('', [
        paper_col('6', paper_doc_placeholder('Birth Certificate', 'birth_certificate', !empty($r['birth_certificate']))),
        paper_col('6', paper_doc_placeholder("Guarantor's ID", 'guarantor_id', !empty($r['guarantor_id']))),
        paper_col('6', paper_doc_placeholder('Proof of Address', 'proof_address', !empty($r['proof_address']))),
        paper_col('6', paper_doc_placeholder('Passport Photos', 'passport_photos', !empty($r['passport_photos']))),
    ]));
    $declStatus = !empty($r['declaration_accepted'])
        ? '<span class="text-success fw-semibold"><i class="fas fa-circle-check me-1"></i>Accepted by applicant</span>'
        : '<span class="text-danger fw-semibold"><i class="fas fa-circle-xmark me-1"></i>Not accepted</span>';
    $html .= '<div class="paper-section"><div class="paper-section-title"><span class="paper-step-badge">6</span> Declaration &amp; Office Use</div>'
        . '<div class="row g-3">'
        . paper_col('4', paper_field('Package', $r['package'] ?? null))
        . paper_col('4', '<div class="paper-field"><div class="lbl">Status</div><div class="val">' . status_badge($r['status'] ?? null) . '</div></div>')
        . paper_col('4', '<div class="paper-field"><div class="lbl">Declaration</div><div class="val">' . $declStatus . '</div></div>')
        . paper_col('12', paper_field('Remarks (Office Use)', $r['remarks'] ?? null))
        . '</div></div>';
    return $html;
}

function render_birth_paper(array $r, string $baseUrl): string {
    $html = paper_section(1, "Child's Information", implode('', [
        paper_col('6', paper_field("Child's Full Name", $r['child_name'])),
        paper_col('3', paper_field('Date of Birth', !empty($r['dob']) ? date('d M Y', strtotime($r['dob'])) : null)),
        paper_col('3', paper_field('Place of Birth', $r['place_of_birth'])),
    ]));
    $html .= paper_section(2, "Father's Information", implode('', [
        paper_col('4', paper_field('Full Name', $r['father_name'])),
        paper_col('4', paper_field('Occupation', $r['father_occupation'])),
        paper_col('4', paper_field('Religion', $r['father_religion'])),
    ]));
    $html .= paper_section(3, "Mother's Information", implode('', [
        paper_col('12', paper_field('Maiden Name', $r['mother_maiden_name'])),
    ]));
    $html .= paper_section(4, 'Informant &amp; Additional Details', implode('', [
        paper_col('6', paper_field('Informant', $r['informant'])),
        paper_col('3', paper_field('Parents Alive?', $r['parents_alive'])),
        paper_col('3', paper_field('Telephone Numbers', $r['telephone_numbers'])),
    ]));
    $html .= paper_section(5, "Uploaded Documents", implode('', [
        paper_col('6', paper_doc_placeholder("Father's ID", 'father_id', !empty($r['father_id']))),
        paper_col('6', paper_doc_placeholder("Mother's ID", 'mother_id', !empty($r['mother_id']))),
    ]));
    $declStatus = !empty($r['declaration_accepted'])
        ? '<span class="text-success fw-semibold"><i class="fas fa-circle-check me-1"></i>Accepted by applicant</span>'
        : '<span class="text-danger fw-semibold"><i class="fas fa-circle-xmark me-1"></i>Not accepted</span>';
    $html .= '<div class="paper-section"><div class="paper-section-title"><span class="paper-step-badge">6</span> Declaration &amp; Office Use</div>'
        . '<div class="row g-3">'
        . paper_col('4', paper_field('Package', $r['package'] ?? null))
        . paper_col('4', '<div class="paper-field"><div class="lbl">Status</div><div class="val">' . status_badge($r['status'] ?? null) . '</div></div>')
        . paper_col('4', '<div class="paper-field"><div class="lbl">Declaration</div><div class="val">' . $declStatus . '</div></div>')
        . paper_col('12', paper_field('Remarks (Office Use)', $r['remarks'] ?? null))
        . '</div></div>';
    return $html;
}

function render_prescreen_paper(array $r, string $baseUrl): string {
    $html = paper_section(1, 'Personal Information', implode('', [
        paper_col('4', paper_field('Last Name', $r['last_name'])),
        paper_col('4', paper_field('First Name', $r['first_name'])),
        paper_col('4', paper_field('Middle Name', $r['middle_name'] ?? null)),
        paper_col('3', paper_field('Date of Birth', $r['dob'] ?? null)),
        paper_col('3', paper_field('Sex', $r['sex'] ?? null)),
        paper_col('3', paper_field('Ghana Card', $r['ghana_card'] ?? null)),
        paper_col('3', paper_field('Phone', $r['phone'] ?? null)),
        paper_col('4', paper_field('Work Phone', $r['work_phone'] ?? null)),
        paper_col('4', paper_field('Email', $r['email'] ?? null)),
        paper_col('2', paper_field('Disability', $r['disability'] ?? null)),
        paper_col('2', paper_field('Criminal Record', $r['criminal_record'] ?? null)),
    ]));
    $html .= paper_section(2, 'Residential Address', implode('', [
        paper_col('12', paper_field('Address', $r['address'] ?? null)),
        paper_col('4', paper_field('City', $r['city'] ?? null)),
        paper_col('4', paper_field('Country', $r['country'] ?? null)),
        paper_col('4', paper_field('GPS', $r['gps'] ?? null)),
    ]));
    $html .= paper_section(3, 'Emergency Contact', implode('', [
        paper_col('6', paper_field('Name', $r['emergency_name'] ?? null)),
        paper_col('6', paper_field('Relationship', $r['emergency_relationship'] ?? null)),
        paper_col('12', paper_field('Address', $r['emergency_address'] ?? null)),
        paper_col('4', paper_field('City', $r['emergency_city'] ?? null)),
        paper_col('4', paper_field('Country', $r['emergency_country'] ?? null)),
        paper_col('4', paper_field('Ghana Card', $r['emergency_ghana_card'] ?? null)),
        paper_col('4', paper_field('Home Phone', $r['emergency_home_phone'] ?? null)),
        paper_col('4', paper_field('Work Phone', $r['emergency_work_phone'] ?? null)),
        paper_col('4', paper_field('Email', $r['emergency_email'] ?? null)),
        paper_col('6', paper_field('GPS', $r['emergency_gps'] ?? null)),
    ]));
    $html .= paper_json_section(4, 'Medical History', $r['medical_history'] ?? null);
    $html .= paper_json_section(5, 'Education History', $r['education_history'] ?? null);
    $html .= paper_json_section(6, 'Former Employers', $r['former_employers'] ?? null, 'Optional');
    $html .= paper_json_section(7, 'References', $r['references'] ?? null);
    $html .= paper_section(8, 'Position Applied For &amp; Declaration', implode('', [
        paper_col('4', paper_field('Position Applied For', $r['position_applied'] ?? null)),
        paper_col('4', paper_field('Referred/Authorized By', $r['auth_name'] ?? null)),
        paper_col('4', paper_field('Signature', $r['signature'] ?? null)),
    ]));
    $html .= paper_section(9, 'Staff Assessment', implode('', [
        paper_col('4', paper_field('Interviewed By', $r['interviewed_by'] ?? null)),
        paper_col('4', paper_field('Interview Date', $r['interview_date'] ?? null)),
        paper_col('4', '<div class="paper-field"><div class="lbl">Final Result</div><div class="val">' . status_badge($r['final_result'] ?? null) . '</div></div>'),
        paper_col('2', paper_field('Neatness', $r['rating_neatness'] ?? null)),
        paper_col('2', paper_field('Character', $r['rating_character'] ?? null)),
        paper_col('2', paper_field('Personality', $r['rating_personality'] ?? null)),
        paper_col('2', paper_field('Ability', $r['rating_ability'] ?? null)),
        paper_col('2', paper_field('Communication', $r['rating_communication'] ?? null)),
        paper_col('2', paper_field('Confidence', $r['rating_confidence'] ?? null)),
        paper_col('12', paper_field('Remark', $r['remark'] ?? null)),
        paper_col('6', paper_field('Approved By', $r['approved_by'] ?? null)),
    ]), '(Office Use Only)');
    $html .= paper_prescreen_doc_section(10);
    return $html;
}

function render_paper_form(string $type, array $record, string $baseUrl): string {
    switch ($type) {
        case 'passport':  return render_passport_paper($record, $baseUrl);
        case 'birth':     return render_birth_paper($record, $baseUrl);
        case 'prescreen': return render_prescreen_paper($record, $baseUrl);
        default:          return '';
    }
}

/**
 * The shared client-side engine behind every paper_doc_placeholder() /
 * paper_prescreen_doc_section() on the page. One fetch() per record to
 * services.php?ajax=doc_preview, then each returned group is matched to
 * its placeholder by data-doc-key and rendered as real thumbnails, a
 * PDF card, or (if a DB value existed but no file was found on disk) a
 * clearly-labelled "file missing" state with a retry link — never a
 * silently broken <img>.
 *
 * $withLightbox controls whether clicking a thumbnail opens the bootstrap
 * modal lightbox (used on the in-app view page) or just opens the image
 * in a new tab (used on the standalone print page, which doesn't load
 * the lightbox modal markup).
 */
function doc_preview_js(string $type, int $id, bool $withLightbox): string {
    $typeJs = json_encode($type);
    $idJs   = json_encode($id);
    $lightboxJs = $withLightbox ? 'true' : 'false';
    return <<<HTML
    <script>
    (function () {
        var DOC_TYPE = {$typeJs};
        var DOC_ID = {$idJs};
        var WITH_LIGHTBOX = {$lightboxJs};

        function escapeHtml(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        function thumbHtml(item, label) {
            var url = item.url;
            if (item.is_pdf) {
                return '<div class="paper-doc-thumb-wrap">'
                    + '<a href="' + url + '" target="_blank" rel="noopener" class="paper-pdf-card"><i class="fas fa-file-pdf"></i><span>PDF</span></a>'
                    + '<div class="paper-doc-caption"><a href="' + url + '" download class="text-decoration-none">Download</a></div>'
                    + '</div>';
            }
            var clickAttr = WITH_LIGHTBOX
                ? ' onclick="window.paperLightbox && window.paperLightbox(this.src)"'
                : ' onclick="window.open(this.src, \\'_blank\\')"';
            // Escape the URL for safe embedding inside the onerror HTML string
            // (both as an HTML attribute value and inside a single-quoted JS string).
            var urlForError = escapeHtml(url).replace(/'/g, '&#39;');
            return '<div class="paper-doc-thumb-wrap">'
                + '<img src="' + url + '" class="paper-doc-thumb" alt="' + escapeHtml(label) + '"' + clickAttr + ' loading="lazy"'
                + ' onerror="this.closest(\\'.paper-doc-thumb-wrap\\').innerHTML=\\''
                +   '<span class=&quot;paper-doc-error&quot; style=&quot;display:block;text-align:left;max-width:220px;white-space:normal;word-break:break-all;&quot;>'
                +     '<i class=&quot;fas fa-triangle-exclamation&quot;></i> Image failed to load<br>'
                +     '<small>Tried: <a href=&quot;' + urlForError + '&quot; target=&quot;_blank&quot;>' + urlForError + '</a></small>'
                +   '</span>\\'">'
                + '<div class="paper-doc-caption"><a href="' + url + '" download class="text-decoration-none">Download</a></div>'
                + '</div>';
        }

        function renderGroupInto(container, group) {
            if (!group.items || group.items.length === 0) {
                container.innerHTML = '<span class="paper-doc-error"><i class="fas fa-triangle-exclamation"></i>File not found on server '
                    + '<button type="button" class="paper-doc-retry" onclick="window.__paperDocPreviewLoad && window.__paperDocPreviewLoad()">Retry</button></span>';
                return;
            }
            var html = '';
            for (var i = 0; i < group.items.length; i++) {
                html += thumbHtml(group.items[i], group.label || '');
            }
            container.innerHTML = html;
        }

        function renderPrescreenAll(container, groups) {
            var withItems = (groups || []).filter(function (g) { return g.items && g.items.length; });
            if (withItems.length === 0) {
                container.innerHTML = '<span class="paper-not-uploaded">No documents on file</span>';
                return;
            }
            var html = '';
            for (var g = 0; g < withItems.length; g++) {
                var group = withItems[g];
                html += '<div class="paper-field mb-2"><div class="lbl">' + escapeHtml(group.label) + '</div></div>'
                    + '<div class="paper-doc-grid mb-2">';
                for (var i = 0; i < group.items.length; i++) {
                    html += thumbHtml(group.items[i], group.label);
                }
                html += '</div>';
            }
            container.innerHTML = html;
        }

        function showFetchError() {
            document.querySelectorAll('[data-doc-key]').forEach(function (el) {
                el.innerHTML = '<span class="paper-doc-error"><i class="fas fa-triangle-exclamation"></i>Could not load documents. '
                    + '<button type="button" class="paper-doc-retry" onclick="window.__paperDocPreviewLoad && window.__paperDocPreviewLoad()">Retry</button></span>';
            });
        }

        function load() {
            var placeholders = document.querySelectorAll('[data-doc-key]');
            if (placeholders.length === 0) return;

            var url = 'services.php?ajax=doc_preview&type=' + encodeURIComponent(DOC_TYPE) + '&id=' + encodeURIComponent(DOC_ID);
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    if (!data || data.success !== true) throw new Error((data && data.error) || 'Unknown error');
                    var byKey = {};
                    (data.groups || []).forEach(function (g) { byKey[g.key] = g; });

                    placeholders.forEach(function (el) {
                        var key = el.getAttribute('data-doc-key');
                        if (key === '__prescreen_all__') {
                            renderPrescreenAll(el, data.groups || []);
                            return;
                        }
                        var group = byKey[key];
                        if (!group) {
                            el.innerHTML = '<span class="paper-doc-error"><i class="fas fa-triangle-exclamation"></i>File not found on server</span>';
                            return;
                        }
                        renderGroupInto(el, group);
                    });
                })
                .catch(function () {
                    showFetchError();
                });
        }

        window.__paperDocPreviewLoad = load;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', load);
        } else {
            load();
        }
    })();
    </script>
HTML;
}
?>
<style>
    :root { --btc-navy:#033754; --btc-lime:#89d900; --btc-cyan:#10b6d7; }
    .btc-page-header { background: linear-gradient(120deg, var(--btc-navy) 0%, #054a6e 100%); border-radius: 16px; padding: 1.75rem 2rem; color: #fff; margin-bottom: 1.5rem; position: relative; overflow: hidden; }
    .btc-page-header::after { content:''; position:absolute; right:-40px; top:-40px; width:180px; height:180px; background: radial-gradient(circle, var(--btc-cyan) 0%, transparent 70%); opacity:.25; }
    .btc-stat-card { background:#fff; border-radius:14px; padding:1.1rem 1.3rem; box-shadow: 0 2px 10px rgba(3,55,84,0.06); border:1px solid #eef1f3; height:100%; }
    .btc-stat-card .num { font-size:1.6rem; font-weight:800; color: var(--btc-navy); }
    .btc-stat-card .lbl { font-size:.78rem; color:#6b7c85; text-transform:uppercase; letter-spacing:.03em; }
    .btc-stat-card .ic { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
    .btc-pill-nav .nav-link { border-radius:50px; font-weight:600; font-size:.88rem; padding:.5rem 1.1rem; color: var(--btc-navy); background:#fff; border:1px solid #e3e8eb; }
    .btc-pill-nav .nav-link.active { background: var(--btc-navy); color:#fff; border-color: var(--btc-navy); }
    .btc-card { background:#fff; border-radius:14px; border:1px solid #eef1f3; box-shadow: 0 2px 10px rgba(3,55,84,0.05); }
    .btc-table thead th { background:#f7f9fa; color:#5b6b74; font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; border-bottom:2px solid #eef1f3; padding:.9rem .75rem; }
    .btc-table tbody td { padding:.85rem .75rem; vertical-align:middle; border-bottom:1px solid #f2f4f5; }
    .btc-table tbody tr:hover { background:#f9fbfc; }
    .btc-type-badge { font-size:.72rem; font-weight:700; padding:.4rem .7rem; border-radius:50px; }
    .btc-empty { text-align:center; padding:3rem 1rem; color:#8a99a1; }
    .btc-empty i { font-size:2.2rem; margin-bottom:.6rem; display:block; }
    .btc-section-title { color: var(--btc-navy); font-weight:700; border-bottom:2px solid #f0f3f4; padding-bottom:.5rem; margin-bottom:1rem; }
    <?php echo paper_css(); ?>
</style>

<!-- Lightbox for document/photo previews on the view page -->
<div class="modal fade" id="paperLightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-dark border-0">
            <div class="modal-header border-0">
                <a href="#" id="paperLightboxDownload" download class="btn btn-sm btn-light rounded-pill"><i class="fas fa-download me-1"></i>Download</a>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <img id="paperLightboxImg" src="" class="img-fluid rounded-3" alt="Document preview">
            </div>
        </div>
    </div>
</div>
<script>
function paperLightbox(src) {
    document.getElementById('paperLightboxImg').src = src;
    document.getElementById('paperLightboxDownload').href = src;
    const modal = new bootstrap.Modal(document.getElementById('paperLightboxModal'));
    modal.show();
}
</script>

<div class="btc-page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-handshake me-2"></i>Manage Services</h2>
            <p class="mb-0 opacity-75 small">Passport, Birth Certificate &amp; Pre-Screening applications in one place</p>
        </div>
        <div class="dropdown">
            <button class="btn btn-light rounded-pill fw-semibold px-3 dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-plus me-2"></i>New Application
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><a class="dropdown-item py-2" href="services.php?action=create&type=passport"><i class="fas fa-passport me-2 text-primary"></i>Passport</a></li>
                <li><a class="dropdown-item py-2" href="services.php?action=create&type=birth"><i class="fas fa-baby me-2 text-primary"></i>Birth Certificate</a></li>
                <li><a class="dropdown-item py-2" href="services.php?action=create&type=prescreen"><i class="fas fa-user-check me-2 text-primary"></i>Pre-Screening</a></li>
            </ul>
        </div>
    </div>
</div>

<?php if ($action === 'list'): ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="btc-stat-card d-flex align-items-center gap-3">
            <div class="ic" style="background:#eaf6ff;color:var(--btc-cyan);"><i class="fas fa-layer-group"></i></div>
            <div><div class="num"><?php echo (int)$counts['all']; ?></div><div class="lbl">Total Applications</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="btc-stat-card d-flex align-items-center gap-3">
            <div class="ic" style="background:#fff6e0;color:#c98a00;"><i class="fas fa-hourglass-half"></i></div>
            <div><div class="num"><?php echo (int)$pendingCount; ?></div><div class="lbl">Pending Review</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="btc-stat-card d-flex align-items-center gap-3">
            <div class="ic" style="background:#eefaf0;color:#1a9c4a;"><i class="fas fa-calendar-week"></i></div>
            <div><div class="num"><?php echo (int)$newThisWeek; ?></div><div class="lbl">New This Week</div></div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="btc-stat-card d-flex align-items-center gap-3">
            <div class="ic" style="background:#eef4ff;color:var(--btc-navy);"><i class="fas fa-user-check"></i></div>
            <div><div class="num"><?php echo (int)$counts['prescreen']; ?></div><div class="lbl">Pre-Screenings</div></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($formError): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2"><i class="fas fa-circle-exclamation"></i><div><?php echo h($formError); ?></div></div>
<?php endif; ?>
<?php if ($formErrors): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2"><i class="fas fa-triangle-exclamation mt-1"></i><div><?php echo implode('<br>', array_map('h', $formErrors)); ?></div></div>
<?php endif; ?>
<?php if ($flashWarning): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2"><i class="fas fa-triangle-exclamation mt-1"></i><div><?php echo h($flashWarning); ?> The rest of the application saved successfully.</div></div>
<?php endif; ?>
<?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-circle-check"></i><div>Saved successfully.</div></div>
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success d-flex align-items-center gap-2"><i class="fas fa-circle-check"></i><div>Record deleted.</div></div>
<?php endif; ?>

<?php if ($action === 'view' && isset(TABLE_MAP[$type]) && $id):
    $table = TABLE_MAP[$type];
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
    $record = $stmt->fetch();
    if (!$record): ?>
        <div class="alert alert-warning">Record not found. <a href="services.php">Back to list</a></div>
    <?php else:
        [$titleText, $subtitleText] = PAPER_FORM_TITLES[$type];
        $subtitleText = $subtitleText !== '' ? $subtitleText : 'Submitted ' . date('d M Y, g:i A', strtotime($record['created_at']));
    ?>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-1"><?php echo h(TYPE_LABELS[$type]); ?> — <?php echo h($record['ref_code']); ?></h2>
                <p class="text-muted mb-0">Submitted <?php echo h(date('d M Y, g:i A', strtotime($record['created_at']))); ?></p>
            </div>
            <div class="d-flex gap-2">
                <a href="services.php?action=edit&type=<?php echo h($type); ?>&id=<?php echo (int) $id; ?>" class="btn btn-primary rounded-pill"><i class="fas fa-pen me-2"></i>Edit</a>
                <a href="services.php?print=1&type=<?php echo h($type); ?>&id=<?php echo (int) $id; ?>" target="_blank" class="btn btn-outline-secondary rounded-pill"><i class="fas fa-print me-2"></i>Print</a>
                <?php if (!empty($_SESSION['admin_id'])): ?>
                <a href="services.php?ajax=doc_debug&type=<?php echo h($type); ?>&id=<?php echo (int) $id; ?>" target="_blank" class="btn btn-outline-warning rounded-pill" title="Show exactly which document paths were tried and why they did/didn't resolve"><i class="fas fa-bug me-2"></i>Debug Documents</a>
                <?php endif; ?>
                <a href="services.php" class="btn btn-outline-secondary rounded-pill"><i class="fas fa-arrow-left me-2"></i>Back</a>
            </div>
        </div>
        <div class="btc-card overflow-hidden paper-doc">
            <?php echo paper_letterhead($titleText, $subtitleText, $record['ref_code'], '../'); ?>
            <div class="paper-body">
                <?php echo render_paper_form($type, $record, '../'); ?>
            </div>
        </div>
        <?php echo doc_preview_js($type, (int) $id, true); ?>
    <?php endif; ?>

<?php elseif (($action === 'edit' || $action === 'create') && $type !== ''):

    $record = [];
    if ($action === 'edit' && $id) {
        $stmt = $pdo->prepare("SELECT * FROM " . TABLE_MAP[$type] . " WHERE id = ?");
        $stmt->execute([$id]);
        $record = $stmt->fetch() ?: [];
    }
    if ($action === 'edit' && empty($record)):
        ?>
        <div class="alert alert-warning">Record not found. <a href="services.php">Back to list</a></div>
        <?php
    else:
    $v = fn($key, $default = '') => h($record[$key] ?? $default);
    ?>
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h2 class="fw-bold mb-0"><?php echo $action === 'edit' ? 'Edit' : 'New'; ?> <?php echo h(TYPE_LABELS[$type]); ?> Application</h2>
        <a href="services.php" class="btn btn-outline-secondary rounded-pill"><i class="fas fa-arrow-left me-2"></i>Back to list</a>
    </div>

    <div class="btc-card p-4">
        <?php if ($type === 'passport'): ?>
        <form method="POST" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_type" value="save_passport">
            <input type="hidden" name="id" value="<?php echo $v('id'); ?>">
            <div class="row g-3">
                <div class="col-12"><h6 class="btc-section-title">Applicant</h6></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Applicant Name <span class="text-danger">*</span></label><input type="text" name="applicant_name" class="form-control" value="<?php echo $v('applicant_name'); ?>" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label><input type="date" name="dob" class="form-control" value="<?php echo $v('dob'); ?>" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Place of Birth <span class="text-danger">*</span></label><input type="text" name="place_of_birth" class="form-control" value="<?php echo $v('place_of_birth'); ?>" required></div>
                <div class="col-md-6"><label class="form-label fw-semibold">Primary Phone <span class="text-danger">*</span></label><input type="text" name="phone_primary" class="form-control" value="<?php echo $v('phone_primary'); ?>" required></div>
                <div class="col-12"><label class="form-label fw-semibold">Residential Address <span class="text-danger">*</span></label><textarea name="residential_address" class="form-control" rows="2" required><?php echo $v('residential_address'); ?></textarea></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Father</h6></div>
                <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="father_name" class="form-control" value="<?php echo $v('father_name'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Hometown <span class="text-danger">*</span></label><input type="text" name="father_hometown" class="form-control" value="<?php echo $v('father_hometown'); ?>" required></div>
                <div class="col-md-8"><label class="form-label">Address <span class="text-danger">*</span></label><input type="text" name="father_residential_address" class="form-control" value="<?php echo $v('father_residential_address'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Phone <span class="text-danger">*</span></label><input type="text" name="father_phone" class="form-control" value="<?php echo $v('father_phone'); ?>" required></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Mother &amp; Grandparents</h6></div>
                <div class="col-md-6"><label class="form-label">Mother's Name <span class="text-danger">*</span></label><input type="text" name="mother_name" class="form-control" value="<?php echo $v('mother_name'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Mother's Hometown <span class="text-danger">*</span></label><input type="text" name="mother_hometown" class="form-control" value="<?php echo $v('mother_hometown'); ?>" required></div>
                <div class="col-12"><label class="form-label">Mother's Address <span class="text-danger">*</span></label><input type="text" name="mother_residential_address" class="form-control" value="<?php echo $v('mother_residential_address'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Grandmother's Name <span class="text-danger">*</span></label><input type="text" name="grandmother_name" class="form-control" value="<?php echo $v('grandmother_name'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Grandmother's Hometown <span class="text-danger">*</span></label><input type="text" name="grandmother_hometown" class="form-control" value="<?php echo $v('grandmother_hometown'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Grandfather's Name <span class="text-danger">*</span></label><input type="text" name="grandfather_name" class="form-control" value="<?php echo $v('grandfather_name'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Grandfather's Hometown <span class="text-danger">*</span></label><input type="text" name="grandfather_hometown" class="form-control" value="<?php echo $v('grandfather_hometown'); ?>" required></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Guarantor</h6></div>
                <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="guarantor_name" class="form-control" value="<?php echo $v('guarantor_name'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Occupation <span class="text-danger">*</span></label><input type="text" name="guarantor_occupation" class="form-control" value="<?php echo $v('guarantor_occupation'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Phone <span class="text-danger">*</span></label><input type="text" name="guarantor_phone" class="form-control" value="<?php echo $v('guarantor_phone'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Address <span class="text-danger">*</span></label><input type="text" name="guarantor_residential_address" class="form-control" value="<?php echo $v('guarantor_residential_address'); ?>" required></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Documents <span class="fw-normal text-muted small">(JPG/PNG/PDF, max 5MB each — leave blank to keep existing file)</span></h6></div>
                <div class="col-md-4"><label class="form-label">Birth Certificate</label><input type="file" name="birth_certificate" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-4"><label class="form-label">Guarantor's ID</label><input type="file" name="guarantor_id" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-4"><label class="form-label">Proof of Address</label><input type="file" name="proof_address" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-6">
                    <label class="form-label">Passport Photos <span class="fw-normal text-muted small">(adds to existing — multiple allowed)</span></label>
                    <input type="file" name="passport_photos[]" class="form-control" accept=".jpg,.jpeg,.png,.pdf" multiple>
                    <?php $existingPhotos = pretty_json_entries($record['passport_photos'] ?? null);
                    if ($existingPhotos): ?>
                        <div class="small text-muted mt-1"><?php echo count($existingPhotos); ?> photo(s) already on file.</div>
                    <?php endif; ?>
                </div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Office Use</h6></div>
                <div class="col-md-4"><label class="form-label">Package</label><input type="text" name="package" class="form-control" value="<?php echo $v('package', 'Express - GH₵1,800'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (STATUS_OPTIONS as $opt): ?>
                            <option value="<?php echo h($opt); ?>" <?php echo (($record['status'] ?? 'Pending') === $opt) ? 'selected' : ''; ?>><?php echo h($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="pp_decl" name="declaration_accepted" <?php echo !empty($record['declaration_accepted']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="pp_decl">Declaration Accepted</label>
                    </div>
                </div>
                <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"><?php echo $v('remarks'); ?></textarea></div>
            </div>
            <div class="mt-4"><button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-floppy-disk me-2"></i>Save Application</button></div>
        </form>

        <?php elseif ($type === 'birth'): ?>
        <form method="POST" enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_type" value="save_birth">
            <input type="hidden" name="id" value="<?php echo $v('id'); ?>">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label fw-semibold">Child's Name <span class="text-danger">*</span></label><input type="text" name="child_name" class="form-control" value="<?php echo $v('child_name'); ?>" required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label><input type="date" name="dob" class="form-control" value="<?php echo $v('dob'); ?>" required></div>
                <div class="col-md-3"><label class="form-label fw-semibold">Place of Birth <span class="text-danger">*</span></label><input type="text" name="place_of_birth" class="form-control" value="<?php echo $v('place_of_birth'); ?>" required></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Father</h6></div>
                <div class="col-md-4"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="father_name" class="form-control" value="<?php echo $v('father_name'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Occupation <span class="text-danger">*</span></label><input type="text" name="father_occupation" class="form-control" value="<?php echo $v('father_occupation'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Religion <span class="text-danger">*</span></label><input type="text" name="father_religion" class="form-control" value="<?php echo $v('father_religion'); ?>" required></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Mother</h6></div>
                <div class="col-12"><label class="form-label">Maiden Name <span class="text-danger">*</span></label><input type="text" name="mother_maiden_name" class="form-control" value="<?php echo $v('mother_maiden_name'); ?>" required></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Informant &amp; Details</h6></div>
                <div class="col-md-6"><label class="form-label">Informant <span class="text-danger">*</span></label><input type="text" name="informant" class="form-control" value="<?php echo $v('informant'); ?>" required></div>
                <div class="col-md-3"><label class="form-label">Parents Alive? <span class="text-danger">*</span></label>
                    <select name="parents_alive" class="form-select" required>
                        <option value="">Select…</option>
                        <option value="Yes" <?php echo (($record['parents_alive'] ?? '') === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                        <option value="No" <?php echo (($record['parents_alive'] ?? '') === 'No') ? 'selected' : ''; ?>>No</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Telephone Numbers <span class="text-danger">*</span></label><input type="text" name="telephone_numbers" class="form-control" value="<?php echo $v('telephone_numbers'); ?>" required></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Documents <span class="fw-normal text-muted small">(JPG/PNG/PDF, max 5MB each — leave blank to keep existing file)</span></h6></div>
                <div class="col-md-6"><label class="form-label">Father's ID</label><input type="file" name="father_id" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="col-md-6"><label class="form-label">Mother's ID</label><input type="file" name="mother_id" class="form-control" accept=".jpg,.jpeg,.png,.pdf"></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Office Use</h6></div>
                <div class="col-md-4"><label class="form-label">Package</label><input type="text" name="package" class="form-control" value="<?php echo $v('package', 'Express - GH₵1,200'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach (STATUS_OPTIONS as $opt): ?>
                            <option value="<?php echo h($opt); ?>" <?php echo (($record['status'] ?? 'Pending') === $opt) ? 'selected' : ''; ?>><?php echo h($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="bc_decl" name="declaration_accepted" <?php echo !empty($record['declaration_accepted']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="bc_decl">Declaration Accepted</label>
                    </div>
                </div>
                <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2"><?php echo $v('remarks'); ?></textarea></div>
            </div>
            <div class="mt-4"><button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-floppy-disk me-2"></i>Save Application</button></div>
        </form>

        <?php else: /* prescreen */ ?>
        <form method="POST" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="form_type" value="save_prescreen">
            <input type="hidden" name="id" value="<?php echo $v('id'); ?>">
            <div class="row g-3">
                <div class="col-12"><h6 class="btc-section-title">Personal Information</h6></div>
                <div class="col-md-4"><label class="form-label">Last Name <span class="text-danger">*</span></label><input type="text" name="last_name" class="form-control" value="<?php echo $v('last_name'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">First Name <span class="text-danger">*</span></label><input type="text" name="first_name" class="form-control" value="<?php echo $v('first_name'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="form-control" value="<?php echo $v('middle_name'); ?>"></div>
                <div class="col-md-3"><label class="form-label">Date of Birth <span class="text-danger">*</span></label><input type="text" name="dob" class="form-control" value="<?php echo $v('dob'); ?>" placeholder="YYYY-MM-DD" required></div>
                <div class="col-md-3"><label class="form-label">Sex <span class="text-danger">*</span></label>
                    <select name="sex" class="form-select" required>
                        <option value="">Select…</option>
                        <option value="Male" <?php echo (($record['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (($record['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Ghana Card <span class="text-danger">*</span></label><input type="text" name="ghana_card" class="form-control" value="<?php echo $v('ghana_card'); ?>" required></div>
                <div class="col-md-3"><label class="form-label">Phone <span class="text-danger">*</span></label><input type="text" name="phone" class="form-control" value="<?php echo $v('phone'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Work Phone</label><input type="text" name="work_phone" class="form-control" value="<?php echo $v('work_phone'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" value="<?php echo $v('email'); ?>" required></div>
                <div class="col-md-2"><label class="form-label">Disability</label>
                    <select name="disability" class="form-select">
                        <option value="No" <?php echo (($record['disability'] ?? 'No') === 'No') ? 'selected' : ''; ?>>No</option>
                        <option value="Yes" <?php echo (($record['disability'] ?? '') === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                    </select>
                </div>
                <div class="col-md-2"><label class="form-label">Criminal Record</label>
                    <select name="criminal_record" class="form-select">
                        <option value="No" <?php echo (($record['criminal_record'] ?? 'No') === 'No') ? 'selected' : ''; ?>>No</option>
                        <option value="Yes" <?php echo (($record['criminal_record'] ?? '') === 'Yes') ? 'selected' : ''; ?>>Yes</option>
                    </select>
                </div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Address</h6></div>
                <div class="col-12"><label class="form-label">Address <span class="text-danger">*</span></label><input type="text" name="address" class="form-control" value="<?php echo $v('address'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">City <span class="text-danger">*</span></label><input type="text" name="city" class="form-control" value="<?php echo $v('city'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Country <span class="text-danger">*</span></label><input type="text" name="country" class="form-control" value="<?php echo $v('country'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">GPS</label><input type="text" name="gps" class="form-control" value="<?php echo $v('gps'); ?>"></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Emergency Contact</h6></div>
                <div class="col-md-6"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="emergency_name" class="form-control" value="<?php echo $v('emergency_name'); ?>" required></div>
                <div class="col-md-6"><label class="form-label">Relationship <span class="text-danger">*</span></label><input type="text" name="emergency_relationship" class="form-control" value="<?php echo $v('emergency_relationship'); ?>" required></div>
                <div class="col-12"><label class="form-label">Address</label><input type="text" name="emergency_address" class="form-control" value="<?php echo $v('emergency_address'); ?>"></div>
                <div class="col-md-4"><label class="form-label">City</label><input type="text" name="emergency_city" class="form-control" value="<?php echo $v('emergency_city'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Country</label><input type="text" name="emergency_country" class="form-control" value="<?php echo $v('emergency_country'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Ghana Card</label><input type="text" name="emergency_ghana_card" class="form-control" value="<?php echo $v('emergency_ghana_card'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Home Phone</label><input type="text" name="emergency_home_phone" class="form-control" value="<?php echo $v('emergency_home_phone'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Work Phone</label><input type="text" name="emergency_work_phone" class="form-control" value="<?php echo $v('emergency_work_phone'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="emergency_email" class="form-control" value="<?php echo $v('emergency_email'); ?>"></div>
                <div class="col-md-6"><label class="form-label">GPS</label><input type="text" name="emergency_gps" class="form-control" value="<?php echo $v('emergency_gps'); ?>"></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">JSON Sections <span class="fw-normal text-muted small">(edit as raw JSON — leave empty for none)</span></h6></div>
                <div class="col-md-6"><label class="form-label">Medical History</label><textarea name="medical_history" class="form-control font-monospace small" rows="3" placeholder='[{"condition":"","details":""}]'><?php echo h($record['medical_history'] ?? ''); ?></textarea></div>
                <div class="col-md-6"><label class="form-label">Education History</label><textarea name="education_history" class="form-control font-monospace small" rows="3" placeholder='[{"school":"","qualification":"","year":""}]'><?php echo h($record['education_history'] ?? ''); ?></textarea></div>
                <div class="col-md-6"><label class="form-label">Former Employers</label><textarea name="former_employers" class="form-control font-monospace small" rows="3" placeholder='[{"employer":"","position":"","duration":"","reason_for_leaving":""}]'><?php echo h($record['former_employers'] ?? ''); ?></textarea></div>
                <div class="col-md-6"><label class="form-label">References</label><textarea name="references" class="form-control font-monospace small" rows="3" placeholder='[{"name":"","relationship":"","phone":"","address":""}]'><?php echo h($record['references'] ?? ''); ?></textarea></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Application Details</h6></div>
                <div class="col-md-4"><label class="form-label">Position Applied For <span class="text-danger">*</span></label><input type="text" name="position_applied" class="form-control" value="<?php echo $v('position_applied'); ?>" required></div>
                <div class="col-md-4"><label class="form-label">Referred/Authorized By</label><input type="text" name="auth_name" class="form-control" value="<?php echo $v('auth_name'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Signature <span class="text-danger">*</span></label><input type="text" name="signature" class="form-control" value="<?php echo $v('signature'); ?>" required></div>

                <div class="col-12"><h6 class="btc-section-title mt-2">Staff Assessment <span class="fw-normal text-muted small">(office use only)</span></h6></div>
                <div class="col-md-4"><label class="form-label">Interviewed By</label><input type="text" name="interviewed_by" class="form-control" value="<?php echo $v('interviewed_by'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Interview Date</label><input type="date" name="interview_date" class="form-control" value="<?php echo $v('interview_date'); ?>"></div>
                <div class="col-md-4"><label class="form-label">Final Result</label>
                    <select name="final_result" class="form-select">
                        <option value="">— Not Decided —</option>
                        <?php foreach (FINAL_RESULT_OPTIONS as $opt): ?>
                            <option value="<?php echo h($opt); ?>" <?php echo (($record['final_result'] ?? '') === $opt) ? 'selected' : ''; ?>><?php echo h($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php foreach (['rating_neatness'=>'Neatness','rating_character'=>'Character','rating_personality'=>'Personality','rating_ability'=>'Ability','rating_communication'=>'Communication','rating_confidence'=>'Confidence'] as $field => $label): ?>
                <div class="col-md-2">
                    <label class="form-label small"><?php echo h($label); ?> (1-5)</label>
                    <select name="<?php echo $field; ?>" class="form-select">
                        <option value="">—</option>
                        <?php for ($n=1; $n<=5; $n++): ?>
                            <option value="<?php echo $n; ?>" <?php echo (isset($record[$field]) && (int)$record[$field] === $n) ? 'selected' : ''; ?>><?php echo $n; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php endforeach; ?>
                <div class="col-12"><label class="form-label">Remark</label><textarea name="remark" class="form-control" rows="2"><?php echo $v('remark'); ?></textarea></div>
                <div class="col-md-6"><label class="form-label">Approved By</label><input type="text" name="approved_by" class="form-control" value="<?php echo $v('approved_by'); ?>"></div>
            </div>
            <div class="mt-4"><button type="submit" class="btn btn-primary rounded-pill px-4"><i class="fas fa-floppy-disk me-2"></i>Save Application</button></div>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php else: /* -------------------- LIST VIEW -------------------- */ ?>

    <ul class="nav btc-pill-nav mb-4 gap-2 flex-wrap">
        <li class="nav-item"><a class="nav-link <?php echo $stype==='all'?'active':''; ?>" href="services.php?stype=all">All (<?php echo (int)$counts['all']; ?>)</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $stype==='passport'?'active':''; ?>" href="services.php?stype=passport"><i class="fas fa-passport me-1"></i>Passport (<?php echo (int)$counts['passport']; ?>)</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $stype==='birth'?'active':''; ?>" href="services.php?stype=birth"><i class="fas fa-baby me-1"></i>Birth Certificate (<?php echo (int)$counts['birth']; ?>)</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $stype==='prescreen'?'active':''; ?>" href="services.php?stype=prescreen"><i class="fas fa-user-check me-1"></i>Pre-Screening (<?php echo (int)$counts['prescreen']; ?>)</a></li>
    </ul>

    <div class="btc-card p-3 mb-3">
        <form method="GET" class="row g-2 align-items-center">
            <input type="hidden" name="stype" value="<?php echo h($stype); ?>">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search name, ref code, or phone..." value="<?php echo h($q); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <?php foreach (array_unique(array_merge(STATUS_OPTIONS, FINAL_RESULT_OPTIONS)) as $opt): ?>
                        <option value="<?php echo h($opt); ?>" <?php echo $statusFilter === $opt ? 'selected' : ''; ?>><?php echo h($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary rounded-pill w-100" type="submit"><i class="fas fa-filter me-1"></i>Filter</button></div>
            <?php if ($q !== '' || $statusFilter !== ''): ?>
            <div class="col-md-2"><a href="services.php?stype=<?php echo h($stype); ?>" class="btn btn-outline-secondary rounded-pill w-100">Clear</a></div>
            <?php endif; ?>
        </form>
    </div>

    <div class="btc-card">
        <div class="table-responsive">
            <table class="table btc-table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Ref Code</th>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7"><div class="btc-empty"><i class="fas fa-inbox"></i>No applications found<?php echo ($q !== '' || $statusFilter !== '') ? ' for these filters' : ''; ?>.</div></td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><span class="btc-type-badge text-white" style="background:var(--btc-cyan);"><i class="fas <?php echo TYPE_ICONS[$r['type']]; ?> me-1"></i><?php echo h(TYPE_LABELS[$r['type']]); ?></span></td>
                            <td><strong><?php echo h($r['ref_code']); ?></strong></td>
                            <td><?php echo h($r['name']); ?></td>
                            <td><?php echo h($r['contact']); ?></td>
                            <td>
                                <?php if ($r['type'] === 'prescreen'): ?>
                                    <?php echo status_badge($r['status']); ?>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="form_type" value="quick_status">
                                        <input type="hidden" name="type" value="<?php echo h($r['type']); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
                                        <input type="hidden" name="redirect_to" value="services.php?stype=<?php echo h($stype); ?>&page=<?php echo (int)$page; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width:auto;display:inline-block;" onchange="this.form.submit()">
                                            <?php foreach (STATUS_OPTIONS as $opt): ?>
                                                <option value="<?php echo h($opt); ?>" <?php echo $r['status'] === $opt ? 'selected' : ''; ?>><?php echo h($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo h(date('d M Y, g:i A', strtotime($r['created_at']))); ?></small></td>
                            <td>
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="services.php?action=view&type=<?php echo h($r['type']); ?>&id=<?php echo (int) $r['id']; ?>" class="btn btn-sm btn-outline-primary" title="View"><i class="fas fa-eye"></i></a>
                                    <a href="services.php?action=edit&type=<?php echo h($r['type']); ?>&id=<?php echo (int) $r['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fas fa-pen"></i></a>
                                    <a href="services.php?print=1&type=<?php echo h($r['type']); ?>&id=<?php echo (int) $r['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Print"><i class="fas fa-print"></i></a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        data-type="<?php echo h($r['type']); ?>" data-id="<?php echo (int) $r['id']; ?>"
                                        data-name="<?php echo h($r['name']); ?>" data-redirect="services.php?stype=<?php echo h($stype); ?>&page=<?php echo (int)$page; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (!empty($rows) && $totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination justify-content-center">
            <?php
            $baseQs = ['stype' => $stype, 'q' => $q, 'status' => $statusFilter];
            for ($p = 1; $p <= $totalPages; $p++):
                $qs = http_build_query(array_merge($baseQs, ['page' => $p]));
            ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="services.php?<?php echo h($qs); ?>"><?php echo $p; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Delete confirmation modal (replaces confirm()) -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-triangle-exclamation text-danger me-2"></i>Delete Application</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete <strong id="deleteModalName"></strong>? This cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" id="deleteModalForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="form_type" value="delete">
                        <input type="hidden" name="type" id="deleteModalType">
                        <input type="hidden" name="id" id="deleteModalId">
                        <input type="hidden" name="redirect_to" id="deleteModalRedirect">
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.getElementById('deleteModal')?.addEventListener('show.bs.modal', function (event) {
        const btn = event.relatedTarget;
        document.getElementById('deleteModalName').textContent = btn.getAttribute('data-name') || 'this record';
        document.getElementById('deleteModalType').value = btn.getAttribute('data-type');
        document.getElementById('deleteModalId').value = btn.getAttribute('data-id');
        document.getElementById('deleteModalRedirect').value = btn.getAttribute('data-redirect');
    });
    </script>

<?php endif; ?>