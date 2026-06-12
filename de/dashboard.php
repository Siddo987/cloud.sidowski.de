<?php
// /de/dashboard.php

$current_language = 'de';
// Bootstrap laden (Pfad korrigiert zu ../config/)
require_once __DIR__ . '/../config/bootstrap.php';

// Login prüfen
if (!$is_logged_in) { redirect($current_language . '/login'); }

// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token(); // CSRF prüfen
    // Debug-Log: Rohes POST zur Analyse (einmalig aktivieren wenn nötig)
    error_log("dashboard POST: " . var_export($_POST, true));

    $action_taken = false; // Flag
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];
    $redirect_url = $current_language . '/dashboard';

    // Datei löschen (soft delete)
    if (isset($_POST['delete_file'])) {
        $file_id_to_delete = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_delete) {
            if (check_file_permission($conn, $file_id_to_delete, $current_user_id, $current_user_role)) {
                $stmt = $conn->prepare("UPDATE files SET deleted = 1 WHERE id = ? AND deleted = 0");
                $stmt->bind_param("i", $file_id_to_delete);
                if ($stmt->execute()) {
                    if (!$ajax_mode) {
                        set_flash_message('Datei erfolgreich in den Papierkorb verschoben', 'success');
                    }
                    if ($ajax_mode) {
                        $ajax_response = ['success' => true, 'message' => 'Datei erfolgreich verschoben', 'action' => 'delete_file', 'data' => ['file_id' => $file_id_to_delete]];
                    }
                } else {
                    if (!$ajax_mode) {
                        set_flash_message('Fehler beim Löschen der Datei', 'error');
                    }
                    if ($ajax_mode) {
                        $ajax_response = ['success' => false, 'message' => 'Fehler beim Löschen der Datei'];
                    }
                }
                $stmt->close();
            } else {
                set_flash_message(lang('error_no_permission'), 'error');
                if ($ajax_mode) {
                    $ajax_response = ['success' => false, 'message' => 'Keine Berechtigung'];
                }
            }
        } else { set_flash_message(lang('error_invalid_data'), 'error'); }
        $action_taken = true;
    }

    // Status ändern
    if (isset($_POST['toggle_public_status'])) {
        $file_id_toggle = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        $current_status = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT);
        $current_status_int = is_numeric($current_status) ? (int)$current_status : null;
        error_log("Dashboard TogglePublic: file_id={$file_id_toggle}, current_status_raw=" . var_export($current_status, true) . ", normalized=" . var_export($current_status_int, true));

        if ($file_id_toggle && ($current_status_int === 0 || $current_status_int === 1)) {
            // Explizite Berechtigungsprüfung (eigene Datei oder Admin)
            if (check_file_permission($conn, $file_id_toggle, $current_user_id, $current_user_role)) {
                $new_status = 1 - $current_status_int;
                $update_query = $conn->prepare("UPDATE files SET public = ? WHERE id = ?");
                if ($update_query) {
                    $update_query->bind_param("ii", $new_status, $file_id_toggle);
                    if ($update_query->execute()) {
                        if (!$ajax_mode) {
                            set_flash_message(lang('success_status_changed'), 'success');
                        }
                        if ($ajax_mode) {
                            $file_info_stmt = $conn->prepare("SELECT f.filename, f.created_at, f.size, f.uploader_id, f.public, u.username AS uploader_username FROM files f LEFT JOIN users u ON f.uploader_id = u.id WHERE f.id = ?");
                            if ($file_info_stmt) {
                                $file_info_stmt->bind_param('i', $file_id_toggle);
                                $file_info_stmt->execute();
                                $file_result = $file_info_stmt->get_result();
                                $file_info = $file_result ? $file_result->fetch_assoc() : null;
                                $file_info_stmt->close();
                            } else {
                                $file_info = null;
                            }
                            $ajax_response = [
                                'success' => true,
                                'message' => 'Status erfolgreich aktualisiert',
                                'action' => 'toggle_public_status',
                                'data' => array_merge([
                                    'file_id' => $file_id_toggle,
                                    'new_status' => $new_status
                                ], $file_info ? $file_info : [])
                            ];
                        }
                    } else {
                        if (!$ajax_mode) {
                            set_flash_message(lang('error_db_update'), 'error');
                        }
                        error_log("DB Update Error (dashboard toggle): " . $update_query->error);
                        if ($ajax_mode) {
                            $ajax_response = ['success' => false, 'message' => 'Fehler beim Ändern des Status'];
                        }
                    }
                    $update_query->close();
                } else {
                    set_flash_message(lang('error_db_prepare'), 'error');
                    error_log("DB Prepare Error (dashboard toggle): " . $conn->error);
                    if ($ajax_mode) {
                        $ajax_response = ['success' => false, 'message' => 'Datenbankfehler'];
                    }
                }
            } else {
                set_flash_message(lang('error_no_permission'), 'error');
                if ($ajax_mode) {
                    $ajax_response = ['success' => false, 'message' => 'Keine Berechtigung'];
                }
            }
        } else {
            set_flash_message(lang('error_invalid_data'), 'error');
            if ($ajax_mode) {
                $ajax_response = ['success' => false, 'message' => 'Ungültige Daten'];
            }
        }
        $action_taken = true;
    }

    // Datei umbenennen
    if (isset($_POST['rename_file'])) {
        $file_id_rename = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        $new_filename = trim(isset($_POST['new_filename']) ? $_POST['new_filename'] : '');
        if ($file_id_rename && !empty($new_filename)) {
            // Berechtigung prüfen (eigene Datei oder Admin)
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

    // Nur redirecten, wenn eine Aktion behandelt wurde
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
        redirect($redirect_url);
    }
}
// --- ENDE POST Handling ---


// --- Daten für die Seitenanzeige holen ---
$own_files = []; $public_files = []; // Initialisieren
try {
    // Eigene Dateien holen
    $own_files_query = $conn->prepare("SELECT id, filename, created_at, public, size FROM files WHERE uploader_id = ? AND deleted = 0 ORDER BY created_at DESC LIMIT 3");
    if(!$own_files_query) throw new Exception("DB Prepare Error (own_files): " . $conn->error);
    $own_files_query->bind_param("i", $current_user_id); $own_files_query->execute(); $own_files_result = $own_files_query->get_result(); $own_files = $own_files_result->fetch_all(MYSQLI_ASSOC); $own_files_query->close();

    // Öffentliche Dateien holen
    $public_files_query = $conn->prepare("SELECT f.id, f.filename, f.created_at, f.uploader_id, f.public, f.size, u.username as uploader_username FROM files f LEFT JOIN users u ON f.uploader_id = u.id WHERE f.public = 1 AND f.deleted = 0 ORDER BY f.created_at DESC LIMIT 3");
    if(!$public_files_query) throw new Exception("DB Prepare Error (public_files): " . $conn->error);
    $public_files_query->execute(); $public_files_result = $public_files_query->get_result(); $public_files = $public_files_result->fetch_all(MYSQLI_ASSOC); $public_files_query->close();

} catch (Exception $e) {
    error_log("DB Error fetching dashboard data: " . $e->getMessage());
    set_flash_message('error_db_fetch_dashboard', 'error');
}


// --- HTML-Ausgabe beginnt HIER ---
require_once __DIR__ . '/../includes/header.php';
?>

<?php // Hier beginnt der eigentliche HTML-Inhalt der Seite ?>

<h3><?php echo lang('nav_my_files'); ?> (Top 3)</h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th><?php echo lang('th_filename'); ?></th>
                <th><?php echo lang('th_upload_date'); ?></th>
                <th><?php echo lang('th_size'); ?></th>
                <th><?php echo lang('th_status'); ?></th>
                <th class="actions-cell"><?php echo lang('th_actions'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($own_files)): ?>
                <tr><td colspan="5"><?php echo lang('text_no_files_found'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($own_files as $file): ?>
                <tr class="file-row" data-file-id="<?php echo $file['id']; ?>">
                    <td><a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo shorten_filename($file['filename']); ?></a></td>
                    <td><?php echo format_date_lang($file['created_at']); ?></td>
                    <td><?php echo format_bytes($file['size']); ?></td>
                    <td><span class="status-label <?php echo $file['public'] ? 'status-public' : 'status-private'; ?>"><?php echo $file['public'] ? lang('status_public') : lang('status_private'); ?></span></td>
                    <td class="actions-cell">
                        <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="<?php echo lang('button_view'); ?>">👁️</a>
                        <form method="post" action="dashboard" class="ajax-action" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                            <input type="hidden" name="current_status" value="<?php echo $file['public']; ?>">
                            <input type="hidden" name="toggle_public_status" value="1">
                            <button type="submit" name="toggle_public_status" class="action-button <?php echo $file['public'] ? 'private-button' : 'public-button'; ?>" title="<?php echo $file['public'] ? lang('button_make_private') : lang('button_make_public'); ?>"><?php echo $file['public'] ? '🔒' : '🌍'; ?></button>
                        </form>
                        <a href="download?id=<?php echo $file['id']; ?>" class="action-button download-button" title="<?php echo lang('button_download'); ?>">💾</a>
                        <button onclick="renameFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['filename']); ?>')" class="action-button" title="Umbenennen">✏️</button>
                        <form method="post" action="dashboard" class="ajax-action" style="display:inline;" data-custom-confirm="<?php printf(lang('text_confirm_delete_file'), htmlspecialchars(addslashes($file['filename']))); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                            <input type="hidden" name="delete_file" value="1">
                            <button type="submit" name="delete_file" class="action-button delete-button" title="<?php echo lang('button_delete'); ?>">🗑️</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php // Link "Alle anzeigen" für Eigene Dateien ENTFERNT ?>


<h3><?php echo lang('nav_public_files'); ?> (Top 3)</h3>
<div class="table-container">
    <table>
        <thead>
            <tr>
                <th><?php echo lang('th_filename'); ?></th>
                <th><?php echo lang('th_upload_date'); ?></th>
                <th><?php echo lang('th_uploader'); ?></th>
                <th><?php echo lang('th_size'); ?></th>
                <th><?php echo lang('th_status'); ?></th>
                <th class="actions-cell"><?php echo lang('th_actions'); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($public_files)): ?>
            <tr><td colspan="6"><?php echo lang('text_no_files_found'); ?></td></tr>
        <?php else: ?>
            <?php foreach ($public_files as $file): ?>
            <tr class="file-row" data-file-id="<?php echo $file['id']; ?>">
                <td class="filename-cell"><a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo shorten_filename($file['filename']); ?></a></td>
                <td><?php echo format_date_lang($file['created_at']); ?></td>
                <td><?php echo $file['uploader_username'] ? htmlspecialchars($file['uploader_username']) : get_username_by_id($conn, $file['uploader_id']); ?></td>
                <td><?php echo format_bytes($file['size']); ?></td>
                <td><span class="status-label status-public"><?php echo lang('status_public'); ?></span></td>
                <td class="actions-cell">
                     <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="<?php echo lang('button_view'); ?>">👁️</a>
                     <?php if ($file['uploader_id'] == $current_user_id || $is_admin): ?>
                     <form method="post" action="dashboard" class="ajax-action" style="display:inline;">
                         <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                         <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                         <input type="hidden" name="current_status" value="<?php echo $file['public']; ?>">
                         <input type="hidden" name="toggle_public_status" value="1">
                         <button type="submit" name="toggle_public_status" class="action-button <?php echo $file['public'] ? 'private-button' : 'public-button'; ?>" title="<?php echo $file['public'] ? lang('button_make_private') : lang('button_make_public'); ?>"><?php echo $file['public'] ? '🔒' : '🌍'; ?></button>
                     </form>
                     <?php endif; ?>
                     <a href="download?id=<?php echo $file['id']; ?>" class="action-button download-button" title="<?php echo lang('button_download'); ?>">💾</a>
                     <?php if ($file['uploader_id'] == $current_user_id || $is_admin): ?>
                     <button onclick="renameFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['filename']); ?>')" class="action-button" title="Umbenennen">✏️</button>
                     <form method="post" action="dashboard" class="ajax-action" style="display:inline;" data-custom-confirm="<?php printf(lang('text_confirm_delete_file'), htmlspecialchars(addslashes($file['filename']))); ?>">
                         <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                         <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                         <input type="hidden" name="delete_file" value="1">
                         <button type="submit" name="delete_file" class="action-button delete-button" title="<?php echo lang('button_delete'); ?>">🗑️</button>
                     </form>
                     <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php // Link "Alle anzeigen" für Öffentliche Dateien ENTFERNT ?>


<?php
// Footer laden
require_once __DIR__ . '/../includes/footer.php';
?>

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

// Kompatibilität: Sicherstelle dass postAjaxAction global verfügbar ist
if (typeof postAjaxAction === 'undefined') {
    window.postAjaxAction = function(actionData) {
        const endpoint = window.location.pathname;
        return fetch(endpoint, { 
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
            body: new URLSearchParams(actionData) 
        })
        .then(response => response.text())
        .then(text => {
            try { 
                const data = JSON.parse(text); 
                if (window.handleAjaxResponse) handleAjaxResponse(data); 
            } catch (e) { 
                console.error('AJAX JSON Fehler:', e); 
            } 
        })
        .catch(error => console.error('AJAX Fehler:', error));
    };
}
</script>