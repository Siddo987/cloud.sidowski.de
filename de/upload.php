<?php
// /de/upload.php

$current_language = 'de';
// Bootstrap laden (Pfad korrigiert)
require_once __DIR__ . '/../config/bootstrap.php';

// 1. Login prüfen
if (!$is_logged_in) {
    redirect($current_language . '/login');
}

// Ordner prüfen
$current_folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
if ($current_folder_id) {
    $stmt = $conn->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ? AND deleted = 0");
    $stmt->bind_param("ii", $current_folder_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        set_flash_message('Ordner nicht gefunden', 'error');
        redirect($current_language . '/own_files');
    }
    $stmt->close();
}

// User-Info laden (für unlimited upload)
$stmt = $conn->prepare("SELECT unlimited_upload FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();
$stmt->close();
$unlimited_upload = isset($user['unlimited_upload']) ? $user['unlimited_upload'] : 0;
$max_file_size = $unlimited_upload ? PHP_INT_MAX : MAX_FILE_SIZE;

// 2. Upload-Verzeichnis prüfen und ggf. erstellen (mit Fehlerbehandlung)
$user_upload_path = rtrim(USER_UPLOAD_DIR, '/') . '/' . $current_user_id;
$can_proceed = false; // Flag

if (!is_dir(USER_UPLOAD_DIR)) {
    error_log("FATAL: Basis-Upload-Verzeichnis '" . USER_UPLOAD_DIR . "' existiert nicht!");
    set_flash_message('error_upload_basedir_not_found', 'error'); // Nur Key übergeben
    redirect($current_language . '/dashboard');
} elseif (!is_writable(USER_UPLOAD_DIR)) {
    error_log("FATAL: Basis-Upload-Verzeichnis '" . USER_UPLOAD_DIR . "' ist nicht beschreibbar!");
    set_flash_message('error_upload_basedir_permission', 'error'); // Nur Key übergeben
    redirect($current_language . '/dashboard');
} else {
    if (!is_dir($user_upload_path)) {
        if (!@mkdir($user_upload_path, 0755, true)) {
            $error = error_get_last();
            error_log("Konnte Upload-Verzeichnis nicht erstellen: " . $user_upload_path . " Fehler: " . (isset($error['message']) ? $error['message'] : 'Unbekannt'));
            set_flash_message('error_upload_dir_creation', 'error'); // Nur Key übergeben
            redirect($current_language . '/dashboard');
        } elseif (!is_writable($user_upload_path)) {
            error_log("Upload-Verzeichnis erstellt, aber nicht beschreibbar: " . $user_upload_path);
            set_flash_message('error_upload_dir_permission', 'error'); // Nur Key übergeben
            redirect($current_language . '/dashboard');
        } else { $can_proceed = true; }
    } elseif (!is_writable($user_upload_path)) {
        error_log("Upload-Verzeichnis nicht beschreibbar: " . $user_upload_path);
        set_flash_message('error_upload_dir_permission', 'error'); // Nur Key übergeben
        redirect($current_language . '/dashboard');
    } else { $can_proceed = true; }
}
if (!$can_proceed) { /* Sollte nicht passieren, da oben redirects sind */ redirect($current_language . '/dashboard'); }


// 3. Upload verarbeiten (nur wenn POST-Request und Dateien vorhanden)
// Wichtig: Dieser Block wird jetzt durch die JS FormData Submission getriggert!
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['files'])) {
    validate_csrf_token(); // CSRF-Token prüfen

    $total_files = count($_FILES['files']['name']);
    $uploaded_files_count = 0;
    $error_files_count = 0;
    $upload_errors = []; // Sammle spezifische Fehler (Schlüssel + Argumente)
    $used_filenames_in_batch = []; // Verhindert Cache-Probleme bei identischen Namen im selben Request

    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

        if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) {
            $error_files_count++; $filename_for_error = basename(isset($_FILES['files']['name'][$i]) ? $_FILES['files']['name'][$i] : 'unbekannte Datei');
            switch ($_FILES['files']['error'][$i]) {
                case UPLOAD_ERR_INI_SIZE: case UPLOAD_ERR_FORM_SIZE: $upload_errors[] = ['key' => 'error_file_too_large_php', 'args' => [$filename_for_error]]; break;
                case UPLOAD_ERR_PARTIAL: $upload_errors[] = ['key' => 'error_upload_partial', 'args' => [$filename_for_error]]; break;
                default: $upload_errors[] = ['key' => 'error_upload_unknown', 'args' => [$filename_for_error]];
            }
            continue;
        }

        $original_filename = $_FILES['files']['name'][$i]; $filename = basename($original_filename);
        $file_tmp = $_FILES['files']['tmp_name'][$i]; $file_size = $_FILES['files']['size'][$i];
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        // Validierungen
        if (empty($filename)) { $error_files_count++; $upload_errors[] = ['key' => 'error_upload_invalid_name', 'args' => []]; continue; }
        if ($file_size == 0) { $error_files_count++; $upload_errors[] = ['key' => 'error_upload_empty_file', 'args' => [$filename]]; continue; }
        if ($file_size > $max_file_size) { $error_files_count++; $upload_errors[] = ['key' => 'error_file_too_large', 'args' => [$filename, format_bytes($max_file_size)]]; continue; }

        // Zielpfad bestimmen & Überschreiben verhindern
        $target_filename = $filename; $target_file_path = $user_upload_path . '/' . $target_filename; $counter = 1;
        clearstatcache(true, $target_file_path); // Wichtig, um PHP File-Cache zu leeren
        while (file_exists($target_file_path) || in_array($target_filename, $used_filenames_in_batch)) {
            $filename_no_ext = pathinfo($filename, PATHINFO_FILENAME);
            $target_filename = $filename_no_ext . '_' . $counter . '.' . $file_extension;
            $target_file_path = $user_upload_path . '/' . $target_filename;
            $counter++;
            clearstatcache(true, $target_file_path);
            if ($counter > 100) { $error_files_count++; $upload_errors[] = ['key' => 'error_file_exists_rename', 'args' => [$filename]]; continue 2; }
        }
        
        $used_filenames_in_batch[] = $target_filename;

        // Datei verschieben & DB Eintrag
        if (move_uploaded_file($file_tmp, $target_file_path)) {
            $public = 0; $stmt = $conn->prepare("INSERT INTO files (filename, size, uploader_id, folder_id, public, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("siiis", $target_filename, $file_size, $current_user_id, $current_folder_id, $public);
                if ($stmt->execute()) { $uploaded_files_count++; }
                else { $error_files_count++; $upload_errors[] = ['key' => 'error_db_insert_file', 'args' => [$target_filename]]; error_log("DB Insert failed: " . $conn->error); @unlink($target_file_path); }
                $stmt->close();
            } else { $error_files_count++; $upload_errors[] = ['key' => 'error_db_prepare_file', 'args' => [$target_filename]]; error_log("DB Prepare failed: " . $conn->error); @unlink($target_file_path); }
        } else { $error_files_count++; $upload_errors[] = ['key' => 'error_failed_to_move_file', 'args' => [$filename]]; error_log("Failed to move uploaded file"); }
    } // Ende for-Schleife

    // --- Feedback generieren (NUR Schlüssel und Argumente!) ---
    $final_key = 'info_upload_no_files'; $final_args = []; $final_type = 'info'; // Fallback
    if ($uploaded_files_count > 0 && $error_files_count == 0) {
        $final_key = ($uploaded_files_count == 1) ? 'success_upload_single' : 'success_upload_multi';
        $final_args = [$uploaded_files_count]; $final_type = 'success';
    } elseif ($uploaded_files_count > 0 && $error_files_count > 0) {
        $final_key = 'warning_upload_partial'; $final_args = [$uploaded_files_count, $error_files_count]; $final_type = 'warning';
    } elseif ($uploaded_files_count == 0 && $error_files_count > 0) {
        $final_key = 'error_upload_multi'; $final_args = [$error_files_count]; $final_type = 'error';
    }

    // Speichere detaillierte Fehler separat für die Anzeige nach dem Redirect
    $_SESSION['upload_errors_details'] = $upload_errors;

    // Setze die Haupt-Flash-Nachricht (wird im Header angezeigt)
    set_flash_message($final_key, $final_type, $final_args);

    // Leite zurück zur Upload-Seite (Browser lädt neu und zeigt Nachricht an)
    // Da der Submit jetzt über JS läuft, ist der Redirect die einfachste Feedback-Methode,
    // auch wenn er technisch nicht mehr nötig wäre (man könnte auch mit JS die Seite aktualisieren).
    redirect($current_language . '/upload' . ($current_folder_id ? '?folder=' . $current_folder_id : ''));
} // Ende POST-Verarbeitung


// --- HTML-Ausgabe ---
// Header laden (lädt auch functions_lang.php!)
require_once __DIR__ . '/../includes/header.php';

// Zeige evtl. detaillierte Upload-Fehler aus der letzten Session an
if (isset($_SESSION['upload_errors_details']) && is_array($_SESSION['upload_errors_details']) && count($_SESSION['upload_errors_details']) > 0) {
    echo '<div class="alert alert-error">'; // Oder alert-warning, je nach Fall
    echo '<strong>Fehlerdetails beim letzten Upload:</strong>';
    echo '<ul>';
    foreach ($_SESSION['upload_errors_details'] as $error_detail) {
        // Übersetze jede Fehlermeldung hier (lang() ist jetzt verfügbar)
        echo '<li>' . lang($error_detail['key'], ...$error_detail['args']) . '</li>';
    }
    echo '</ul>';
    echo '</div>';
    unset($_SESSION['upload_errors_details']); // Details nach Anzeige löschen
}
?>

<h1><?php echo lang('title_upload'); ?></h1>

<div class="card">
    <?php /* Flash Messages (die Hauptmeldung) werden im Header angezeigt */ ?>

    <?php // Das Formular wird jetzt per JavaScript abgeschickt (siehe main.js) ?>
    <form id="upload-form" action="upload" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); // CSRF Token für JS Fetch ?>">

        <div class="drop-zone" id="drop-zone">
            <p><?php echo lang('text_drop_zone'); ?></p>
            <?php // Input ist versteckt, wird per JS getriggert ?>
            <input type="file" id="file-input" name="files[]" multiple style="display: none;" />
            <small><?php echo sprintf(lang('text_max_file_size'), format_bytes($max_file_size)); ?></small>
        </div>

        <label for="file-list"><?php echo lang('label_selected_files'); ?></label>
        <?php // Füge data-Attribut hinzu, damit JS die Übersetzung hat für "Keine Dateien..." ?>
        <div class="file-list" id="file-list" data-no-files-text="<?php echo htmlspecialchars(lang('text_no_files_selected')); ?>">
            <p><?php echo lang('text_no_files_selected'); ?></p> <?php // Standardtext ?>
        </div>

        <?php // Button ist initial deaktiviert, wird per JS aktiviert / Zustand geändert ?>
        <button type="submit" class="button" disabled><?php echo lang('button_upload_selected'); ?></button>
    </form>
</div>

<p style="text-align: center; margin-top:20px;"><a href="dashboard" class="button button-secondary"><?php echo lang('button_back_dashboard'); ?></a></p>

<?php
// Footer laden (lädt main.js)
require_once __DIR__ . '/../includes/footer.php';
?>