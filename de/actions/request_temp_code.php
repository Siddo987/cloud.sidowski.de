<?php
// /de/request_temp_code.php
$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';
if (!$is_logged_in) { redirect($current_language . '/login'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect($current_language . '/profil'); }
validate_csrf_token();

// Hole E-Mail und ob verifiziert
$stmt = $conn->prepare("SELECT email, email_verified, username FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $current_user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$u || empty($u['email'])) { set_flash_message('info_email_not_set','info'); redirect($current_language . '/profil'); }
if (!$u['email_verified']) { set_flash_message('error_email_verification_invalid','error'); redirect($current_language . '/profil'); }

// Erzeuge 6-stelligen Code
$code = random_int(100000, 999999);
$expires = date('Y-m-d H:i:s', time() + 60*15); // 15 Minuten
if (!create_user_token($conn, $current_user_id, (string)$code, 'password_temp_code', $expires)) {
    set_flash_message('error_db_insert','error'); redirect($current_language . '/profil');
}

// Mail senden
$subject = 'Temporärer Bestätigungscode';
$body = '<p>Hallo ' . htmlspecialchars($u['username']) . ',</p>' .
        '<p>Dein temporärer Bestätigungscode lautet: <strong>' . htmlspecialchars((string)$code) . '</strong></p>' .
        '<p>Der Code ist 15 Minuten gültig.</p>';
send_email($u['email'], $subject, $body, true);
set_flash_message('success_temp_code_sent', 'success');
redirect($current_language . '/profil');
