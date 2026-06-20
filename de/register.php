<?php
// /de/register.php
$current_language = 'de';
require_once __DIR__ . '/../config/bootstrap.php';

if ($is_logged_in) {
    redirect($current_language . '/dashboard');
}

$error_message = null;
$success_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : ''; 

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $error_message = lang('error_all_fields_required');
    } elseif (!validate_password_strength($password)) {
        $error_message = lang('error_password_weak');
    } elseif ($password !== $confirm_password) {
        $error_message = lang('error_passwords_dont_match');
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $error_message = lang('error_username_taken');
            }
            $stmt_check->close();
        } else {
            $error_message = lang('error_db_prepare');
            error_log("DB Prepare Error (check username): " . $conn->error);
        }

        // Wenn E-Mail angegeben: prüfen, ob sie bereits existiert
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $stmt_email_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if ($stmt_email_check) {
                $stmt_email_check->bind_param("s", $email);
                $stmt_email_check->execute();
                $stmt_email_check->store_result();
                if ($stmt_email_check->num_rows > 0) {
                    $error_message = lang('error_email_already_taken');
                }
                $stmt_email_check->close();
            }
        }

        if ($error_message === null) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $default_role = 'user';
            // Email optional unterstützen (Benutzer kann ohne E-Mail registrieren)
            $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = lang('error_invalid_data');
            }
            if ($error_message === null) {
                $stmt_insert = $conn->prepare("INSERT INTO users (username, password, role, created_at, email) VALUES (?, ?, ?, NOW(), ?)");
                if ($stmt_insert) {
                    $email_param = !empty($email) ? $email : null;
                    $stmt_insert->bind_param("ssss", $username, $hashed_password, $default_role, $email_param);
                    if ($stmt_insert->execute()) {
                        $new_user_id = $stmt_insert->insert_id;
                        // Wenn E-Mail angegeben: Token anlegen und Bestätigungs-Mail senden (optional)
                        if (!empty($email)) {
                            $token = generate_random_token(24);
                            $expires = date('Y-m-d H:i:s', time() + 60*60*24); // 24h
                            if (!create_user_token($conn, $new_user_id, $token, 'email_verification', $expires)) {
                                error_log("Failed to create verification token for user {$new_user_id}");
                            } else {
                                $verify_url = (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/' . $current_language . '/actions/verify_email?token=' . urlencode($token);
                                $subject = 'Bitte bestätige deine E-Mail-Adresse';
                                $body = '<p>Hallo ' . htmlspecialchars($username) . ',</p>' .
                                        '<p>bitte bestätige deine E-Mail-Adresse, indem du auf den folgenden Link klickst:</p>' .
                                        '<p><a href="' . htmlspecialchars($verify_url) . '">' . htmlspecialchars($verify_url) . '</a></p>' .
                                        '<p>Der Link ist 24 Stunden gültig.</p>';
                                send_email($email, $subject, $body, true);
                            }
                        }

                        set_flash_message(lang('success_registration'), 'success');
                        redirect($current_language . '/login');
                    } else {
                        $error_message = lang('error_registration_failed');
                        error_log("DB Insert Error (user registration): " . $stmt_insert->error);
                    }
                    $stmt_insert->close();
                } else {
                    $error_message = lang('error_db_prepare');
                    error_log("DB Prepare Error (insert user): " . $conn->error);
                }
            }
        }
    }
    if ($error_message) {
        set_flash_message($error_message, 'error');
        redirect($current_language . '/register');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card auth-card">
    <h1><?php echo lang('title_register'); ?></h1>

    <form method="post" action="register">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

        <label for="username"><?php echo lang('label_username'); ?></label>
        <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars(isset($_POST['username']) ? $_POST['username'] : ''); ?>">

        <label for="email"><?php echo lang('label_email'); ?></label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars(isset($_POST['email']) ? $_POST['email'] : ''); ?>">

        <div class="password-wrapper">
            <label for="password"><?php echo lang('label_password'); ?></label>
            <input type="password" id="password" name="password" required minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}" title="Mindestens 8 Zeichen, Groß-, Kleinbuchstaben, Zahl und Sonderzeichen">
             <span class="toggle-password-icon icon-eye-open" aria-label="Passwort anzeigen"></span>
        </div>
        <div class="password-wrapper">
            <label for="confirm_password"><?php echo lang('label_confirm_password'); ?></label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}" title="Mindestens 8 Zeichen, Groß-, Kleinbuchstaben, Zahl und Sonderzeichen">
             <span class="toggle-password-icon icon-eye-open" aria-label="Passwort anzeigen"></span>
        </div>
        <button type="submit" class="button"><?php echo lang('button_register'); ?></button>
    </form>
    <p style="text-align: center; margin-top: 20px;">
        <?php echo lang('text_already_have_account'); ?> <a href="login"><?php echo lang('text_login_now'); ?></a>
    </p>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>