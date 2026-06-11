<?php
// /de/login.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if ($is_logged_in) {
    redirect($current_language . '/dashboard');
}

$error_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error_message = lang('error_missing_credentials');
    } else {
        $is_email = filter_var($username, FILTER_VALIDATE_EMAIL);
        $column = $is_email ? 'email' : 'username';
        $stmt = $conn->prepare("SELECT id, username, password, role, session_version, deleted, email, two_factor_enabled, two_factor_method FROM users WHERE {$column} = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if ($user && $user['deleted']) {
                $error_message = lang('error_user_not_found');
            } elseif ($user && password_verify($password, $user['password'])) {
                // 2FA prüfen: nur aktiv wenn two_factor_enabled und die gewählte Methode unterstützt wird
                $user_two_factor_enabled = !empty($user['two_factor_enabled']);
                $user_two_factor_method = isset($user['two_factor_method']) ? $user['two_factor_method'] : null;
                $can_use_totp = (isset($twofactor_available) && $twofactor_available && function_exists('tf_verify_code'));
                $use_2fa = false;
                if ($user_two_factor_enabled) {
                    if (in_array($user_two_factor_method, ['email', 'both'])) $use_2fa = true;
                    if ($user_two_factor_method === 'totp' && $can_use_totp) $use_2fa = true;
                    if ($user_two_factor_method === 'both' && $can_use_totp) $use_2fa = true;
                }

                if ($use_2fa) {
                    // Lege Pending-2FA-Session fest
                    session_regenerate_id(true);
                    $_SESSION['2fa_pending'] = true;
                    $_SESSION['2fa_user_id'] = $user['id'];
                    $_SESSION['2fa_username'] = $user['username'];
                    $_SESSION['2fa_method'] = $user_two_factor_method;
                    $_SESSION['2fa_created'] = time();

                    // Wenn E-Mail-Methode möglich ist, sende einen Code per E-Mail
                    if (in_array($_SESSION['2fa_method'], ['email', 'both'])) {
                        $token = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                        if (create_user_token($conn, $user['id'], $token, 'two_factor_email', $expires)) {
                            $subject = 'Dein 2FA-Code';
                            $body = '<p>Hallo ' . htmlspecialchars($user['username']) . ',</p><p>Dein 2FA‑Code lautet: <strong>' . htmlspecialchars($token) . '</strong></p><p>Der Code ist 10 Minuten gültig.</p>';
                            send_email(isset($user['email']) ? $user['email'] : '', $subject, $body, true);
                            set_flash_message('info_2fa_email_sent','info');
                        }
                    }

                    redirect($current_language . '/2fa_challenge');
                }

                // Normales Login (kein 2FA)
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['session_version'] = isset($user['session_version']) ? (int)$user['session_version'] : 0;
                redirect($current_language . '/dashboard');
            } else {
                $error_message = lang('error_login_failed');
            }
        } else {
            error_log("DB Prepare Error (Login): " . $conn->error);
            $error_message = lang('error_db_prepare');
        }
    }
    if ($error_message) {
        set_flash_message($error_message, 'error');
        redirect($current_language . '/login');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card auth-card">
    <h1><?php echo lang('title_login'); ?></h1>

    <form id="login-form" method="post" action="login">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <div class="form-group">
            <label for="username"><?php echo lang('label_username'); ?></label>
            <input type="text" id="username" name="username" required autofocus>
        </div>

        <div class="form-group" id="password-group">
            <label for="password"><?php echo lang('label_password'); ?></label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required>
                <span class="toggle-password-icon icon-eye-open" aria-label="Passwort anzeigen"></span>
            </div>
        </div>

        <button type="submit" id="login-btn" class="button"><?php echo lang('button_login'); ?></button>
    </form>

    <div class="auth-links">
        <p><a href="forgot_password"><?php echo lang('link_forgot_password'); ?></a></p>
        <p><?php echo lang('text_no_account'); ?> <a href="register"><?php echo lang('nav_register'); ?></a></p>
    </div>
</div>

<script>
let webauthnAvailable = false;

document.getElementById('username').addEventListener('blur', async function() {
    const username = this.value.trim();
    if (!username) return;

    // Prüfe, ob WebAuthn verfügbar ist
    try {
        const challengeResponse = await fetch('actions/webauthn_challenge', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username })
        });
        if (challengeResponse.ok) {
            const challengeData = await challengeResponse.json();
            webauthnAvailable = true;
            // Starte WebAuthn automatisch
            performWebAuthnLogin(username, challengeData);
        } else {
            // WebAuthn nicht verfügbar, zeige Passwort-Feld
            webauthnAvailable = false;
            document.getElementById('password-group').style.display = 'block';
            document.getElementById('login-btn').style.display = 'block';
        }
    } catch (error) {
        // Fehler, zeige Passwort
        webauthnAvailable = false;
        document.getElementById('password-group').style.display = 'block';
        document.getElementById('login-btn').style.display = 'block';
    }
});

async function performWebAuthnLogin(username, challengeData) {
    try {
        const assertion = await navigator.credentials.get({
            publicKey: {
                challenge: Uint8Array.from(atob(challengeData.challenge), c => c.charCodeAt(0)),
                allowCredentials: challengeData.allowCredentials.map(cred => ({
                    id: Uint8Array.from(atob(cred.id), c => c.charCodeAt(0)),
                    type: cred.type
                })),
                userVerification: 'preferred'
            }
        });

        // Verifiziere
        const verifyResponse = await fetch('actions/webauthn_verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username: username,
                assertion: {
                    id: assertion.id,
                    rawId: btoa(String.fromCharCode(...new Uint8Array(assertion.rawId))),
                    response: {
                        authenticatorData: btoa(String.fromCharCode(...new Uint8Array(assertion.response.authenticatorData))),
                        clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(assertion.response.clientDataJSON))),
                        signature: btoa(String.fromCharCode(...new Uint8Array(assertion.response.signature))),
                        userHandle: assertion.response.userHandle ? btoa(String.fromCharCode(...new Uint8Array(assertion.response.userHandle))) : null
                    },
                    type: assertion.type
                }
            })
        });
        const verifyData = await verifyResponse.json();
        if (verifyResponse.ok && verifyData.success) {
            window.location.href = 'dashboard';
        } else {
            alert(verifyData.error || 'Authentifizierung fehlgeschlagen');
            // Fallback zu Passwort
            document.getElementById('password-group').style.display = 'block';
            document.getElementById('login-btn').style.display = 'block';
        }
    } catch (error) {
        console.error('WebAuthn error:', error);
        // Fallback zu Passwort
        document.getElementById('password-group').style.display = 'block';
        document.getElementById('login-btn').style.display = 'block';
    }
}

// Toggle Password Visibility
document.querySelector('.toggle-password-icon').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this;
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('icon-eye-open');
        icon.classList.add('icon-eye-closed');
    } else {
        passwordInput.type = 'password';
        icon.classList.remove('icon-eye-closed');
        icon.classList.add('icon-eye-open');
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>