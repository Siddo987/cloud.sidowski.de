<?php
// /config/functions_core.php - Sprachunabhängige Kernfunktionen

/**
 * Prüft, ob ein Benutzer angemeldet ist.
 * @return bool True, wenn angemeldet, sonst false.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Prüft, ob der aktuelle Benutzer Admin-Rechte (admin oder owner) hat.
 * @return bool True, wenn Admin oder Owner, sonst false.
 */
function is_admin() {
    return isset($_SESSION['role']) && in_array(strtolower($_SESSION['role']), ['admin', 'owner']);
}

/**
 * Leitet den Benutzer zu einer URL weiter und beendet das Skript.
 * Baut die URL relativ zum BASE_URL auf, wenn nicht absolut.
 * @param string $path Der Zielpfad (z.B. 'de/dashboard.php' oder '/css/styles.css').
 */
function redirect($path) {
    $path = ltrim($path, '/');
    $url = (defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '') . '/' . $path;
    header("Location: " . $url);
    exit();
}

/**
 * Setzt eine Flash-Nachricht (nur den Schlüssel und Argumente) in der Session.
 * @param string $key Der Sprachschlüssel der Nachricht (z.B. 'success_file_deleted').
 * @param string $type Typ der Nachricht ('success', 'error', 'info', 'warning').
 * @param array $args Optionale Argumente für sprintf() (z.B. Dateiname).
 */
function set_flash_message($key, $type = 'info', $args = []) {
    $_SESSION['flash_message'] = [
        'key' => $key, // Speichere den Schlüssel
        'args' => $args, // Speichere die Argumente
        'type' => in_array($type, ['success', 'error', 'info', 'warning']) ? $type : 'info'
    ];
}

/**
 * Holt die Flash-Nachrichtendaten (Schlüssel, Argumente, Typ) aus der Session (löscht sie dabei).
 * Die Übersetzung passiert erst bei der Anzeige im Header.
 * @return array|null Nachrichtendaten oder null.
 */
function get_flash_message() {
     if (isset($_SESSION['flash_message'])) {
        $message_data = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        // Gibt Schlüssel, Argumente und Typ zurück
        return $message_data;
     }
     return null;
}


/**
 * Generiert einen sicheren CSRF-Token (zufällige Zeichenkette).
 * @return string Der CSRF-Token als Hex-String.
 */
function generate_csrf_token() {
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes(32));
    } else {
        // Fallback für sehr alte PHP-Versionen ohne OpenSSL
        return bin2hex(mt_rand() . mt_rand() . mt_rand() . mt_rand());
    }
}

/**
 * Gibt den aktuellen CSRF-Token aus der Session zurück.
 * Stellt sicher, dass ein Token existiert (generiert einen, falls nötig).
 * @return string Der CSRF-Token.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = generate_csrf_token(); }
    return $_SESSION['csrf_token'];
}

/**
 * Validiert einen übergebenen CSRF-Token gegen den in der Session.
 * Ruft redirect() mit Fehlermeldung auf, wenn ungültig.
 * @param string|null $submitted_token Der per POST übermittelte Token. Standardmäßig wird $_POST['csrf_token'] verwendet.
 */
function validate_csrf_token($submitted_token = null) {
    $token_from_post = isset($submitted_token) ? $submitted_token : (isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null);
    if (empty($token_from_post) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_from_post)) {
        error_log("CSRF Token Validation Failed. Session: " . (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : 'None') . ", Submitted: " . (isset($token_from_post) ? $token_from_post : 'None'));
        global $current_language;
        $lang_prefix = isset($current_language) && $current_language ? $current_language . '/' : 'de/';
        // Wichtig: Hier NUR den Key übergeben! Die lang() Funktion ist hier noch nicht sicher verfügbar.
        set_flash_message('error_csrf_token_invalid', 'error');
        if (is_logged_in()) { redirect($lang_prefix . 'dashboard'); }
        else { redirect($lang_prefix . 'login'); }
    }
}

/**
 * Generiert einen kryptographisch sicheren Token (z.B. für Email-Verification oder temporäre Codes).
 * @param int $bytes Anzahl Bytes (Default 32 -> 64 hex Zeichen)
 * @return string Hex-String Token
 */
function generate_random_token($bytes = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($bytes));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($bytes));
    } else {
        // Fallback: nicht sicher, aber funktioniert
        return bin2hex(substr(md5(mt_rand()), 0, $bytes*2));
    }
}

/**
 * Einfacher Mail-Wrapper. Nutzt die Einstellungen in config/mail_config.php (SMTP_FROM, SMTP_NAME).
 * Für Authenticated SMTP / bessere Zustellbarkeit ist PHPMailer/SwiftMailer empfehlenswert.
 * @param string $to Empfänger (E-Mail)
 * @param string $subject Betreff
 * @param string $body HTML-Inhalt der Mail
 * @param bool $is_html Ob der Body HTML ist
 * @return bool True wenn mail() erfolgreich ausgeführt wurde (nicht unbedingt zugestellt)
 */
function send_email($to, $subject, $body, $is_html = true) {
    if (empty($to) || empty($subject) || empty($body)) return false;

    // Versuche Mail-Konfiguration zu laden, falls vorhanden
    if (file_exists(__DIR__ . '/mail_config.php')) {
        include_once __DIR__ . '/mail_config.php';
    }

    // Wenn PHPMailer installiert ist, verwende SMTP (zuverlässiger)
    // Falls die Klasse noch nicht verfügbar ist, versuchen wir zuerst Composer Autoload
    // und dann direkte Includes aus dem PHPMailer Vendor‑Pfad als Fallback.
    if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            @include_once $autoload; // still safe to include, silenced to avoid runtime warnings
        }
        // Direkte Includes, falls das Paket vorhanden ist, aber Autoloader nicht geladen wurde
        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
            @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
            @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
            @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
        }
    }

    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            // Server settings
            $mail->isSMTP();
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            if (defined('SMTP_HOST')) $mail->Host = SMTP_HOST;
            if (defined('SMTP_PORT')) $mail->Port = SMTP_PORT;
            if (defined('SMTP_SECURE') && in_array(strtolower(SMTP_SECURE), ['ssl','tls'])) $mail->SMTPSecure = SMTP_SECURE;
            if (defined('SMTP_USER')) { $mail->SMTPAuth = true; $mail->Username = SMTP_USER; $mail->Password = SMTP_PASS; }
            $mail->CharSet = 'UTF-8';
            // From
            $from = defined('SMTP_FROM') && SMTP_FROM !== '' ? SMTP_FROM : 'no-reply@example.com';
            $name = defined('SMTP_NAME') && SMTP_NAME !== '' ? SMTP_NAME : 'No-Reply Datei Wolke';
            $mail->setFrom($from, $name);
            // Recipient
            $mail->addAddress($to);
            // Content
            $mail->isHTML((bool)$is_html);
            $mail->Subject = $subject;
            $mail->Body = $body;
            // Send
            $mail->preSend();
            error_log($mail->createHeader(), 3, __DIR__ . '/phpmailer_headers.log');
            $sent = $mail->send();
            if (!$sent) {
                error_log('PHPMailer: send() returned false for recipient ' . $to, 3, __DIR__ . '/phpmailer_errors.log');
            }
            return (bool)$sent;
        } catch (Exception $e) {
            error_log('PHPMailer Exception: ' . $e->getMessage(), 3, __DIR__ . '/phpmailer_errors.log');
            // Fallthrough to fallback mail()
        }
    }

    $from = defined('SMTP_FROM') && SMTP_FROM !== '' ? SMTP_FROM : 'no-reply@example.com';
    $name = defined('SMTP_NAME') && SMTP_NAME !== '' ? SMTP_NAME : 'No-Reply Datei Wolke';
    $headers = "From: \"{$name}\" <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= ($is_html ? "Content-type: text/html; charset=utf-8\r\n" : "Content-type: text/plain; charset=utf-8\r\n");

    $res = mail($to, $subject, $body, $headers);
    if (!$res) error_log("mail() failed sending to {$to}; headers: {$headers}", 3, __DIR__ . '/phpmailer_errors.log');
    return $res;
}

/**
 * Legt einen Token in der Tabelle `user_tokens` ab (z.B. Email-Verifikation, temporäre Codes).
 * @param mysqli $conn DB-Verbindung
 * @param int $user_id
 * @param string $token
 * @param string $type
 * @param string $expires_at DATETIME
 * @return bool True on success
 */
function create_user_token($conn, $user_id, $token, $type, $expires_at, $meta = null) {
    $stmt = $conn->prepare("INSERT INTO user_tokens (user_id, token, type, expires_at, meta) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) { error_log("DB Prepare Error (create_user_token): " . $conn->error); return false; }
    $stmt->bind_param("issss", $user_id, $token, $type, $expires_at, $meta);
    $res = $stmt->execute(); $stmt->close(); return $res;
}

/**
 * Validiert einen Token (noch nicht verwendet, nicht abgelaufen) und gibt die zugehörige Zeile zurück.
 */
function validate_user_token($conn, $token, $type) {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT id, user_id, token, type, expires_at, used, meta FROM user_tokens WHERE token = ? AND type = ? LIMIT 1");
    if (!$stmt) { error_log("DB Prepare Error (validate_user_token): " . $conn->error); return null; }
    $stmt->bind_param("ss", $token, $type); $stmt->execute(); $result = $stmt->get_result(); $row = $result->fetch_assoc(); $stmt->close();
    if (!$row) return null; if ($row['used']) return null; if ($row['expires_at'] < $now) return null; return $row;
}

/**
 * Markiert einen Token als verwendet
 */
function mark_user_token_used($conn, $id) {
    $stmt = $conn->prepare("UPDATE user_tokens SET used = 1 WHERE id = ?"); if (!$stmt) { error_log($conn->error); return false; }
    $stmt->bind_param("i", $id); $res = $stmt->execute(); $stmt->close(); return $res;
}

/**
 * Prüft, ob für einen User und Token-Type gerade ein Cooldown aktiv ist.
 * Gibt die Anzahl der verbleibenden Sekunden zurück oder 0, wenn kein Cooldown aktiv ist.
 * @param mysqli $conn DB-Verbindung
 * @param int $user_id Die User-ID
 * @param string $type Der Token-Type (z.B. 'two_factor_email')
 * @param int $cooldown_seconds Cooldown-Dauer in Sekunden (Standard: 60)
 * @return int Verbleibende Sekunden oder 0 wenn kein Cooldown aktiv ist
 */
function check_token_cooldown($conn, $user_id, $type, $cooldown_seconds = 60) {
    $cooldown_start = date('Y-m-d H:i:s', strtotime('-' . $cooldown_seconds . ' seconds'));
    $stmt = $conn->prepare("SELECT created_at FROM user_tokens WHERE user_id = ? AND type = ? AND created_at > ? ORDER BY created_at DESC LIMIT 1");
    if (!$stmt) { error_log("DB Prepare Error (check_token_cooldown): " . $conn->error); return 0; }
    $stmt->bind_param("iss", $user_id, $type, $cooldown_start);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) return 0; // Kein Token gefunden, kein Cooldown
    
    $created_time = strtotime($row['created_at']);
    $current_time = time();
    $elapsed = $current_time - $created_time;
    $remaining = max(0, $cooldown_seconds - $elapsed);
    
    return (int)$remaining;
}

/**
 * Generiert Initialen aus einem Namen (z.B. für Profilbilder).
 * @param string|null $name Der vollständige Name.
 * @return string Die Initialen (max. 2 Buchstaben) oder '?'.
 */
function get_initials($name) {
    $name = trim((string)$name);
    if (empty($name)) return '?';
    $words = preg_split('/\s+/', $name);
    $initials = '';
    if (is_array($words)) {
        if (!empty($words[0])) $initials .= mb_strtoupper(mb_substr($words[0], 0, 1, 'UTF-8'), 'UTF-8');
        if (count($words) > 1 && !empty($words[count($words) - 1])) $initials .= mb_strtoupper(mb_substr($words[count($words) - 1], 0, 1, 'UTF-8'), 'UTF-8');
    }
    return $initials ?: '?';
}

/**
 * Holt den Benutzernamen anhand der User-ID aus der Datenbank.
 * Verwendet einen einfachen statischen Cache.
 * @param mysqli $conn Die Datenbankverbindung.
 * @param int|null $user_id Die User-ID.
 * @return string Der Benutzername, "Unbekannt (ID)" oder ein Fehlertext.
 */
function get_username_by_id($conn, $user_id) {
    $lang_func = function_exists('lang') ? 'lang' : function($key){ return $key; }; // Fallback
    if (!$conn || !$conn->thread_id) { return $lang_func('error_db_error'); } // Verwende Key hier
    if ($user_id === null || $user_id <= 0) { return $lang_func('error_invalid_id'); } // Verwende Key hier
    static $user_cache = [];
    if (isset($user_cache[$user_id])) { return $user_cache[$user_id]; }
    try {
        $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
        if (!$stmt) { error_log(/*...*/); return $lang_func('error_db_prepare'); } // Verwende Key hier
        $stmt->bind_param("i", $user_id); $stmt->execute(); $result = $stmt->get_result(); $user = $result->fetch_assoc(); $stmt->close();
        $unknown_user_text = $lang_func('text_unknown_user'); // Holt Übersetzung wenn lang() da ist
        $name = $user ? htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') : $unknown_user_text . ' (' . $user_id . ')';
        $user_cache[$user_id] = $name; return $name;
    } catch (Exception $e) { error_log(/*...*/); return $lang_func('text_error'); } // Verwende Key hier
}

/**
 * Kürzt einen Dateinamen sicher für die Anzeige (fügt '...' hinzu).
 * @param string|null $filename Der Dateiname.
 * @param int $max_length Die maximale Anzeigelänge (inklusive '...').
 * @return string Der gekürzte und HTML-gesicherte Dateiname oder '-'.
 */
function shorten_filename($filename, $max_length = 30) {
    $filename = basename((string)$filename); if (empty($filename)) return '-';
    if (mb_strlen($filename, 'UTF-8') > $max_length) {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name_length = $max_length - (mb_strlen($extension, 'UTF-8') + 4);
        if ($name_length < 1) $name_length = 1;
        $name_part = mb_substr($filename, 0, $name_length, 'UTF-8');
        $ext_part = $extension ? '.' . $extension : '';
        return htmlspecialchars($name_part . '...' . $ext_part, ENT_QUOTES, 'UTF-8');
    }
    return htmlspecialchars($filename, ENT_QUOTES, 'UTF-8');
}

/**
 * Formatiert eine Dateigröße in Bytes in ein lesbares Format (KB, MB, GB etc.).
 * @param int|null $bytes Die Dateigröße in Bytes.
 * @param int $precision Anzahl der Nachkommastellen.
 * @return string Die formatierte Dateigröße oder 'N/A'.
 */
function format_bytes($bytes, $precision = 2) {
    if ($bytes === null || $bytes < 0) return 'N/A'; if ($bytes == 0) return '0 Bytes';
    $units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $factor = floor((strlen((string)$bytes) - 1) / 3);
    if ($factor >= count($units)) $factor = count($units) - 1;
    return sprintf("%.{$precision}f", $bytes / pow(1024, $factor)) . ' ' . (isset($units[$factor]) ? $units[$factor] : 'Bytes');
}


/**
 * Prüft, ob der aktuell angemeldete Benutzer Berechtigung hat, eine bestimmte Datei zu sehen/zu verwalten.
 * @param mysqli $conn Datenbankverbindung.
 * @param int $file_id ID der Datei.
 * @param int|null $user_id ID des aktuellen Benutzers (null für Gast).
 * @param string|null $role Rolle des aktuellen Benutzers (null für Gast).
 * @return bool True bei Berechtigung, sonst false.
 */
function check_file_permission($conn, $file_id, $user_id, $role) {
    if ($file_id <= 0) return false;
    $stmt = $conn->prepare("SELECT uploader_id, public FROM files WHERE id = ?");
    if (!$stmt) { error_log(/*...*/); return false; }
    $stmt->bind_param("i", $file_id); $stmt->execute(); $result = $stmt->get_result(); $file = $result->fetch_assoc(); $stmt->close();
    if (!$file) return false; if ($file['public']) return true; if ($user_id === null) return false;
    $role_lower = $role ? strtolower($role) : null;
    return ($file['uploader_id'] == $user_id || in_array($role_lower, ['admin', 'owner']));
}

/**
 * Löscht eine Datei permanent (DB-Eintrag und physische Datei).
 * Überprüft Berechtigungen. Gibt jetzt Sprachschlüssel zurück.
 * Benötigt die Konstante USER_UPLOAD_DIR.
 * @param mysqli $conn Datenbankverbindung.
 * @param int $file_id ID der zu löschenden Datei.
 * @param int $current_user_id ID des Benutzers, der die Aktion ausführt.
 * @param string $current_user_role Rolle des Benutzers.
 * @return array ['success' => bool, 'message_key' => string, 'message_args' => array] Ergebnis der Operation.
 */
function delete_file_permanently($conn, $file_id, $current_user_id, $current_user_role) {
    $role_lower = strtolower($current_user_role);

    // 1. Datei-Infos holen
    $fetch_stmt = $conn->prepare("SELECT filename, uploader_id FROM files WHERE id = ?");
    if (!$fetch_stmt) { error_log("DB Prep Err (del/sel): ".$conn->error); return ['success' => false, 'message_key' => 'error_db_prepare', 'message_args' => []]; }
    $fetch_stmt->bind_param("i", $file_id); $fetch_stmt->execute(); $result = $fetch_stmt->get_result(); $file_info = $result->fetch_assoc(); $fetch_stmt->close();
    if (!$file_info) return ['success' => false, 'message_key' => 'error_file_not_found', 'message_args' => [$file_id]];

    $filename_to_delete = $file_info['filename']; $uploader_id_of_file = $file_info['uploader_id'];

    // 2. Berechtigung prüfen
    if (!($uploader_id_of_file == $current_user_id || in_array($role_lower, ['admin', 'owner']))) {
        return ['success' => false, 'message_key' => 'error_no_permission', 'message_args' => []];
    }

    // 3. DB-Eintrag löschen
    $delete_stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
    if (!$delete_stmt) { error_log("DB Prep Err (del/del): ".$conn->error); return ['success' => false, 'message_key' => 'error_db_prepare', 'message_args' => []]; }
    $delete_stmt->bind_param("i", $file_id); $db_deleted = $delete_stmt->execute(); $affected_rows = $delete_stmt->affected_rows; $delete_stmt->close();
    if (!$db_deleted || $affected_rows === 0) { error_log("DB Del Err (del) for file id {$file_id}: ".$conn->error); return ['success' => false, 'message_key' => 'error_db_delete', 'message_args' => []]; }

    // 4. Physische Datei löschen
    $filepath_to_delete = rtrim(USER_UPLOAD_DIR, '/') . '/' . $uploader_id_of_file . '/' . basename($filename_to_delete);
    if (file_exists($filepath_to_delete)) {
        if (@unlink($filepath_to_delete)) {
            $user_dir = dirname($filepath_to_delete);
            if (is_dir($user_dir) && is_readable($user_dir)) { $scan = @scandir($user_dir); if ($scan && count($scan) == 2) @rmdir($user_dir); }
            // Erfolg: Schlüssel und Dateiname als Argument zurückgeben
            return ['success' => true, 'message_key' => 'success_file_deleted', 'message_args' => [$filename_to_delete]];
        } else {
            $unlink_error = isset(error_get_last()['message']) ? error_get_last()['message'] : 'Unknown error'; error_log("Unlink Err (del): {$filepath_to_delete}: {$unlink_error}");
            // Fehler: Schlüssel und Argumente zurückgeben
            return ['success' => false, 'message_key' => 'error_file_delete_failed', 'message_args' => [$filename_to_delete, $unlink_error]];
        }
    } else {
        // Erfolg (Datei war eh weg): Schlüssel und Dateiname als Argument zurückgeben
         return ['success' => true, 'message_key' => 'success_file_deleted', 'message_args' => [$filename_to_delete]];
    }
}

?>