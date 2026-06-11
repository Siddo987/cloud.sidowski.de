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

// Suchbegriff
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action_taken = false;
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];
    $redirect_url = $current_language . '/all_files?search=' . urlencode($search_term);

    // Datei löschen (soft delete)
    if (isset($_POST['delete_file'])) {
        $file_id_to_delete = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_delete) {
            $stmt = $conn->prepare("UPDATE files SET deleted = 1 WHERE id = ? AND deleted = 0");
            $stmt->bind_param("i", $file_id_to_delete);
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
        } else {
            set_flash_message(lang('error_invalid_data'), 'error');
        }
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
                    if ($ajax_mode) {
                        $ajax_response = [
                            'success' => true,
                            'message' => 'Status erfolgreich aktualisiert',
                            'action' => 'toggle_public_status',
                            'data' => [
                                'file_id' => $file_id_toggle,
                                'new_status' => $new_status
                            ]
                        ];
                    }
                } else {
                    set_flash_message('Fehler bei Update', 'error');
                    if ($ajax_mode) {
                        $ajax_response = ['success' => false, 'message' => 'Fehler bei Update'];
                    }
                }
                $update_query->close();
            } else {
                set_flash_message('Datenbankfehler', 'error');
            }
        } else {
            set_flash_message('Ungültige Daten', 'error');
        }
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
            set_flash_message('Ungültige Daten', 'error');
        }
        $action_taken = true;
    }

    if ($action_taken) {
        if ($ajax_mode) {
            header('Content-Type: application/json');
            echo json_encode($ajax_response);
            exit;
        }
        redirect($redirect_url);
    }
}
// --- ENDE POST Handling ---

// Dateien holen (alle Dateien, nicht gelöscht)
$files_sql = "SELECT f.id, f.filename, f.created_at, f.public, f.size, f.uploader_id, u.username as uploader_username FROM files f LEFT JOIN users u ON f.uploader_id = u.id WHERE f.deleted = 0";
$files_params = [];
$files_types = "";

if (!empty($search_term)) {
    $files_sql .= " AND f.filename LIKE ?";
    $files_params[] = '%' . $search_term . '%';
    $files_types .= "s";
}

$files_sql .= " ORDER BY f.created_at DESC";

$files_stmt = $conn->prepare($files_sql);
if ($files_stmt) {
    if (!empty($files_params)) {
        $files_stmt->bind_param($files_types, ...$files_params);
    }
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

<h1>Alle Dateien</h1>

<div class="card">
    <form method="GET" action="all_files" class="search-form-inline" style="margin-bottom: 20px;">
        <input type="search" name="search" placeholder="Dateien durchsuchen..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit" class="button button-secondary">Suchen</button>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Dateiname</th>
                    <th>Hochgeladen am</th>
                    <th>Hochgeladen von</th>
                    <th>Größe</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($files)): ?>
                    <tr><td colspan="6">Keine Dateien gefunden.</td></tr>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <tr class="file-row" data-file-id="<?php echo $file['id']; ?>">
                            <td class="filename-cell"><a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>"><?php echo shorten_filename($file['filename']); ?></a></td>
                            <td><?php echo format_date_lang($file['created_at']); ?></td>
                            <td><?php echo $file['uploader_username'] ? htmlspecialchars($file['uploader_username']) : get_username_by_id($conn, $file['uploader_id']); ?></td>
                            <td><?php echo format_bytes($file['size']); ?></td>
                            <td><span class="status-label <?php echo $file['public'] ? 'status-public' : 'status-private'; ?>"><?php echo $file['public'] ? 'Öffentlich' : 'Privat'; ?></span></td>
                            <td class="actions-cell">
                                <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="Ansehen">👁️</a>
                                <form method="post" action="all_files?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="current_status" value="<?php echo $file['public']; ?>"><input type="hidden" name="toggle_public_status" value="1"><button type="submit" name="toggle_public_status" class="action-button <?php echo $file['public'] ? 'private-button' : 'public-button'; ?>" title="<?php echo $file['public'] ? 'Privat machen' : 'Öffentlich machen'; ?>"><?php echo $file['public'] ? '🔒' : '🌍'; ?></button></form>
                                <a href="download?id=<?php echo $file['id']; ?>" class="action-button download-button" title="Herunterladen">💾</a>
                                <form method="post" action="all_files?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;" onsubmit="return confirm('Datei wirklich löschen?');"><input type="hidden" name="ajax" value="1"><input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>"><input type="hidden" name="file_id" value="<?php echo $file['id']; ?>"><input type="hidden" name="delete_file" value="1"><button type="submit" name="delete_file" class="action-button delete-button" title="Löschen">🗑️</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
