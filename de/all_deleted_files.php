<?php
// /de/all_deleted_files.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php'; // Pfad korrigiert

if (!$is_admin) { redirect($current_language . '/dashboard'); }

// Suchbegriff und Ordner holen
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$current_folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action_taken = false;
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];
    $redirect_url = $current_language . '/all_deleted_files' . ($current_folder_id ? '?folder=' . $current_folder_id : '') . (!empty($search_term) ? '&search=' . urlencode($search_term) : '');

    // Datei wiederherstellen
    if (isset($_POST['restore_file'])) {
        $file_id_to_restore = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_restore) {
            $stmt = $conn->prepare("UPDATE files SET deleted = 0 WHERE id = ? AND deleted = 1");
            $stmt->bind_param("i", $file_id_to_restore);
            if ($stmt->execute()) {
                // Wenn die Datei in einem noch gelöschten Ordner lag, ins Root verschieben
                $stmt_f = $conn->prepare("SELECT folder_id, uploader_id FROM files WHERE id = ?");
                $stmt_f->bind_param("i", $file_id_to_restore);
                $stmt_f->execute();
                $f_row = $stmt_f->get_result()->fetch_assoc();
                $stmt_f->close();

                if ($f_row && $f_row['folder_id']) {
                    $stmt_cf = $conn->prepare("SELECT deleted FROM folders WHERE id = ?");
                    $stmt_cf->bind_param("i", $f_row['folder_id']);
                    $stmt_cf->execute();
                    $cf_row = $stmt_cf->get_result()->fetch_assoc();
                    $stmt_cf->close();
                    if ($cf_row && $cf_row['deleted'] == 1) {
                        $stmt_upd = $conn->prepare("UPDATE files SET folder_id = NULL WHERE id = ?");
                        $stmt_upd->bind_param("i", $file_id_to_restore);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }
                    cleanup_empty_trashed_folders($conn, $f_row['folder_id'], $f_row['uploader_id']);
                }
                
                set_flash_message('Datei erfolgreich wiederhergestellt', 'success');
                if ($ajax_mode) {
                    $ajax_response = ['success' => true, 'message' => 'Datei erfolgreich wiederhergestellt', 'action' => 'restore_file', 'data' => ['file_id' => $file_id_to_restore]];
                }
            } else {
                set_flash_message('Fehler beim Wiederherstellen der Datei', 'error');
                if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Fehler beim Wiederherstellen der Datei']; }
            }
            $stmt->close();
        } else { set_flash_message(lang('error_invalid_data'), 'error'); if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'error_invalid_data']; }}
        $action_taken = true;
    }

    // Datei endgültig löschen
    if (isset($_POST['delete_permanently'])) {
        $file_id_to_delete = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_delete) {
            $stmt_f = $conn->prepare("SELECT folder_id, uploader_id FROM files WHERE id = ?");
            $stmt_f->bind_param("i", $file_id_to_delete);
            $stmt_f->execute();
            $f_row = $stmt_f->get_result()->fetch_assoc();
            $stmt_f->close();
            
            $uploader_id = $f_row ? $f_row['uploader_id'] : $current_user_id;

            $delete_result = delete_file_permanently($conn, $file_id_to_delete, $uploader_id, $current_user_role);
            if ($delete_result['success'] && $f_row && $f_row['folder_id']) {
                cleanup_empty_trashed_folders($conn, $f_row['folder_id'], $uploader_id);
            }
            
            set_flash_message($delete_result['message_key'], $delete_result['success'] ? 'success' : 'error', $delete_result['message_args']);
            if ($ajax_mode) {
                $ajax_response = ['success' => $delete_result['success'], 'message' => $delete_result['message_key'], 'action' => 'delete_permanently', 'data' => ['file_id' => $file_id_to_delete], 'message_args' => isset($delete_result['message_args']) && is_array($delete_result['message_args']) ? $delete_result['message_args'] : []];
            }
        } else { set_flash_message(lang('error_invalid_data'), 'error'); if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'error_invalid_data']; }}
        $action_taken = true;
    }

    // Ordner wiederherstellen
    if (isset($_POST['restore_folder'])) {
        $folder_id_to_restore = filter_input(INPUT_POST, 'folder_id', FILTER_VALIDATE_INT);
        if ($folder_id_to_restore) {
            // Finde den Besitzer des Ordners, um ihn richtig wiederherzustellen
            $stmt_owner = $conn->prepare("SELECT user_id FROM folders WHERE id = ?");
            $stmt_owner->bind_param("i", $folder_id_to_restore);
            $stmt_owner->execute();
            $owner_row = $stmt_owner->get_result()->fetch_assoc();
            $stmt_owner->close();
            
            $owner_id = $owner_row ? $owner_row['user_id'] : $current_user_id;

            $stmt = $conn->prepare("UPDATE folders SET deleted = 0 WHERE id = ? AND deleted = 1");
            $stmt->bind_param("i", $folder_id_to_restore);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                cascade_restore_folder($conn, $folder_id_to_restore, $owner_id);
                
                // Falls Parent noch gelöscht ist, in Root verschieben
                $stmt_p = $conn->prepare("SELECT parent_id FROM folders WHERE id = ?");
                $stmt_p->bind_param("i", $folder_id_to_restore);
                $stmt_p->execute();
                $p_row = $stmt_p->get_result()->fetch_assoc();
                $stmt_p->close();

                if ($p_row && $p_row['parent_id']) {
                    $stmt_cp = $conn->prepare("SELECT deleted FROM folders WHERE id = ?");
                    $stmt_cp->bind_param("i", $p_row['parent_id']);
                    $stmt_cp->execute();
                    $cp_row = $stmt_cp->get_result()->fetch_assoc();
                    $stmt_cp->close();
                    if ($cp_row && $cp_row['deleted'] == 1) {
                        $stmt_upd = $conn->prepare("UPDATE folders SET parent_id = NULL WHERE id = ?");
                        $stmt_upd->bind_param("i", $folder_id_to_restore);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }
                    cleanup_empty_trashed_folders($conn, $p_row['parent_id'], $owner_id);
                }
                
                set_flash_message('Ordner erfolgreich wiederhergestellt', 'success');
                if ($ajax_mode) {
                    $ajax_response = ['success' => true, 'message' => 'Ordner erfolgreich wiederhergestellt', 'action' => 'restore_folder', 'data' => ['folder_id' => $folder_id_to_restore]];
                }
            } else {
                set_flash_message('Fehler beim Wiederherstellen', 'error');
                if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Fehler beim Wiederherstellen']; }
            }
            $stmt->close();
        }
        $action_taken = true;
    }

    // Ordner endgültig löschen
    if (isset($_POST['delete_folder_permanently'])) {
        $folder_id_to_delete = filter_input(INPUT_POST, 'folder_id', FILTER_VALIDATE_INT);
        if ($folder_id_to_delete) {
            $stmt_owner = $conn->prepare("SELECT user_id, parent_id FROM folders WHERE id = ?");
            $stmt_owner->bind_param("i", $folder_id_to_delete);
            $stmt_owner->execute();
            $owner_row = $stmt_owner->get_result()->fetch_assoc();
            $stmt_owner->close();
            
            $owner_id = $owner_row ? $owner_row['user_id'] : $current_user_id;
            
            delete_folder_permanently($conn, $folder_id_to_delete, $owner_id, $current_user_role);
            
            if ($owner_row && $owner_row['parent_id']) {
                cleanup_empty_trashed_folders($conn, $owner_row['parent_id'], $owner_id);
            }
            
            set_flash_message('Ordner endgültig gelöscht', 'success');
            if ($ajax_mode) {
                $ajax_response = ['success' => true, 'message' => 'Ordner endgültig gelöscht', 'action' => 'delete_folder_permanently', 'data' => ['folder_id' => $folder_id_to_delete]];
            }
        }
        $action_taken = true;
    }

    if ($action_taken) {
        if ($ajax_mode) {
            ob_start();
            try {
                if (function_exists('lang') && isset($ajax_response['message']) && preg_match('/^[a-z_]+$/', $ajax_response['message'])) {
                    $args = isset($ajax_response['message_args']) && is_array($ajax_response['message_args']) ? $ajax_response['message_args'] : [];
                    $ajax_response['message'] = call_user_func_array('lang', array_merge([$ajax_response['message']], $args));
                    unset($ajax_response['message_args']);
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
function build_trash_breadcrumb($conn, $folder_id) {
    $path = [];
    $current_id = $folder_id;
    while ($current_id) {
        $stmt = $conn->prepare("SELECT id, name, parent_id FROM folders WHERE id = ? AND deleted = 1");
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

$breadcrumb = build_trash_breadcrumb($conn, $current_folder_id);


// --- Daten für Seitenanzeige holen ---

// Ordner holen
$folders_sql = "SELECT f.id, f.name, f.created_at, u.username as owner_username FROM folders f LEFT JOIN users u ON f.user_id = u.id WHERE f.deleted = 1";
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
        $folders_sql .= " AND (f.parent_id IS NULL OR f.parent_id NOT IN (SELECT id FROM folders WHERE deleted = 1))";
    }
}
$folders_sql .= " ORDER BY f.created_at DESC";
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
$files_sql = "SELECT f.id, f.filename, f.created_at, f.public, f.size, u.username as uploader_username FROM files f LEFT JOIN users u ON f.uploader_id = u.id WHERE f.deleted = 1";
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
        $files_sql .= " AND (f.folder_id IS NULL OR f.folder_id NOT IN (SELECT id FROM folders WHERE deleted = 1))";
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
    set_flash_message(lang('error_db_prepare'), 'error'); 
    $files = []; 
}

// --- HTML Ausgabe beginnt HIER ---
require_once __DIR__ . '/../includes/header.php'; // Header erst jetzt!
?>

<h1>Alle gelöschten Dateien (Admin)</h1>

<!-- Breadcrumb -->
<?php if (empty($search_term)): ?>
<div class="breadcrumb" style="margin-bottom: 20px;">
    <a href="all_deleted_files" class="breadcrumb-link">Papierkorb Start</a>
    <?php foreach ($breadcrumb as $crumb): ?>
        / <a href="all_deleted_files?folder=<?php echo $crumb['id']; ?>" class="breadcrumb-link"><?php echo htmlspecialchars($crumb['name']); ?></a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="breadcrumb" style="margin-bottom: 20px;">
    <a href="all_deleted_files" class="breadcrumb-link">Papierkorb Start</a>
    / Suchergebnisse für "<?php echo htmlspecialchars($search_term); ?>"
</div>
<?php endif; ?>

<div class="card">
    <form method="GET" action="all_deleted_files" class="search-form-inline" style="margin-bottom: 20px;">
         <input type="hidden" name="folder" value="<?php echo $current_folder_id ?: ''; ?>">
         <input type="search" name="search" placeholder="Suchen..." value="<?php echo htmlspecialchars($search_term); ?>" style="width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
         <button type="submit" class="button button-secondary" style="padding: 8px 16px; margin-left: 10px;">Suchen</button>
    </form>
    <div class="table-container"><table>
        <thead><tr><th>Name</th><th>Gelöscht am</th><th>Größe</th><th>Benutzer</th><th>Status</th><th>Aktionen</th></tr></thead>
        <tbody>
             <?php if (empty($files) && empty($folders)) { echo '<tr><td colspan="6">Keine gelöschten Elemente gefunden.</td></tr>'; } else { ?>
             
             <?php foreach ($folders as $folder) { ?>
             <tr class="folder-row" data-folder-id="<?php echo $folder['id']; ?>">
                 <td>
                     <i class="icon-folder"></i> 
                     <a href="all_deleted_files?folder=<?php echo $folder['id']; ?>" class="folder-link" data-folder-id="<?php echo $folder['id']; ?>"><?php echo htmlspecialchars($folder['name']); ?></a>
                 </td>
                 <td><?php echo format_date_lang($folder['created_at']); ?></td>
                 <td><?php echo format_bytes(get_folder_size($conn, $folder['id'], true)); ?></td>
                 <td><?php echo htmlspecialchars(isset($folder['owner_username']) ? $folder['owner_username'] : 'Unbekannt'); ?></td>
                 <td>-</td>
                 <td class="actions-cell">
                     <?php if ($current_folder_id === null): ?>
                     <form method="post" action="all_deleted_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>" class="ajax-action" style="display:inline;"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>"><input type="hidden" name="restore_folder" value="1"><button type="submit" name="restore_folder" class="action-button" title="Wiederherstellen" style="background-color: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px;">↩️</button></form>
                     <form method="post" action="all_deleted_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>" class="ajax-action" style="display:inline;" data-custom-confirm="Ordner endgültig löschen?"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>"><input type="hidden" name="delete_folder_permanently" value="1"><button type="submit" name="delete_folder_permanently" class="action-button delete-button" title="Endgültig löschen">🗑️</button></form>
                     <?php endif; ?>
                 </td>
             </tr>
             <?php } ?>

             <?php foreach ($files as $file) { ?>
             <tr class="file-row" data-file-id="<?php echo $file['id']; ?>">
                 <td><a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo shorten_filename($file['filename']); ?></a></td>
                 <td><?php echo format_date_lang($file['created_at']); ?></td>
                 <td><?php echo format_bytes($file['size']); ?></td>
                 <td><?php echo htmlspecialchars(isset($file['uploader_username']) ? $file['uploader_username'] : 'Unbekannt'); ?></td>
                 <td><span class="status-label <?php echo $file['public'] ? 'status-public' : 'status-private'; ?>"><?php echo $file['public'] ? 'Öffentlich' : 'Privat'; ?></span></td>
                 <td class="actions-cell"> <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="Ansehen">👁️</a>
                     <?php if ($current_folder_id === null): ?>
                     <form method="post" action="all_deleted_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>" class="ajax-action" style="display:inline;"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="restore_file" value="1"><button type="submit" name="restore_file" class="action-button" title="Wiederherstellen" style="background-color: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px;">↩️</button></form>
                     <form method="post" action="all_deleted_files?search=<?php echo urlencode($search_term); ?>&folder=<?php echo $current_folder_id ?: ''; ?>" class="ajax-action" style="display:inline;" data-custom-confirm="Datei endgültig löschen?"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="delete_permanently" value="1"><button type="submit" name="delete_permanently" class="action-button delete-button" title="Endgültig löschen">🗑️</button></form>
                     <?php endif; ?>
                 </td>
             </tr>
             <?php } ?>
             
             <?php } ?>
        </tbody>
    </table></div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php'; // Footer am Ende
?>