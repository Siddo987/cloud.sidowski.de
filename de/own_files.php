<?php
// /de/own_files.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php'; // Pfad korrigiert

if (!$is_logged_in) { redirect($current_language . '/login'); }

// Suchbegriff holen
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Aktueller Ordner
$current_folder_id = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Prüfe Ordner-Berechtigung
if ($current_folder_id) {
    $stmt = $conn->prepare("SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ? AND deleted = 0");
    $stmt->bind_param("ii", $current_folder_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_folder = $result->fetch_assoc();
    $stmt->close();
    if (!$current_folder) {
        set_flash_message('Ordner nicht gefunden oder keine Berechtigung', 'error');
        redirect($current_language . '/own_files');
    }
} else {
    $current_folder = null;
}

// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    // Debug-Log: Rohes POST zur Analyse (einmalig aktivieren wenn nötig)
    error_log("own_files POST: " . var_export($_POST, true));
    $action_taken = false;
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];
    $redirect_url = $current_language . '/own_files' . ($current_folder_id ? '?folder=' . $current_folder_id : '') . (!empty($search_term) ? '&search=' . urlencode($search_term) : ''); // Redirect-URL vorbereiten

    // Ordner löschen
    if (isset($_POST['delete_folder'])) {
        $folder_id_to_delete = filter_input(INPUT_POST, 'folder_id', FILTER_VALIDATE_INT);
        if ($folder_id_to_delete) {
            $stmt = $conn->prepare("UPDATE folders SET deleted = 1 WHERE id = ? AND user_id = ? AND deleted = 0");
            $stmt->bind_param("ii", $folder_id_to_delete, $current_user_id);
            if ($stmt->execute()) {
                set_flash_message('Ordner erfolgreich in den Papierkorb verschoben', 'success');
                if ($ajax_mode) {
                    $ajax_response = ['success' => true, 'message' => 'Ordner erfolgreich verschoben', 'action' => 'delete_folder', 'data' => ['folder_id' => $folder_id_to_delete]];
                }
            } else {
                set_flash_message('Fehler beim Löschen des Ordners', 'error');
                if ($ajax_mode) {
                    $ajax_response = ['success' => false, 'message' => 'Fehler beim Löschen des Ordners'];
                }
            }
            $stmt->close();
        } else {
            set_flash_message('Ungültige Ordner-ID', 'error');
        }
        $action_taken = true;
    }

    // Ordner umbenennen
    if (isset($_POST['rename_folder'])) {
        $folder_id_rename = filter_input(INPUT_POST, 'folder_id', FILTER_VALIDATE_INT);
        $new_foldername = trim(isset($_POST['new_foldername']) ? $_POST['new_foldername'] : '');
        if ($folder_id_rename && !empty($new_foldername)) {
            if (preg_match('/^[a-zA-Z0-9._\-\s\(\)]+$/', $new_foldername) && strlen($new_foldername) <= 255) {
                $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param('sii', $new_foldername, $folder_id_rename, $current_user_id);
                if ($stmt->execute()) {
                    set_flash_message('Ordner erfolgreich umbenannt', 'success');
                    if ($ajax_mode) {
                        $ajax_response = ['success' => true, 'message' => 'Ordner erfolgreich umbenannt', 'action' => 'rename_folder', 'data' => ['folder_id' => $folder_id_rename, 'new_name' => $new_foldername]];
                    }
                } else {
                    set_flash_message('Fehler beim Umbenennen', 'error');
                    if ($ajax_mode) {
                        $ajax_response = ['success' => false, 'message' => 'Fehler beim Umbenennen'];
                    }
                }
                $stmt->close();
            } else {
                set_flash_message('Ungültiger Ordnername', 'error');
            }
        } else {
            set_flash_message('Ungültige Daten', 'error');
        }
        $action_taken = true;
    }

    // Datei löschen (soft delete)
    if (isset($_POST['delete_file'])) {
        $file_id_to_delete = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        if ($file_id_to_delete) {
            $stmt = $conn->prepare("UPDATE files SET deleted = 1 WHERE id = ? AND uploader_id = ? AND deleted = 0");
            $stmt->bind_param("ii", $file_id_to_delete, $current_user_id);
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
        } else { set_flash_message(lang('error_invalid_data'), 'error'); }
        $action_taken = true;
    }

    // Status ändern
    if (isset($_POST['toggle_public_status'])) {
        $file_id_toggle = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        $current_status = filter_input(INPUT_POST, 'current_status', FILTER_VALIDATE_INT);
        $current_status_int = is_numeric($current_status) ? (int)$current_status : null;
        error_log("OwnFiles TogglePublic: file_id={$file_id_toggle}, current_status_raw=" . var_export($current_status, true) . ", normalized=" . var_export($current_status_int, true));

        if ($file_id_toggle && ($current_status_int === 0 || $current_status_int === 1)) {
            // Explizite Berechtigungsprüfung (ist es wirklich die eigene Datei?)
            $perm_check_stmt = $conn->prepare("SELECT id FROM files WHERE id = ? AND uploader_id = ?");
            if ($perm_check_stmt) {
                $perm_check_stmt->bind_param("ii", $file_id_toggle, $current_user_id);
                $perm_check_stmt->execute();
                $perm_check_stmt->store_result();
                $has_permission = $perm_check_stmt->num_rows > 0;
                $perm_check_stmt->close();

                if ($has_permission) {
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
                                    $file_info = $file_info_stmt->get_result()->fetch_assoc();
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
                            error_log("DB Update Error (own_files toggle): " . $update_query->error);
                            if ($ajax_mode) {
                                $ajax_response = ['success' => false, 'message' => 'Fehler beim Ändern des Status'];
                            }
                        }
                        $update_query->close();
                    } else {
                        set_flash_message(lang('error_db_prepare'), 'error');
                        error_log("DB Prepare Error (own_files toggle): " . $conn->error);
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
                set_flash_message(lang('error_db_prepare'), 'error');
                if ($ajax_mode) {
                    $ajax_response = ['success' => false, 'message' => 'Datenbankfehler'];
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
            // Berechtigung prüfen (eigene Datei)
            $perm_check_stmt = $conn->prepare("SELECT id FROM files WHERE id = ? AND uploader_id = ? AND deleted = 0");
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
                    }
                } else {
                    set_flash_message('error_no_permission', 'error');
                }
            } else {
                set_flash_message('error_db_prepare', 'error');
            }
        } else {
            set_flash_message('error_invalid_data', 'error');
        }
        $action_taken = true;
    }

    // Ordner verschieben (Drag & Drop)
    if (isset($_POST['move_folder'])) {
        $folder_id_move = filter_input(INPUT_POST, 'folder_id', FILTER_VALIDATE_INT);
        // Behandle leeren String als null für "verschiebe zu Root"
        $target_folder_id = isset($_POST['target_folder_id']) && !empty($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : null;
        error_log("move_folder: folder_id={$folder_id_move}, target_folder_id=" . var_export($target_folder_id, true));
        if ($folder_id_move) {
            // Berechtigung prüfen (eigener Ordner)
            $perm_check_stmt = $conn->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ? AND deleted = 0");
            if ($perm_check_stmt) {
                $perm_check_stmt->bind_param("ii", $folder_id_move, $current_user_id);
                $perm_check_stmt->execute(); $perm_check_stmt->store_result();
                $has_permission = $perm_check_stmt->num_rows > 0;
                $perm_check_stmt->close();
                if ($has_permission) {
                    // Zielordner-Berechtigung prüfen (falls angegeben)
                    if ($target_folder_id) {
                        $folder_check_stmt = $conn->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ? AND deleted = 0");
                        $folder_check_stmt->bind_param("ii", $target_folder_id, $current_user_id);
                        $folder_check_stmt->execute(); $folder_check_stmt->store_result();
                        $folder_exists = $folder_check_stmt->num_rows > 0;
                        $folder_check_stmt->close();
                        if (!$folder_exists) {
                            set_flash_message('Zielordner nicht gefunden oder keine Berechtigung', 'error');
                            $action_taken = true;
                        }
                    }
                    // Zyklus verhindern: Prüfen, ob target_folder_id ein Unterordner des zu verschiebenden Ordners ist
                    if ($target_folder_id && !$action_taken) {
                        $is_descendant = false;
                        $check_id = $target_folder_id;
                        while ($check_id) {
                            if ($check_id == $folder_id_move) {
                                $is_descendant = true;
                                break;
                            }
                            $parent_stmt = $conn->prepare("SELECT parent_id FROM folders WHERE id = ? AND user_id = ?");
                            $parent_stmt->bind_param("ii", $check_id, $current_user_id);
                            $parent_stmt->execute();
                            $parent_result = $parent_stmt->get_result();
                            $parent_row = $parent_result->fetch_assoc();
                            $parent_stmt->close();
                            $check_id = $parent_row ? $parent_row['parent_id'] : null;
                        }
                        if ($is_descendant) {
                            set_flash_message('Ordner kann nicht in einen Unterordner verschoben werden', 'error');
                            $action_taken = true;
                        }
                    }
                    if (!$action_taken) { // Nur ausführen wenn keine Fehler aufgetreten
                        if ($target_folder_id === null) {
                            $stmt = $conn->prepare("UPDATE folders SET parent_id = NULL WHERE id = ?");
                            $stmt->bind_param('i', $folder_id_move);
                        } else {
                            $stmt = $conn->prepare("UPDATE folders SET parent_id = ? WHERE id = ?");
                            $stmt->bind_param('ii', $target_folder_id, $folder_id_move);
                        }
                        if ($stmt->execute()) {
                            set_flash_message('Ordner erfolgreich verschoben', 'success');
                            if ($ajax_mode) {
                                $ajax_response = ['success' => true, 'message' => 'Ordner erfolgreich verschoben', 'action' => 'move_folder', 'data' => ['folder_id' => $folder_id_move, 'target_folder_id' => $target_folder_id]];
                            }
                        } else {
                            set_flash_message('Fehler beim Verschieben des Ordners', 'error');
                            if ($ajax_mode) {
                                $ajax_response = ['success' => false, 'message' => 'Fehler beim Verschieben des Ordners'];
                            }
                        }
                        $stmt->close();
                    }
                } else {
                    set_flash_message('Keine Berechtigung zum Verschieben dieses Ordners', 'error');
                }
            } else {
                set_flash_message('Datenbankfehler', 'error');
            }
        } else {
            set_flash_message('Ungültige Ordner-ID', 'error');
        }
        $action_taken = true;
    }

    // Datei verschieben (Drag & Drop)
    if (isset($_POST['move_file'])) {
        $file_id_move = filter_input(INPUT_POST, 'file_id', FILTER_VALIDATE_INT);
        // Behandle leeren String als null für "verschiebe zu Root"
        $target_folder_id = isset($_POST['target_folder_id']) && !empty($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : null;
        error_log("move_file: file_id={$file_id_move}, target_folder_id=" . var_export($target_folder_id, true));
        if ($file_id_move) {
            // Berechtigung prüfen (eigene Datei)
            $perm_check_stmt = $conn->prepare("SELECT id FROM files WHERE id = ? AND uploader_id = ? AND deleted = 0");
            if ($perm_check_stmt) {
                $perm_check_stmt->bind_param("ii", $file_id_move, $current_user_id);
                $perm_check_stmt->execute(); $perm_check_stmt->store_result();
                $has_permission = $perm_check_stmt->num_rows > 0;
                $perm_check_stmt->close();
                if ($has_permission) {
                    // Zielordner-Berechtigung prüfen (falls angegeben)
                    if ($target_folder_id) {
                        $folder_check_stmt = $conn->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ? AND deleted = 0");
                        $folder_check_stmt->bind_param("ii", $target_folder_id, $current_user_id);
                        $folder_check_stmt->execute(); $folder_check_stmt->store_result();
                        $folder_exists = $folder_check_stmt->num_rows > 0;
                        $folder_check_stmt->close();
                        if (!$folder_exists) {
                            set_flash_message('Zielordner nicht gefunden oder keine Berechtigung', 'error');
                            $action_taken = true;
                        }
                    }
                    if (!$action_taken) { // Nur ausführen wenn keine Fehler aufgetreten
                        if ($target_folder_id === null) {
                            $stmt = $conn->prepare("UPDATE files SET folder_id = NULL WHERE id = ?");
                            $stmt->bind_param('i', $file_id_move);
                        } else {
                            $stmt = $conn->prepare("UPDATE files SET folder_id = ? WHERE id = ?");
                            $stmt->bind_param('ii', $target_folder_id, $file_id_move);
                        }
                        if ($stmt->execute()) {
                            set_flash_message('Datei erfolgreich verschoben', 'success');
                            if ($ajax_mode) {
                                $ajax_response = ['success' => true, 'message' => 'Datei erfolgreich verschoben', 'action' => 'move_file', 'data' => ['file_id' => $file_id_move, 'target_folder_id' => $target_folder_id]];
                            }
                        } else {
                            set_flash_message('Fehler beim Verschieben der Datei', 'error');
                            if ($ajax_mode) {
                                $ajax_response = ['success' => false, 'message' => 'Fehler beim Verschieben der Datei'];
                            }
                        }
                        $stmt->close();
                    }
                } else {
                    set_flash_message('Keine Berechtigung zum Verschieben dieser Datei', 'error');
                }
            } else {
                set_flash_message('Datenbankfehler', 'error');
            }
        } else {
            set_flash_message('Ungültige Datei-ID', 'error');
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


// Breadcrumb-Pfad bauen
function build_breadcrumb($conn, $folder_id, $user_id) {
    $path = [];
    $current_id = $folder_id;
    while ($current_id) {
        $stmt = $conn->prepare("SELECT id, name, parent_id FROM folders WHERE id = ? AND user_id = ? AND deleted = 0");
        $stmt->bind_param("ii", $current_id, $user_id);
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

$breadcrumb = build_breadcrumb($conn, $current_folder_id, $current_user_id);
// Ordner holen
$folders_sql = "SELECT id, name, created_at FROM folders WHERE user_id = ? AND deleted = 0";
$folders_params = [$current_user_id]; $folders_types = "i";
if ($current_folder_id !== null) {
    $folders_sql .= " AND parent_id " . ($current_folder_id ? "= ?" : "IS NULL");
    if ($current_folder_id) {
        $folders_params[] = $current_folder_id;
        $folders_types .= "i";
    }
} else {
    $folders_sql .= " AND parent_id IS NULL";
}
$folders_sql .= " ORDER BY name ASC";
$folders_stmt = $conn->prepare($folders_sql);
if ($folders_stmt) {
    $folders_stmt->bind_param($folders_types, ...$folders_params);
    $folders_stmt->execute();
    $folders_result = $folders_stmt->get_result();
    $folders = $folders_result->fetch_all(MYSQLI_ASSOC);
    $folders_stmt->close();
} else {
    $folders = [];
}

// Dateien holen
$files_sql = "SELECT id, filename, created_at, public, size FROM files WHERE uploader_id = ? AND deleted = 0";
$files_params = [$current_user_id]; $files_types = "i";
if ($current_folder_id !== null) {
    $files_sql .= " AND folder_id " . ($current_folder_id ? "= ?" : "IS NULL");
    if ($current_folder_id) {
        $files_params[] = $current_folder_id;
        $files_types .= "i";
    }
} else {
    $files_sql .= " AND folder_id IS NULL";
}
if (!empty($search_term)) {
    $files_sql .= " AND filename LIKE ?";
    $files_params[] = '%' . $search_term . '%';
    $files_types .= "s";
}
$files_sql .= " ORDER BY created_at DESC";
$files_stmt = $conn->prepare($files_sql);
if ($files_stmt) {
    $files_stmt->bind_param($files_types, ...$files_params);
    $files_stmt->execute();
    $files_result = $files_stmt->get_result();
    $files = $files_result->fetch_all(MYSQLI_ASSOC);
    $files_stmt->close();
} else {
    $files = [];
}


// --- HTML Ausgabe beginnt HIER ---
require_once __DIR__ . '/../includes/header.php'; // Header erst jetzt!
?>

<div class="view-header">
    <h1><?php echo lang('title_my_files'); ?></h1>
    <div class="nav-actions">
        <a href="create_folder<?php echo isset($_GET['folder']) && $_GET['folder'] ? '?folder=' . (int)$_GET['folder'] : ''; ?>" class="btn btn-primary btn-large">
            <i class="icon-plus"></i> <?php echo (function_exists('lang') && lang('button_folder') ? lang('button_folder') : 'Ordner'); ?>
        </a>
        <a href="upload<?php echo isset($_GET['folder']) && $_GET['folder'] ? '?folder=' . (int)$_GET['folder'] : ''; ?>" class="btn btn-secondary btn-large">
            <i class="icon-upload"></i> <?php echo lang('button_upload'); ?>
        </a>
    </div>
</div>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="own_files" class="breadcrumb-link" data-target-folder="">Start</a>
    <?php foreach ($breadcrumb as $crumb): ?>
        / <a href="own_files?folder=<?php echo $crumb['id']; ?>" class="breadcrumb-link" data-target-folder="<?php echo $crumb['id']; ?>"><?php echo htmlspecialchars($crumb['name']); ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <form method="GET" action="own_files" class="search-form-inline" style="margin-bottom: 20px;">
         <input type="hidden" name="folder" value="<?php echo $current_folder_id ?: ''; ?>">
         <input type="search" name="search" placeholder="<?php echo lang('placeholder_search_files'); ?>" value="<?php echo htmlspecialchars($search_term); ?>" style="/*...*/">
         <button type="submit" class="button button-secondary" style="/*...*/"><?php echo lang('button_search'); ?></button>
    </form>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th><?php echo lang('th_filename'); ?></th>
                    <th><?php echo lang('th_upload_date'); ?></th>
                    <th><?php echo lang('th_size'); ?></th>
                    <th><?php echo lang('th_status'); ?></th>
                    <th><?php echo lang('th_actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($folders) && empty($files)) { ?>
                    <tr><td colspan="5"><?php echo lang('text_no_files'); ?></td></tr>
                <?php } else { ?>
                    <?php foreach ($folders as $folder): ?>
                        <tr class="folder-row" data-folder-id="<?php echo $folder['id']; ?>">
                            <td>
                                <a href="own_files?folder=<?php echo $folder['id']; ?>" title="<?php echo htmlspecialchars($folder['name']); ?>">
                                    <i class="icon-folder"></i> <?php echo htmlspecialchars($folder['name']); ?>
                                </a>
                            </td>
                            <td><?php echo format_date_lang($folder['created_at']); ?></td>
                            <td>-</td>
                            <td>-</td>
                            <td class="actions-cell">
                                <button onclick="renameFolder(<?php echo $folder['id']; ?>, '<?php echo addslashes($folder['name']); ?>')" class="action-button" title="Umbenennen">
                                    <i class="icon-edit"></i>
                                </button>
                                <form method="post" action="own_files?folder=<?php echo $current_folder_id ?: ''; ?>" class="ajax-action" style="display:inline;" data-custom-confirm="Ordner '<?php echo addslashes($folder['name']); ?>' wirklich löschen?">
                                    <input type="hidden" name="ajax" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="delete_folder" value="1">
                                    <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                    <button type="submit" class="action-button delete-button" title="Löschen">
                                        <i class="icon-delete"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($files as $file): ?>
                        <tr class="file-row" data-file-id="<?php echo $file['id']; ?>">
                            <td class="filename-cell">
                                <a href="view_file?id=<?php echo $file['id']; ?>" title="<?php echo htmlspecialchars($file['filename']); ?>">
                                    <i class="icon-file"></i> <?php echo shorten_filename($file['filename']); ?>
                                </a>
                            </td>
                            <td><?php echo format_date_lang($file['created_at']); ?></td>
                            <td><?php echo format_bytes($file['size']); ?></td>
                            <td><span class="status-label <?php echo $file['public'] ? 'status-public' : 'status-private'; ?>"><?php echo $file['public'] ? lang('status_public') : lang('status_private'); ?></span></td>
                            <td class="actions-cell">
                                <a href="view_file?id=<?php echo $file['id']; ?>" class="action-button view-button" title="<?php echo lang('button_view'); ?>">
                                    <i class="icon-view"></i>
                                </a>
                                <form method="post" action="own_files?folder=<?php echo $current_folder_id ?: ''; ?>&search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;">
                                    <input type="hidden" name="ajax" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $file['public']; ?>">
                                    <input type="hidden" name="toggle_public_status" value="1">
                                    <button type="submit" name="toggle_public_status" class="action-button <?php echo $file['public'] ? 'private-button' : 'public-button'; ?>" title="<?php echo $file['public'] ? lang('button_make_private') : lang('button_make_public'); ?>">
                                        <?php echo $file['public'] ? '<i class="icon-private"></i>' : '<i class="icon-public"></i>'; ?>
                                    </button>
                                </form>
                                <a href="download?id=<?php echo $file['id']; ?>" class="action-button download-button" title="<?php echo lang('button_download'); ?>">
                                    <i class="icon-download"></i>
                                </a>
                                <button onclick="renameFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['filename']); ?>')" class="action-button" title="Umbenennen">
                                    <i class="icon-edit"></i>
                                </button>
                                <form method="post" action="own_files?folder=<?php echo $current_folder_id ?: ''; ?>&search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline;" data-custom-confirm="<?php printf(lang('text_confirm_delete_file'), htmlspecialchars(addslashes($file['filename']))); ?>">
                                    <input type="hidden" name="ajax" value="1">
                                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <input type="hidden" name="delete_file" value="1">
                                    <button type="submit" name="delete_file" class="action-button delete-button" title="<?php echo lang('button_delete'); ?>">
                                        <i class="icon-delete"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function showAjaxFlash(message, type) {
    let flashContainer = document.getElementById('ajaxFlashContainer');
    if (!flashContainer) {
        flashContainer = document.createElement('div');
        flashContainer.id = 'ajaxFlashContainer';
        flashContainer.style.position = 'fixed';
        flashContainer.style.right = '1rem';
        flashContainer.style.zIndex = '9999';
        // Platz unter dem Header lassen (Header-Höhe dynamisch bestimmen)
        const header = document.querySelector('header');
        const headerHeight = header ? header.offsetHeight + 10 : 70;
        flashContainer.style.top = `${headerHeight}px`;
        document.body.appendChild(flashContainer);
    }
    flashContainer.innerHTML = `<div class="alert alert-${type}" role="alert">${message}</div>`;
    setTimeout(() => { flashContainer.innerHTML = ''; }, 3000);
}

function customConfirm(message) {
    return new Promise((resolve) => {
        // Modal erstellen
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="text-align: center; padding: 2rem;">
                <div style="font-size: 3rem; color: #dc3545; margin-bottom: 1rem;">⚠️</div>
                <p style="font-size: 1.1rem; margin-bottom: 2rem;">${message}</p>
                <div style="display: flex; justify-content: center; gap: 1rem;">
                    <button class="button button-primary" id="confirm-yes" style="min-width: 100px;">Ja</button>
                    <button class="button button-secondary" id="confirm-no" style="min-width: 100px;">Nein</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Modal öffnen
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');

        // Event-Listener
        const yesBtn = modal.querySelector('#confirm-yes');
        const noBtn = modal.querySelector('#confirm-no');

        const closeModal = () => {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            setTimeout(() => modal.remove(), 300);
        };

        yesBtn.addEventListener('click', () => {
            closeModal();
            resolve(true);
        });

        noBtn.addEventListener('click', () => {
            closeModal();
            resolve(false);
        });

        // ESC schließen
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                closeModal();
                resolve(false);
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    });
}

function handleAjaxResponse(data) {
    if (!data) {
        showAjaxFlash('Ungültige Serverantwort', 'error');
        return;
    }

    if (data.success) {
        if (data.action !== 'delete_file' && data.action !== 'delete_folder') {
            showAjaxFlash(data.message || 'Erfolg', 'success');
        }
        if (data.action === 'delete_file' && data.data && data.data.file_id) {
            const row = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"]`);
            if (row) row.remove();
        }
        if (data.action === 'delete_folder' && data.data && data.data.folder_id) {
            const row = document.querySelector(`tr.folder-row[data-folder-id="${data.data.folder_id}"]`);
            if (row) row.remove();
        }
        if (data.action === 'toggle_public_status' && data.data && data.data.file_id) {
            const row = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"]`);
            if (row) {
                const isPublic = data.data.new_status == 1 || data.data.new_status === true;

                const statusElem = row.querySelector('.status-label');
                if (statusElem) {
                    statusElem.className = `status-label ${isPublic ? 'status-public' : 'status-private'}`;
                    statusElem.textContent = isPublic ? '<?php echo lang('status_public'); ?>' : '<?php echo lang('status_private'); ?>';
                }

                const actionBtn = row.querySelector('button[name="toggle_public_status"]');
                if (actionBtn) {
                    actionBtn.className = `action-button ${isPublic ? 'private-button' : 'public-button'}`;
                    actionBtn.title = isPublic ? '<?php echo lang('button_make_private'); ?>' : '<?php echo lang('button_make_public'); ?>';
                    actionBtn.innerHTML = isPublic ? '<i class="icon-private"></i>' : '<i class="icon-public"></i>';

                    const currentStatusInput = actionBtn.closest('form')?.querySelector('input[name="current_status"]');
                    if (currentStatusInput) {
                        currentStatusInput.value = isPublic ? '1' : '0';
                    }
                }
            }
        }
        if (data.action === 'rename_file' && data.data && data.data.file_id && data.data.new_name) {
            const nameCell = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"] .filename-cell a`);
            if (nameCell) nameCell.textContent = data.data.new_name;
        }
        if (data.action === 'rename_folder' && data.data && data.data.folder_id && data.data.new_name) {
            const nameCell = document.querySelector(`tr.folder-row[data-folder-id="${data.data.folder_id}"] .filename-cell a`);
            if (nameCell) nameCell.textContent = data.data.new_name;
        }
        if (data.action === 'move_file' && data.data && data.data.file_id) {
            const row = document.querySelector(`tr.file-row[data-file-id="${data.data.file_id}"]`);
            if (row) row.remove();
        }
        if (data.action === 'move_folder' && data.data && data.data.folder_id) {
            const row = document.querySelector(`tr.folder-row[data-folder-id="${data.data.folder_id}"]`);
            if (row) row.remove();
        }
    } else {
        showAjaxFlash(data.message || 'Ein Fehler ist aufgetreten', 'error');
    }
}

function postAjaxAction(actionData) {
    return fetch('own_files', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams(actionData)
    })
    .then(response => response.json())
    .then(data => {
        handleAjaxResponse(data);
        return data;
    })
    .catch(error => {
        console.error('AJAX Fehler:', error);
        showAjaxFlash('Ein Netzwerkfehler ist aufgetreten.', 'error');
    });
}

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

function renameFolder(folderId, currentName) {
    const newName = prompt('Neuer Ordnername:', currentName);
    if (newName && newName !== currentName) {
        postAjaxAction({
            csrf_token: '<?php echo csrf_token(); ?>',
            rename_folder: '1',
            folder_id: folderId,
            new_foldername: newName,
            ajax: '1'
        });
    }
}

// --- Drag & Drop Funktionalität ---
document.addEventListener('DOMContentLoaded', function() {
    // Datei-Zellen als draggable machen
    const fileCells = document.querySelectorAll('tbody tr.file-row td.filename-cell');
    fileCells.forEach(cell => {
        cell.draggable = true;
        cell.addEventListener('dragstart', function(e) {
            const fileRow = this.closest('tr');
            const fileId = fileRow.dataset.fileId;
            e.dataTransfer.setData('file', fileId);
            e.dataTransfer.effectAllowed = 'move';
            fileRow.classList.add('dragging');
        });
    });

    // Ordner-Zeilen als draggable machen
    const folderRows = document.querySelectorAll('tbody tr.folder-row');
    folderRows.forEach(row => {
        row.draggable = true;
        row.addEventListener('dragstart', function(e) {
            const folderId = this.dataset.folderId;
            if (folderId) {
                e.dataTransfer.setData('folder', folderId);
                e.dataTransfer.effectAllowed = 'move';
                this.classList.add('dragging');
            }
        });
    });

    // Drag-End für alle Zeilen
    const allRows = document.querySelectorAll('tbody tr.file-row, tbody tr.folder-row');
    allRows.forEach(row => {
        row.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
        });
    });

    // AJAX-Formulare abfangen und inline bearbeiten
    const ajaxForms = document.querySelectorAll('form.ajax-action');
    ajaxForms.forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            // Bestätigung prüfen
            const confirmMessage = this.getAttribute('data-custom-confirm');
            if (confirmMessage) {
                const confirmed = await customConfirm(confirmMessage);
                if (!confirmed) {
                    return; // Abbrechen wenn nicht bestätigt
                }
            }
            const formData = new FormData(this);
            if (!formData.has('ajax')) {
                formData.append('ajax', '1');
            }
            const payload = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                payload.append(key, value);
            }
            fetch(this.action, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: payload
            })
            .then(res => res.json())
            .then(data => handleAjaxResponse(data))
            .catch(err => {
                console.error('AJAX-Formular Fehler:', err);
                showAjaxFlash('Ein Netzwerkfehler ist aufgetreten.', 'error');
            });
        });
    });

    // Ordner-Zeilen als droppable machen
    folderRows.forEach(row => {
        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });
        row.addEventListener('dragleave', function(e) {
            this.classList.remove('drag-over');
        });
        row.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            let targetFolderId = this.dataset.folderId || null;
            if (targetFolderId) {
                targetFolderId = parseInt(targetFolderId);
            }

            if (e.dataTransfer.types.includes('folder')) {
                const folderId = e.dataTransfer.getData('folder');
                if (folderId && targetFolderId !== null) {
                    moveFolder(folderId, targetFolderId);
                }
            } else if (e.dataTransfer.types.includes('file')) {
                const fileId = e.dataTransfer.getData('file');
                if (fileId) {
                    moveFile(fileId, targetFolderId);
                }
            }
        });
    });

    // Breadcrumb-Links als droppable machen
    const breadcrumbLinks = document.querySelectorAll('.breadcrumb-link');
    console.log('Initializing breadcrumb drop targets. Count:', breadcrumbLinks.length);
    breadcrumbLinks.forEach(link => {
        link.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.classList.add('drag-over');
        });
        link.addEventListener('dragleave', function(e) {
            this.classList.remove('drag-over');
        });
        link.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');

            // Behandle leeren String (Root) explizit
            let targetFolderId = this.dataset.targetFolder;
            if (targetFolderId === '') {
                targetFolderId = null;  // Root = null
            } else if (targetFolderId) {
                targetFolderId = parseInt(targetFolderId);
            }
            console.log('Breadcrumb drop:', {targetAttr: this.dataset.targetFolder, final: targetFolderId});

            if (e.dataTransfer.types.includes('folder')) {
                const folderId = e.dataTransfer.getData('folder');
                if (folderId) {
                    console.log('Moving folder', folderId, 'to target:', targetFolderId);
                    moveFolder(folderId, targetFolderId);
                }
            } else if (e.dataTransfer.types.includes('file')) {
                const fileId = e.dataTransfer.getData('file');
                if (fileId) {
                    console.log('Moving file', fileId, 'to target:', targetFolderId);
                    moveFile(fileId, targetFolderId);
                }
            }
        });
    });

    function moveFile(fileId, targetFolderId) {
        const payload = {
            csrf_token: '<?php echo csrf_token(); ?>',
            move_file: '1',
            file_id: fileId,
            target_folder_id: targetFolderId === null ? '' : (targetFolderId || ''),
            ajax: '1'
        };
        postAjaxAction(payload);
    }

    function moveFolder(folderId, targetFolderId) {
        const payload = {
            csrf_token: '<?php echo csrf_token(); ?>',
            move_folder: '1',
            folder_id: folderId,
            target_folder_id: targetFolderId === null ? '' : (targetFolderId || ''),
            ajax: '1'
        };
        postAjaxAction(payload);
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php'; // Footer am Ende
?>