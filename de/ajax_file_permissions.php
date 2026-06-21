<?php
// /de/ajax_file_permissions.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if (!$is_logged_in) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$file_id = isset($_REQUEST['file_id']) ? (int)$_REQUEST['file_id'] : 0;
if ($file_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
    exit;
}

// Check permission to EDIT file (must be owner or admin)
$stmt = $conn->prepare("SELECT uploader_id, public, login_required FROM files WHERE id = ?");
if (!$stmt) { echo json_encode(['success'=>false, 'message'=>'DB Error']); exit; }
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();
$stmt->close();

if (!$file) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

$is_owner = ($file['uploader_id'] == $current_user_id);
if (!$is_owner && !$is_admin) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all users
    $all_users = [];
    $stmt_users = $conn->prepare("SELECT id, username FROM users WHERE id != ? ORDER BY username ASC");
    $stmt_users->bind_param("i", $file['uploader_id']);
    $stmt_users->execute();
    $res = $stmt_users->get_result();
    while ($row = $res->fetch_assoc()) {
        $all_users[] = $row;
    }
    $stmt_users->close();
    
    // Fetch restricted users for this file
    $restricted_users = [];
    $stmt_access = $conn->prepare("SELECT user_id FROM file_access_users WHERE file_id = ?");
    $stmt_access->bind_param("i", $file_id);
    $stmt_access->execute();
    $res_access = $stmt_access->get_result();
    while ($row = $res_access->fetch_assoc()) {
        $restricted_users[] = $row['user_id'];
    }
    $stmt_access->close();
    
    echo json_encode([
        'success' => true,
        'login_required' => (bool)$file['login_required'],
        'all_users' => $all_users,
        'restricted_users' => $restricted_users
    ]);
    exit;
}

// POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (empty($csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $login_required = isset($_POST['login_required']) && $_POST['login_required'] == '1' ? 1 : 0;
    $restricted_users = isset($_POST['restricted_users']) ? $_POST['restricted_users'] : [];
    if (!is_array($restricted_users)) $restricted_users = [];
    
    // Update files table
    $stmt = $conn->prepare("UPDATE files SET login_required = ? WHERE id = ?");
    $stmt->bind_param("ii", $login_required, $file_id);
    $stmt->execute();
    $stmt->close();
    
    // Update file_access_users table
    $conn->query("DELETE FROM file_access_users WHERE file_id = $file_id");
    
    if ($login_required && !empty($restricted_users)) {
        $stmt_insert = $conn->prepare("INSERT INTO file_access_users (file_id, user_id) VALUES (?, ?)");
        foreach ($restricted_users as $uid) {
            $uid = (int)$uid;
            if ($uid > 0) {
                $stmt_insert->bind_param("ii", $file_id, $uid);
                $stmt_insert->execute();
            }
        }
        $stmt_insert->close();
    }
    
    echo json_encode(['success' => true]);
    exit;
}
