<?php
// /de/change_username.php
$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';
if (!$is_logged_in) { redirect($current_language . '/login'); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect($current_language . '/profil'); }
validate_csrf_token();

$new_username = trim(isset($_POST['new_username']) ? $_POST['new_username'] : '');
$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : ''; 

if (empty($new_username) || strlen($new_username) < 3) { set_flash_message('error_invalid_data','error'); redirect($current_language . '/profil'); }

// Passwort prüfen
$stmt = $conn->prepare("SELECT password, email FROM users WHERE id = ? LIMIT 1"); $stmt->bind_param('i', $current_user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$u || !password_verify($current_password, $u['password'])) { set_flash_message('error_no_permission','error'); redirect($current_language . '/profil'); }

// Prüfen ob neuer Username verfügbar
$stmt2 = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?"); if ($stmt2) { $stmt2->bind_param('si', $new_username, $current_user_id); $stmt2->execute(); $stmt2->store_result(); if ($stmt2->num_rows > 0) { $stmt2->close(); set_flash_message('error_username_taken','error'); redirect($current_language . '/profil'); } $stmt2->close(); }

$update = $conn->prepare("UPDATE users SET username = ?, session_version = session_version + 1 WHERE id = ?"); if ($update) { $update->bind_param('si', $new_username, $current_user_id); if ($update->execute()) {
    // Mail an E-Mail-Adresse senden (falls vorhanden)
    if (!empty($u['email'])) { $subject = 'Benutzername geändert'; $body = '<p>Hallo,</p><p>Dein Benutzername wurde geändert. Neuer Benutzername: ' . htmlspecialchars($new_username) . '</p>'; send_email($u['email'], $subject, $body, true); }
    // Session-Version neu holen und setzen
    $vstmt = $conn->prepare("SELECT session_version FROM users WHERE id = ? LIMIT 1"); $vstmt->bind_param('i', $current_user_id); $vstmt->execute(); $row = $vstmt->get_result()->fetch_assoc(); $vstmt->close(); if ($row) { $_SESSION['session_version'] = (int)$row['session_version']; $_SESSION['username'] = $new_username; }
    set_flash_message('success_username_changed','success'); redirect($current_language . '/profil'); }
}
set_flash_message('error_db_update','error'); redirect($current_language . '/profil');
