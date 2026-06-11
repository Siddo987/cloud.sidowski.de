<?php
// /de/reset_password.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';

    if (empty($token) || empty($new_password) || strlen($new_password) < 8) {
        set_flash_message('error_invalid_data', 'error');
        redirect($current_language . '/reset_password');
    }
    if ($new_password !== $confirm_new_password) {
        set_flash_message('error_passwords_dont_match', 'error');
        redirect($current_language . '/reset_password');
    }

    // Token validieren
    $token_row = validate_user_token($conn, $token, 'password_temp_code');
    if (!$token_row) {
        set_flash_message('error_invalid_token', 'error');
        redirect($current_language . '/reset_password');
    }

    // Passwort ändern
    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, session_version = session_version + 1 WHERE id = ?");
    if ($update_stmt) {
        $update_stmt->bind_param('si', $new_hash, $token_row['user_id']); $res = $update_stmt->execute(); $update_stmt->close();
        if ($res) {
            // Markiere alle noch nicht verwendeten temporären Codes dieses Typs als verwendet,
            // damit alte/mehrere Codes nicht nachträglich genutzt werden können.
            $stmt_mark_all = $conn->prepare("UPDATE user_tokens SET used = 1 WHERE user_id = ? AND type = 'password_temp_code' AND used = 0");
            if ($stmt_mark_all) { $stmt_mark_all->bind_param('i', $token_row['user_id']); $stmt_mark_all->execute(); $stmt_mark_all->close(); } else { error_log("DB Prepare Error (mark_all_tokens): " . $conn->error); }

            // E-Mail senden
            $stmt = $conn->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $token_row['user_id']); $stmt->execute(); $u = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if ($u && !empty($u['email'])) {
                $subject = 'Passwort zurückgesetzt';
                $body = '<p>Hallo,</p><p>Dein Passwort wurde zurückgesetzt. Wenn du diese Änderung nicht durchgeführt hast, kontaktiere den Support.</p>';
                send_email($u['email'], $subject, $body, true);
            }
            set_flash_message('success_password_reset', 'success');
            redirect($current_language . '/login');
        }
    }
    set_flash_message('error_db_update', 'error');
    redirect($current_language . '/reset_password');
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h1>Passwort zurücksetzen</h1>
    <p>Gib den temporären Code und dein neues Passwort ein.</p>

    <form method="post" action="reset_password">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <label for="token">Temporärer Code</label>
        <input type="text" id="token" name="token" required pattern="\d{6}">
        <label for="new_password">Neues Passwort</label>
        <input type="password" id="new_password" name="new_password" required minlength="8">
        <label for="confirm_new_password">Passwort bestätigen</label>
        <input type="password" id="confirm_new_password" name="confirm_new_password" required minlength="8">
        <button type="submit" class="button">Passwort zurücksetzen</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>