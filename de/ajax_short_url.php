<?php
// /de/ajax_short_url.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if (!$is_logged_in) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (empty($csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$file_id = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
if (!$file_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
    exit;
}

$stmt = $conn->prepare("SELECT id, public, uploader_id FROM files WHERE id = ? AND deleted = 0");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();
$stmt->close();

if (!$file) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Either public, or owner, or admin
if (!$file['public'] && $file['uploader_id'] !== $current_user_id && !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'File is not public and you are not the owner']);
    exit;
}

$base_url = rtrim(getenv('BASE_URL'), '/');
$long_url = $base_url . '/' . $current_language . '/view_file?id=' . $file_id;

$short_url = generate_short_url($long_url);

if ($short_url) {
    echo json_encode(['success' => true, 'short_url' => $short_url]);
} else {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Generieren des Kurzlinks']);
}
