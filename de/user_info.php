<?php
// /de/user_info.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

// Rollen-Hierarchie (wie in all_users.php)
$role_hierarchy = [
    'user'  => 0,
    'mod'   => 1,
    'admin' => 2,
    'owner' => 3,
    'root'  => 4,
];
function has_higher_role($current, $target) {
    global $role_hierarchy;
    $cur_lvl = isset($role_hierarchy[$current]) ? $role_hierarchy[$current] : -1;
    $tgt_lvl = isset($role_hierarchy[$target]) ? $role_hierarchy[$target] : -1;
    return $cur_lvl > $tgt_lvl;
}

// nur eingeloggte Admins/Owner/etc dürfen hierhin
if (!$is_admin) {
    set_flash_message('error_no_permission', 'error');
    redirect($current_language . '/dashboard');
}

// id entweder aus GET (erstaufruf) oder POST (nach Formular)
$user_id = null;
if (isset($_GET['id'])) {
    $user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
}
if (!$user_id && isset($_POST['id'])) {
    $user_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}
if (!$user_id) {
    set_flash_message('error_invalid_id', 'error');
    redirect($current_language . '/all_users');
}

// lade Benutzer
$select_fields = "id, username, email, role, session_version, created_at, last_login, is_active, deleted, deleted_at, deletion_recovery_until, two_factor_enabled, two_factor_method";
$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'unlimited_upload'");
$has_unlimited_column = false;
if ($col_check && $col_check->num_rows > 0) {
    $select_fields .= ", unlimited_upload";
    $has_unlimited_column = true;
}
$stmt = $conn->prepare("SELECT $select_fields FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;
$stmt->close();

if ($user && !array_key_exists('unlimited_upload', $user)) {
    $user['unlimited_upload'] = 0;
}

if (!$user) {
    set_flash_message('error_user_not_found', 'error');
    redirect($current_language . '/all_users');
}

$current_role_lower = strtolower($current_user_role);
$target_role_lower  = strtolower($user['role']);

// zugriffsberechtigung: man muss eine höhere Rolle als das Ziel haben
if (!has_higher_role($current_role_lower, $target_role_lower)) {
    set_flash_message('error_no_permission', 'error');
    redirect($current_language . '/all_users');
}

// Formularauswertung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    // 1. save_user
    if (isset($_POST['save_user'])) {
        $new_username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $new_email    = isset($_POST['email']) ? trim($_POST['email']) : '';
        $new_role     = isset($_POST['role']) ? trim($_POST['role']) : $user['role'];
        $new_unlimited = isset($_POST['unlimited_upload']) ? 1 : 0;

        // Validierungen
        if ($new_username === '' || strlen($new_username) < 3) {
            set_flash_message('error_invalid_data', 'error');
        } elseif ($new_email !== '' && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            set_flash_message('error_invalid_data', 'error');
        } elseif (!isset($role_hierarchy[$new_role])) {
            set_flash_message('error_invalid_data', 'error');
        } elseif (!has_higher_role($current_role_lower, strtolower($new_role))) {
            set_flash_message('error_no_permission', 'error');
        } else {
            // check duplicates
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check_stmt->bind_param('si', $new_username, $user_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                set_flash_message('error_username_taken', 'error');
                $check_stmt->close();
            } else {
                $check_stmt->close();
                if ($new_email !== '') {
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $check_stmt->bind_param('si', $new_email, $user_id);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows > 0) {
                        set_flash_message('error_email_taken', 'error');
                        $check_stmt->close();
                    } else {
                        $check_stmt->close();
                        if ($has_unlimited_column) {
                            $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, unlimited_upload = ? WHERE id = ?");
                            $update_stmt->bind_param('sssii', $new_username, $new_email, $new_role, $new_unlimited, $user_id);
                        } else {
                            $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                            $update_stmt->bind_param('sssi', $new_username, $new_email, $new_role, $user_id);
                        }
                        if ($update_stmt->execute()) {
                            set_flash_message('success_role_changed', 'success');
                            $user['username'] = $new_username;
                            $user['email'] = $new_email;
                            $user['role'] = $new_role;
                            $user['unlimited_upload'] = $new_unlimited;
                        } else {
                            set_flash_message('error_db_update', 'error');
                        }
                        $update_stmt->close();
                    }
                } else {
                    if ($has_unlimited_column) {
                        $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = NULL, role = ?, unlimited_upload = ? WHERE id = ?");
                        $update_stmt->bind_param('ssii', $new_username, $new_role, $new_unlimited, $user_id);
                    } else {
                        $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = NULL, role = ? WHERE id = ?");
                        $update_stmt->bind_param('ssi', $new_username, $new_role, $user_id);
                    }
                    if ($update_stmt->execute()) {
                        set_flash_message('success_role_changed', 'success');
                        $user['username'] = $new_username;
                        $user['email'] = null;
                        $user['role'] = $new_role;
                        $user['unlimited_upload'] = $new_unlimited;
                    } else {
                        set_flash_message('error_db_update', 'error');
                    }
                    $update_stmt->close();
                }
            }
        }
    }

    // 2. set_password
    if (isset($_POST['set_password'])) {
        $new_pass = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        if (empty($new_pass) || strlen($new_pass) < 8) {
            set_flash_message('error_password_too_short', 'error');
        } elseif ($new_pass !== $confirm) {
            set_flash_message('error_passwords_dont_match', 'error');
        } else {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pw_stmt = $conn->prepare("UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?");
            $pw_stmt->bind_param('si', $hash, $user_id);
            if ($pw_stmt->execute()) {
                set_flash_message('success_password_changed', 'success');
                if (!empty($user['email'])) {
                    $subject = 'Passwort zurückgesetzt';
                    $body = '<p>Dein Passwort wurde von einem Administrator zurückgesetzt.</p>';
                    send_email($user['email'], $subject, $body, true);
                }
            } else {
                set_flash_message('error_db_update', 'error');
            }
            $pw_stmt->close();
        }
    }

    // 3. send_reset
    if (isset($_POST['send_reset'])) {
        if (empty($user['email']) || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            set_flash_message('error_no_email', 'error');
        } else {
            $token = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            if (create_user_token($conn, $user_id, $token, 'password_temp_code', $expires)) {
                $subject = 'Temporärer Code für Passwortänderung';
                $body = '<p>Hallo ' . htmlspecialchars($user['username']) . ',</p>' .
                        '<p>Dein temporärer Code: <strong>' . $token . '</strong></p>' .
                        '<p>Der Code ist 1 Stunde gültig. Zurücksetzen hier: ' .
                        '<a href="' . BASE_URL . '/' . $current_language . '/reset_password">Passwort zurücksetzen</a></p>';
                if (send_email($user['email'], $subject, $body, true)) {
                    set_flash_message('success_temp_code_sent', 'success');
                } else {
                    set_flash_message('error_email_send_failed', 'error');
                }
            } else {
                set_flash_message('error_db_insert', 'error');
            }
        }
    }

    // 4. disable_2fa
    if (isset($_POST['disable_2fa'])) {
        $fa_stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_method = NULL, two_factor_secret = NULL, two_factor_backup_codes = NULL WHERE id = ?");
        $fa_stmt->bind_param('i', $user_id);
        if ($fa_stmt->execute()) {
            set_flash_message('success_2fa_disabled', 'success');
            $user['two_factor_enabled'] = 0;
            $user['two_factor_method'] = null;
        } else {
            set_flash_message('error_db_update', 'error');
        }
        $fa_stmt->close();
    }

    // 5. force_logout
    if (isset($_POST['force_logout'])) {
        $fl_stmt = $conn->prepare("UPDATE users SET session_version = session_version + 1 WHERE id = ?");
        $fl_stmt->bind_param('i', $user_id);
        if ($fl_stmt->execute()) {
            set_flash_message('Sitzungen erfolgreich beendet.', 'success');
        } else {
            set_flash_message('error_db_update', 'error');
        }
        $fl_stmt->close();
    }

    // 6. toggle_active
    if (isset($_POST['toggle_active'])) {
        $new_active = $user['is_active'] ? 0 : 1;
        // Increase session_version when locking
        $sess_sql = $new_active == 0 ? ", session_version = session_version + 1" : "";
        $ta_stmt = $conn->prepare("UPDATE users SET is_active = ?$sess_sql WHERE id = ?");
        $ta_stmt->bind_param('ii', $new_active, $user_id);
        if ($ta_stmt->execute()) {
            set_flash_message($new_active ? 'Account entsperrt.' : 'Account gesperrt.', 'success');
            $user['is_active'] = $new_active;
        } else {
            set_flash_message('error_db_update', 'error');
        }
        $ta_stmt->close();
    }

    // 7. restore_user
    if (isset($_POST['restore_user'])) {
        $ru_stmt = $conn->prepare("UPDATE users SET deleted = 0, deleted_at = NULL, deletion_recovery_until = NULL WHERE id = ?");
        $ru_stmt->bind_param('i', $user_id);
        if ($ru_stmt->execute()) {
            set_flash_message('Account wiederhergestellt.', 'success');
            $user['deleted'] = 0;
            $user['deleted_at'] = null;
            $user['deletion_recovery_until'] = null;
        } else {
            set_flash_message('error_db_update', 'error');
        }
        $ru_stmt->close();
    }

    // 8. delete_webauthn
    if (isset($_POST['delete_webauthn'])) {
        $cred_id = filter_input(INPUT_POST, 'credential_id', FILTER_VALIDATE_INT);
        if ($cred_id) {
            $dw_stmt = $conn->prepare("DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?");
            $dw_stmt->bind_param('ii', $cred_id, $user_id);
            if ($dw_stmt->execute()) {
                set_flash_message('Security Key entfernt.', 'success');
            } else {
                set_flash_message('error_db_delete', 'error');
            }
            $dw_stmt->close();
        }
    }

    // 9. impersonate_user
    if (isset($_POST['impersonate_user'])) {
        if ($current_role_lower !== 'owner' && $current_role_lower !== 'admin') {
            set_flash_message('error_no_permission', 'error');
        } else {
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['session_version'] = $user['session_version'];
            $_SESSION['_impersonated_by_admin_id'] = $current_user_id;
            $_SESSION['_impersonation_start'] = time();
            $_SESSION['_impersonation_timeout'] = time() + (30 * 60);
            
            error_log("IMPERSONATION: Admin/Owner {$current_user_id} ({$current_user_role}) hat sich als Benutzer {$user_id} ({$user['username']}) angemeldet.");
            
            set_flash_message('success_impersonation_started', 'success');
            redirect($current_language . '/dashboard');
        }
    }
}

// Fetch stats
$stats_stmt = $conn->prepare("SELECT COUNT(id) as file_count, SUM(size) as total_size FROM files WHERE uploader_id = ? AND deleted = 0");
$stats_stmt->bind_param('i', $user_id);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result ? $stats_result->fetch_assoc() : ['file_count' => 0, 'total_size' => 0];
$stats_stmt->close();

function format_size($bytes) {
    if (!$bytes) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Fetch webauthn credentials
$webauthn_keys = [];
$wa_stmt = $conn->prepare("SELECT id, name, created_at, last_used FROM webauthn_credentials WHERE user_id = ?");
$wa_stmt->bind_param('i', $user_id);
$wa_stmt->execute();
$wa_result = $wa_stmt->get_result();
if ($wa_result) {
    while ($row = $wa_result->fetch_assoc()) {
        $webauthn_keys[] = $row;
    }
}
$wa_stmt->close();

// Seite ausgeben
require_once __DIR__ . '/../includes/header.php';
?>

<h1>Benutzer-Info: <?php echo htmlspecialchars($user['username']); ?></h1>

<!-- Allgemein & Statistiken -->
<div class="card">
    <h2>Übersicht & Statistik</h2>
    <div style="display: flex; flex-wrap: wrap; gap: 20px;">
        <div style="flex: 1; min-width: 250px;">
            <p><strong>ID:</strong> <?php echo $user['id']; ?></p>
            <p><strong>Registriert am:</strong> <?php echo $user['created_at']; ?></p>
            <p><strong>Letzter Login:</strong> <?php echo $user['last_login'] ? $user['last_login'] : 'Nie'; ?></p>
        </div>
        <div style="flex: 1; min-width: 250px;">
            <p><strong>Hochgeladene Dateien:</strong> <?php echo number_format((float)$stats['file_count']); ?></p>
            <p><strong>Speicherplatz belegt:</strong> <?php echo format_size($stats['total_size']); ?></p>
        </div>
    </div>
</div>

<!-- Account Status -->
<div class="card">
    <h2>Account-Status</h2>
    <div style="margin-bottom: 15px;">
        <?php if ($user['deleted']): ?>
            <p style="color: #ff4d4f;"><strong>Status: Gelöscht (Papierkorb)</strong></p>
            <p>Dieser Account ist zur Löschung markiert.</p>
            <p>Wiederherstellbar bis: <strong><?php echo $user['deletion_recovery_until']; ?></strong></p>
            <form method="post" action="user_info">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                <button type="submit" name="restore_user" class="button button-primary">Account sofort wiederherstellen</button>
            </form>
        <?php else: ?>
            <p><strong>Status:</strong> <?php echo $user['is_active'] ? '<span style="color:green;">Aktiv</span>' : '<span style="color:red;">Gesperrt</span>'; ?></p>
            <form method="post" action="user_info">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                <button type="submit" name="toggle_active" class="button <?php echo $user['is_active'] ? 'button-danger' : 'button-primary'; ?>">
                    <?php echo $user['is_active'] ? 'Account sperren' : 'Account entsperren'; ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Profil bearbeiten -->
<div class="card">
    <h2>Profil bearbeiten</h2>
    <form method="post" action="user_info">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

        <label for="username"><?php echo lang('label_username'); ?></label>
        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <label for="email"><?php echo lang('label_email'); ?></label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">

        <label for="role"><?php echo lang('label_role'); ?></label>
        <select name="role" id="role">
            <?php
            foreach ($role_hierarchy as $role_name => $lvl) {
                if (!has_higher_role($current_role_lower, $role_name)) continue;
                $sel = ($role_name === strtolower($user['role'])) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($role_name) . '" ' . $sel . '>' . htmlspecialchars(lang('role_' . $role_name)) . '</option>';
            }
            ?>
        </select>

        <?php if ($has_unlimited_column): ?>
            <label><input type="checkbox" name="unlimited_upload" <?php echo !empty($user['unlimited_upload']) ? 'checked' : ''; ?>> <?php echo lang('label_unlimited_upload'); ?></label>
        <?php endif; ?>

        <button type="submit" name="save_user" class="button button-primary"><?php echo lang('button_save_changes'); ?></button>
    </form>
</div>

<!-- Sicherheit & Zugriff -->
<div class="card">
    <h2>Sicherheit & Zugriff</h2>
    
    <div style="margin-bottom: 20px;">
        <h3>Sitzungen verwalten</h3>
        <form method="post" action="user_info">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <button type="submit" name="force_logout" class="button button-danger" onclick="return confirm('Soll dieser Benutzer auf allen Geräten ausgeloggt werden?');">Force Logout (Alle Sitzungen beenden)</button>
        </form>
    </div>
    
    <hr>
    
    <div style="margin-bottom: 20px;">
        <h3>Zwei-Faktor-Authentifizierung (2FA)</h3>
        <p>Status: <strong><?php echo $user['two_factor_enabled'] ? 'Aktiviert (' . htmlspecialchars($user['two_factor_method']) . ')' : 'Deaktiviert'; ?></strong></p>
        <?php if ($user['two_factor_enabled']): ?>
            <form method="post" action="user_info">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                <button type="submit" name="disable_2fa" class="button button-danger" onclick="return confirm('2FA wirklich deaktivieren?');"><?php echo lang('button_disable_2fa'); ?></button>
            </form>
        <?php endif; ?>
    </div>
    
    <hr>
    
    <div style="margin-bottom: 20px;">
        <h3>WebAuthn (Passkeys / Security Keys)</h3>
        <?php if (empty($webauthn_keys)): ?>
            <p>Keine Schlüssel registriert.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="border-bottom: 1px solid #ddd; text-align: left;">
                        <th style="padding: 8px;">Name</th>
                        <th style="padding: 8px;">Hinzugefügt am</th>
                        <th style="padding: 8px;">Letzte Nutzung</th>
                        <th style="padding: 8px;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($webauthn_keys as $key): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 8px;"><?php echo htmlspecialchars($key['name']); ?></td>
                        <td style="padding: 8px;"><?php echo $key['created_at']; ?></td>
                        <td style="padding: 8px;"><?php echo $key['last_used'] ? $key['last_used'] : 'Nie'; ?></td>
                        <td style="padding: 8px;">
                            <form method="post" action="user_info" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="credential_id" value="<?php echo $key['id']; ?>">
                                <button type="submit" name="delete_webauthn" class="action-button delete-button" onclick="return confirm('Sicherheitsschlüssel wirklich löschen?');" style="background: none; border: none; font-size: 1.2em; cursor: pointer;" title="Löschen">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <hr>

    <div style="margin-bottom: 20px;">
        <h3><?php echo lang('button_change_password'); ?></h3>
        <form method="post" action="user_info">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <label for="new_password"><?php echo lang('label_new_password'); ?></label>
            <input type="password" name="new_password" id="new_password">
            <label for="confirm_password"><?php echo lang('label_confirm_new_password'); ?></label>
            <input type="password" name="confirm_password" id="confirm_password">
            <button type="submit" name="set_password" class="button button-secondary"><?php echo lang('button_change_password'); ?></button>
        </form>
        <br>
        <form method="post" action="user_info">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
            <button type="submit" name="send_reset" class="button button-secondary"><?php echo lang('button_send_reset_code'); ?></button>
        </form>
    </div>
</div>

<div class="card">
    <h2>Support & Diagnosis</h2>
    <form method="post" action="user_info">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        <button type="submit" name="impersonate_user" class="button button-warning" onclick="return confirm('Als <?php echo htmlspecialchars($user['username']); ?> anmelden? (30 Min. Timeout)');"><?php echo lang('button_impersonate_user'); ?></button>
    </form>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
