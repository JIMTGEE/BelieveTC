<?php
// Basic helper functions
function sanitize($data) {
    return htmlspecialchars(strip_tags($data));
}

function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function redirect($url) {
    header("Location: $url");
    exit();
}

// Secure image upload handler
function uploadImage($file, $targetDir = '../uploads/') {

    // 1. Basic error check (e.g. no file selected, upload too large, etc.)
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // 2. Limit file size (5MB here — adjust as needed)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return false;
    }

    // 3. Verify it's actually an image by checking its real MIME type
    //    (never trust $file['type'], which is client-supplied and easily spoofed)
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mimeType, $allowedTypes)) {
        return false; // not a real image file
    }

    // 4. Generate a completely new, safe filename
    //    (ignore the original name entirely — that's the part attackers control)
    $extension = $allowedTypes[$mimeType];
    $newName   = bin2hex(random_bytes(16)) . '.' . $extension;

    // 5. Make sure the target directory exists and is writable
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $targetFile = rtrim($targetDir, '/') . '/' . $newName;

    // 6. Move the file into place
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $newName;
    }

    return false;
}
function excerptText(string $html, int $maxWords = 20): string
{
    $text = trim(strip_tags($html));
    if ($text === '') {
        return '';
    }

    $words = preg_split('/\s+/', $text);
    if (count($words) <= $maxWords) {
        return $text;
    }

    return implode(' ', array_slice($words, 0, $maxWords)) . '…';
}
?>