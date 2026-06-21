<?php
// /de/ajax_delete_orphan.php
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

if (isset($_POST['delete_all']) && $_POST['delete_all'] === '1') {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($upload_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    $deleted_count = 0;
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $path = $file->getRealPath();
            $filename = $file->getFilename();
            if ($filename === '.htaccess' || $filename === 'index.php' || $filename === 'index.html' || $filename === '.DS_Store') {
                continue;
            }
            if (!in_array($path, $valid_paths)) {
                if (unlink($path)) {
                    $deleted_count++;
                }
            }
        }
    }
    echo json_encode(['success' => true, 'deleted_count' => $deleted_count]);
    exit;
}

if (isset($_POST['path'])) {
    $path = $_POST['path'];
    if (!isPathSafe($path, $upload_dir)) {
        echo json_encode(['success' => false, 'message' => 'Invalid path']);
        exit;
    }
    $real_path = realpath($path);
    if (in_array($real_path, $valid_paths)) {
        echo json_encode(['success' => false, 'message' => 'File exists in database! Safety abort.']);
        exit;
    }
    if (unlink($real_path)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unlink failed']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Bad request']);
