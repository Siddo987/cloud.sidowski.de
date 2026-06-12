<?php
// /de/exit_impersonation.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

// Nur wenn impersonation aktiv ist
if (!isset($_SESSION['_impersonated_by_admin_id'])) {
    set_flash_message('error_no_permission', 'error');
    redirect($current_language . '/dashboard');
}

$admin_id = $_SESSION['_impersonated_by_admin_id'];

// Hole Admin-Daten neu aus DB
$stmt = $conn->prepare("SELECT id, username, role, session_version FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$admin_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin_user) {
    set_flash_message('error_user_not_found', 'error');
    redirect($current_language . '/login');
}

// Stelle Admin-Session wieder her
$_SESSION['user_id'] = $admin_user['id'];
$_SESSION['username'] = $admin_user['username'];
$_SESSION['role'] = $admin_user['role'];
$_SESSION['session_version'] = $admin_user['session_version'];
unset($_SESSION['_impersonated_by_admin_id']);
unset($_SESSION['_impersonation_start']);
unset($_SESSION['_impersonation_timeout']);

// Log
error_log("IMPERSONATION EXIT: Admin {$admin_user['id']} ({$admin_user['username']}) hat die Impersonation beendet.");

set_flash_message('success_impersonation_ended', 'success');
redirect($current_language . '/all_users');
?>