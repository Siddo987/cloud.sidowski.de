<?php
// /de/all_users.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php'; // Pfad korrigiert

// Prüfen, ob der Benutzer Admin-Rechte hat (is_admin() prüft auf 'admin' oder 'owner')
if (!$is_admin) {
    set_flash_message('error_no_permission', 'error');
    redirect($current_language . '/dashboard'); // Redirect zum Dashboard wenn kein Admin/Owner
}

// Suchbegriff & Sortierung (POST statt GET) auslesen
$valid_sort_columns = ['id', 'username', 'email', 'role'];

// Rollen-Hierarchie wird an mehreren Stellen gebraucht
$role_hierarchy = [
    'user'  => 0,
    'mod'   => 1,
    'admin' => 2,
    'owner' => 3,
    'root'  => 4, // root existiert nur per NAS/Login, darf nicht bearbeitet werden
];
function has_higher_role($current, $target) {
    global $role_hierarchy;
    $cur_lvl = isset($role_hierarchy[$current]) ? $role_hierarchy[$current] : -1;
    $tgt_lvl = isset($role_hierarchy[$target]) ? $role_hierarchy[$target] : -1;
    return $cur_lvl > $tgt_lvl;
}

// Defaultwerte
$search_term = '';
$sort_column = 'username';
$sort_direction = 'ASC';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search_term = isset($_POST['search']) ? trim($_POST['search']) : '';
    if (isset($_POST['sort']) && in_array($_POST['sort'], $valid_sort_columns)) {
        $sort_column = $_POST['sort'];
    }
    if (isset($_POST['order']) && strtolower($_POST['order']) === 'desc') {
        $sort_direction = 'DESC';
    }
}

$base_path = defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : ''; // Basis-URL holen
$redirect_url = $base_path . '/' . $current_language . '/all_users'; // keine Querystring-Parameter mehr


// --- POST Handling ZUERST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $action_taken = false;
    $ajax_mode = isset($_POST['ajax']) && $_POST['ajax'] === '1';
    $ajax_response = ['success' => false, 'message' => '', 'action' => null, 'data' => null];

    // Rolle änderen (wird typischerweise vom separaten Bearbeitungsformular aufgerufen)
    if (isset($_POST['update_role'])) {
        $user_id_to_update = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $new_role = trim(isset($_POST['role']) ? $_POST['role'] : '');
        $allowed_roles = array_keys($role_hierarchy);

        if ($user_id_to_update && in_array($new_role, $allowed_roles)) {
            // Berechtigungsprüfungen mithilfe der Hierarchiefunktionen
            if ($user_id_to_update == $current_user_id) {
                set_flash_message('error_cannot_change_own_role', 'error');
            } else {
                // aktuelle Rolle des Ziels ermitteln
                $user_check_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
                if ($user_check_stmt) {
                    $user_check_stmt->bind_param("i", $user_id_to_update);
                    $user_check_stmt->execute();
                    $user_check_result = $user_check_stmt->get_result();
                    $user_to_update = $user_check_result->fetch_assoc();
                    $user_check_stmt->close();

                    if ($user_to_update) {
                        $target_role_lower = strtolower($user_to_update['role']);
                        $current_role_lower = strtolower($current_user_role);
                        if (!has_higher_role($current_role_lower, $target_role_lower) || !has_higher_role($current_role_lower, strtolower($new_role))) {
                            set_flash_message('error_no_permission', 'error');
                        } else {
                            // Bestellung läuft
                            $update_stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                            if ($update_stmt) {
                                $update_stmt->bind_param("si", $new_role, $user_id_to_update);
                                if ($update_stmt->execute()) {
                                    set_flash_message('success_role_changed', 'success');
                                } else {
                                    set_flash_message('error_db_update', 'error');
                                    error_log("DB Update Error (role): " . $update_stmt->error);
                                }
                                $update_stmt->close();
                            } else {
                                set_flash_message('error_db_prepare', 'error');
                                error_log("DB Prepare Error (role update): " . $conn->error);
                            }
                        }
                    } else {
                         set_flash_message('error_user_not_found', 'error');
                    }
                } else {
                     set_flash_message('error_db_prepare', 'error');
                     error_log("DB Prepare Error (user check): " . $conn->error);
                }
            }
        } else {
            set_flash_message('error_invalid_data', 'error');
            if ($ajax_mode) {
                $ajax_response = ['success' => false, 'message' => 'error_invalid_data'];
            }
        }
        $action_taken = true;
    }

    // Benutzer löschen
    if (isset($_POST['delete_user'])) {
        $user_id_to_delete = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        if ($user_id_to_delete) {
            // Berechtigungsprüfungen
            if ($user_id_to_delete == $current_user_id) {
                set_flash_message('error_cannot_delete_self', 'error');
            } else {
                // Rolle des zu löschenden Benutzers holen
                $role_check_stmt = $conn->prepare("SELECT role, username FROM users WHERE id = ?");
                if ($role_check_stmt) {
                    $role_check_stmt->bind_param("i", $user_id_to_delete);
                    $role_check_stmt->execute();
                    $role_result = $role_check_stmt->get_result();
                    $user_to_delete = $role_result->fetch_assoc();
                    $role_check_stmt->close();

                    if ($user_to_delete) {
                        $role_to_delete_lower = strtolower($user_to_delete['role']);
                        $current_user_role_lower = strtolower($current_user_role);

                        // Prüfen, ob der Löschvorgang erlaubt ist
                        if ($role_to_delete_lower === 'owner') {
                            set_flash_message('error_cannot_delete_owner', 'error'); // Owner kann nicht gelöscht werden
                        } elseif ($role_to_delete_lower === 'admin' && $current_user_role_lower !== 'owner') {
                            set_flash_message('error_permission_delete_admin', 'error'); // Nur Owner darf Admins löschen
                        } else {
                            // TODO: Dateien des Users löschen? Aktuell nicht implementiert!
                            // $files_to_delete_query = $conn->query("SELECT id FROM files WHERE uploader_id = $user_id_to_delete");
                            // while ($file = $files_to_delete_query->fetch_assoc()) {
                            //     delete_file_permanently($conn, $file['id'], $current_user_id, $current_user_role); // Admin löscht
                            // }

                            // Benutzer löschen
                            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                            if ($delete_stmt) {
                                $delete_stmt->bind_param("i", $user_id_to_delete);
                                if ($delete_stmt->execute()) {
                                    set_flash_message('success_user_deleted', 'success', [htmlspecialchars($user_to_delete['username'])]);
                                    if ($ajax_mode) {
                                        $ajax_response = ['success' => true, 'message' => 'success_user_deleted', 'action' => 'delete_user', 'data' => ['user_id' => $user_id_to_delete], 'message_args' => [htmlspecialchars($user_to_delete['username'])]];
                                    }
                                } else {
                                    set_flash_message('error_db_delete', 'error');
                                    error_log("DB Delete Error (user): " . $delete_stmt->error);
                                    if ($ajax_mode) {
                                        $ajax_response = ['success' => false, 'message' => 'error_db_delete'];
                                    }
                                }
                                $delete_stmt->close();
                            } else {
                                set_flash_message('error_db_prepare', 'error');
                                error_log("DB Prepare Error (user delete): " . $conn->error);
                            }
                        }
                    } else {
                        set_flash_message('error_user_not_found', 'error');
                    }
                } else {
                     set_flash_message('error_db_prepare', 'error');
                     error_log("DB Prepare Error (role check delete): " . $conn->error);
                }
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
                    $args = isset($ajax_response['message_args']) && is_array($ajax_response['message_args']) ? $ajax_response['message_args'] : [];
                    $ajax_response['message'] = call_user_func_array('lang', array_merge([$ajax_response['message']], $args));
                    unset($ajax_response['message_args']);
                }
            } catch (Throwable $e) {
                error_log('Error translating message: ' . $e->getMessage());
            }
            ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode($ajax_response);
            exit;
        }
        // nach erfolgreicher Aktion einfach zur Basis-URL ohne Parameter
        redirect($redirect_url);
    }
}
// --- ENDE POST Handling ---


// --- Daten für Seitenanzeige holen ---
$users = []; // Initialisieren
$sql = "SELECT id, username, email, role FROM users";
$params = [];
$types = "";
if (!empty($search_term)) {
    $sql .= " WHERE username LIKE ? OR email LIKE ?";
    $search_like = '%' . $search_term . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss";
}
// Sortierung anfügen (standardmäßig nach $sort_column)
$sql .= " ORDER BY " . $sort_column . " " . $sort_direction;

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        error_log("DB Execute Error (fetch users): " . $stmt->error);
        set_flash_message('error_db_error', 'error'); // Allgemeine Fehlermeldung
    }
    $stmt->close();
} else {
    error_log("DB Prepare Error (fetch users): " . $conn->error);
    set_flash_message('error_db_prepare', 'error');
}


// --- HTML Ausgabe beginnt HIER ---
require_once __DIR__ . '/../includes/header.php'; // Header erst jetzt!
?>

<h1><?php echo lang('title_all_users'); ?></h1>

<div class="card">
    <?php // Suchformular mit korrekter Klasse ?>
    <form method="POST" action="all_users" class="search-form-inline">
         <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
         <input type="search" name="search" placeholder="<?php echo lang('placeholder_search_users'); ?>" value="<?php echo htmlspecialchars($search_term); ?>">
         <button type="submit" class="button button-secondary"><?php echo lang('button_search'); ?></button>
     </form>
     <!-- Hidden form to carry filters/sorting without querystring -->
     <form id="filter-form" method="POST" action="all_users">
         <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
         <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
         <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_column); ?>">
         <input type="hidden" name="order" value="<?php echo htmlspecialchars(strtolower($sort_direction)); ?>">
     </form>

    <div class="table-container">
        <?php
        // Helper für sortierbare Spaltenüberschriften
        function sort_header($col, $label, $current_sort, $current_dir, $search) {
            $dir = 'ASC';
            $symbol = '';
            if ($col === $current_sort) {
                if ($current_dir === 'ASC') {
                    $symbol = ' ▲';
                    $dir = 'DESC';
                } else {
                    $symbol = ' ▼';
                    $dir = 'ASC';
                }
            }
            $url = '#';
            return '<a href="' . htmlspecialchars($url) . '" class="sort-link" data-col="' . htmlspecialchars($col) . '" data-dir="' . htmlspecialchars($dir) . '">' . htmlspecialchars($label . $symbol) . '</a>';
        }
        ?>
        <table>
        <script>
        // submit helper that updates table container and re-binds handlers
        function ajaxSubmit(form) {
            var data = new FormData(form);
            fetch(form.action, {method: 'POST', body: data, headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function(resp){ return resp.text(); })
                .then(function(html){
                    var parser = new DOMParser();
                    var doc = parser.parseFromString(html, 'text/html');
                    var newContainer = doc.querySelector('.table-container');
                    if (newContainer) {
                        document.querySelector('.table-container').innerHTML = newContainer.innerHTML;
                        // after replacing the markup, reinitialize interactions
                        initTableEvents();
                    }
                });
        }

        function sortClickHandler(e) {
            e.preventDefault();
            var filterForm = document.getElementById('filter-form');
            var col = this.dataset.col;
            var current = filterForm.sort.value;
            var dir = this.dataset.dir || 'ASC';
            // toggle direction if same column
            if (current === col) {
                dir = (filterForm.order.value.toUpperCase() === 'ASC') ? 'DESC' : 'ASC';
            }
            filterForm.sort.value = col;
            filterForm.order.value = dir;
            ajaxSubmit(filterForm);
        }

        function mainSearchHandler(e) {
            e.preventDefault();
            var filterForm = document.getElementById('filter-form');
            filterForm.search.value = this.search.value;
            ajaxSubmit(filterForm);
        }

        function initTableEvents() {
            // sort links
            document.querySelectorAll('.sort-link').forEach(function(el) {
                el.removeEventListener('click', sortClickHandler);
                el.addEventListener('click', sortClickHandler);
            });
            // main search
            var mainSearch = document.querySelector('form.search-form-inline');
            if (mainSearch) {
                mainSearch.removeEventListener('submit', mainSearchHandler);
                mainSearch.addEventListener('submit', mainSearchHandler);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            initTableEvents();
        });
        </script>
            <thead>
                <tr>
                    <th class="col-id"><?php echo sort_header('id', lang('th_user_id'), $sort_column, $sort_direction, $search_term); ?></th>
                    <th><?php echo sort_header('username', lang('th_username'), $sort_column, $sort_direction, $search_term); ?></th>
                    <th><?php echo sort_header('email', lang('th_email'), $sort_column, $sort_direction, $search_term); ?></th>
                    <th><?php echo sort_header('role', lang('th_role'), $sort_column, $sort_direction, $search_term); ?></th>
                    <th><?php echo lang('th_actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                 <?php
                 if (empty($users)) {
                     echo '<tr><td colspan="5">' . lang('text_no_users_found' . (!empty($search_term) ? '_search' : '')) . '</td></tr>';
                 } else {
                     foreach ($users as $user) {
                                         // --- NEU: Berechtigungen für diese Zeile berechnen ---
                        $user_role_lower = strtolower($user['role']);
                        $current_user_role_lower = strtolower($current_user_role); // Rolle des Besuchers

                        // kann der aktuelle Benutzer diese Zielzeile bearbeiten (edit page)?
                        $can_edit_user = false;
                        if ($user['id'] != $current_user_id && has_higher_role($current_user_role_lower, $user_role_lower)) {
                            $can_edit_user = true;
                        }

                        // darf ein Löschvorgang ausgeführt werden?
                        $can_delete_user = false;
                        if ($user['id'] != $current_user_id && has_higher_role($current_user_role_lower, $user_role_lower)) {
                            // niemals Owner oder Root löschen
                            if (!in_array($user_role_lower, ['owner', 'root'])) {
                                $can_delete_user = true;
                            }
                        }
                        // --- ENDE NEU ---

                 ?>
                 <tr data-user-id="<?php echo $user['id']; ?>">
                     <td><?php echo $user['id']; ?></td>
                     <td><?php echo htmlspecialchars($user['username']); ?></td>
                     <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                     <td><?php echo lang('role_' . $user_role_lower) ?: htmlspecialchars(ucfirst($user['role'])); ?></td>
                     <td class="actions-cell">
                         <?php if ($can_edit_user): ?>
                             <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="action-button" title="<?php echo lang('button_edit'); ?>">✏️</a>
                         <?php endif; ?>
                         <?php if ($can_delete_user): ?>
                             <form method="post" action="all_users?search=<?php echo urlencode($search_term); ?>" class="ajax-action" style="display:inline; margin:0;" onsubmit="return confirm('<?php printf(lang('text_confirm_delete_user'), htmlspecialchars(addslashes($user['username']))); ?>');">
                                 <input type="hidden" name="ajax" value="1">
                                 <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                 <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                 <button type="submit" name="delete_user" class="action-button delete-button" title="<?php echo lang('button_delete'); ?>">🗑️</button>
                             </form>
                         <?php endif; ?>
                         <?php if (!$can_edit_user && !$can_delete_user): echo '-'; endif; ?>
                     </td>
                 </tr>
                 <?php }} // Ende foreach und else ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php'; // Footer am Ende
?>