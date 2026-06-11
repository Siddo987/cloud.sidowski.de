<?php
// /de/profil.php

$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

// Login prüfen
if (!$is_logged_in) {
    redirect($current_language . '/login');
}

// Hilfsfunktionen
function handle_post_action($conn, $current_user_id, &$enabled_2fa, &$method_2fa, &$secret_2fa, &$backup_plain_2fa, &$show_verification, &$totp_url) {
    global $current_language, $current_username;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    validate_csrf_token();
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';

    switch ($action) {
        case 'change_password':
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
            $confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';

            if (empty($new_password) || strlen($new_password) < 8) {
                set_flash_message('error_password_too_short', 'error');
                return;
            }
            if ($new_password !== $confirm_new_password) {
                set_flash_message('error_passwords_dont_match', 'error');
                return;
            }

            $stmt = $conn->prepare("SELECT password, email FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $current_user_id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$u || !password_verify($current_password, $u['password'])) {
                set_flash_message('error_wrong_password', 'error');
                return;
            }

            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?");
            $update_stmt->bind_param('si', $new_hash, $current_user_id);
            if ($update_stmt->execute()) {
                $_SESSION['session_version'] = $_SESSION['session_version'] + 1;
                if (!empty($u['email'])) {
                    $subject = 'Passwort geändert';
                    $body = '<p>Dein Passwort wurde geändert.</p>';
                    send_email($u['email'], $subject, $body, true);
                }
                set_flash_message('success_password_changed', 'success');
            } else {
                set_flash_message('error_db_update: ' . $update_stmt->error, 'error');
            }
            $update_stmt->close();
            break;

        case 'change_username':
            $new_username = trim(isset($_POST['new_username']) ? $_POST['new_username'] : '');
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';

            if (empty($new_username) || strlen($new_username) < 3) {
                set_flash_message('error_username_too_short', 'error');
                return;
            }

            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $current_user_id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$u || !password_verify($current_password, $u['password'])) {
                set_flash_message('error_wrong_password', 'error');
                return;
            }

            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $check_stmt->bind_param('si', $new_username, $current_user_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();

            if ($exists) {
                set_flash_message('error_username_taken', 'error');
                return;
            }

            $update_stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $update_stmt->bind_param('si', $new_username, $current_user_id);
            if ($update_stmt->execute()) {
                $_SESSION['username'] = $new_username;
                set_flash_message('success_username_changed', 'success');
            } else {
                set_flash_message('error_db_update: ' . $update_stmt->error, 'error');
            }
            $update_stmt->close();
            break;

        case 'change_email':
            $new_email = trim(isset($_POST['new_email']) ? $_POST['new_email'] : '');
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';

            if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                set_flash_message('error_invalid_email', 'error');
                return;
            }

            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $current_user_id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$u || !password_verify($current_password, $u['password'])) {
                set_flash_message('error_wrong_password', 'error');
                return;
            }

            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->bind_param('si', $new_email, $current_user_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();

            if ($exists) {
                set_flash_message('error_email_taken', 'error');
                return;
            }

            $token = generate_random_token(32);
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            if (create_user_token($conn, $current_user_id, $token, 'email_change', $expires, $new_email)) {
                $subject = 'E-Mail-Änderung bestätigen';
                $body = '<p>Klicke <a href="' . BASE_URL . '/' . $current_language . '/actions/verify_email_change?token=' . $token . '">hier</a> um die E-Mail-Änderung zu bestätigen.</p>';
                if (send_email($new_email, $subject, $body, true)) {
                    set_flash_message('success_email_change_requested', 'success');
                } else {
                    set_flash_message('error_email_send_failed', 'error');
                }
            } else {
                set_flash_message('error_db_insert: ' . $conn->error, 'error');
            }
            break;

        case 'remove_email':
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';

            $stmt = $conn->prepare("SELECT password, email FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $current_user_id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$u || !password_verify($current_password, $u['password'])) {
                set_flash_message('error_wrong_password', 'error');
                return;
            }

            $old_email = $u['email'];
            $update_stmt = $conn->prepare("UPDATE users SET email = NULL, email_verified = 0 WHERE id = ?");
            $update_stmt->bind_param('i', $current_user_id);
            if ($update_stmt->execute()) {
                if (!empty($old_email)) {
                    $subject = 'E-Mail entfernt';
                    $body = '<p>Deine E-Mail wurde entfernt.</p>';
                    send_email($old_email, $subject, $body, true);
                }
                // Deaktiviere 2FA wenn email-basiert
                if ($enabled_2fa && in_array($method_2fa, ['email', 'both'])) {
                    $update_2fa = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_method = NULL, two_factor_secret = NULL WHERE id = ?");
                    $update_2fa->bind_param('i', $current_user_id);
                    $update_2fa->execute();
                    $update_2fa->close();
                    $enabled_2fa = false;
                    $method_2fa = null;
                    $secret_2fa = null;
                    set_flash_message('info_2fa_disabled', 'info');
                }
                set_flash_message('success_email_removed', 'success');
            } else {
                set_flash_message('error_db_update: ' . $update_stmt->error, 'error');
            }
            $update_stmt->close();
            break;

        case 'setup_2fa':
            $method = isset($_POST['method']) ? $_POST['method'] : '';
            $code = isset($_POST['code']) ? trim($_POST['code']) : '';
            
            if (!in_array($method, ['email', 'totp'])) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => lang('error_invalid_action')]);
                    exit;
                }
                set_flash_message('error_invalid_action', 'error');
                return;
            }

            if (!empty($code)) {
                // Verifiziere den Code
                $valid = false;
                $secret = null;

                if ($method === 'totp') {
                    // Get secret from session
                    $secret = isset($_SESSION['pending_totp_secret']) ? $_SESSION['pending_totp_secret'] : null;
                    if (!$secret) {
                        if ($is_ajax) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => lang('error_totp_expired')]);
                            exit;
                        }
                        set_flash_message('error_totp_expired', 'error');
                        return;
                    }
                    if (function_exists('tf_verify_code')) {
                        $valid = tf_verify_code($secret, $code, 5);
                    }
                } elseif ($method === 'email') {
                    $token_row = validate_user_token($conn, $code, 'two_factor_email');
                    $valid = $token_row && $token_row['user_id'] == $current_user_id;
                    if ($valid) {
                        mark_user_token_used($conn, $token_row['id']);
                    }
                }

                if ($valid) {
                    // Aktiviere 2FA und speichere die neue Methode/Secret
                    if ($method === 'totp' && $secret) {
                        $update_stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_method = ?, two_factor_secret = ? WHERE id = ?");
                        $update_stmt->bind_param('ssi', $method, $secret, $current_user_id);
                        if ($update_stmt->execute()) {
                            $enabled_2fa = true;
                            $method_2fa = $method;
                            $secret_2fa = $secret;
                            unset($_SESSION['pending_totp_secret']);
                            if ($is_ajax) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => true, 'message' => lang('success_2fa_enabled')]);
                                exit;
                            }
                            set_flash_message('success_2fa_enabled', 'success');
                        } else {
                            if ($is_ajax) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => false, 'message' => 'DB Error: ' . $update_stmt->error]);
                                exit;
                            }
                            set_flash_message('error_db_update', 'error');
                        }
                        $update_stmt->close();
                    } elseif ($method === 'email') {
                        // Generate backup codes if not already done
                        $stmt = $conn->prepare("SELECT two_factor_backup_codes FROM users WHERE id = ?");
                        $stmt->bind_param('i', $current_user_id);
                        $stmt->execute();
                        $existing = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        $backup_json = isset($existing['two_factor_backup_codes']) ? $existing['two_factor_backup_codes'] : null;
                        if (!$backup_json) {
                            $bc = tf_generate_backup_codes(8);
                            $backup_json = isset($bc['json']) ? $bc['json'] : $bc['hashes'];
                        }
                        
                        $update_stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 1, two_factor_method = ?, two_factor_backup_codes = ? WHERE id = ?");
                        $update_stmt->bind_param('ssi', $method, $backup_json, $current_user_id);
                        if ($update_stmt->execute()) {
                            $enabled_2fa = true;
                            $method_2fa = $method;
                            if ($is_ajax) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => true, 'message' => lang('success_2fa_enabled')]);
                                exit;
                            }
                            set_flash_message('success_2fa_enabled', 'success');
                        } else {
                            if ($is_ajax) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => false, 'message' => 'DB Error: ' . $update_stmt->error]);
                                exit;
                            }
                            set_flash_message('error_db_update', 'error');
                        }
                        $update_stmt->close();
                    }
                } else {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => lang('error_2fa_invalid')]);
                        exit;
                    }
                    set_flash_message('error_2fa_invalid', 'error');
                }
            } else {
                // Setup ohne Code: Speichere Secret und Backup, aber aktiviere nicht
                // Für Email: Sende E-Mail-Code
                if ($method === 'email') {
                    $token = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    if (create_user_token($conn, $current_user_id, $token, 'two_factor_email', $expires)) {
                        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
                        $stmt->bind_param('i', $current_user_id);
                        $stmt->execute();
                        $u = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        if ($u && !empty($u['email'])) {
                            $subject = '2FA Setup Code';
                            $body = '<p>Dein 2FA Setup Code: <strong>' . htmlspecialchars($token) . '</strong></p><p>Gültig für 10 Minuten.</p>';
                            send_email($u['email'], $subject, $body, true);
                            if ($is_ajax) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => true, 'message' => 'Code wurde versendet! Überprüfe dein E-Mail-Postfach.']);
                                exit;
                            }
                            set_flash_message('info_2fa_setup_code_sent', 'info');
                        }
                    }
                }
            }
            break;

        case 'disable_2fa':
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';

            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $current_user_id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$u || !password_verify($current_password, $u['password'])) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => lang('error_wrong_password')]);
                    exit;
                } else {
                    set_flash_message('error_wrong_password', 'error');
                    return;
                }
            }

            $update_stmt = $conn->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_method = NULL, two_factor_secret = NULL, two_factor_backup_codes = NULL WHERE id = ?");
            $update_stmt->bind_param('i', $current_user_id);
            if ($update_stmt->execute()) {
                $enabled_2fa = false;
                $method_2fa = null;
                $secret_2fa = null;
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => lang('success_2fa_disabled')]);
                    exit;
                } else {
                    set_flash_message('success_2fa_disabled', 'success');
                }
            } else {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $update_stmt->error]);
                    exit;
                } else {
                    set_flash_message('error_db_update: ' . $update_stmt->error, 'error');
                }
            }
            $update_stmt->close();
            break;

        case 'change_2fa_method':
            $new_method = isset($_POST['new_method']) ? $_POST['new_method'] : '';
            if (!in_array($new_method, ['email', 'totp']) || !$enabled_2fa || $method_2fa === $new_method) {
                set_flash_message('error_invalid_action', 'error');
                return;
            }
            // Verifiziere mittels aktuellem Passwort (einfachere UX)
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $current_user_id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$u || !password_verify($current_password, $u['password'])) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => lang('error_wrong_password')]);
                    exit;
                } else {
                    set_flash_message('error_wrong_password', 'error');
                    return;
                }
            }

            $new_secret = ($new_method === 'totp') ? tf_generate_secret() : null;
            $update_stmt = $conn->prepare("UPDATE users SET two_factor_method = ?, two_factor_secret = ? WHERE id = ?");
            $update_stmt->bind_param('ssi', $new_method, $new_secret, $current_user_id);
            if ($update_stmt->execute()) {
                $method_2fa = $new_method;
                $secret_2fa = $new_secret;
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => lang('success_2fa_method_changed')]);
                    exit;
                } else {
                    set_flash_message('success_2fa_method_changed', 'success');
                }
            } else {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $update_stmt->error]);
                    exit;
                } else {
                    set_flash_message('error_db_update: ' . $update_stmt->error, 'error');
                }
            }
            $update_stmt->close();
            break;

        case 'backup_2fa':
            $backup_action = isset($_POST['backup_action']) ? $_POST['backup_action'] : '';
            $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';

            // Passwort-Authentifizierung
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $current_user_id);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$u || !password_verify($current_password, $u['password'])) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Falsches Passwort']);
                    exit;
                } else {
                    set_flash_message('error_wrong_password', 'error');
                    return;
                }
            }

            if ($backup_action === 'regen') {
                // Neue Backup-Codes generieren
                $bc = tf_generate_backup_codes(8);
                $backup_json = isset($bc['json']) ? $bc['json'] : (isset($bc['hashes']) ? $bc['hashes'] : json_encode($bc));
                
                $update_stmt = $conn->prepare("UPDATE users SET two_factor_backup_codes = ? WHERE id = ?");
                $update_stmt->bind_param('si', $backup_json, $current_user_id);
                
                if ($update_stmt->execute()) {
                    $update_stmt->close();
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => 'Backup-Codes erfolgreich regeneriert']);
                        exit;
                    } else {
                        set_flash_message('success_backup_regenerated', 'success');
                    }
                } else {
                    $update_stmt->close();
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Fehler beim Regenerieren: ' . $conn->error]);
                        exit;
                    } else {
                        set_flash_message('error_db_update', 'error');
                    }
                }
                
            } elseif ($backup_action === 'download') {
                // Backup-Codes validieren
                $stmt_check = $conn->prepare("SELECT two_factor_backup_codes FROM users WHERE id = ? LIMIT 1");
                $stmt_check->bind_param('i', $current_user_id);
                $stmt_check->execute();
                $u_check = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();
                
                if (!$u_check || empty($u_check['two_factor_backup_codes'])) {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Keine Backup-Codes vorhanden']);
                        exit;
                    }
                    set_flash_message('error_no_backup_codes', 'error');
                    return;
                }
                
                $data = json_decode($u_check['two_factor_backup_codes'], true);
                if (!is_array($data)) {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Backup-Codes sind beschädigt. Bitte regenerieren.']);
                        exit;
                    }
                    set_flash_message('error_invalid_backup_codes', 'error');
                    return;
                }
                
                // Prüfe auf gültige Codes-Struktur
                $has_codes = false;
                if (isset($data['plain']) && is_array($data['plain']) && !empty($data['plain'])) {
                    $has_codes = true;
                } elseif (array_keys($data) === range(0, count($data) - 1) && !empty($data)) {
                    $has_codes = true;
                }
                
                if (!$has_codes) {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Keine gültigen Backup-Codes gefunden']);
                        exit;
                    }
                    set_flash_message('error_no_backup_codes', 'error');
                    return;
                }
                
                // Download-URL generieren - auf die separate download_backup_codes Datei
                $download_url = (defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/de/actions/download_backup_codes';
                $download_url = 'actions/download_backup_codes.php';
                
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Download wird gestartet...', 'download_url' => $download_url]);
                    exit;
                } else {
                    redirect($current_language . '/actions/download_backup_codes');
                    redirect($current_language . '/actions/download_backup_codes.php');
                }
            }
            break;

        default:
            set_flash_message('error_invalid_action', 'error');
    }

    // Redirect nach POST (nicht bei AJAX requests)
    if (!$is_ajax) {
        redirect($current_language . '/profil');
    }
}

// Daten laden
$stmt = $conn->prepare("SELECT email, email_verified FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $current_user_id);
$stmt->execute();
$user_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

$enabled_2fa = false;
$method_2fa = null;
$secret_2fa = null;
$backup_plain_2fa = null;
$show_verification = false;
$totp_url = '';

$stmt_2fa = $conn->prepare("SELECT two_factor_enabled, two_factor_method, two_factor_secret FROM users WHERE id = ? LIMIT 1");
$stmt_2fa->bind_param('i', $current_user_id);
$stmt_2fa->execute();
$row_2fa = $stmt_2fa->get_result()->fetch_assoc();
$stmt_2fa->close();

if ($row_2fa) {
    $enabled_2fa = $row_2fa['two_factor_enabled'];
    $method_2fa = $row_2fa['two_factor_method'];
    $secret_2fa = $row_2fa['two_factor_secret'];
    if (!$enabled_2fa && !empty($method_2fa)) {
        // Wenn eine Methode festgelegt, aber noch nicht aktiviert, muss
        // nach dem Setup eine Verifikation angezeigt werden.
        $show_verification = true;
        if ($method_2fa === 'totp' && $secret_2fa) {
            $totp_url = 'otpauth://totp/Datei%20Wolke:' . urlencode($current_username) . '?secret=' . $secret_2fa . '&issuer=Datei%20Wolke';
        }
    }
}

// POST verarbeiten
handle_post_action($conn, $current_user_id, $enabled_2fa, $method_2fa, $secret_2fa, $backup_plain_2fa, $show_verification, $totp_url);

// Header
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container">
    <h1>Profil verwalten</h1>

    <div class="profile-overview">
        <h2>Account-Übersicht</h2>
        <div class="info-grid">
            <div><strong>Benutzername:</strong> <?php echo htmlspecialchars($current_username); ?></div>
            <div><strong>E-Mail:</strong> 
                <?php if (empty($user_data['email'])): ?>
                    Nicht gesetzt
                <?php else: ?>
                    <?php echo htmlspecialchars($user_data['email']); ?> 
                    <?php if ($user_data['email_verified']): ?>
                        <span class="verified">✓ Verifiziert</span>
                    <?php else: ?>
                        <span class="unverified">✗ Unverifiziert</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div><strong>2FA:</strong> 
                <?php if ($enabled_2fa): ?>
                    Aktiviert (<?php echo htmlspecialchars($method_2fa); ?>)
                <?php else: ?>
                    Nicht aktiviert
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="profile-sections">
        <div class="section">
            <h2>Sicherheit</h2>
            <div class="actions">
                <button class="button" data-modal="change-password">Passwort ändern</button>
                <button class="button" data-modal="manage-2fa">2FA verwalten</button>
                <button class="button" data-modal="manage-webauthn">WebAuthn verwalten</button>
            </div>
        </div>

        <div class="section">
            <h2>Account-Einstellungen</h2>
            <div class="actions">
                <button class="button" data-modal="change-username">Benutzername ändern</button>
                <button class="button" data-modal="change-email">E-Mail ändern</button>
                <?php if (!empty($user_data['email']) && !$user_data['email_verified']): ?>
                    <form method="post" action="actions/resend_verification" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <button class="button">Verifizierungs-E-Mail senden</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal" id="modal-change-password">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <h2>Passwort ändern</h2>
        <form method="post" action="profil">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="change_password">
            <label>Aktuelles Passwort</label>
            <input type="password" name="current_password" required>
            <label>Neues Passwort</label>
            <input type="password" name="new_password" required minlength="8">
            <label>Neues Passwort bestätigen</label>
            <input type="password" name="confirm_new_password" required minlength="8">
            <button type="submit" class="button">Ändern</button>
        </form>
    </div>
</div>

<div class="modal" id="modal-change-username">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <h2>Benutzername ändern</h2>
        <form method="post" action="profil">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="change_username">
            <label>Neuer Benutzername</label>
            <input type="text" name="new_username" required minlength="3">
            <label>Aktuelles Passwort</label>
            <input type="password" name="current_password" required>
            <button type="submit" class="button">Ändern</button>
        </form>
    </div>
</div>

<div class="modal" id="modal-change-email">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <h2>E-Mail ändern</h2>
        <form method="post" action="profil">
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="change_email">
            <label>Neue E-Mail</label>
            <input type="email" name="new_email" required>
            <label>Aktuelles Passwort</label>
            <input type="password" name="current_password" required>
            <button type="submit" class="button">Ändern</button>
        </form>
        <?php if (!empty($user_data['email'])): ?>
            <hr>
            <form method="post" action="profil">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="remove_email">
                <h3>E-Mail entfernen</h3>
                <label>Aktuelles Passwort</label>
                <input type="password" name="current_password" required>
                <button type="submit" class="button danger">Entfernen</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="modal" id="modal-manage-2fa">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <h2>2FA verwalten</h2>
        <p>Hier kannst du die Zwei-Faktor-Authentifizierung einrichten, ändern oder deaktivieren. Folge den unten stehenden Schritten.</p>
        <div class="wizard">
            <!-- wizard step labels removed per user request -->
            <div class="wizard-content">
                <div class="wizard-pane active" data-step="1">
                    <h3>Verfügbare Aktionen</h3>
                    <p>Wähle aus, was du tun möchtest:</p>
                    <div class="action-buttons">
                        <button class="twofa-button twofa-button-primary" data-action="setup_totp" <?php if ($enabled_2fa && $method_2fa === 'totp'): ?>disabled<?php endif; ?> data-totp-url="<?php echo htmlspecialchars($totp_url); ?>">TOTP einrichten</button>
                        <?php if (!empty($user_data['email']) && $user_data['email_verified']): ?>
                            <button class="twofa-button twofa-button-primary" data-action="setup_email" style="margin-left:8px;" <?php if ($enabled_2fa && $method_2fa === 'email'): ?>disabled<?php endif; ?>>E-Mail-2FA einrichten</button>
                        <?php endif; ?>
                        <button class="twofa-button twofa-button-primary" data-action="backup" style="margin-left:8px;" <?php if (!$enabled_2fa): ?>disabled<?php endif; ?>>Backup Codes generieren</button>
                        <button class="twofa-button twofa-button-danger" data-action="disable" <?php if (!$enabled_2fa): ?>disabled<?php endif; ?>>2FA deaktivieren</button>
                    </div>
                    <div id="action-setup_totp" class="action-content" style="display:none;">
                        <h3>TOTP einrichten</h3>
                        <?php if ($enabled_2fa): ?>
                            <p>Du richtest TOTP ein. Deine aktuelle 2FA-Methode wird deaktiviert.</p>
                        <?php endif; ?>
                        <p>Bitte bestätige die Einrichtung mit dem Code, den du erhalten hast.</p>
                        <p>Scanne den QR-Code mit deiner Authenticator-App:</p>
                        <div class="qr-code-container" id="totp-qr-container">
                            <!-- QR code will be generated here by JavaScript -->
                        </div>
                        <p>Oder kopiere den Link und füge ihn in deiner Authenticator-App ein:</p>
                        <p><button type="button" class="button button-secondary" id="copy-totp-btn" onclick="copyToClipboard(document.querySelector('[data-action=setup_totp]').dataset.totpUrl)">Link kopieren</button></p>
                        <form method="post" action="profil">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="setup_2fa">
                            <input type="hidden" name="method" value="totp">
                            <div class="form-group">
                                <label>Code</label>
                                <input type="tel" name="code" pattern="\d{6}" inputmode="numeric" placeholder="6-stelliger Code" maxlength="6">
                            </div>
                            <button type="submit" class="button button-primary">Bestätigen</button>
                        </form>
                    </div>

                    <div id="action-setup_email" class="action-content" style="display:none;">
                        <h3>E-Mail-2FA einrichten</h3>
                        <?php if ($enabled_2fa): ?>
                            <p>Du richtest E-Mail-2FA ein. Deine aktuelle 2FA-Methode wird deaktiviert.</p>
                        <?php endif; ?>
                        <p>Klicke auf den Button um einen Code an deine E-Mail-Adresse anzufordern.</p>
                        <form id="request-email-code-form" method="post" action="profil" style="margin-bottom: 1rem;">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="setup_2fa">
                            <input type="hidden" name="method" value="email">
                            <button type="submit" class="button button-secondary">Code anfordern</button>
                        </form>
                        
                        <p>Gib den Code ein, den du per E-Mail erhalten hast:</p>
                        <form method="post" action="profil">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="setup_2fa">
                            <input type="hidden" name="method" value="email">
                            <div class="form-group">
                                <label>Code</label>
                                <input type="tel" name="code" required pattern="\d{6}" inputmode="numeric" placeholder="6-stelliger Code" maxlength="6">
                            </div>
                            <button type="submit" class="button button-primary">Bestätigen</button>
                        </form>
                    </div>

                    <div id="action-backup" class="action-content" style="display:none;">
                        <h3>Backup-Codes</h3>
                        <p>Backup-Codes helfen dir, wenn du keinen Zugriff mehr auf deine primäre 2FA-Methode hast. Du kannst sie herunterladen oder neue generieren. Beim Regenerieren werden die alten Codes ungültig.</p>
                        <form method="post" action="profil">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="backup_2fa">
                            <div class="form-group">
                                <label>Aktion:</label>
                                <select name="backup_action" required>
                                    <option value="download">Download</option>
                                    <option value="regen">Regenerieren</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Passwort:</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <button type="submit" class="button button-primary">Ausführen</button>
                        </form>
                    </div>
                    <div id="action-disable" class="action-content" style="display:none;">
                        <h3>2FA deaktivieren</h3>
                        <p>Wenn du die Zwei-Faktor-Authentifizierung nicht mehr verwenden möchtest, kannst du sie hier ausschalten. Du wirst zur Bestätigung nach deinem Passwort gefragt.</p>
                        <form method="post" action="profil">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="disable_2fa">
                            <div class="form-group">
                                <label>Passwort:</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <button type="submit" class="button button-danger">Deaktivieren</button>
                        </form>
                    </div>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal" id="modal-manage-webauthn">
    <div class="modal-content">
        <button class="modal-close">&times;</button>
        <h2>WebAuthn verwalten</h2>
        <div id="webauthn-list">
            <!-- Credentials werden hier geladen -->
        </div>
        <button id="add-webauthn" class="button">Neuen Sicherheitsschlüssel hinzufügen</button>
    </div>
</div>

<script>
// Modal logic - warte auf DOMContentLoaded
function setupModalListeners() {
    document.querySelectorAll('[data-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('modal-' + btn.dataset.modal);
            if (modal) {
                modal.classList.add('open');
                modal.style.display = 'flex';
            }
        });
    });
    
    document.querySelectorAll('.modal-close').forEach(close => {
        close.addEventListener('click', () => {
            close.closest('.modal').classList.remove('open');
            close.closest('.modal').style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('open');
                modal.style.display = 'none';
            }
        });
    });
}

// Setup 2FA action buttons and forms inside DOMContentLoaded
function setup2FAListeners() {
    // 2FA action buttons - toggle content display
    document.querySelectorAll('.twofa-button').forEach(btn => {
        btn.addEventListener('click', async () => {
            // Don't open disabled buttons
            if (btn.disabled) return;
            
            const action = btn.dataset.action;
            const content = document.getElementById('action-' + action);
            
            if (content) {
                // Close all content first
                document.querySelectorAll('.action-content').forEach(c => c.style.display = 'none');
                
                // Toggle the clicked one
                if (content.style.display === 'none' || content.style.display === '') {
                    content.style.display = 'block';
                    
                    // Generate TOTP QR code if this is the TOTP button
                    if (action === 'setup_totp') {
                        try {
                            // Get TOTP URL from server (stored in session, not saved to DB yet)
                            const response = await fetch('actions/generate_totp_secret');
                            const data = await response.json();
                            
                            if (data.totp_url) {
                                const container = document.getElementById('totp-qr-container');
                                container.innerHTML = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(data.totp_url) + '" alt="TOTP QR-Code" />';
                                btn.dataset.totpUrl = data.totp_url;
                            }
                        } catch (error) {
                            console.error('Error generating TOTP:', error);
                        }
                    }
                } else {
                    content.style.display = 'none';
                }
            }
        });
    });

    // Helper function to update button states
    function updateButtonStates(enabled2FA, method2FA) {
        const totpBtn = document.querySelector('[data-action="setup_totp"]');
        const emailBtn = document.querySelector('[data-action="setup_email"]');
        const backupBtn = document.querySelector('[data-action="backup"]');
        const disableBtn = document.querySelector('[data-action="disable"]');
        
        if (totpBtn) totpBtn.disabled = (enabled2FA && method2FA === 'totp');
        if (emailBtn) emailBtn.disabled = (enabled2FA && method2FA === 'email');
        if (backupBtn) backupBtn.disabled = !enabled2FA;
        if (disableBtn) disableBtn.disabled = !enabled2FA;
    }

    // 2FA forms - submit via AJAX to keep modal open
    document.querySelectorAll('#action-setup_totp form, #action-setup_email form, #action-backup form, #action-disable form').forEach(form => {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && submitBtn.disabled) return;

            let originalBtnText = '';
            let isEmailRequestForm = (form.id === 'request-email-code-form');
            
            if (submitBtn) {
                originalBtnText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
                
                if (isEmailRequestForm) {
                    let cooldown = 60;
                    submitBtn.textContent = `Erneut senden in ${cooldown}s`;
                    const timer = setInterval(() => {
                        cooldown--;
                        if (cooldown <= 0) {
                            clearInterval(timer);
                            submitBtn.disabled = false;
                            submitBtn.style.opacity = '';
                            submitBtn.style.cursor = '';
                            submitBtn.textContent = originalBtnText;
                        } else {
                            submitBtn.textContent = `Erneut senden in ${cooldown}s`;
                        }
                    }, 1000);
                } else {
                    submitBtn.textContent = 'Wird gesendet...';
                }
            }
            
            // Remove any existing error/success messages
            form.querySelectorAll('.alert, .alert-success, .alert-error, [style*="background-color"]').forEach(el => {
                if (el.style.backgroundColor) el.remove();
            });
            
            const formData = new FormData(form);
            const actionValue = formData.get('action');
            
            try {
                const response = await fetch('profil', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });
                
                // Try to parse as JSON
                const text = await response.text();
                let data = null;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    try {
                        // Repariert doppelte JSON-Objekte vom Server (Behebt den Fehler an Position 55)
                        const fixedText = '[' + text.replace(/\}\s*(?=\{)/g, '},') + ']';
                        const jsonArray = JSON.parse(fixedText);
                        data = jsonArray[jsonArray.length - 1]; // Letztes Element nehmen
                    } catch (err) {
                        console.warn('Response was not JSON:', text.substring(0, 100));
                    }
                }
                
                if (data && typeof data === 'object' && 'success' in data) {
                    // Handle JSON response
                    if (data.success) {
                        // Display success message in the wizard-pane (outside action-content)
                        // so it remains visible even when action-content is hidden
                        if (data.message) {
                            const successDiv = document.createElement('div');
                            successDiv.style.cssText = 'background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; padding: 12px 16px; margin: 1rem 0; font-weight: bold;';
                            successDiv.textContent = '✓ ' + data.message;
                            
                            // Insert message in the wizard-pane, after the action-buttons
                            const wizardPane = document.querySelector('.wizard-pane');
                            const actionButtons = wizardPane.querySelector('.action-buttons');
                            if (wizardPane && actionButtons) {
                                wizardPane.insertBefore(successDiv, actionButtons.nextSibling);
                            }
                            
                            setTimeout(() => successDiv.remove(), 4000);
                        }
                        
                        // If download is needed (backup codes)
                        if (data.download_url) {
                            // Erstelle einen echten <a> Tag für den Download
                            const link = document.createElement('a');
                            link.href = data.download_url;
                            link.setAttribute('download', '');
                            link.style.display = 'none';
                            document.body.appendChild(link);
                            // Starte den Download durch Zuweisung der URL
                            // (Da der Server 'Content-Disposition: attachment' sendet, 
                            // wird die aktuelle Seite nicht verlassen)
                            window.location.href = data.download_url;
                            
                            // Klicke den Link um den Download zu starten
                            link.click();
                            
                            // Gib dem Browser Zeit um den Download zu verarbeiten
                            setTimeout(() => {
                                document.body.removeChild(link);
                                
                                // Button nach Download-Start wieder freigeben
                                if (!isEmailRequestForm && submitBtn) {
                                    submitBtn.disabled = false;
                                    submitBtn.style.opacity = '';
                                    submitBtn.style.cursor = '';
                                    submitBtn.textContent = originalBtnText;
                                }
                                
                                // Bereich nach Download automatisch schließen
                                if (actionValue === 'backup_2fa') {
                                    form.closest('.action-content').style.display = 'none';
                                }
                            }, 1000);
                            return;
                        }
                        
                        // Update UI based on action
                        if (actionValue === 'setup_2fa') {
                            // After successful 2FA setup:
                            const methodInput = form.querySelector('input[name="method"]');
                            const codeInput = form.querySelector('input[name="code"]');
                            
                            // Only close section if code verification was successful
                            // (i.e., code input exists and was filled in the form)
                            if (codeInput && codeInput.value) {
                                // Code was verified - close section and update buttons
                                const activatedMethod = methodInput ? methodInput.value : null;
                                updateButtonStates(true, activatedMethod);
                                form.closest('.action-content').style.display = 'none';
                            }
                        } else if (actionValue === 'disable_2fa') {
                            // After disable_2fa: re-enable both setup buttons, disable backup and disable buttons
                            updateButtonStates(false, null);
                            form.closest('.action-content').style.display = 'none';
                        } else if (actionValue === 'backup_2fa') {
                            // Nach Backup-Aktion verarbeiten
                            const backupAction = form.querySelector('select[name="backup_action"]');
                            if (backupAction && backupAction.value === 'regen') {
                                // Nach Regenerierung: select auf 'download' umschalten, Bereich bleibt offen
                                backupAction.value = 'download';
                            } else if (backupAction && backupAction.value === 'download') {
                                // Nach Download: Bereich schließen
                                form.closest('.action-content').style.display = 'none';
                            }
                        }
                        
                        // Reset form (if not redirecting)
                        if (!data.redirect) {
                            form.reset();
                        }
                        
                        // Button nach normalen Erfolgen wieder freigeben
                        if (!isEmailRequestForm && submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.style.opacity = '';
                            submitBtn.style.cursor = '';
                            submitBtn.textContent = originalBtnText;
                        }
                        
                    } else {
                        // Handle error response
                        if (!isEmailRequestForm && submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.style.opacity = '';
                            submitBtn.style.cursor = '';
                            submitBtn.textContent = originalBtnText;
                        }
                        const errorDiv = document.createElement('div');
                        errorDiv.style.cssText = 'background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24; padding: 12px 16px; margin: 1rem 0; font-weight: bold;';
                        errorDiv.textContent = '✗ ' + (data.message || 'Ein Fehler ist aufgetreten');
                        form.appendChild(errorDiv);
                        
                        setTimeout(() => errorDiv.remove(), 6000);
                    }
                } else {
                    // No valid JSON response
                    throw new Error('Ungültige Antwort vom Server (kein JSON)');
                }
            } catch (error) {
                console.error('Form submission error:', error);
                if (!isEmailRequestForm && submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '';
                    submitBtn.style.cursor = '';
                    submitBtn.textContent = originalBtnText;
                }
                const errorDiv = document.createElement('div');
                errorDiv.style.cssText = 'background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24; padding: 12px 16px; margin: 1rem 0; font-weight: bold;';
                errorDiv.textContent = '✗ Fehler: ' + error.message;
                form.appendChild(errorDiv);
                
                setTimeout(() => errorDiv.remove(), 6000);
            }
        });
    });
}

// copy string to clipboard utility
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Link in Zwischenablage kopiert');
    }).catch(err => {
        console.error('Kopieren fehlgeschlagen', err);
        alert('Kopieren fehlgeschlagen');
    });
}

// Auto-open disabled - 2FA modal is no longer automatically opened on page load

// WebAuthn logic - warte auf DOMContentLoaded
function setupWebAuthnListener() {
    const modal = document.getElementById('modal-manage-webauthn');
    if (!modal) {
        console.warn('WebAuthn modal not found');
        return;
    }
    
    // Use event delegation on the modal to handle the add-webauthn button
    modal.addEventListener('click', async (e) => {
        if (e.target.id !== 'add-webauthn') return;
        
        try {
            const challengeResponse = await fetch('actions/webauthn_register_challenge', {
                method: 'POST'
            });
            
            if (!challengeResponse.ok) {
                const errorData = await challengeResponse.json();
                throw new Error(errorData.error || 'Challenge failed');
            }
            
            const challengeData = await challengeResponse.json();

            const credential = await navigator.credentials.create({
                publicKey: {
                    challenge: Uint8Array.from(atob(challengeData.challenge), c => c.charCodeAt(0)),
                    rp: challengeData.rp,
                    user: {
                        id: Uint8Array.from(atob(challengeData.user.id), c => c.charCodeAt(0)),
                        name: challengeData.user.name,
                        displayName: challengeData.user.displayName
                    },
                    pubKeyCredParams: challengeData.pubKeyCredParams,
                    authenticatorSelection: challengeData.authenticatorSelection,
                    timeout: challengeData.timeout,
                    attestation: challengeData.attestation || 'direct'
                }
            });

            if (!credential) {
                throw new Error('Credential creation cancelled or failed');
            }

            const verifyResponse = await fetch('actions/webauthn_register_verify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attestation: {
                        id: credential.id,
                        rawId: btoa(String.fromCharCode(...new Uint8Array(credential.rawId))),
                        response: {
                            attestationObject: btoa(String.fromCharCode(...new Uint8Array(credential.response.attestationObject))),
                            clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(credential.response.clientDataJSON)))
                        },
                        type: credential.type
                    }
                })
            });
            
            const verifyData = await verifyResponse.json();
            if (verifyResponse.ok && verifyData.success) {
                alert('✓ Sicherheitsschlüssel erfolgreich hinzugefügt!');
                loadWebAuthnCredentials();
            } else {
                alert('✗ ' + (verifyData.error || 'Registrierung fehlgeschlagen'));
            }
        } catch (error) {
            console.error('WebAuthn register error:', error);
            alert('WebAuthn-Fehler: ' + error.message);
        }
    });
}

function loadWebAuthnCredentials() {
    fetch('actions/webauthn_list')
        .then(response => {
            if (!response.ok) throw new Error('Failed to load credentials');
            return response.json();
        })
        .then(data => {
            const list = document.getElementById('webauthn-list');
            if (!data || data.length === 0) {
                list.innerHTML = '<p>Keine Sicherheitsschlüssel registriert.</p>';
            } else {
                let html = '<table style="width:100%; border-collapse: collapse;"><tr><th style="border:1px solid #ddd; padding:8px; text-align:left;">Name</th><th style="border:1px solid #ddd; padding:8px; text-align:left;">Registriert</th><th style="border:1px solid #ddd; padding:8px; text-align:center;">Aktion</th></tr>';
                data.forEach(cred => {
                    const createdAt = cred.created_at ? new Date(cred.created_at).toLocaleDateString('de-DE') : 'Unbekannt';
                    html += `<tr style="border-bottom:1px solid #ddd;"><td style="border:1px solid #ddd; padding:8px;">${escapeHtml(cred.name || 'Sicherheitsschlüssel')}</td><td style="border:1px solid #ddd; padding:8px;">${createdAt}</td><td style="border:1px solid #ddd; padding:8px; text-align:center;"><button class="button button-danger" onclick="deleteWebAuthnCredential(${cred.id}, '${escapeHtml(cred.name || 'Sicherheitsschlüssel')}')">Löschen</button></td></tr>`;
                });
                html += '</table>';
                list.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error loading credentials:', error);
            document.getElementById('webauthn-list').innerHTML = '<p style="color:#d32f2f;">Fehler beim Laden der Credentials: ' + error.message + '</p>';
        });
}

async function deleteWebAuthnCredential(credentialId, credentialName) {
    if (!confirm('Möchtest du "' + credentialName + '" wirklich löschen?')) {
        return;
    }
    
    try {
        const response = await fetch('actions/webauthn_delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ credential_id: credentialId })
        });
        
        const data = await response.json();
        if (response.ok && data.success) {
            alert('✓ Sicherheitsschlüssel gelöscht!');
            loadWebAuthnCredentials();
        } else {
            alert('✗ ' + (data.error || 'Löschen fehlgeschlagen'));
        }
    } catch (error) {
        console.error('Error deleting credential:', error);
        alert('Fehler beim Löschen: ' + error.message);
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

function initProfilePage() {
    setupModalListeners();
    setup2FAListeners();
    loadWebAuthnCredentials();
    setupWebAuthnListener();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initProfilePage);
} else {
    initProfilePage(); // Startet sofort, falls die Seite schon geladen ist
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>