<?php
// /de/actions/get_totp_url.php

$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';

// Login prüfen
if (!$is_logged_in) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Nur GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Hole die aktuelle 2FA info
$stmt = $conn->prepare("SELECT two_factor_method, two_factor_secret FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $current_user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || $row['two_factor_method'] !== 'totp') {
    http_response_code(400);
    echo json_encode(['error' => 'TOTP not setup']);
    exit;
}

$secret = $row['two_factor_secret'];
if (empty($secret)) {
    http_response_code(400);
    echo json_encode(['error' => 'TOTP secret not found']);
    exit;
}

$totp_url = 'otpauth://totp/Datei%20Wolke:' . urlencode($current_username) . '?secret=' . $secret . '&issuer=Datei%20Wolke';

header('Content-Type: application/json');
echo json_encode(['totp_url' => $totp_url]);
?>
