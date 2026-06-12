<?php
// /de/reset_password.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/twofactor_fallback.php';

// Wenn noch keine reset_user_id existiert, zurück zum Anfang
if (!isset($_SESSION['reset_user_id'])) {
    set_flash_message('error_invalid_token', 'error'); // Generische Meldung
    redirect($current_language . '/forgot_password');
}

$user_id = (int)$_SESSION['reset_user_id'];

$last_sent = isset($_SESSION['last_code_sent_time']) ? (int)$_SESSION['last_code_sent_time'] : 0;
$remaining_cooldown = max(0, 60 - (time() - $last_sent));

// Code erneut senden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_code') {
    validate_csrf_token();
    
    if ($user_id === 0) {
        // Fake-ID Abfang
        set_flash_message('success_temp_code_sent', 'success');
        redirect($current_language . '/reset_password');
    }
    
    if (time() - $last_sent < 60) {
        set_flash_message('error_cooldown_active', 'error');
        redirect($current_language . '/reset_password');
    }
    
    $token = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Alte Tokens invalidieren
    $stmt_mark_all = $conn->prepare("UPDATE user_tokens SET used = 1 WHERE user_id = ? AND type = 'password_temp_code' AND used = 0");
    if ($stmt_mark_all) { $stmt_mark_all->bind_param('i', $user_id); $stmt_mark_all->execute(); $stmt_mark_all->close(); }
    
    if (create_user_token($conn, $user_id, $token, 'password_temp_code', $expires)) {
        $_SESSION['last_code_sent_time'] = time();
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($u && !empty($u['email'])) {
            $subject = 'Temporärer Code für Passwortänderung';
            $body = '<p>Hallo,</p><p>Dein neuer temporärer Code: <strong>' . $token . '</strong></p><p>Der Code ist 1 Stunde gültig.</p><p><a href="' . BASE_URL . '/' . $current_language . '/reset_password">Passwort zurücksetzen</a></p>';
            send_email($u['email'], $subject, $body, true);
        }
        set_flash_message('success_temp_code_sent', 'success');
    } else {
        set_flash_message('error_db_insert', 'error');
    }
    redirect($current_language . '/reset_password');
}

// Schritt 2 verarbeiten: Neues Passwort setzen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_password') {
    validate_csrf_token();
    
    if (empty($_SESSION['reset_verified'])) {
        redirect($current_language . '/reset_password');
    }
    
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';

    if (empty($new_password) || strlen($new_password) < 8) {
        set_flash_message('error_invalid_data', 'error');
        redirect($current_language . '/reset_password');
    }
    if ($new_password !== $confirm_new_password) {
        set_flash_message('error_passwords_dont_match', 'error');
        redirect($current_language . '/reset_password');
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param('si', $new_hash, $user_id);
        $res = $update_stmt->execute();
        $update_stmt->close();
        if ($res) {
            // Wenn Token verwendet wurde, markiere alle als genutzt
            if (isset($_SESSION['reset_method']) && $_SESSION['reset_method'] === 'token') {
                $stmt_mark_all = $conn->prepare("UPDATE user_tokens SET used = 1 WHERE user_id = ? AND type = 'password_temp_code' AND used = 0");
                if ($stmt_mark_all) { $stmt_mark_all->bind_param('i', $user_id); $stmt_mark_all->execute(); $stmt_mark_all->close(); }
            }

            // E-Mail senden
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($u && !empty($u['email'])) {
                $subject = 'Passwort zurückgesetzt';
                $body = '<p>Hallo,</p><p>Dein Passwort wurde zurückgesetzt. Wenn du diese Änderung nicht durchgeführt hast, kontaktiere den Support.</p>';
                send_email($u['email'], $subject, $body, true);
            }
            
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_verified']);
            unset($_SESSION['reset_method']);
            
            set_flash_message('success_password_reset', 'success');
            redirect($current_language . '/login');
        }
    }
    set_flash_message('error_db_update', 'error');
    redirect($current_language . '/reset_password');
}

// Schritt 1 verarbeiten: Code validieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_code') {
    validate_csrf_token();
    
    $method = isset($_POST['reset_method']) ? $_POST['reset_method'] : 'token';
    
    // Fake-ID Abfang: Enumeration verhindern
    if ($user_id === 0) {
        // Tun so, als wäre der Code falsch
        set_flash_message($method === 'backup' ? 'error_invalid_backup_code' : 'error_invalid_token', 'error');
        redirect($current_language . '/reset_password');
    }

    if ($method === 'token') {
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        if (empty($token)) {
            set_flash_message('error_invalid_data', 'error');
            redirect($current_language . '/reset_password');
        }
        $token_row = validate_user_token($conn, $token, 'password_temp_code');
        if (!$token_row || $token_row['user_id'] != $user_id) {
            set_flash_message('error_invalid_token', 'error');
            redirect($current_language . '/reset_password');
        }
        $_SESSION['reset_verified'] = true;
        $_SESSION['reset_method'] = 'token';
        redirect($current_language . '/reset_password');
    } elseif ($method === 'backup') {
        $backup_code = isset($_POST['backup_code']) ? $_POST['backup_code'] : '';
        if (empty($backup_code)) {
            set_flash_message('error_invalid_data', 'error');
            redirect($current_language . '/reset_password');
        }
        // Überprüfen und direkt konsumieren
        if (tf_verify_and_consume_backup_code($conn, $user_id, $backup_code)) {
            $_SESSION['reset_verified'] = true;
            $_SESSION['reset_method'] = 'backup';
            redirect($current_language . '/reset_password');
        } else {
            set_flash_message('error_invalid_backup_code', 'error');
            redirect($current_language . '/reset_password');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <?php if (!empty($_SESSION['reset_verified'])): ?>
        <!-- Schritt 2: Neues Passwort vergeben -->
        <h1>Neues Passwort vergeben</h1>
        <p>Code erfolgreich verifiziert. Bitte gib dein neues Passwort ein.</p>

        <form method="post" action="reset_password">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="set_password">
            
            <label for="new_password">Neues Passwort</label>
            <input type="password" id="new_password" name="new_password" required minlength="8">
            <label for="confirm_new_password">Passwort bestätigen</label>
            <input type="password" id="confirm_new_password" name="confirm_new_password" required minlength="8">
            <button type="submit" class="button">Passwort speichern</button>
        </form>
    <?php else: ?>
        <!-- Schritt 1: Code eingeben -->
        <h1>Code eingeben</h1>
        
        <div id="method-token-view">
            <p>Wir haben dir einen temporären Code gesendet (sofern eine E-Mail-Adresse hinterlegt war). Bitte gib ihn hier ein.</p>
            <form method="post" action="reset_password">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="verify_code">
                <input type="hidden" name="reset_method" value="token">
                <label for="token">Temporärer Code</label>
                <input type="text" id="token" name="token" required pattern="\d{6}">
                <button type="submit" class="button">Bestätigen</button>
            </form>
            
            <form method="post" action="reset_password" style="margin-top: 10px;">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="resend_code">
                <button type="submit" id="resend-button" class="button button-secondary" <?php echo $remaining_cooldown > 0 ? 'disabled' : ''; ?>>
                    Code erneut senden <?php echo $remaining_cooldown > 0 ? '(' . $remaining_cooldown . 's)' : ''; ?>
                </button>
            </form>

            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    let remaining = <?php echo $remaining_cooldown; ?>;
                    const btn = document.getElementById("resend-button");
                    if (remaining > 0 && btn) {
                        const interval = setInterval(function() {
                            remaining--;
                            if (remaining <= 0) {
                                clearInterval(interval);
                                btn.disabled = false;
                                btn.innerText = "Code erneut senden";
                            } else {
                                btn.innerText = "Code erneut senden (" + remaining + "s)";
                            }
                        }, 1000);
                    }
                });
            </script>

            <p style="margin-top: 15px; font-size: 0.9em; text-align: center;">
                <a href="#" onclick="document.getElementById('method-token-view').style.display='none'; document.getElementById('method-backup-view').style.display='block'; return false;">Backup-Code nutzen</a>
            </p>
        </div>

        <div id="method-backup-view" style="display: none;">
            <p>Gib deinen 2FA Backup-Code ein, um das Passwort zurückzusetzen.</p>
            <form method="post" action="reset_password">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="verify_code">
                <input type="hidden" name="reset_method" value="backup">
                <label for="backup_code">Backup-Code</label>
                <input type="text" id="backup_code" name="backup_code" required>
                <button type="submit" class="button">Bestätigen</button>
            </form>
            <p style="margin-top: 15px; font-size: 0.9em; text-align: center;">
                <a href="#" onclick="document.getElementById('method-backup-view').style.display='none'; document.getElementById('method-token-view').style.display='block'; return false;">E-Mail Token nutzen</a>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>