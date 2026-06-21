<?php
// /de/2fa_challenge.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

// Nur zugänglich, wenn eine 2FA-Pending-Session existiert
if (empty($_SESSION['2fa_pending']) || empty($_SESSION['2fa_user_id'])) {
    redirect($current_language . '/login');
}

$user_id = (int)$_SESSION['2fa_user_id'];
$method = isset($_SESSION['2fa_method']) ? $_SESSION['2fa_method'] : null;
$error = null;
$info = null;

// Flash-Nachricht holen (wird im HTML angezeigt)
$flash_data = get_flash_message();
$flash_type = $flash_data ? $flash_data['type'] : null;
$flash_key = $flash_data ? $flash_data['key'] : null;
$flash_args = $flash_data ? $flash_data['args'] : [];

// Cooldown-Status für JavaScript
$email_cooldown_remaining = 0;
if (in_array($method, ['email', 'both'])) {
    $email_cooldown_remaining = check_token_cooldown($conn, $user_id, 'two_factor_email', 60);
}

// Lade TOTP-Secret
$u = null;
if (in_array($method, ['totp', 'both'])) {
    $stmt = $conn->prepare("SELECT two_factor_secret FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    // Resend email code
    if (!empty($_POST['resend_email']) && in_array($method, ['email', 'both'])) {
        // Prüfe Cooldown (60 Sekunden zwischen Email-Versand)
        $cooldown_remaining = check_token_cooldown($conn, $user_id, 'two_factor_email', 60);
        if ($cooldown_remaining > 0) {
            set_flash_message('error_2fa_email_cooldown', 'error', ['seconds' => $cooldown_remaining]);
            redirect($current_language . '/2fa_challenge');
        }
        
        $token = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        if (create_user_token($conn, $user_id, $token, 'two_factor_email', $expires)) {
            // E-Mail-Adresse holen
            $stmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1"); $stmt->bind_param('i', $user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($u && !empty($u['email'])) {
                $subject = 'Dein 2FA-Code';
                $body = '<p>Hallo ' . htmlspecialchars($u['username']) . ',</p><p>Dein 2FA‑Code lautet: <strong>' . htmlspecialchars($token) . '</strong></p><p>Der Code ist 10 Minuten gültig.</p>';
                send_email($u['email'], $subject, $body, true);
                set_flash_message('success_2fa_email_resent','success');
            }
        }
        redirect($current_language . '/2fa_challenge');
    }

    $code = isset($_POST['code']) ? trim($_POST['code']) : '';
    $backup = isset($_POST['backup_code']) ? trim($_POST['backup_code']) : '';

    // Wenn Backup-Code vorhanden, versuche diesen
    if (!empty($backup)) {
        if (function_exists('tf_verify_and_consume_backup_code') && tf_verify_and_consume_backup_code($conn, $user_id, $backup)) {
            // Erfolgreich — finalize login
            session_regenerate_id(true);
            // Lade user data
            $stmt = $conn->prepare("SELECT id, username, role, session_version FROM users WHERE id = ? LIMIT 1"); $stmt->bind_param('i', $user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($u) {
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['username'] = $u['username'];
                $_SESSION['role'] = $u['role'];
                $_SESSION['session_version'] = isset($u['session_version']) ? (int)$u['session_version'] : 0;
                
                $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . (int)$user_id);
                // Remove 2FA pending
                unset($_SESSION['2fa_pending'], $_SESSION['2fa_user_id'], $_SESSION['2fa_method'], $_SESSION['2fa_created']);
                set_flash_message('success_2fa_verified','success');
                redirect($current_language . '/dashboard');
            }
        } else {
            // Backup-Code nicht verfügbar oder ungültig
            set_flash_message('error_2fa_invalid','error');
            redirect($current_language . '/2fa_challenge');
        }
    }

    // Wenn Code eingegeben wurde: prüfe TOTP und/oder E‑Mail
    if (!empty($code)) {
        // Prüfe E‑Mail-Code
        $token_row = validate_user_token($conn, $code, 'two_factor_email');
        if ($token_row && $token_row['user_id'] == $user_id) {
            // mark used
            mark_user_token_used($conn, $token_row['id']);
            // finalize
            session_regenerate_id(true);
            $stmt = $conn->prepare("SELECT id, username, role, session_version FROM users WHERE id = ? LIMIT 1"); $stmt->bind_param('i', $user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($u) {
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['username'] = $u['username'];
                $_SESSION['role'] = $u['role'];
                $_SESSION['session_version'] = isset($u['session_version']) ? (int)$u['session_version'] : 0;
                
                $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . (int)$user_id);
                unset($_SESSION['2fa_pending'], $_SESSION['2fa_user_id'], $_SESSION['2fa_method'], $_SESSION['2fa_created']);
                set_flash_message('success_2fa_verified','success');
                redirect($current_language . '/dashboard');
            }
        }

        // Prüfe TOTP (nur wenn TOTP-Funktionen verfügbar sind)
        // hole secret
        $stmt = $conn->prepare("SELECT two_factor_secret, username, email FROM users WHERE id = ? LIMIT 1"); $stmt->bind_param('i', $user_id); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($u && !empty($u['two_factor_secret']) && function_exists('tf_verify_code')) {
            $totp_valid = tf_verify_code($u['two_factor_secret'], $code, 5);
            if ($totp_valid) {
                // finalize login
                session_regenerate_id(true);
                $stmt2 = $conn->prepare("SELECT id, username, role, session_version FROM users WHERE id = ? LIMIT 1"); $stmt2->bind_param('i', $user_id); $stmt2->execute(); $u2 = $stmt2->get_result()->fetch_assoc(); $stmt2->close();
                if ($u2) {
                    $_SESSION['user_id'] = $u2['id'];
                    $_SESSION['username'] = $u2['username'];
                    $_SESSION['role'] = $u2['role'];
                    $_SESSION['session_version'] = isset($u2['session_version']) ? (int)$u2['session_version'] : 0;
                    
                    $conn->query("UPDATE users SET last_login = NOW() WHERE id = " . (int)$user_id);
                    unset($_SESSION['2fa_pending'], $_SESSION['2fa_user_id'], $_SESSION['2fa_method'], $_SESSION['2fa_created']);
                    set_flash_message('success_2fa_verified','success');
                    redirect($current_language . '/dashboard');
                }
            }
        }

        // Wenn wir hier sind: kein gültiger Code
        set_flash_message('error_2fa_invalid','error');
        redirect($current_language . '/2fa_challenge');
    }

    // Keine gültigen Daten
    set_flash_message('error_invalid_data','error');
    redirect($current_language . '/2fa_challenge');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card auth-card">
    <div class="auth-header">
        <div class="auth-icon">🔐</div>
        <h1>2‑Faktor‑Authentifizierung</h1>
    </div>
    
    <?php if ($flash_data): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flash_type); ?>">
            <?php 
                // Übersetze die Flash-Message
                echo lang($flash_key, ...$flash_args);
            ?>
        </div>
    <?php endif; ?>

    <p>Um deine Sicherheit zu gewährleisten, gib bitte einen deiner 2FA-Codes ein:</p>

    <div class="auth-instructions">
        <?php if (in_array($method, ['totp', 'both'])): ?>
            <div class="instruction-item">
                <strong>📱 TOTP-Code aus App:</strong> Öffne deine Authenticator-App (z.B. Google Authenticator) und gib den 6-stelligen Code ein.
            </div>
        <?php endif; ?>
        <?php if (in_array($method, ['email', 'both'])): ?>
            <div class="instruction-item">
                <strong>📧 E-Mail-Code:</strong> Überprüfe dein E-Mail-Postfach und gib den 6-stelligen Code ein, den wir dir geschickt haben.
            </div>
        <?php endif; ?>
        <div class="instruction-item">
            <strong>🔑 Backup-Code:</strong> Falls du keinen Zugriff auf deine App oder E-Mail hast, verwende einen deiner gespeicherten Backup-Codes.
        </div>
    </div>

    <form method="post" action="2fa_challenge">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <div class="form-group">
            <label for="code">Code (6-stellig)</label>
            <input type="tel" id="code" name="code" pattern="\d{6}" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" maxlength="6">
        </div>
        <div class="form-group">
            <label for="backup_code">Oder Backup-Code</label>
            <input type="text" id="backup_code" name="backup_code" placeholder="XXXX-XXXX-XXXX">
        </div>
        <button type="submit" class="button button-primary">Anmelden</button>
    </form>

    <?php if (in_array($method, ['email', 'both'])): ?>
    <form method="post" action="2fa_challenge" style="margin-top:20px;" id="resend_form">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="resend_email" value="1">
        <button type="submit" class="button button-secondary" id="resend_btn" 
                <?php if ($email_cooldown_remaining > 0): ?>disabled<?php endif; ?>>
            📧 Neuen E-Mail-Code senden<?php if ($email_cooldown_remaining > 0): ?> (<span id="cooldown_timer"><?php echo $email_cooldown_remaining; ?></span>s)<?php endif; ?>
        </button>
    </form>
    <?php endif; ?>

    <p style="margin-top: 20px; font-size: 0.9em; color: var(--text-secondary);">
        <strong>Hinweis:</strong> Backup-Codes können nur einmal verwendet werden. Bewahre sie sicher auf!
        <br>Falls du weiterhin Probleme hast, kontaktiere den Support.
    </p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Cooldown-Timer für E-Mail-Resend
(function() {
    const cooldownRemaining = <?php echo (int)$email_cooldown_remaining; ?>;
    const resendBtn = document.getElementById('resend_btn');
    const cooldownTimer = document.getElementById('cooldown_timer');
    const resendForm = document.getElementById('resend_form');
    
    if (cooldownRemaining > 0 && resendBtn) {
        let remaining = cooldownRemaining;
        
        // Funktion zum Aktualisieren des Timers
        function updateTimer() {
            remaining--;
            if (cooldownTimer) {
                cooldownTimer.textContent = remaining;
            }
            
            if (remaining <= 0) {
                // Cooldown abgelaufen - Button aktivieren
                resendBtn.disabled = false;
                // "s" und Timer aus Button entfernen
                resendBtn.innerHTML = '📧 Neuen E-Mail-Code senden';
                clearInterval(timerInterval);
            }
        }
        
        // Timer alle 1 Sekunde aktualisieren
        const timerInterval = setInterval(updateTimer, 1000);
    }
})();
</script>
