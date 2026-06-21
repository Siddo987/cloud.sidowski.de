<?php
// /de/all_files.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if (!$is_logged_in) { redirect($current_language . '/login'); }

// Admin-Zugriff prüfen
if (!$is_admin) {
    set_flash_message('Nur Administratoren können diese Seite sehen', 'error');
    redirect($current_language . '/dashboard');
}

// Suchbegriff und Ordner
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$current_folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action_taken = false;
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];
    $redirect_url = $current_language . '/all_files' . ($current_folder_id ? '?folder=' . $current_folder_id : '') . (!empty($search_term) ? '&search=' . urlencode($search_term) : '');

    // Ordner löschen
    if (isset($_POST['delete_folder'])) {
        $folder_id_to_delete = filter_input(INPUT_POST, 'folder_id', FILTER_VALIDATE_INT);
        if ($folder_id_to_delete) {
            // Hole User ID um cascade_soft_delete_folder korrekt auszuführen
            $stmt_u = $conn->prepare("SELECT user_id FROM folders WHERE id = ?");
            $stmt_u->bind_param("i", $folder_id_to_delete);
            $stmt_u->execute();
            $owner_row = $stmt_u->get_result()->fetch_assoc();
            $stmt_u->close();

            $stmt = $conn->prepare("UPDATE folders SET deleted = 1 WHERE id = ? AND deleted = 0");
            $stmt->bind_param("i", $folder_id_to_delete);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                if ($owner_row) cascade_soft_delete_folder($conn, $folder_id_to_delete, $owner_row['user_id']);
                set_flash_message('Ordner erfolgreich in den Papierkorb verschoben', 'success');
                if ($ajax_mode) { $ajax_response = ['success' => true, 'message' => 'Ordner erfolgreich verschoben', 'action' => 'delete_folder', 'data' => ['folder_id' => $folder_id_to_delete]]; }
            } else {
                set_flash_message('Fehler beim Löschen des Ordners', 'error');
                if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Fehler beim Löschen des Ordners']; }
            }
            $stmt->close();
        } else { set_flash_message('Ungültige Ordner-ID', 'error'); }
        $action_taken = true;
    }

    // Ordner umbenennen
    if (isset($_POST['rename_folder'])) {
        $folder_id_rename = filter_input(INPUT_POST, 'folder_id', FILTER_VALIDATE_INT);
        $new_foldername = trim(isset($_POST['new_foldername']) ? $_POST['new_foldername'] : '');
        if ($folder_id_rename && !empty($new_foldername)) {
            if (preg_match('/^[a-zA-Z0-9._\-\s\(\)]+$/', $new_foldername) && strlen($new_foldername) <= 255) {
                $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ?");
                $stmt->bind_param('si', $new_foldername, $folder_id_rename);
                if ($stmt->execute()) {
                    set_flash_message('Ordner erfolgreich umbenannt', 'success');
                    if ($ajax_mode) { $ajax_response = ['success' => true, 'message' => 'Ordner erfolgreich umbenannt', 'action' => 'rename_folder', 'data' => ['folder_id' => $folder_id_rename, 'new_name' => $new_foldername]]; }
                } else {
                    set_flash_message('Fehler beim Umbenennen', 'error');
                    if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Fehler beim Umbenennen']; }
                }
                $stmt->close();
            } else { set_flash_message('Ungültiger Ordnername', 'error'); }
        } else { set_flash_message('Ungültige Daten', 'error'); }
        $action_taken = true;
    }

    // Datei löschen (soft delete)
    if (isset($_POST['delete_file'])) {
        $file_id_to_delete = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_delete) {
            $stmt = $conn->prepare("UPDATE files SET deleted = 1 WHERE id = ? AND deleted = 0");
            $stmt->bind_param("i", $file_id_to_delete);
            if ($stmt->execute()) {
                set_flash_message('Datei erfolgreich in den Papierkorb verschoben', 'success');
                if ($ajax_mode) { $ajax_response = ['success' => true, 'message' => 'Datei erfolgreich in den Papierkorb verschoben', 'action' => 'delete_file', 'data' => ['file_id' => $file_id_to_delete]]; }
            } else {
                set_flash_message('Fehler beim Löschen der Datei', 'error');
                if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Fehler beim Löschen der Datei']; }
            }
            $stmt->close();
        } else { set_flash_message(lang('error_invalid_data'), 'error'); }
        $action_taken = true;
    }

    // Status ändern (Public/Private)
    if (isset($_POST['toggle_public_status'])) {
        $file_id_toggle = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        $current_status = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT);
        $current_status_int = is_numeric($current_status) ? (int)$current_status : null;

        if ($file_id_toggle && ($current_status_int === 0 || $current_status_int === 1)) {
            $new_status = 1 - $current_status_int;
            $update_query = $conn->prepare("UPDATE files SET public = ? WHERE id = ?");
            if ($update_query) {
                $update_query->bind_param("ii", $new_status, $file_id_toggle);
                if ($update_query->execute()) {
                    set_flash_message('Status erfolgreich aktualisiert', 'success');
                    if ($ajax_mode) { $ajax_response = ['success' => true, 'message' => 'Status erfolgreich aktualisiert', 'action' => 'toggle_public_status', 'data' => ['file_id' => $file_id_toggle, 'new_status' => $new_status]]; }
                } else {
                    set_flash_message('Fehler bei Update', 'error');
                    if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Fehler bei Update']; }
                }
                $update_query->close();
            } else { set_flash_message('Datenbankfehler', 'error'); }
        } else { set_flash_message('Ungültige Daten', 'error'); }
        $action_taken = true;
    }

    // Datei umbenennen
    if (isset($_POST['rename_file'])) {
        $file_id_rename = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        $new_filename = trim(isset($_POST['new_filename']) ? $_POST['new_filename'] : '');
        if ($file_id_rename && !empty($new_filename)) {
            if (preg_match('/^[a-zA-Z0-9._\-\s\(\)]+$/', $new_filename) && strlen($new_filename) <= 255) {
                $stmt = $conn->prepare("UPDATE files SET filename = ? WHERE id = ?");
                $stmt->bind_param('si', $new_filename, $file_id_rename);
                if ($stmt->execute()) {
                    set_flash_message('Datei erfolgreich umbenannt', 'success');
                    if ($ajax_mode) { $ajax_response = ['success' => true, 'message' => 'Datei erfolgreich umbenannt', 'action' => 'rename_file', 'data' => ['file_id' => $file_id_rename, 'new_name' => $new_filename]]; }
                } else {
                    set_flash_message('Fehler beim Umbenennen', 'error');
                    if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Fehler beim Umbenennen']; }
                }
                $stmt->close();
            } else { set_flash_message('Ungültiger Dateiname', 'error'); }
        } else { set_flash_message('Ungültige Daten', 'error'); }
        $action_taken = true;
    }

    if ($action_taken) {
        if ($ajax_mode) {
            ob_start();
            try {
                if (function_exists('lang') && isset($ajax_response['message']) && preg_match('/^[a-z_]+$/', $ajax_response['message'])) {
                    $ajax_response['message'] = lang($ajax_response['message']);
                }
            } catch (Throwable $e) { error_log('Error translating message: ' . $e->getMessage()); }
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode($ajax_response);
            exit;
        }
        redirect($redirect_url);
    }
}
// --- ENDE POST Handling ---

// Breadcrumb-Pfad bauen
function build_admin_breadcrumb($conn, $folder_id) {
    $path = [];
    $current_id = $folder_id;
    while ($current_id) {
        $stmt = $conn->prepare("SELECT id, name, parent_id FROM folders WHERE id = ? AND deleted = 0");
        $stmt->bind_param("i", $current_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $folder = $result->fetch_assoc();
        $stmt->close();
        if ($folder) {
            array_unshift($path, $folder);
            $current_id = $folder['parent_id'];
        } else {
            break;
        }
    }
    return $path;
}

$breadcrumb = build_admin_breadcrumb($conn, $current_folder_id);

// Ordner holen
$folders_sql = "SELECT f.id, f.name, f.created_at, u.username as owner_username FROM folders f LEFT JOIN users u ON f.user_id = u.id WHERE f.deleted = 0";
$folders_params = []; $folders_types = "";

if (!empty($search_term)) {
    $folders_sql .= " AND f.name LIKE ?";
    $folders_params[] = '%' . $search_term . '%';
    $folders_types .= "s";
} else {
    if ($current_folder_id !== null) {
        $folders_sql .= " AND f.parent_id = ?";
        $folders_params[] = $current_folder_id;
        $folders_types .= "i";
    } else {
        $folders_sql .= " AND f.parent_id IS NULL";
    }
}

$folders_sql .= " ORDER BY f.name ASC";
$folders_stmt = $conn->prepare($folders_sql);
if ($folders_stmt) {
    if (!empty($folders_params)) { $folders_stmt->bind_param($folders_types, ...$folders_params); }
    $folders_stmt->execute();
    $folders_result = $folders_stmt->get_result();
    $folders = $folders_result->fetch_all(MYSQLI_ASSOC);
    $folders_stmt->close();
} else {
    $folders = [];
}

// Dateien holen
$files_sql = "SELECT f.id, f.filename, f.created_at, f.public, f.size, f.uploader_id, u.username as uploader_username FROM files f LEFT JOIN users u ON f.uploader_id = u.id WHERE f.deleted = 0";
$files_params = []; $files_types = "";

if (!empty($search_term)) {
    $files_sql .= " AND f.filename LIKE ?";
    $files_params[] = '%' . $search_term . '%';
    $files_types .= "s";
} else {
    if ($current_folder_id !== null) {
        $files_sql .= " AND f.folder_id = ?";
        $files_params[] = $current_folder_id;
        $files_types .= "i";
    } else {
        $files_sql .= " AND f.folder_id IS NULL";
    }
}

$files_sql .= " ORDER BY f.created_at DESC";
$files_stmt = $conn->prepare($files_sql);
if ($files_stmt) {
    if (!empty($files_params)) { $files_stmt->bind_param($files_types, ...$files_params); }
    $files_stmt->execute();
    $files_result = $files_stmt->get_result();
    $files = $files_result->fetch_all(MYSQLI_ASSOC);
    $files_stmt->close();
} else {
    $files = [];
}

// --- HTML Ausgabe ---
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Alle Dateien (Admin)</h1>

<!-- Breadcrumb -->
<?php if (empty($search_term)): ?>
<div class="breadcrumb" style="margin-bottom: 20px;">
    <a href="all_files" class="breadcrumb-link">Alle Dateien Start</a>
    <?php foreach ($breadcrumb as $crumb): ?>
        / <a href="all_files?folder=<?php echo $crumb['id']; ?>" class="breadcrumb-link"><?php echo htmlspecialchars($crumb['name']); ?></a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="breadcrumb" style="margin-bottom: 20px;">
    <a href="all_files" class="breadcrumb-link">Alle Dateien Start</a>
    / Suchergebnisse für "<?php echo htmlspecialchars($search_term); ?>"
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="all_files" class="search-form-inline" style="margin-bottom: 20px;">
        <?php if (!empty($current_folder_id)): ?>
            <!-- Current folder is ignored during search but we keep it in case search is cleared -->
        <?php endif; ?>
        <input type="search" name="search" placeholder="Dateien & Ordner durchsuchen..." value="<?php echo htmlspecialchars($search_term); ?>" style="width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
        <button type="submit" class="button button-secondary" style="padding: 8px 16px; margin-left: 10px;">Suchen</button>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Erstellt am</th>
                    <th>Besitzer/Uploader</th>
                    <th>Größe</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($files) && empty($folders)): ?>
                    <tr><td colspan="6">Keine Elemente gefunden.</td></tr>
                <?php else: ?>
                    <?php foreach ($folders as $folder): ?>
                        <tr class="folder-row" data-folder-id="<?php echo $folder['id']; ?>">
                            <td>
                                <i class="icon-folder"></i> 
                                <a href="all_files?folder=<?php echo $folder['id']; ?>" class="folder-link"><?php echo htmlspecialchars($folder['name']); ?></a>
                            </td>
                            <td><?php echo format_date_lang($folder['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($folder['owner_username'] ?? 'Unbekannt'); ?></td>
                            <td><?php echo format_bytes(get_folder_size($conn, $folder['id'])); ?></td>
                            <td>-</td>
                            <td class="actions-cell">
                                <button onclick="renameFolder(<?php echo $folder['id']; ?>, '<?php echo addslashes($folder['name']); ?>')" class="action-button" title="Umbenennen">✏️</button>
                                <form method="post" action="all_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>" class="ajax-action" style="display:inline;" data-custom-confirm="Ordner wirklich löschen?">
                                    <input type="hidden" name="ajax" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                    <input type="hidden" name="delete_folder" value="1">
                                    <button type="submit" name="delete_folder" class="action-button delete-button" title="Löschen">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($files as $file): ?>
                        <tr class="file-row" data-file-id="<?php echo $file['id']; ?>">
                            <td class="filename-cell"><a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo shorten_filename($file['filename']); ?></a></td>
                            <td><?php echo format_date_lang($file['created_at']); ?></td>
                            <td><?php echo $file['uploader_username'] ? htmlspecialchars($file['uploader_username']) : get_username_by_id($conn, $file['uploader_id']); ?></td>
                            <td><?php echo format_bytes($file['size']); ?></td>
                            <td><span class="status-label <?php echo $file['public'] ? 'status-public' : 'status-private'; ?>"><?php echo $file['public'] ? 'Öffentlich' : 'Privat'; ?></span></td>
                            <td class="actions-cell">
                                <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="Ansehen">👁️</a>
                                <form method="post" action="all_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>" class="ajax-action" style="display:inline;">
                                    <input type="hidden" name="ajax" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $file['public']; ?>">
                                    <input type="hidden" name="toggle_public_status" value="1">
                                    <button type="submit" name="toggle_public_status" class="action-button <?php echo $file['public'] ? 'private-button' : 'public-button'; ?>" title="<?php echo $file['public'] ? 'Privat machen' : 'Öffentlich machen'; ?>"><?php echo $file['public'] ? '🔒' : '🌍'; ?></button>
                                </form>
                                <a href="download?id=<?php echo $file['id']; ?>" class="action-button download-button" title="Herunterladen">💾</a>
                                <button onclick="renameFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['filename']); ?>')" class="action-button" title="Umbenennen">✏️</button>
                                <form method="post" action="all_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>" class="ajax-action" style="display:inline;" data-custom-confirm="<?php printf(lang('text_confirm_delete_file'), htmlspecialchars(addslashes($file['filename']))); ?>">
                                    <input type="hidden" name="ajax" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <input type="hidden" name="delete_file" value="1">
                                    <button type="submit" name="delete_file" class="action-button delete-button" title="Löschen">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function renameFolder(folderId, oldName) {
    var newName = prompt("Neuer Name für den Ordner:", oldName);
    if (newName !== null && newName !== oldName && newName.trim() !== "") {
        var form = document.createElement("form");
        form.method = "POST";
        form.action = "all_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>";
        
        var inputAjax = document.createElement("input");
        inputAjax.type = "hidden";
        inputAjax.name = "ajax";
        inputAjax.value = "0"; // Hier kein echtes AJAX, normales POST ist einfacher für prompt fallback
        form.appendChild(inputAjax);

        var inputCsrf = document.createElement("input");
        inputCsrf.type = "hidden";
        inputCsrf.name = "csrf_token";
        inputCsrf.value = "<?php echo csrf_token(); ?>";
        form.appendChild(inputCsrf);

        var inputAction = document.createElement("input");
        inputAction.type = "hidden";
        inputAction.name = "rename_folder";
        inputAction.value = "1";
        form.appendChild(inputAction);

        var inputId = document.createElement("input");
        inputId.type = "hidden";
        inputId.name = "folder_id";
        inputId.value = folderId;
        form.appendChild(inputId);

        var inputName = document.createElement("input");
        inputName.type = "hidden";
        inputName.name = "new_foldername";
        inputName.value = newName;
        form.appendChild(inputName);

        document.body.appendChild(form);
        form.submit();
    }
}
function renameFile(fileId, oldName) {
    var newName = prompt("Neuer Name für die Datei:", oldName);
    if (newName !== null && newName !== oldName && newName.trim() !== "") {
        var form = document.createElement("form");
        form.method = "POST";
        form.action = "all_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>";
        
        var inputAjax = document.createElement("input");
        inputAjax.type = "hidden";
        inputAjax.name = "ajax";
        inputAjax.value = "0";
        form.appendChild(inputAjax);

        var inputCsrf = document.createElement("input");
        inputCsrf.type = "hidden";
        inputCsrf.name = "csrf_token";
        inputCsrf.value = "<?php echo csrf_token(); ?>";
        form.appendChild(inputCsrf);

        var inputAction = document.createElement("input");
        inputAction.type = "hidden";
        inputAction.name = "rename_file";
        inputAction.value = "1";
        form.appendChild(inputAction);

        var inputId = document.createElement("input");
        inputId.type = "hidden";
        inputId.name = "file_id";
        inputId.value = fileId;
        form.appendChild(inputId);

        var inputName = document.createElement("input");
        inputName.type = "hidden";
        inputName.name = "new_filename";
        inputName.value = newName;
        form.appendChild(inputName);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
