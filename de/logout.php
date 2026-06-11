<?php
// /de/logout.php

$current_language = 'de';
// Bootstrap laden (Pfad korrigiert)
require_once __DIR__ . '/../config/bootstrap.php';

// Optional: CSRF Check
if ($_SERVER['REQUEST_METHOD'] === 'POST') { validate_csrf_token(); }

// Session-Variablen löschen
$_SESSION = array();

// Session-Cookie löschen
if (ini_get("session.use_cookies")) { /* ... wie vorher ... */
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

// Session zerstören
session_destroy();

// Zur Login-Seite weiterleiten
redirect($current_language . '/login');
?>