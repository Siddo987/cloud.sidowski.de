<?php
// /de/resend_verification.php
$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';

if (!$is_logged_in) { redirect($current_language . '/login'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect($current_language . '/profil'); }
validate_csrf_token();

// Hole aktuelle E-Mail und Status
$stmt = $conn->prepare("SELECT email, email_verified, username FROM users WHERE id = ? LIMIT 1");
if (!$stmt) { set_flash_message('error_db_prepare','error'); redirect($current_language . '/profil'); }
$stmt->bind_param('i', $current_user_id); $stmt->execute(); $res = $stmt->get_result(); $u = $res->fetch_assoc(); $stmt->close();

if (!$u || empty($u['email'])) { set_flash_message('info_email_not_set', 'info'); redirect($current_language . '/profil'); }
if ($u['email_verified']) { set_flash_message('info_email_verified','info'); redirect($current_language . '/profil'); }

$token = generate_random_token(24);
$expires = date('Y-m-d H:i:s', time() + 60*60*24);
if (!create_user_token($conn, $current_user_id, $token, 'email_verification', $expires)) { set_flash_message('error_db_insert','error'); redirect($current_language . '/profil'); }

$verify_url = (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/' . $current_language . '/actions/verify_email?token=' . urlencode($token);
$subject = 'Bitte bestätige deine E-Mail-Adresse';
$body = '<p>Hallo ' . htmlspecialchars($u['username']) . ',</p>' .
        '<p>bitte bestätige deine E-Mail-Adresse, indem du auf den folgenden Link klickst:</p>' .
        '<p><a href="' . htmlspecialchars($verify_url) . '">' . htmlspecialchars($verify_url) . '</a></p>' .
        '<p>Der Link ist 24 Stunden gültig.</p>';

send_email($u['email'], $subject, $body, true);
set_flash_message('success_email_verification_sent', 'success', [$u['email']]);
redirect($current_language . '/profil');
