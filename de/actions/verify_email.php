<?php
// /de/actions/verify_email.php
$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    set_flash_message('error_invalid_data', 'error');
    redirect($current_language . '/login');
}

$token_row = validate_user_token($conn, $token, 'email_verification');
if (!$token_row) {
    set_flash_message('error_email_verification_invalid', 'error');
    redirect($current_language . '/login');
}

// Mark token used, update user
if (mark_user_token_used($conn, $token_row['id'])) {
    $stmt = $conn->prepare("UPDATE users SET email_verified = 1, email_verification_token = NULL, email_verification_expires = NULL WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $token_row['user_id']); $stmt->execute(); $stmt->close();
        set_flash_message('success_email_verified', 'success');
        redirect($current_language . '/login');
    }
}

set_flash_message('error_email_verification_invalid', 'error');
redirect($current_language . '/login');
