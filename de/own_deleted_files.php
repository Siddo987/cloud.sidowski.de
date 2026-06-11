<?php
// /de/own_deleted_files.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php'; // Pfad korrigiert

if (!$is_logged_in) { redirect($current_language . '/login'); }

// Suchbegriff holen
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action_taken = false;
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];
    $redirect_url = $current_language . '/own_deleted_files?search=' . urlencode($search_term); // Redirect-URL vorbereiten

    // Datei wiederherstellen
    if (isset($_POST['restore_file'])) {
        $file_id_to_restore = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_restore) {
            $stmt = $conn->prepare("UPDATE files SET deleted = 0 WHERE id = ? AND uploader_id = ? AND deleted = 1");
            $stmt->bind_param("ii", $file_id_to_restore, $current_user_id);
            if ($stmt->execute()) {
                set_flash_message('Datei erfolgreich wiederhergestellt', 'success');
                if ($ajax_mode) {
                    $ajax_response = ['success' => true, 'message' => 'Datei erfolgreich wiederhergestellt', 'action' => 'restore_file', 'data' => ['file_id' => $file_id_to_restore]];
                }
            } else {
                set_flash_message('Fehler beim Wiederherstellen der Datei', 'error');
                if ($ajax_mode) {
                    $ajax_response = ['success' => false, 'message' => 'Fehler beim Wiederherstellen der Datei'];
                }
            }
            $stmt->close();
        } else {
            set_flash_message(lang('error_invalid_data'), 'error');
            if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => lang('error_invalid_data')]; }
        }
        $action_taken = true;
    }

    // Datei endgültig löschen
    if (isset($_POST['delete_permanently'])) {
        $file_id_to_delete = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_delete) {
            $delete_result = delete_file_permanently($conn, $file_id_to_delete, $current_user_id, $current_user_role);
            set_flash_message($delete_result['message_key'], $delete_result['success'] ? 'success' : 'error', $delete_result['message_args']);
            if ($ajax_mode) {
                $messageArgs = isset($delete_result['message_args']) && is_array($delete_result['message_args']) ? $delete_result['message_args'] : [];
                $message = call_user_func_array('lang', array_merge([$delete_result['message_key']], $messageArgs));
                $ajax_response = ['success' => $delete_result['success'], 'message' => $message, 'action' => 'delete_permanently', 'data' => ['file_id' => $file_id_to_delete]];
            }
        } else {
            set_flash_message(lang('error_invalid_data'), 'error');
            if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => lang('error_invalid_data')]; }
        }
        $action_taken = true;
    }

    // Datei umbenennen
    if (isset($_POST['rename_file'])) {
        $file_id_rename = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        $new_filename = trim(isset($_POST['new_filename']) ? $_POST['new_filename'] : '');
        if ($file_id_rename && !empty($new_filename)) {
            // Berechtigung prüfen (eigene Datei)
            $perm_check_stmt = $conn->prepare("SELECT id FROM files WHERE id = ? AND uploader_id = ? AND deleted = 1");
            if ($perm_check_stmt) {
                $perm_check_stmt->bind_param("ii", $file_id_rename, $current_user_id);
                $perm_check_stmt->execute(); $perm_check_stmt->store_result();
                $has_permission = $perm_check_stmt->num_rows > 0;
                $perm_check_stmt->close();
                if ($has_permission) {
                    // Validiere Dateiname
                    if (preg_match('/^[a-zA-Z0-9._\-\s\(\)]+$/', $new_filename) && strlen($new_filename) <= 255) {
                        $stmt = $conn->prepare("UPDATE files SET filename = ? WHERE id = ?");
                        $stmt->bind_param('si', $new_filename, $file_id_rename);
                        if ($stmt->execute()) {
                            set_flash_message('Datei erfolgreich umbenannt', 'success');
                            if ($ajax_mode) {
                                $ajax_response = ['success' => true, 'message' => 'Datei erfolgreich umbenannt', 'action' => 'rename_file', 'data' => ['file_id' => $file_id_rename, 'new_name' => $new_filename]];
                            }
                        } else {
                            set_flash_message('Fehler beim Umbenennen', 'error');
                            if ($ajax_mode) {
                                $ajax_response = ['success' => false, 'message' => 'Fehler beim Umbenennen'];
                            }
                        }
                        $stmt->close();
                    } else {
                        set_flash_message('Ungültiger Dateiname', 'error');
                        if ($ajax_mode) {
                            $ajax_response = ['success' => false, 'message' => 'Ungültiger Dateiname'];
                        }
                    }
                } else {
                    set_flash_message('error_no_permission', 'error');
                    if ($ajax_mode) {
                        $ajax_response = ['success' => false, 'message' => 'Keine Berechtigung'];
                    }
                }
            } else {
                set_flash_message('error_db_prepare', 'error');
                if ($ajax_mode) {
                    $ajax_response = ['success' => false, 'message' => 'Datenbankfehler'];
                }
            }
        } else {
            set_flash_message('error_invalid_data', 'error');
        }
        $action_taken = true;
    }

    if ($action_taken) {
        if ($ajax_mode) {
            header('Content-Type: application/json');
            echo json_encode($ajax_response);
            exit;
        }
        redirect($redirect_url); // Redirect hier!
    }
}
// --- ENDE POST Handling ---


// --- Daten für Seitenanzeige holen ---
$sql = "SELECT id, filename, created_at, public, size FROM files WHERE uploader_id = ? AND deleted = 1";
$params = [$current_user_id]; $types = "i";
if (!empty($search_term)) { $sql .= " AND filename LIKE ?"; $params[] = '%' . $search_term . '%'; $types .= "s"; }
$sql .= " ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt) { $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); $files = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
else { set_flash_message(lang('error_db_prepare'), 'error'); $files = []; }


// --- HTML Ausgabe beginnt HIER ---
require_once __DIR__ . '/../includes/header.php'; // Header erst jetzt!
?>

<h1>Gelöschte Dateien</h1>
<div class="card">
    <form method="GET" action="own_deleted_files" class="search-form-inline" style="margin-bottom: 20px;">
         <input type="search" name="search" placeholder="Dateien suchen..." value="<?php echo htmlspecialchars($search_term); ?>" style="width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
         <button type="submit" class="button button-secondary" style="padding: 8px 16px; margin-left: 10px;">Suchen</button>
    </form>
    <div class="table-container"><table>
        <thead><tr><th>Dateiname</th><th>Hochgeladen am</th><th>Größe</th><th>Status</th><th>Aktionen</th></tr></thead>
        <tbody>
             <?php if (empty($files)) { echo '<tr><td colspan="5">Keine gelöschten Dateien gefunden.</td></tr>'; } else { foreach ($files as $file) { ?>
             <tr class="file-row" data-file-id="<?php echo $file['id']; ?>">
                 <td class="filename-cell"><a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo shorten_filename($file['filename']); ?></a></td>
                 <td><?php echo format_date_lang($file['created_at']); ?></td><td><?php echo format_bytes($file['size']); ?></td>
                 <td><span class="status-label <?php echo $file['public'] ? 'status-public' : 'status-private'; ?>"><?php echo $file['public'] ? 'Öffentlich' : 'Privat'; ?></span></td>
                 <td class="actions-cell"> <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="Ansehen">👁️</a>
                     <form method="post" action="own_deleted_files?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="restore_file" value="1"><button type="submit" name="restore_file" class="action-button" title="Wiederherstellen" style="background-color: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px;">↩️</button></form>
                     <button onclick="renameFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['filename']); ?>')" class="action-button" title="Umbenennen">✏️</button>
                     <form method="post" action="own_deleted_files?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;" onsubmit="return confirm('Datei endgültig löschen?');"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="delete_permanently" value="1"><button type="submit" name="delete_permanently" class="action-button delete-button" title="Endgültig löschen">🗑️</button></form>
                </td>
             </tr>
             <?php }} ?>
        </tbody>
    </table></div>
</div>

<script>
function renameFile(fileId, currentName) {
    const newName = prompt('Neuer Dateiname:', currentName);
    if (newName && newName !== currentName) {
        postAjaxAction({
            csrf_token: '<?php echo csrf_token(); ?>',
            rename_file: '1',
            file_id: fileId,
            new_filename: newName,
            ajax: '1'
        });
    }
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php'; // Footer am Ende
?>