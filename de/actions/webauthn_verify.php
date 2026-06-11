<?php
// /de/actions/webauthn_verify.php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/webauthn_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$assertion = $input['assertion'] ?? null;

if (empty($username) || !$assertion) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and assertion required']);
    exit;
}

$is_email = filter_var($username, FILTER_VALIDATE_EMAIL);
$column = $is_email ? 'email' : 'username';
$stmt = $conn->prepare("SELECT id, username, role, session_version FROM users WHERE {$column} = ? AND deleted = 0");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$user_id = $user['id'];

try {
    $valid = validate_webauthn_assertion($user_id, $assertion);
    if ($valid) {
        // Login successful
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['session_version'] = isset($user['session_version']) ? (int)$user['session_version'] : 0;
        echo json_encode(['success' => true]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication failed']);
    }
} catch (Exception $e) {
    error_log('WebAuthn verify error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Verification failed']);
}
?>