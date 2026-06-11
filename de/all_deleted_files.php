<?php
// /de/all_deleted_files.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php'; // Pfad korrigiert

if (!$is_admin) { redirect($current_language . '/dashboard'); }

// Suchbegriff holen
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action_taken = false;
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];
    $redirect_url = $current_language . '/all_deleted_files?search=' . urlencode($search_term); // Redirect-URL vorbereiten

    // Datei wiederherstellen
    if (isset($_POST['restore_file'])) {
        $file_id_to_restore = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_restore) {
            $stmt = $conn->prepare("UPDATE files SET deleted = 0 WHERE id = ? AND deleted = 1");
            $stmt->bind_param("i", $file_id_to_restore);
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
        } else { set_flash_message(lang('error_invalid_data'), 'error'); if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => lang('error_invalid_data')]; }}
        $action_taken = true;
    }

    // Datei endgültig löschen
    if (isset($_POST['delete_permanently'])) {
        $file_id_to_delete = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_delete) {
            $delete_result = delete_file_permanently($conn, $file_id_to_delete, $current_user_id, $current_user_role);
            set_flash_message($delete_result['message_key'], $delete_result['success'] ? 'success' : 'error', $delete_result['message_args']);
            if ($ajax_mode) {
                $ajax_response = ['success' => $delete_result['success'], 'message' => lang($delete_result['message_key'], ...(isset($delete_result['message_args']) ? $delete_result['message_args'] : [])), 'action' => 'delete_permanently', 'data' => ['file_id' => $file_id_to_delete]];
            }
        } else { set_flash_message(lang('error_invalid_data'), 'error'); if ($ajax_mode) { $ajax_response = ['success' => false, 'message' => lang('error_invalid_data')]; }}
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
$sql = "SELECT f.id, f.filename, f.created_at, f.public, f.size, u.username as uploader_username FROM files f LEFT JOIN users u ON f.uploader_id = u.id WHERE f.deleted = 1";
$params = []; $types = "";
if (!empty($search_term)) { $sql .= " AND f.filename LIKE ?"; $params[] = '%' . $search_term . '%'; $types .= "s"; }
$sql .= " ORDER BY f.created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt) { if (!empty($params)) { $stmt->bind_param($types, ...$params); } $stmt->execute(); $result = $stmt->get_result(); $files = $result->fetch_all(MYSQLI_ASSOC); $stmt->close(); }
else { set_flash_message(lang('error_db_prepare'), 'error'); $files = []; }


// --- HTML Ausgabe beginnt HIER ---
require_once __DIR__ . '/../includes/header.php'; // Header erst jetzt!
?>

<h1>Alle gelöschten Dateien (Admin)</h1>
<div class="card">
    <form method="GET" action="all_deleted_files" class="search-form-inline" style="margin-bottom: 20px;">
         <input type="search" name="search" placeholder="Dateien suchen..." value="<?php echo htmlspecialchars($search_term); ?>" style="width: 300px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
         <button type="submit" class="button button-secondary" style="padding: 8px 16px; margin-left: 10px;">Suchen</button>
    </form>
    <div class="table-container"><table>
        <thead><tr><th>Dateiname</th><th>Hochgeladen am</th><th>Größe</th><th>Hochgeladen von</th><th>Status</th><th>Aktionen</th></tr></thead>
        <tbody>
             <?php if (empty($files)) { echo '<tr><td colspan="6">Keine gelöschten Dateien gefunden.</td></tr>'; } else { foreach ($files as $file) { ?>
             <tr>
                 <td><a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo shorten_filename($file['filename']); ?></a></td>
                 <td><?php echo format_date_lang($file['created_at']); ?></td><td><?php echo format_bytes($file['size']); ?></td><td><?php echo htmlspecialchars(isset($file['uploader_username']) ? $file['uploader_username'] : 'Unbekannt'); ?></td>
                 <td><span class="status-label <?php echo $file['public'] ? 'status-public' : 'status-private'; ?>"><?php echo $file['public'] ? 'Öffentlich' : 'Privat'; ?></span></td>
                 <td class="actions-cell"> <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="Ansehen">👁️</a>
                     <form method="post" action="all_deleted_files?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="restore_file" value="1"><button type="submit" name="restore_file" class="action-button" title="Wiederherstellen" style="background-color: #28a745; color: white; border: none; padding: 5px 10px; border-radius: 3px;">↩️</button></form>
                     <form method="post" action="all_deleted_files?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;" onsubmit="return confirm('Datei endgültig löschen?');"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="delete_permanently" value="1"><button type="submit" name="delete_permanently" class="action-button delete-button" title="Endgültig löschen">🗑️</button></form>
                </td>
             </tr>
             <?php }} ?>
        </tbody>
    </table></div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php'; // Footer am Ende
?>