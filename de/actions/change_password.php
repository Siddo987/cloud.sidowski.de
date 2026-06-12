<?php
// /de/change_password.php
$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';
if (!$is_logged_in) { redirect($current_language . '/login'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect($current_language . '/profil'); }
validate_csrf_token();

$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
$confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';
$temp_code = isset($_POST['temp_code']) ? $_POST['temp_code'] : ''; 

if (empty($new_password) || strlen($new_password) < 8) { set_flash_message('error_password_too_short','error'); redirect($current_language . '/profil'); }
if ($new_password !== $confirm_new_password) { set_flash_message('error_passwords_dont_match','error'); redirect($current_language . '/profil'); }

// Authentifikation: Entweder aktuelles Passwort oder Temp-Code
$authorized = false;
if (!empty($current_password)) {
    $stmt = $conn->prepare("SELECT password, email, username FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $current_user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($u && password_verify($current_password, $u['password'])) { $authorized = true; }
}
if (!$authorized && !empty($temp_code)) {
    $token_row = validate_user_token($conn, $temp_code, 'password_temp_code');
    if ($token_row && $token_row['user_id'] == $current_user_id) { $authorized = true; mark_user_token_used($conn, $token_row['id']); }
}
if (!$authorized) { set_flash_message('error_no_permission', 'error'); redirect($current_language . '/profil'); }

// Passwort ändern
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$update_stmt = $conn->prepare("UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?");
if ($update_stmt) {
    $update_stmt->bind_param('si', $new_hash, $current_user_id); $res = $update_stmt->execute(); $update_stmt->close();
    if ($res) {
        // Session-Version neu holen und in der aktuellen Session speichern
        $vstmt = $conn->prepare("SELECT session_version, email FROM users WHERE id = ? LIMIT 1"); $vstmt->bind_param('i', $current_user_id); $vstmt->execute(); $row = $vstmt->get_result()->fetch_assoc(); $vstmt->close();
        if ($row) { $_SESSION['session_version'] = (int)$row['session_version']; if (!empty($row['email'])) {
            $subject = 'Passwortänderung';
            $body = '<p>Hallo,</p><p>Dein Passwort wurde soeben geändert. Wenn du diese Änderung nicht durchgeführt hast, kontaktiere den Support.</p>';
            send_email($row['email'], $subject, $body, true);
        } }
        set_flash_message('success_password_changed','success');
        redirect($current_language . '/profil');
    }
}
set_flash_message('error_db_update','error');
redirect($current_language . '/profil');
