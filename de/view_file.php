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

$file = null; $stmt = $conn->prepare("SELECT filename, uploader_id, size, physical_path, public FROM files WHERE id = ?");
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
        <input type="hidden" id="csrf_token" value="<?php echo csrf_token(); ?>">
        <button onclick="if(window.history.length > 1) { window.history.back(); } else { window.location.href='<?php echo $current_language; ?>/dashboard'; }" class="button button-secondary" style="margin-right: 5px;">&laquo; Zurück</button>
        <?php if ($file['public']): ?>
        <?php if ($file['uploader_id'] == $current_user_id || $is_admin): ?>
        <button type="button" class="button button-secondary" onclick="openPermissionsModal(<?php echo $file_id; ?>)" style="margin-right: 5px;" title="Wer darf diese Datei sehen?">🔒 Freigabe</button>
        <?php endif; ?>
        <button type="button" class="button button-secondary" onclick="shareFile(<?php echo $file_id; ?>)" style="margin-right: 5px;" title="Link kopieren">🔗 Teilen</button>
        <?php endif; ?>
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

<script>
function shareFile(fileId) {
    const csrfToken = document.getElementById('csrf_token').value;
    fetch('ajax_short_url.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'file_id=' + fileId + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => {
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(data.short_url).then(() => {
                    alert('Kurzlink in die Zwischenablage kopiert:\n' + data.short_url);
                }).catch(err => {
                    prompt('Konnte Link nicht automatisch kopieren. Bitte manuell kopieren:', data.short_url);
                });
            } else {
                prompt('Kurzlink generiert. Bitte manuell kopieren:', data.short_url);
            }
        } else {
            alert('Fehler: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ein Fehler ist aufgetreten: ' + error.message);
    });
}

function openPermissionsModal(fileId) {
    document.getElementById('perm_file_id').value = fileId;
    document.getElementById('perm_users_list').innerHTML = '<div style="padding:10px;">Lade Benutzer...</div>';
    
    let modal = document.getElementById('permissionsModal');
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    
    fetch('ajax_file_permissions.php?file_id=' + fileId)
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert('Fehler: ' + data.message);
            closePermissionsModal();
            return;
        }
        
        // Determine selected radio
        if (!data.login_required) {
            document.querySelector('input[name="perm_type"][value="everyone"]').checked = true;
        } else if (data.restricted_users.length === 0) {
            document.querySelector('input[name="perm_type"][value="loggedin"]').checked = true;
        } else {
            document.querySelector('input[name="perm_type"][value="specific"]').checked = true;
        }
        
        toggleUserSelection();
        
        let html = '';
        data.all_users.forEach(u => {
            let checked = data.restricted_users.includes(u.id) ? 'checked' : '';
            html += `<label style="display:block; padding: 5px; cursor: pointer; border-bottom: 1px solid var(--card-border);">
                <input type="checkbox" class="perm_user_cb" value="${u.id}" ${checked}> ${u.username}
            </label>`;
        });
        document.getElementById('perm_users_list').innerHTML = html;
    })
    .catch(err => alert('Ein Fehler ist aufgetreten: ' + err.message));
}

function closePermissionsModal() {
    let modal = document.getElementById('permissionsModal');
    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function toggleUserSelection() {
    let type = document.querySelector('input[name="perm_type"]:checked').value;
    document.getElementById('perm_users_section').style.display = (type === 'specific') ? 'block' : 'none';
}

function filterUserList() {
    let input = document.getElementById('perm_user_search').value.toLowerCase();
    let labels = document.getElementById('perm_users_list').getElementsByTagName('label');
    for (let i = 0; i < labels.length; i++) {
        if (labels[i].innerText.toLowerCase().includes(input)) {
            labels[i].style.display = 'block';
        } else {
            labels[i].style.display = 'none';
        }
    }
}

function savePermissions() {
    let fileId = document.getElementById('perm_file_id').value;
    let csrfToken = document.getElementById('perm_csrf_token').value;
    
    let type = document.querySelector('input[name="perm_type"]:checked').value;
    let loginRequired = (type !== 'everyone') ? 1 : 0;
    
    let formData = new URLSearchParams();
    formData.append('file_id', fileId);
    formData.append('csrf_token', csrfToken);
    formData.append('login_required', loginRequired);
    
    if (type === 'specific') {
        document.querySelectorAll('.perm_user_cb:checked').forEach(cb => formData.append('restricted_users[]', cb.value));
    }
    
    fetch('ajax_file_permissions.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: formData.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Berechtigungen erfolgreich gespeichert!');
            closePermissionsModal();
        } else {
            alert('Fehler beim Speichern: ' + data.message);
        }
    })
    .catch(err => alert('Fehler: ' + err.message));
}
</script>

<!-- Permissions Modal -->
<div id="permissionsModal" class="modal" aria-hidden="true">
    <div class="modal-content card" style="max-width: 500px; width: 90%; position:relative;">
        <span onclick="closePermissionsModal()" style="position:absolute; right:15px; top:15px; cursor:pointer; font-size:1.5rem; font-weight:bold; color:var(--text-secondary);">&times;</span>
        <h2 style="margin-top:0;">🔒 Zugriffsberechtigungen</h2>
        <p style="color:var(--text-secondary); margin-bottom: 20px;">Wer darf diese Datei sehen?</p>
        
        <form id="permissionsForm">
            <input type="hidden" id="perm_file_id" value="">
            <input type="hidden" id="perm_csrf_token" value="<?php echo csrf_token(); ?>">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 10px; cursor:pointer;">
                    <input type="radio" name="perm_type" value="everyone" onchange="toggleUserSelection()" checked> 
                    <strong>Jeder mit dem Link</strong>
                    <div style="color: var(--text-secondary); font-size: 0.9em; margin-left: 25px;">Auch Gäste ohne Account können die Datei sehen.</div>
                </label>
                
                <label style="display: block; margin-bottom: 10px; cursor:pointer;">
                    <input type="radio" name="perm_type" value="loggedin" onchange="toggleUserSelection()"> 
                    <strong>Alle angemeldeten Nutzer</strong>
                    <div style="color: var(--text-secondary); font-size: 0.9em; margin-left: 25px;">Nur Nutzer mit einem eigenen Account haben Zugriff.</div>
                </label>

                <label style="display: block; margin-bottom: 10px; cursor:pointer;">
                    <input type="radio" name="perm_type" value="specific" onchange="toggleUserSelection()"> 
                    <strong>Nur bestimmte Nutzer</strong>
                    <div style="color: var(--text-secondary); font-size: 0.9em; margin-left: 25px;">Zugriff stark einschränken.</div>
                </label>
            </div>
            
            <div id="perm_users_section" style="display: none; margin-left: 25px; margin-bottom: 1rem; border-left: 2px solid var(--primary-color); padding-left: 15px;">
                <label style="display: block; margin-bottom: 5px;"><strong>Nutzer auswählen:</strong></label>
                
                <input type="text" id="perm_user_search" onkeyup="filterUserList()" placeholder="Benutzer suchen..." class="form-control" style="width: 100%; margin-bottom: 10px; padding: 5px; border: 1px solid var(--card-border); border-radius: 4px;">
                
                <div id="perm_users_list" style="max-height: 200px; overflow-y: auto; border: 1px solid var(--card-border); border-radius: 4px; padding: 5px; background:var(--bg-secondary);">
                    <!-- JavaScript füllt dies -->
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="button" class="button button-secondary" onclick="closePermissionsModal()" style="margin-right: 10px;">Abbrechen</button>
                <button type="button" class="button" onclick="savePermissions()">Speichern</button>
            </div>
        </form>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php'; // Footer laden
?>