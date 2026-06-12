<?php
// /de/public_files.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php'; // Pfad korrigiert

if (!$is_logged_in) { redirect($current_language . '/login'); }

// Suchbegriff
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action_taken = false;
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];
    $redirect_url = $current_language . '/public_files?search=' . urlencode($search_term);

    // Datei löschen (soft delete)
    if (isset($_POST['delete_file'])) {
        $file_id_to_delete = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_delete) {
            $stmt = $conn->prepare("UPDATE files SET deleted = 1 WHERE id = ? AND uploader_id = ? AND deleted = 0");
            $stmt->bind_param("ii", $file_id_to_delete, $current_user_id);
            if ($stmt->execute()) {
                set_flash_message('Datei erfolgreich in den Papierkorb verschoben', 'success');
                if ($ajax_mode) {
                    $ajax_response = ['success' => true, 'message' => 'Datei erfolgreich in den Papierkorb verschoben', 'action' => 'delete_file', 'data' => ['file_id' => $file_id_to_delete]];
                }
            } else {
                set_flash_message('Fehler beim Löschen der Datei', 'error');
                if ($ajax_mode) {
                    $ajax_response = ['success' => false, 'message' => 'Fehler beim Löschen der Datei'];
                }
            }
            $stmt->close();
        } else { set_flash_message(lang('error_invalid_data'), 'error'); }
        $action_taken = true;
    }

    // Status ändern (hier nur "Privat machen")
    if (isset($_POST['make_private'])) { // Name des Buttons prüfen
        $file_id_toggle = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_toggle) {
            if (check_file_permission($conn, $file_id_toggle, $current_user_id, $current_user_role)) { // Prüft ob Uploader oder Admin
                $update_query = $conn->prepare("UPDATE files SET public = 0 WHERE id = ? AND public = 1"); // Nur ändern, wenn aktuell public=1
                if ($update_query) {
                    $update_query->bind_param("i", $file_id_toggle);
                    if ($update_query->execute()) {
                        if ($update_query->affected_rows > 0) {
                            set_flash_message('Status erfolgreich aktualisiert', 'success');
                            if ($ajax_mode) {
                                $ajax_response = ['success' => true, 'message' => 'Status erfolgreich aktualisiert', 'action' => 'make_private', 'data' => ['file_id' => $file_id_toggle]];
                            }
                        } else {
                            set_flash_message('Datei ist bereits privat', 'info');
                            if ($ajax_mode) {
                                $ajax_response = ['success' => true, 'message' => 'Datei ist bereits privat', 'action' => 'make_private', 'data' => ['file_id' => $file_id_toggle]];
                            }
                        }
                    } else {
                        set_flash_message('Fehler beim Aktualisieren der Datenbank', 'error');
                        if ($ajax_mode) {
                            $ajax_response = ['success' => false, 'message' => 'Fehler beim Aktualisieren der Datenbank'];
                        }
                        error_log(/*...*/);
                    }
                    $update_query->close();
                } else { set_flash_message('Fehler beim Vorbereiten der Datenbank-Abfrage', 'error'); error_log(/*...*/); }
            } else { set_flash_message('Keine Berechtigung für diese Aktion', 'error'); if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Keine Berechtigung für diese Aktion']; } }
        } else { set_flash_message('Ungültige Daten', 'error'); if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => 'Ungültige Daten']; } }
        $action_taken = true;
    }

    // Datei umbenennen
    if (isset($_POST['rename_file'])) {
        $file_id_rename = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        $new_filename = trim(isset($_POST['new_filename']) ? $_POST['new_filename'] : '');
        if ($file_id_rename && !empty($new_filename)) {
            if (check_file_permission($conn, $file_id_rename, $current_user_id, $current_user_role)) {
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
                }
            } else {
                set_flash_message('error_no_permission', 'error');
            }
        } else {
            set_flash_message('error_invalid_data', 'error');
        }
        $action_taken = true;
    }

    if ($action_taken) {
        if ($ajax_mode) {
            // Use output buffer to protect JSON response
            ob_start();
            try {
                // Ensure message is a string
                if (function_exists('lang') && isset($ajax_response['message']) && preg_match('/^[a-z_]+$/', $ajax_response['message'])) {
                    $ajax_response['message'] = lang($ajax_response['message']);
                }
            } catch (Throwable $e) {
                error_log('Error translating message: ' . $e->getMessage());
            }
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode($ajax_response);
            exit;
        }
        redirect($redirect_url); // Redirect hier!
    }
}
// --- ENDE POST Handling ---


// --- Daten für Seitenanzeige holen ---
$sql = "SELECT f.id, f.filename, f.created_at, f.uploader_id, f.public, f.size, u.username as uploader_username FROM files f LEFT JOIN users u ON f.uploader_id = u.id WHERE f.public = 1"; $params = []; $types = "";
if (!empty($search_term)) { $sql .= " AND (f.filename LIKE ? OR u.username LIKE ?)"; $search_like = '%' . $search_term . '%'; $params[] = $search_like; $params[] = $search_like; $types .= "ss"; }
$sql .= " ORDER BY f.created_at DESC";
$stmt = $conn->prepare($sql);
// ... bind_param, execute, fetch ...
if ($stmt) { if (!empty($params)) $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); $files = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
else { error_log(/*...*/); set_flash_message(lang('error_db_prepare'), 'error'); $files = []; }


// --- HTML Ausgabe beginnt HIER ---
require_once __DIR__ . '/../includes/header.php'; // Header erst jetzt!
?>

<h1><?php echo lang('title_public_files'); ?></h1>
<div class="card">
     <form method="GET" action="public_files" class="search-form-inline" style="margin-bottom: 20px;">
         <input type="search" name="search" placeholder="<?php echo lang('placeholder_search_files'); ?>" value="<?php echo htmlspecialchars($search_term); ?>" style="/*...*/">
         <button type="submit" class="button button-secondary" style="/*...*/"><?php echo lang('button_search'); ?></button>
     </form>
    <div class="table-container"><table>
        <thead><tr><th><?php echo lang('th_filename'); ?></th><th><?php echo lang('th_upload_date'); ?></th><th><?php echo lang('th_uploader'); ?></th><th><?php echo lang('th_size'); ?></th><th><?php echo lang('th_status'); ?></th><th><?php echo lang('th_actions'); ?></th></tr></thead>
        <tbody>
             <?php if (empty($files)) { ?>
                 <tr><td colspan="6"><?php echo lang('text_no_files_found'); ?></td></tr>
             <?php } else { foreach ($files as $file) { $user_can_manage = ($file['uploader_id'] == $current_user_id || $is_admin); ?>
             <tr class="file-row" data-file-id="<?php echo $file['id']; ?>">
                 <td class="filename-cell"><a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo shorten_filename($file['filename']); ?></a></td>
                 <td><?php echo format_date_lang($file['created_at']); ?></td><td><?php echo $file['uploader_username'] ? htmlspecialchars($file['uploader_username']) : get_username_by_id($conn, $file['uploader_id']); ?></td><td><?php echo format_bytes($file['size']); ?></td>
                 <td><span class="status-label status-public"><?php echo lang('status_public'); ?></span></td>
                 <td class="actions-cell"> <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="<?php echo lang('button_view'); ?>">👁️</a>
                      <?php if ($user_can_manage): ?>
                         <form method="post" action="public_files?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="make_private" value="1"><button type="submit" name="make_private" class="action-button private-button" title="<?php echo lang('button_make_private'); ?>">🔒</button></form>
                         <button onclick="renameFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['filename']); ?>')" class="action-button" title="Umbenennen">✏️</button>
                         <form method="post" action="public_files?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;" data-confirm="<?php printf(lang('text_confirm_delete_file'), htmlspecialchars(addslashes($file['filename']))); ?>"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="delete_file" value="1"><button type="submit" name="delete_file" class="action-button delete-button" title="<?php echo lang('button_delete'); ?>">🗑️</button></form>
                      <?php endif; ?>
                      <a href="download?id=<?php echo $file['id']; ?>" class="action-button download-button" title="<?php echo lang('button_download'); ?>">💾</a>
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