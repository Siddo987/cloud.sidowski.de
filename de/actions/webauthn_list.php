<?php
// /de/actions/webauthn_list.php
require_once __DIR__ . '/../../config/bootstrap.php';
require_once __DIR__ . '/../../config/webauthn_helpers.php';

// Login prüfen
if (!$is_logged_in) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$credentials = get_user_webauthn_credentials($conn, $user_id);

echo json_encode($credentials);
?>