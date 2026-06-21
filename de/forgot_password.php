<?php
// /de/forgot_password.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'GET')) {
    error_log("Request received for forgot_password", 3, __DIR__ . '/../log/phpmailer_errors.log');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validate_csrf_token();
        $login_id = isset($_POST['login_id']) ? trim($_POST['login_id']) : '';
    } elseif ($is_logged_in) {
        // E-Mail aus DB holen für eingeloggte User
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $current_user_id);
        $stmt->execute();
        $u_db = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$u_db || empty($u_db['email'])) {
            error_log("No email found for logged in user", 3, __DIR__ . '/../log/phpmailer_errors.log');
            set_flash_message('error_no_email', 'error');
            redirect($current_language . '/profil');
        }
        $email = $u_db['email'];
        error_log("Email from DB for logged in user: " . $email, 3, __DIR__ . '/../log/phpmailer_errors.log');
    }

    if (empty($login_id)) {
        error_log("Empty login_id", 3, __DIR__ . '/../log/phpmailer_errors.log');
        set_flash_message('error_invalid_data', 'error');
        redirect($current_language . '/forgot_password');
    }

    error_log("Login ID validated: " . $login_id, 3, __DIR__ . '/../log/phpmailer_errors.log');

    // Prüfe, ob E-Mail oder Username existiert
    $stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->bind_param('ss', $login_id, $login_id); $stmt->execute(); 
    if ($stmt->error) {
        error_log("DB error during user check: " . $stmt->error, 3, __DIR__ . '/../log/phpmailer_errors.log');
    }
    $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
    error_log("DB query result for " . $login_id . ": " . json_encode($u), 3, __DIR__ . '/../log/phpmailer_errors.log');
    if (!$u) {
        $_SESSION['reset_user_id'] = 0; // Fake-ID
        set_flash_message('success_temp_code_sent', 'success'); // Fake Erfolg
        redirect($current_language . '/reset_password');
    }

    $_SESSION['reset_user_id'] = $u['id'];
    error_log("User found: " . $u['id'], 3, __DIR__ . '/../log/phpmailer_errors.log');

    // Token generieren
    $token = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT); // 6-stellige Zahl
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    if (create_user_token($conn, $u['id'], $token, 'password_temp_code', $expires)) {
        error_log("Token created successfully", 3, __DIR__ . '/../log/phpmailer_errors.log');
        $subject = 'Temporärer Code für Passwortänderung';
        $body = '<p>Hallo,</p><p>Dein temporärer Code: <strong>' . $token . '</strong></p><p>Der Code ist 1 Stunde gültig.</p><p><a href="' . BASE_URL . '/' . $current_language . '/reset_password">Passwort zurücksetzen</a></p>';
        
        $_SESSION['last_code_sent_time'] = time();
        
        if (!empty($u['email']) && send_email($u['email'], $subject, $body, true)) {
            error_log("Email sent successfully", 3, __DIR__ . '/../log/phpmailer_errors.log');
            set_flash_message('success_temp_code_sent', 'success');
        } else {
            error_log("Email sending skipped or failed (no email set or error)", 3, __DIR__ . '/../log/phpmailer_errors.log');
            // Trotzdem als Erfolg melden, falls der Nutzer Backup-Codes nutzt
            set_flash_message('success_temp_code_sent', 'success');
        }
    } else {
        error_log("Token creation failed", 3, __DIR__ . '/../log/phpmailer_errors.log');
        set_flash_message('error_db_insert', 'error');
    }
    // Nach Anforderung immer auf die Reset-Seite weiterleiten, damit der Nutzer direkt den Code
    // und das neue Passwort eingeben kann (keine automatische Anmeldung).
    redirect($current_language . '/reset_password');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h1>Passwort vergessen</h1>
    <p>Gib deine E-Mail-Adresse oder deinen Benutzernamen ein.</p>

    <form method="post" action="forgot_password">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <label for="login_id">E-Mail-Adresse oder Benutzername</label>
        <input type="text" id="login_id" name="login_id" required>
        <button type="submit" class="button">Bestätigen</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>