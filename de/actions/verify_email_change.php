<?php
// /de/actions/verify_email_change.php
$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) { set_flash_message('error_invalid_data','error'); redirect($current_language . '/login'); }

$token_row = validate_user_token($conn, $token, 'email_change');
if (!$token_row) { set_flash_message('error_email_verification_invalid','error'); redirect($current_language . '/login'); }

// meta enthält neue E-Mail
$new_email = isset($token_row['meta']) ? $token_row['meta'] : null;
if (empty($new_email)) { set_flash_message('error_invalid_data','error'); redirect($current_language . '/login'); }

if (mark_user_token_used($conn, $token_row['id'])) {
    $stmt = $conn->prepare("UPDATE users SET email = ?, email_verified = 1 WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $new_email, $token_row['user_id']); $stmt->execute(); $stmt->close();
        set_flash_message('success_email_verified','success');
        redirect($current_language . '/profil');
    }
}
set_flash_message('error_email_verification_invalid','error'); redirect($current_language . '/login');
