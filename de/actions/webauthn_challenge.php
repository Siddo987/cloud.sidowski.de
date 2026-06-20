<?php
// /de/actions/webauthn_challenge.php
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

if (empty($username)) {
    try {
        $challenge_data = generate_webauthn_discoverable_challenge();
        echo json_encode($challenge_data);
    } catch (Exception $e) {
        error_log('WebAuthn discoverable challenge error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate challenge']);
    }
    exit;
}

$is_email = filter_var($username, FILTER_VALIDATE_EMAIL);
$column = $is_email ? 'email' : 'username';
$stmt = $conn->prepare("SELECT id FROM users WHERE {$column} = ? AND deleted = 0");
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

// Check if user has WebAuthn credentials
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM webauthn_credentials WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row['count'] == 0) {
    http_response_code(404);
    echo json_encode(['error' => 'No WebAuthn credentials found']);
    exit;
}

try {
    $challenge_data = generate_webauthn_auth_challenge($user_id);
    echo json_encode($challenge_data);
} catch (Exception $e) {
    error_log('WebAuthn challenge error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate challenge']);
}
?>