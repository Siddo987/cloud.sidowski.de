<?php
// /de/download.php - Download-Funktion für Dateien

// Alle Ausgaben unterdrücken, um saubere binary Response zu gewährleisten
ini_set('display_errors', 0);
error_reporting(0);
ob_start(); // Output-Buffer starten

// Konfiguration laden
require_once __DIR__ . '/../config/config.php';

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// Session starten, falls nicht bereits geschehen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Datenbankverbindung herstellen
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    // Bei DB-Fehler zur Dashboard weiterleiten
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/dashboard');
    exit();
}
$conn->set_charset("utf8");

// Output-Buffer leeren, um sicherzustellen, dass nichts vor den Headern ausgegeben wird
while (ob_get_level()) {
    ob_end_clean();
}

// Login-Status prüfen
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
if (!$is_logged_in) {
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/login');
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['role']) && in_array(strtolower($_SESSION['role']), ['admin', 'owner']);

// Datei-ID aus GET-Parameter holen und validieren
$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$file_id || $file_id <= 0) {
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/dashboard');
    exit();
}

// Datei-Informationen aus der Datenbank laden
$stmt = $conn->prepare("SELECT filename, public, uploader_id, deleted, size FROM files WHERE id = ?");
if (!$stmt) {
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/dashboard');
    exit();
}
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();
$file = $result->fetch_assoc();
$stmt->close();

if (!$file) {
    // Datei nicht gefunden
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/dashboard');
    exit();
}

// Berechtigung prüfen
$can_download = false;
if ($file['uploader_id'] == $current_user_id) {
    // Eigene Datei
    $can_download = true;
} elseif ($file['public'] == 1) {
    // Öffentliche Datei
    $can_download = true;
} elseif ($is_admin) {
    // Admin kann alles herunterladen
    $can_download = true;
}

if (!$can_download) {
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/dashboard');
    exit();
}

// Dateipfad erstellen
$user_upload_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . (int)$file['uploader_id'];
$file_path = $user_upload_dir . '/' . basename($file['filename']);

// Prüfen, ob die Datei existiert und lesbar ist
if (!file_exists($file_path) || !is_readable($file_path)) {
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/dashboard');
    exit();
}

// HTTP-Header für den Download setzen
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Datei ausliefern
readfile($file_path);

// Skript beenden
exit();
?>