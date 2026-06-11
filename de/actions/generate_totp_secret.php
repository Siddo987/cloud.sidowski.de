<?php
// /de/actions/generate_totp_secret.php

$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';

if (!$is_logged_in) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Generate TOTP secret (but don't save to DB yet)
$secret = tf_generate_secret();

// Store in session for verification later
$_SESSION['pending_totp_secret'] = $secret;

// Build TOTP URL
$totp_url = 'otpauth://totp/Datei%20Wolke:' . urlencode($current_username) . '?secret=' . $secret . '&issuer=Datei%20Wolke';

header('Content-Type: application/json');
echo json_encode(['totp_url' => $totp_url]);
?>
