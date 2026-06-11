<?php
// /de/edit_user.php

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

// lade Benutzer (nur unlimited_upload einbeziehen, falls die Spalte existiert)
$select_fields = "id, username, email, role";
$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'unlimited_upload'");
if ($col_check && $col_check->num_rows > 0) {
    $select_fields .= ", unlimited_upload";
}
$stmt = $conn->prepare("SELECT $select_fields FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
// stelle sicher, dass der Schlüssel definiert ist
if (!array_key_exists('unlimited_upload', $user)) {
    $user['unlimited_upload'] = 0;
}

if (!$user) {
    set_flash_message('error_user_not_found', 'error');
    redirect($current_language . '/all_users');
}


$current_role_lower = strtolower($current_user_role);
$target_role_lower  = strtolower($user['role']);

// prüfen, ob die Tabelle die Option unlimited_upload enthält
$has_unlimited_column = false;
$col_check2 = $conn->query("SHOW COLUMNS FROM users LIKE 'unlimited_upload'");
if ($col_check2 && $col_check2->num_rows > 0) {
    $has_unlimited_column = true;
}

// zugriffsberechtigung: man muss eine höhere Rolle als das Ziel haben
if (!has_higher_role($current_role_lower, $target_role_lower)) {
    set_flash_message('error_no_permission', 'error');
    redirect($current_language . '/all_users');
}

// Formularauswertung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    // während der POST-Anfrage können wir ggf. aktualisierte Daten zurückschreiben
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
            // untersuche Duplikate für username/email
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
                        // führe Update durch
                        if ($has_unlimited_column) {
                            $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, unlimited_upload = ? WHERE id = ?");
                            $update_stmt->bind_param('sssii', $new_username, $new_email, $new_role, $new_unlimited, $user_id);
                        } else {
                            $update_stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                            $update_stmt->bind_param('sssi', $new_username, $new_email, $new_role, $user_id);
                        }
                        if ($update_stmt->execute()) {
                            set_flash_message('success_role_changed', 'success'); // generische Nachricht
                            // neu laden für Anzeige
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
                    // leere Email erlaubt
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
                // evtl. E-Mail schicken
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

    // Temporäres Passwort per E-Mail auslösen
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

    // 2FA deaktivieren
    if (isset($_POST['disable_2fa'])) {
        $fa_stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_method = NULL, two_factor_secret = NULL, two_factor_backup_codes = NULL WHERE id = ?");
        $fa_stmt->bind_param('i', $user_id);
        if ($fa_stmt->execute()) {
            set_flash_message('success_2fa_disabled', 'success');
            // Neu laden des Benutzers
            $reload_stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1");
            $reload_stmt->bind_param('i', $user_id);
            $reload_stmt->execute();
            $user = $reload_stmt->get_result()->fetch_assoc();
            if (!array_key_exists('unlimited_upload', $user)) {
                $user['unlimited_upload'] = 0;
            }
            $reload_stmt->close();
        } else {
            set_flash_message('error_db_update', 'error');
        }
        $fa_stmt->close();
    }

    // Impersonation (als Benutzer anmelden)
    if (isset($_POST['impersonate_user'])) {
        // Sicherheit: nur Owner/Admin dürfen dies tun
        if ($current_role_lower !== 'owner' && $current_role_lower !== 'admin') {
            set_flash_message('error_no_permission', 'error');
        } else {
            // Erstelle Session für Impersonation
            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['_impersonated_by_admin_id'] = $current_user_id;
            $_SESSION['_impersonation_start'] = time();
            $_SESSION['_impersonation_timeout'] = time() + (30 * 60); // 30 Minuten
            
            // Log für Audit-Trail
            error_log("IMPERSONATION: Admin/Owner {$current_user_id} ({$current_user_role}) hat sich als Benutzer {$user_id} ({$user['username']}) angemeldet.");
            
            set_flash_message('success_impersonation_started', 'success');
            redirect($current_language . '/dashboard');
        }
    }
}

// Seite ausgeben
require_once __DIR__ . '/../includes/header.php';
?>

<h1><?php echo lang('title_edit_user'); ?></h1>

<div class="card">
    <form method="post" action="edit_user">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">

        <label for="username"><?php echo lang('label_username'); ?></label>
        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <label for="email"><?php echo lang('label_email'); ?></label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>">

        <label for="role"><?php echo lang('label_role'); ?></label>
        <select name="role" id="role">
            <?php
            foreach ($role_hierarchy as $role_name => $lvl) {
                // zeige nur Rollen die kleiner als aktuelle Benutzerrolle sind
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

<div class="card">
    <h2><?php echo lang('button_change_password'); ?></h2>
    <form method="post" action="edit_user">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        <label for="new_password"><?php echo lang('label_new_password'); ?></label>
        <input type="password" name="new_password" id="new_password">
        <label for="confirm_password"><?php echo lang('label_confirm_new_password'); ?></label>
        <input type="password" name="confirm_password" id="confirm_password">
        <button type="submit" name="set_password" class="button button-secondary"><?php echo lang('button_change_password'); ?></button>
    </form>
    <hr>
    <form method="post" action="edit_user" style="margin-top:10px;">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        <button type="submit" name="send_reset" class="button button-secondary"><?php echo lang('button_send_reset_code'); ?></button>
    </form>
</div>

<div class="card">
    <h2>Sicherheit</h2>
    <form method="post" action="edit_user">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        <button type="submit" name="disable_2fa" class="button button-danger" onclick="return confirm('2FA f\u00fcr diesen Benutzer wirklich deaktivieren?');"><?php echo lang('button_disable_2fa'); ?></button>
    </form>
</div>

<div class="card">
    <h2>Support & Diagnosis</h2>
    <form method="post" action="edit_user">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
        <button type="submit" name="impersonate_user" class="button button-warning" onclick="return confirm('Als <?php echo htmlspecialchars($user['username']); ?> anmelden? (30 Min. Timeout)');"><?php echo lang('button_impersonate_user'); ?></button>
    </form>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>