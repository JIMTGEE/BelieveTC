<?php
session_start();
include '../includes/db.php';
include '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$action = $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['approve', 'delete'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE comments SET approved = 1 WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
    }
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Comment moderation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}