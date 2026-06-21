<?php
// /de/download.php - Download-Funktion für Dateien

// Alle Ausgaben unterdrücken, um saubere binary Response zu gewährleisten
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../log/download_error.log');
ini_set('zlib.output_compression', 'Off');
error_reporting(E_ALL);
ob_start(); // Output-Buffer starten

// Konfiguration und Umgebung laden (dies stellt auch die DB-Verbindung $conn her)
require_once __DIR__ . '/../config/bootstrap.php';

// Login-Status prüfen
if (!$is_logged_in) {
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/login');
    exit();
}

// Datei-ID aus GET-Parameter holen und validieren
$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$file_id || $file_id <= 0) {
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/dashboard');
    exit();
}

// Datei-Informationen aus der Datenbank laden
$stmt = $conn->prepare("SELECT filename, public, uploader_id, deleted, size, physical_path FROM files WHERE id = ?");
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

// Pfad zur Datei zusammensetzen
$user_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . $file['uploader_id'];

if (!empty($file['physical_path'])) {
    $file_path = $user_dir . '/' . $file['physical_path'];
} else {
    // Fallback auf alte Speichermethoden
    $new_filepath = $user_dir . '/' . $file_id . '_' . basename($file['filename']);
    $old_filepath = $user_dir . '/' . basename($file['filename']);
    $file_path = file_exists($new_filepath) ? $new_filepath : $old_filepath;
}

// Prüfen, ob die Datei existiert und lesbar ist
if (!file_exists($file_path) || !is_readable($file_path)) {
    header("Location: " . (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/de/dashboard');
    exit();
}

// Output-Buffer leeren, um sicherzustellen, dass nichts vor den Headern ausgegeben wird
while (ob_get_level()) {
    ob_end_clean();
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