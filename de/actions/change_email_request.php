<?php
// /de/change_email_request.php
$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';
if (!$is_logged_in) { redirect($current_language . '/login'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect($current_language . '/profil'); }
validate_csrf_token();

$new_email = trim(isset($_POST['new_email']) ? $_POST['new_email'] : '');
$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : ''; 

if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) { set_flash_message('error_invalid_data','error'); redirect($current_language . '/profil'); }

// Passwort prüfen
$stmt = $conn->prepare("SELECT password, email FROM users WHERE id = ? LIMIT 1"); $stmt->bind_param('i', $current_user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$u || !password_verify($current_password, $u['password'])) { set_flash_message('error_no_permission','error'); redirect($current_language . '/profil'); }

// Prüfen ob E-Mail bereits verwendet wird
$stmt2 = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?"); if ($stmt2) { $stmt2->bind_param('si', $new_email, $current_user_id); $stmt2->execute(); $stmt2->store_result(); if ($stmt2->num_rows > 0) { $stmt2->close(); set_flash_message('error_email_already_taken','error'); redirect($current_language . '/profil'); } $stmt2->close(); }

// Token anlegen (mit meta = neue E-Mail)
$token = generate_random_token(24); $expires = date('Y-m-d H:i:s', time() + 60*60*24);
if (!create_user_token($conn, $current_user_id, $token, 'email_change', $expires, $new_email)) { set_flash_message('error_db_insert','error'); redirect($current_language . '/profil'); }

// Sende Bestätigungs-Mail an die NEUE E-Mail-Adresse
$verify_url = (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/' . $current_language . '/actions/verify_email_change?token=' . urlencode($token);
$subject = 'Bitte bestätige deine neue E-Mail-Adresse';
$body = '<p>Hallo ' . htmlspecialchars(isset($u['email']) ? $u['email'] : '') . ',</p>' .
        '<p>klicke auf den folgenden Link, um die neue E-Mail-Adresse zu bestätigen:</p>' .
        '<p><a href="' . htmlspecialchars($verify_url) . '">' . htmlspecialchars($verify_url) . '</a></p>' .
        '<p>Der Link ist 24 Stunden gültig.</p>';
send_email($new_email, $subject, $body, true);
set_flash_message('success_email_verification_sent', 'success', [$new_email]);
redirect($current_language . '/profil');
