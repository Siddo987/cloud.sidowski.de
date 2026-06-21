<?php
// /de/ajax_restore_orphan.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if (!$is_logged_in || !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$upload_dir = realpath(USER_UPLOAD_DIR);
if (!$upload_dir) {
    echo json_encode(['success' => false, 'message' => 'Upload dir not found']);
    exit;
}

function isPathSafe($path, $baseDir) {
    $real = realpath($path);
    if ($real === false) return false;
    return strpos($real, $baseDir) === 0;
}

$path = $_POST['path'] ?? '';
$filename = trim($_POST['filename'] ?? '');
$uploader_id = (int)($_POST['uploader_id'] ?? 0);
$public = (int)($_POST['public'] ?? 0);

if (!$path || !$filename || !$uploader_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!isPathSafe($path, $upload_dir)) {
    echo json_encode(['success' => false, 'message' => 'Invalid path']);
    exit;
}
$real_path = realpath($path);

// Hole alle legitimen DB-Pfade, um sicherzugehen, dass es eine Leiche ist
$valid_paths = [];
$stmt = $conn->query("SELECT id, filename, physical_path, uploader_id FROM files");
while ($row = $stmt->fetch_assoc()) {
    $user_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . $row['uploader_id'];
    if (!empty($row['physical_path'])) {
        $real = realpath($user_dir . '/' . $row['physical_path']);
        if ($real) $valid_paths[] = $real;
    } else {
        $real1 = realpath($user_dir . '/' . $row['id'] . '_' . basename($row['filename']));
        $real2 = realpath($user_dir . '/' . basename($row['filename']));
        if ($real1) $valid_paths[] = $real1;
        if ($real2) $valid_paths[] = $real2;
    }
}
$valid_paths = array_filter($valid_paths);
$valid_paths = array_unique($valid_paths);

if (in_array($real_path, $valid_paths)) {
    echo json_encode(['success' => false, 'message' => 'File exists in database! Safety abort.']);
    exit;
}

// User-Upload-Verzeichnis
$target_user_dir = $upload_dir . '/' . $uploader_id;
if (!is_dir($target_user_dir)) {
    mkdir($target_user_dir, 0755, true);
}

// Generiere neuen verschachtelten Pfad
$rand1 = substr(md5(uniqid(mt_rand(), true)), 0, 8);
$rand2 = substr(md5(uniqid(mt_rand(), true)), 0, 8);
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$rand3 = md5(uniqid(mt_rand(), true));
if ($ext) { $rand3 .= '.' . $ext; }

$new_physical_path = $rand1 . '/' . $rand2 . '/' . $rand3;
$new_target_path = $target_user_dir . '/' . $new_physical_path;

// Unterordner erstellen
$dir_path = dirname($new_target_path);
if (!is_dir($dir_path)) {
    mkdir($dir_path, 0755, true);
}

// Datei verschieben
if (!rename($real_path, $new_target_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move file']);
    exit;
}

// In DB eintragen
$file_size = filesize($new_target_path);
$mime_type = mime_content_type($new_target_path);
if (!$mime_type) $mime_type = 'application/octet-stream';

$folder_id = null; // Root Ordner
$login_required = 0; // Standard

$stmt = $conn->prepare("INSERT INTO files (filename, size, mime_type, physical_path, uploader_id, folder_id, public, login_required, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
if ($stmt) {
    $stmt->bind_param("sisssiii", $filename, $file_size, $mime_type, $new_physical_path, $uploader_id, $folder_id, $public, $login_required);
    if ($stmt->execute()) {
        $file_id = $conn->insert_id;
        $stmt->close();
        echo json_encode(['success' => true, 'file_id' => $file_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
