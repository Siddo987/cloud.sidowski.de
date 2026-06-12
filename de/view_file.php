<?php
// /de/view_file.php
// Zeigt Dateien in einem HTML-Rahmen an (wenn möglich) oder bietet einen Download-Link an.
// Verwendet ?raw=1, um die reinen Dateidaten für eingebettete Elemente bereitzustellen.

// Definiere die Sprache früh
$current_language = 'de';

// Polyfill für str_starts_with (Kompatibilität mit PHP < 8)
// Einige Umgebungen laufen noch mit PHP-Versionen < 8, die diese Funktion nicht bereitstellen.
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        if ($needle === '') return true; // Verhalten wie native Funktion
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

// --- Schritt 0: Prüfen, ob nur Rohdaten angefordert werden ---
if (isset($_GET['raw']) && $_GET['raw'] == '1') {

    // Bootstrap laden für DB-Verbindung, Funktionen, Session-Variablen
    require_once __DIR__ . '/../config/bootstrap.php';

    // ID holen und validieren
    $file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$file_id || $file_id <= 0) { http_response_code(400); exit('Error: Invalid file ID requested for raw output.'); }

    // Berechtigung prüfen
    global $current_user_id, $current_user_role, $conn; // Globale Variablen holen
    if (!check_file_permission($conn, $file_id, $current_user_id, $current_user_role)) { http_response_code(403); exit('Error: Permission denied for raw output.'); }

    // Dateiinfos holen
    $stmt = $conn->prepare("SELECT filename, uploader_id, physical_path FROM files WHERE id = ?");
    if (!$stmt) { http_response_code(500); exit('Error: DB prepare failed.'); }
    $stmt->bind_param("i", $file_id); $stmt->execute(); $result = $stmt->get_result(); $file = $result->fetch_assoc(); $stmt->close();
    if (!$file) { http_response_code(404); exit('Error: File not found in database.'); }

    // Pfad bauen und Existenz/Lesbarkeit prüfen
    $filename = $file['filename']; $uploader_id = $file['uploader_id'];
    $user_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . $uploader_id;
    
    if (!empty($file['physical_path'])) {
        $file_path = $user_dir . '/' . $file['physical_path'];
    } else {
        $new_filepath = $user_dir . '/' . $file_id . '_' . basename($filename);
        $old_filepath = $user_dir . '/' . basename($filename);
        $file_path = file_exists($new_filepath) ? $new_filepath : $old_filepath;
    }
    if (!file_exists($file_path) || !is_readable($file_path)) { http_response_code(404); error_log("Raw file access failed: " . $file_path); exit('Error: File not found or not readable on server.'); }

    // MIME-Typ bestimmen
    $mime_type = 'application/octet-stream'; $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) { $detected_mime = @finfo_file($finfo, $file_path); finfo_close($finfo); if ($detected_mime && strpos($detected_mime, '/') !== false) { $mime_type = $detected_mime; } }
    else { error_log("Could not open finfo database."); }

    // --- Header für Rohdaten senden ---
    if (ob_get_level()) ob_end_clean(); // Puffer leeren!

    header("Content-Type: " . $mime_type); // Wichtigster Header
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=3600');
    header('Pragma: cache');

    // === TEST: Content-Disposition auskommentiert ===
    // Dieser Header schlägt 'inline' vor, kann aber von Browsern (wie Edge?) ignoriert/überschrieben werden.
    // header("Content-Disposition: inline; filename=\"" . basename($filename) . "\"");
    // ===============================================

    // Dateiinhalt senden
    $readfile_result = readfile($file_path);
    if ($readfile_result === false) { error_log("readfile() failed for raw output: " . $file_path); }
    exit(); // Skript beenden
}
// --- Ende Rohdaten-Anforderung ---


// === Normale Seitenanforderung (HTML-Rahmen + Vorschau/Download) ===

// Bootstrap laden
require_once __DIR__ . '/../config/bootstrap.php';

// --- Prüfungen mit Redirects bei Fehlern (bevor HTML gesendet wird) ---
$file_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$file_id || $file_id <= 0) { set_flash_message('error_invalid_file_id', 'error'); redirect($current_language . '/dashboard'); }
if (!check_file_permission($conn, $file_id, $current_user_id, $current_user_role)) { set_flash_message('error_no_permission_view_file', 'error'); redirect($current_language . '/dashboard'); }

$file = null; $stmt = $conn->prepare("SELECT filename, uploader_id, size, physical_path FROM files WHERE id = ?");
if (!$stmt) { error_log("DB Prepare Error (view_file SELECT): " . $conn->error); set_flash_message('error_db_prepare', 'error'); redirect($current_language . '/dashboard'); }
$stmt->bind_param("i", $file_id); $stmt->execute(); $result = $stmt->get_result(); $file = $result->fetch_assoc(); $stmt->close();
if (!$file) { set_flash_message('error_file_not_found', 'error'); redirect($current_language . '/dashboard'); }

$filename = $file['filename']; $uploader_id = $file['uploader_id']; $filesize = $file['size'];
$user_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . $uploader_id;

if (!empty($file['physical_path'])) {
    $file_path = $user_dir . '/' . $file['physical_path'];
} else {
    $new_filepath = $user_dir . '/' . $file_id . '_' . basename($filename);
    $old_filepath = $user_dir . '/' . basename($filename);
    $file_path = file_exists($new_filepath) ? $new_filepath : $old_filepath;
}
if (!file_exists($file_path) || !is_readable($file_path)) { @error_log("Datei physisch nicht gefunden: " . $file_path); set_flash_message('error_file_physical_not_found', 'error'); redirect($current_language . '/dashboard'); }

// MIME-Typ bestimmen
$mime_type = 'application/octet-stream'; $finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo) { $detected_mime = @finfo_file($finfo, $file_path); finfo_close($finfo); if ($detected_mime && strpos($detected_mime, '/') !== false) { $mime_type = $detected_mime; } }

// URLs für eingebettete Inhalte und Download-Button vorbereiten
$raw_file_url = "view_file.php?id=" . $file_id . "&raw=1";
$download_url = "download.php?id=" . $file_id;


// --- HTML-Seite generieren ---
// Header laden (erst jetzt!)
require_once __DIR__ . '/../includes/header.php';
?>

<?php // Kopfzeile der Ansicht ?>
<div class="view-header" style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--card-border); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px;">
    <div>
        <h1 style="margin-bottom: 0.2rem; border: none; padding: 0;"><?php echo htmlspecialchars($filename); ?></h1>
        <small style="color: var(--text-secondary);"><?php echo format_bytes($filesize); ?> - <?php echo htmlspecialchars($mime_type); ?></small>
    </div>
    <div style="flex-shrink: 0;">
        <button onclick="if(window.history.length > 1) { window.history.back(); } else { window.location.href='<?php echo $current_language; ?>/dashboard'; }" class="button button-secondary" style="margin-right: 5px;">&laquo; Zurück</button>
        <a href="<?php echo htmlspecialchars($download_url); ?>" class="button">💾 <?php echo lang('button_download'); ?></a>
    </div>
</div>

<?php // Hauptcontainer für die Vorschau ?>
<div class="view-content card">
    <?php
    // Entscheide basierend auf MIME-Typ
    if (str_starts_with($mime_type, 'image/')) {
        // --- Bild ---
        echo '<img src="' . htmlspecialchars($raw_file_url) . '" alt="' . htmlspecialchars($filename) . '" style="max-width: 100%; height: auto; display: block; margin: auto;" onerror="this.parentElement.innerHTML = \'<div class=unsupported-view><p>Bild konnte nicht geladen werden (möglicherweise ungültiges Format).</p><a href=' . "'" . htmlspecialchars($download_url) . "'" . ' class=button>' . lang('button_download') . '</a></div>\';">';
    } elseif ($mime_type === 'application/pdf') {
        // --- PDF ---
        echo '<iframe src="' . htmlspecialchars($raw_file_url) . '" style="width: 100%; height: 75vh; border: 1px solid var(--card-border);" title="PDF Vorschau">Ihr Browser unterstützt keine eingebetteten PDFs. Sie können die Datei <a href="' . htmlspecialchars($download_url) . '">herunterladen</a>.</iframe>';
        // Alternative: <object data="..." type="application/pdf">...</object>
    } elseif (str_starts_with($mime_type, 'video/')) {
        // --- Video ---
        echo '<video controls preload="metadata" style="max-width: 100%; height: auto; display: block; margin: auto; border: 1px solid var(--card-border);"><source src="' . htmlspecialchars($raw_file_url) . '" type="' . htmlspecialchars($mime_type) . '">Ihr Browser unterstützt die Video-Wiedergabe nicht. <a href="' . htmlspecialchars($download_url) . '">Datei herunterladen</a>.</video>';
    } elseif (str_starts_with($mime_type, 'audio/')) {
        // --- Audio ---
        echo '<audio controls preload="metadata" style="width: 100%;"><source src="' . htmlspecialchars($raw_file_url) . '" type="' . htmlspecialchars($mime_type) . '">Ihr Browser unterstützt die Audio-Wiedergabe nicht. <a href="' . htmlspecialchars($download_url) . '">Datei herunterladen</a>.</audio>';
    } elseif (str_starts_with($mime_type, 'text/')) {
        // --- Text/Code ---
        $content = @file_get_contents($file_path);
        if ($content === false) { echo '<p class="alert alert-error">' . lang('error_reading_file') . '</p>'; }
        else { if (!mb_check_encoding($content, 'UTF-8')) { $content_utf8 = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $content); if ($content_utf8 !== false) $content = $content_utf8; } echo '<pre>' . htmlspecialchars($content) . '</pre>'; }
    } else {
        // --- Fallback für nicht unterstützte Typen ---
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension) {
            $message = '.' . htmlspecialchars($extension) . ' ist noch nicht verfügbar';
        } else {
            $message = 'Vorschau für diesen Dateityp ist noch nicht verfügbar';
        }
        echo '<div class="unsupported-view">';
        echo '<p>' . $message . '</p>';
        echo '<a href="' . htmlspecialchars($download_url) . '" class="button">' . lang('button_download') . '</a>';
        echo '</div>';
    }
    ?>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php'; // Footer laden
?>