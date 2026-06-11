<?php
// /config/bootstrap.php

// Load environment variables from .env (project root) into getenv/$_ENV/$_SERVER
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $val) = explode('=', $line, 2);
        $name = trim($name);
        $val = trim($val);
        if ((substr($val, 0, 1) === '"' && substr($val, -1) === '"') || (substr($val, 0, 1) === "'" && substr($val, -1) === "'")) {
            $val = substr($val, 1, -1);
        }
        putenv($name . '=' . $val);
        $_ENV[$name] = $val;
        $_SERVER[$name] = $val;
    }
}

// Config laden (enthält DEBUG_MODE Definition und DB-Konstanten)
require_once __DIR__ . '/config.php';

// Fehler-Reporting basierend auf DEBUG_MODE steuern
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin');

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Globale sprachunabhängige Kernfunktionen laden
require_once __DIR__ . '/functions_core.php';

// Composer Autoloader laden
$composer_autoload_available = true;
try {
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Exception $e) {
    // Composer detected incompatible platform (z.B. alte PHP-Version).
    error_log('Composer autoload failed: ' . $e->getMessage());
    $composer_autoload_available = false;
}
// Falls PHPMailer nicht automatisch geladen wurde, aber das Paket vorhanden ist,
// versuchen wir direkte Includes, damit SMTP sofort verfügbar ist.
if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
}

// Zwei-Faktor-Auth laden: Fallback immer verfügbar; wenn Composer verfügbar ist, verwende die "bessere" Implementation
$twofactor_available = false;
// Fallback definieren (funktioniert ohne Composer/PHP >= 8.1)
require_once __DIR__ . '/twofactor_fallback.php';
if (function_exists('tf_verify_code')) {
    $twofactor_available = true;
}

// Wenn Composer Autoload verfügbar ist, versuche zusätzliche Implementation zu laden (falls vorhanden)
if ($composer_autoload_available) {
    // twofactor.php nutzt die Composer-Bibliothek falls möglich; sie ist optional
    if (file_exists(__DIR__ . '/twofactor.php')) {
        require_once __DIR__ . '/twofactor.php';
        // Wenn die bessere Implementierung genutzt werden kann, setze Flag
        if (function_exists('tf_verify_code')) $twofactor_available = true;
    }
}

// Sprach-Funktionen früh laden, falls $current_language gesetzt ist (damit Seiten wie all_files.php lang() vor dem Header verwenden können)
if (!empty($current_language)) {
    // Nur Deutsch unterstützen - andere Sprachen auf Deutsch umleiten
    if ($current_language !== 'de') {
        $current_language = 'de';
    }
    $lang_file = __DIR__ . '/../' . $current_language . '/includes/functions_lang.php';
    if (file_exists($lang_file)) require_once $lang_file;
}

// --- Datenbankverbindung herstellen ---
global $conn;
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    $errorMessage = DEBUG_MODE ? "DB Connection Error: " . $conn->connect_error : "Database connection failed.";
    error_log("DB Connection Error: " . $conn->connect_error);
    // Im Live-Betrieb sollte hier eine freundliche Fehlerseite angezeigt werden.
    die($errorMessage);
}

if (!$conn->set_charset("utf8")) {
    error_log("Error loading character set utf8: " . $conn->error);
}

// CSRF Token für die Session generieren/sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

// Globale Variablen für Benutzerinformationen für einfachen Zugriff bereitstellen
$is_logged_in = is_logged_in();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$current_username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$is_admin = is_admin(); // Prüft 'admin' oder 'owner'

// Prüfe Session-Version und Soft-Delete-Status in der DB (erlaubt Invalidierung anderer Sessions bei Passwort-Änderung)
if ($is_logged_in && $current_user_id) {
    $stmt_check = $conn->prepare("SELECT session_version, deleted FROM users WHERE id = ? LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param('i', $current_user_id);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        $u = $res->fetch_assoc();
        $stmt_check->close();
        if ($u) {
            // Wenn Benutzer soft-deleted wurde, abmelden
            if (isset($u['deleted']) && $u['deleted']) {
                // Lösche Session und leite zur Anmeldung
                session_unset(); session_destroy();
                set_flash_message('error_user_not_found', 'error');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
            }
        }
    }

// PrÃ¼fe Session-Version und Soft-Delete-Status in der DB (erlaubt Invalidierung anderer Sessions bei Passwort-Ã„nderung)
if ($is_logged_in && $current_user_id) {
    $stmt_check = $conn->prepare("SELECT session_version, deleted FROM users WHERE id = ? LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param('i', $current_user_id);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        $u = $res->fetch_assoc();
        $stmt_check->close();
        if ($u) {
            // Wenn Benutzer soft-deleted wurde, abmelden
            if (isset($u['deleted']) && $u['deleted']) {
                // LÃ¶sche Session und leite zur Anmeldung
                session_unset(); session_destroy();
                set_flash_message('error_user_not_found', 'error');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
            // Session-Version prÃ¼fen
            $session_version_in_session = isset($_SESSION['session_version']) ? (int)$_SESSION['session_version'] : 0;
            $session_version_in_db = isset($u['session_version']) ? (int)$u['session_version'] : 0;
            if ($session_version_in_db !== $session_version_in_session) {
                session_unset(); session_destroy();
                set_flash_message('error_session_invalidated', 'info');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
        }
    }
}

// --- Datenbankverbindung herstellen ---
global $conn;
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    $errorMessage = DEBUG_MODE ? "DB Connection Error: " . $conn->connect_error : "Database connection failed.";
    error_log("DB Connection Error: " . $conn->connect_error);
    // Im Live-Betrieb sollte hier eine freundliche Fehlerseite angezeigt werden.
    die($errorMessage);
}

if (!$conn->set_charset("utf8")) {
    error_log("Error loading character set utf8: " . $conn->error);
}

// CSRF Token fÃ¼r die Session generieren/sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

// Globale Variablen fÃ¼r Benutzerinformationen fÃ¼r einfachen Zugriff bereitstellen
$is_logged_in = is_logged_in();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$current_username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$is_admin = is_admin(); // PrÃ¼ft 'admin' oder 'owner'

// PrÃ¼fe Session-Version und Soft-Delete-Status in der DB (erlaubt Invalidierung anderer Sessions bei Passwort-Ã„nderung)
if ($is_logged_in && $current_user_id) {
    $stmt_check = $conn->prepare("SELECT session_version, deleted FROM users WHERE id = ? LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param('i', $current_user_id);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        $u = $res->fetch_assoc();
        $stmt_check->close();
        if ($u) {
            // Wenn Benutzer soft-deleted wurde, abmelden
            if (isset($u['deleted']) && $u['deleted']) {
                // LÃ¶sche Session und leite zur Anmeldung
                session_unset(); session_destroy();
                set_flash_message('error_user_not_found', 'error');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
            // Session-Version prÃ¼fen
            $session_version_in_session = isset($_SESSION['session_version']) ? (int)$_SESSION['session_version'] : 0;
            $session_version_in_db = isset($u['session_version']) ? (int)$u['session_version'] : 0;
            if ($session_version_in_db !== $session_version_in_session) {
                session_unset(); session_destroy();
                set_flash_message('error_session_invalidated', 'info');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
        }
    }
}
        
        
        // /config/bootstrap.php

        // Load environment variables from .env (project root) into getenv/$_ENV/$_SERVER
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) continue;
                if (strpos($line, '=') === false) continue;
                list($name, $val) = explode('=', $line, 2);
                $name = trim($name);
                $val = trim($val);
                if ((substr($val,0,1) === '"' && substr($val,-1) === '"') || (substr($val,0,1) === "'" && substr($val,-1) === "'")) {
                    $val = substr($val,1,-1);
                }
                putenv($name . '=' . $val);
                $_ENV[$name] = $val;
                $_SERVER[$name] = $val;
            }
        }

        // Config laden (enthält DEBUG_MODE Definition)
        // __DIR__ zeigt auf das /config Verzeichnis
        require_once __DIR__ . '/config.php';

// Fehler-Reporting basierend auf DEBUG_MODE steuern
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    // Im Debug-Modus: Fehler im Browser anzeigen
    ini_set('display_errors', 1);         // ANZEIGEN
    ini_set('display_startup_errors', 1); // ANZEIGEN (fÃ¼r Fehler beim PHP-Start)
    error_reporting(E_ALL);               // Alle Fehlertypen melden
} else {
    // Im Live-Betrieb: Fehler im Browser unterdrÃ¼cken
    ini_set('display_errors', 0);         // AUSBLENDEN
    ini_set('display_startup_errors', 0); // AUSBLENDEN
    error_reporting(E_ALL);               // Weiterhin alle Fehler loggen!
    // Stelle sicher, dass dein Server so konfiguriert ist, dass er Fehler in eine Datei loggt!
    // ini_set('log_errors', 1); // Normalerweise schon Standard
    // ini_set('error_log', '/pfad/zu/deinem/server/php-error.log'); // Pfad je nach Server
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin'); // Oder deine Zeitzone

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    // Optional: Sicherere Session-Einstellungen (siehe frÃ¼here Kommentare)
    session_start();
}

// Globale sprachunabhÃ¤ngige Kernfunktionen laden
require_once __DIR__ . '/functions_core.php';

// Composer Autoloader laden
$composer_autoload_available = true;
try {
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Exception $e) {
    // Composer detected incompatible platform (z.B. alte PHP-Version).
    error_log('Composer autoload failed: ' . $e->getMessage());
    $composer_autoload_available = false;
}
// Falls PHPMailer nicht automatisch geladen wurde, aber das Paket vorhanden ist,
// versuchen wir direkte Includes, damit SMTP sofort verfÃ¼gbar ist.
if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
}

// Zwei-Faktor-Auth laden: Fallback immer verfÃ¼gbar; wenn Composer verfÃ¼gbar ist, verwende die "bessere" Implementation
$twofactor_available = false;
// Fallback definieren (funktioniert ohne Composer/PHP >= 8.1)
require_once __DIR__ . '/twofactor_fallback.php';
if (function_exists('tf_verify_code')) {
    $twofactor_available = true;
}

// Wenn Composer Autoload verfÃ¼gbar ist, versuche zusÃ¤tzliche Implementation zu laden (falls vorhanden)
if ($composer_autoload_available) {
    // twofactor.php nutzt die Composer-Bibliothek falls mÃ¶glich; sie ist optional
    if (file_exists(__DIR__ . '/twofactor.php')) {
        require_once __DIR__ . '/twofactor.php';
        // Wenn die bessere Implementierung genutzt werden kann, setze Flag
        if (function_exists('tf_verify_code')) $twofactor_available = true;
    }
}

// Sprach-Funktionen frÃ¼h laden, falls $current_language gesetzt ist (damit Seiten wie all_files.php lang() vor dem Header verwenden kÃ¶nnen)
if (!empty($current_language)) {
    // Nur Deutsch unterstÃ¼tzen - andere Sprachen auf Deutsch umleiten
    if ($current_language !== 'de') {
        $current_language = 'de';
    }
    $lang_file = __DIR__ . '/../' . $current_language . '/includes/functions_lang.php';
    if (file_exists($lang_file)) require_once $lang_file;
}

// --- Datenbankverbindung herstellen ---
global $conn;
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    $errorMessage = DEBUG_MODE ? "DB Connection Error: " . $conn->connect_error : "Database connection failed.";
    error_log("DB Connection Error: " . $conn->connect_error);
    // Im Live-Betrieb sollte hier eine freundliche Fehlerseite angezeigt werden.
    die($errorMessage);
}

if (!$conn->set_charset("utf8")) {
    error_log("Error loading character set utf8: " . $conn->error);
}

// CSRF Token fÃ¼r die Session generieren/sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

// Globale Variablen fÃ¼r Benutzerinformationen fÃ¼r einfachen Zugriff bereitstellen
$is_logged_in = is_logged_in();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$current_username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$is_admin = is_admin(); // PrÃ¼ft 'admin' oder 'owner'

// PrÃ¼fe Session-Version und Soft-Delete-Status in der DB (erlaubt Invalidierung anderer Sessions bei Passwort-Ã„nderung)
if ($is_logged_in && $current_user_id) {
    $stmt_check = $conn->prepare("SELECT session_version, deleted FROM users WHERE id = ? LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param('i', $current_user_id);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        $u = $res->fetch_assoc();
        $stmt_check->close();
        if ($u) {
            // Wenn Benutzer soft-deleted wurde, abmelden
            if (isset($u['deleted']) && $u['deleted']) {
                // LÃ¶sche Session und leite zur Anmeldung
                session_unset(); session_destroy();
                set_flash_message('error_user_not_found', 'error');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
            // Session-Version prÃ¼fen
            $session_version_in_session = isset($_SESSION['session_version']) ? (int)$_SESSION['session_version'] : 0;
            $session_version_in_db = isset($u['session_version']) ? (int)$u['session_version'] : 0;
            if ($session_version_in_db !== $session_version_in_session) {
                session_unset(); session_destroy();
                set_flash_message('error_session_invalidated', 'info');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
        }
    }
}

// Config laden (enthÃ¤lt DEBUG_MODE Definition)
// __DIR__ zeigt auf das /config Verzeichnis
require_once __DIR__ . '/config.php';

// Fehler-Reporting basierend auf DEBUG_MODE steuern
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    // Im Debug-Modus: Fehler im Browser anzeigen
    ini_set('display_errors', 1);         // ANZEIGEN
    ini_set('display_startup_errors', 1); // ANZEIGEN (fÃ¼r Fehler beim PHP-Start)
    error_reporting(E_ALL);               // Alle Fehlertypen melden
} else {
    // Im Live-Betrieb: Fehler im Browser unterdrÃ¼cken
    ini_set('display_errors', 0);         // AUSBLENDEN
    ini_set('display_startup_errors', 0); // AUSBLENDEN
    error_reporting(E_ALL);               // Weiterhin alle Fehler loggen!
    // Stelle sicher, dass dein Server so konfiguriert ist, dass er Fehler in eine Datei loggt!
    // ini_set('log_errors', 1); // Normalerweise schon Standard
    // ini_set('error_log', '/pfad/zu/deinem/server/php-error.log'); // Pfad je nach Server
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin'); // Oder deine Zeitzone

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    // Optional: Sicherere Session-Einstellungen (siehe frÃ¼here Kommentare)
    session_start();
}

// Globale sprachunabhÃ¤ngige Kernfunktionen laden
require_once __DIR__ . '/functions_core.php';

// Composer Autoloader laden
$composer_autoload_available = true;
try {
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Exception $e) {
    // Composer detected incompatible platform (z.B. alte PHP-Version).
    error_log('Composer autoload failed: ' . $e->getMessage());
    $composer_autoload_available = false;
}
// Falls PHPMailer nicht automatisch geladen wurde, aber das Paket vorhanden ist,
// versuchen wir direkte Includes, damit SMTP sofort verfÃ¼gbar ist.
if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
}

// Zwei-Faktor-Auth laden: Fallback immer verfÃ¼gbar; wenn Composer verfÃ¼gbar ist, verwende die "bessere" Implementation
$twofactor_available = false;
// Fallback definieren (funktioniert ohne Composer/PHP >= 8.1)
require_once __DIR__ . '/twofactor_fallback.php';
if (function_exists('tf_verify_code')) {
    $twofactor_available = true;
}

// Wenn Composer Autoload verfÃ¼gbar ist, versuche zusÃ¤tzliche Implementation zu laden (falls vorhanden)
if ($composer_autoload_available) {
    // twofactor.php nutzt die Composer-Bibliothek falls mÃ¶glich; sie ist optional
    if (file_exists(__DIR__ . '/twofactor.php')) {
        require_once __DIR__ . '/twofactor.php';
        // Wenn die bessere Implementierung genutzt werden kann, setze Flag
        if (function_exists('tf_verify_code')) $twofactor_available = true;
    }
}

// Sprach-Funktionen frÃ¼h laden, falls $current_language gesetzt ist (damit Seiten wie all_files.php lang() vor dem Header verwenden kÃ¶nnen)
if (!empty($current_language)) {
    // Nur Deutsch unterstÃ¼tzen - andere Sprachen auf Deutsch umleiten
    if ($current_language !== 'de') {
        $current_language = 'de';
    }
    $lang_file = __DIR__ . '/../' . $current_language . '/includes/functions_lang.php';
    if (file_exists($lang_file)) require_once $lang_file;
}

// --- Datenbankverbindung herstellen ---
global $conn;
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    $errorMessage = DEBUG_MODE ? "DB Connection Error: " . $conn->connect_error : "Database connection failed.";
    error_log("DB Connection Error: " . $conn->connect_error);
    // Im Live-Betrieb sollte hier eine freundliche Fehlerseite angezeigt werden.
    die($errorMessage);
}

if (!$conn->set_charset("utf8")) {
    error_log("Error loading character set utf8: " . $conn->error);
}

// CSRF Token fÃ¼r die Session generieren/sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

// Globale Variablen fÃ¼r Benutzerinformationen fÃ¼r einfachen Zugriff bereitstellen
$is_logged_in = is_logged_in();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$current_username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$is_admin = is_admin(); // PrÃ¼ft 'admin' oder 'owner'

// PrÃ¼fe Session-Version und Soft-Delete-Status in der DB (erlaubt Invalidierung anderer Sessions bei Passwort-Ã„nderung)
if ($is_logged_in && $current_user_id) {
    $stmt_check = $conn->prepare("SELECT session_version, deleted FROM users WHERE id = ? LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param('i', $current_user_id);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        $u = $res->fetch_assoc();
        $stmt_check->close();
        if ($u) {
            // Wenn Benutzer soft-deleted wurde, abmelden
            if (isset($u['deleted']) && $u['deleted']) {
                // LÃ¶sche Session und leite zur Anmeldung
                session_unset(); session_destroy();
                set_flash_message('error_user_not_found', 'error');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
            // Session-Version prÃ¼fen
            $session_version_in_session = isset($_SESSION['session_version']) ? (int)$_SESSION['session_version'] : 0;
            $session_version_in_db = isset($u['session_version']) ? (int)$u['session_version'] : 0;
            if ($session_version_in_db !== $session_version_in_session) {
                session_unset(); session_destroy();
                set_flash_message('error_session_invalidated', 'info');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
        }
    }
}
if (DEBUG_MODE) {    // Im Debug-Modus: Fehler im Browser anzeigen
    ini_set('display_errors', 1);         // ANZEIGEN
    ini_set('display_startup_errors', 1); // ANZEIGEN (fÃ¼r Fehler beim PHP-Start)
    error_reporting(E_ALL);               // Alle Fehlertypen melden
} else {
    // Im Live-Betrieb: Fehler im Browser unterdrÃ¼cken
    ini_set('display_errors', 0);         // AUSBLENDEN
    ini_set('display_startup_errors', 0); // AUSBLENDEN
    error_reporting(E_ALL);               // Weiterhin alle Fehler loggen!
    // Stelle sicher, dass dein Server so konfiguriert ist, dass er Fehler in eine Datei loggt!
    // ini_set('log_errors', 1); // Normalerweise schon Standard
    // ini_set('error_log', '/pfad/zu/deinem/server/php-error.log'); // Pfad je nach Server
}

// Zeitzone setzen
date_default_timezone_set('Europe/Berlin'); // Oder deine Zeitzone

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    // Optional: Sicherere Session-Einstellungen (siehe frÃ¼here Kommentare)
    session_start();
}

// Globale sprachunabhÃ¤ngige Kernfunktionen laden
require_once __DIR__ . '/functions_core.php';

// Composer Autoloader laden
$composer_autoload_available = true;
try {
    require_once __DIR__ . '/../vendor/autoload.php';
} catch (Exception $e) {
    // Composer detected incompatible platform (z.B. alte PHP-Version).
    error_log('Composer autoload failed: ' . $e->getMessage());
    $composer_autoload_available = false;
}
// Falls PHPMailer nicht automatisch geladen wurde, aber das Paket vorhanden ist,
// versuchen wir direkte Includes, damit SMTP sofort verfÃ¼gbar ist.
if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer') && file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    @require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
}

// Zwei-Faktor-Auth laden: Fallback immer verfÃ¼gbar; wenn Composer verfÃ¼gbar ist, verwende die "bessere" Implementation
$twofactor_available = false;
// Fallback definieren (funktioniert ohne Composer/PHP >= 8.1)
require_once __DIR__ . '/twofactor_fallback.php';
if (function_exists('tf_verify_code')) {
    $twofactor_available = true;
}

// Wenn Composer Autoload verfÃ¼gbar ist, versuche zusÃ¤tzliche Implementation zu laden (falls vorhanden)
if ($composer_autoload_available) {
    // twofactor.php nutzt die Composer-Bibliothek falls mÃ¶glich; sie ist optional
    if (file_exists(__DIR__ . '/twofactor.php')) {
        require_once __DIR__ . '/twofactor.php';
        // Wenn die bessere Implementierung genutzt werden kann, setze Flag
        if (function_exists('tf_verify_code')) $twofactor_available = true;
    }
}

// Sprach-Funktionen frÃ¼h laden, falls $current_language gesetzt ist (damit Seiten wie all_files.php lang() vor dem Header verwenden kÃ¶nnen)
if (!empty($current_language)) {
    // Nur Deutsch unterstÃ¼tzen - andere Sprachen auf Deutsch umleiten
    if ($current_language !== 'de') {
        $current_language = 'de';
    }
    $lang_file = __DIR__ . '/../' . $current_language . '/includes/functions_lang.php';
    if (file_exists($lang_file)) require_once $lang_file;
}

// --- Datenbankverbindung herstellen ---
global $conn;
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    $errorMessage = DEBUG_MODE ? "DB Connection Error: " . $conn->connect_error : "Database connection failed.";
    error_log("DB Connection Error: " . $conn->connect_error);
    // Im Live-Betrieb sollte hier eine freundliche Fehlerseite angezeigt werden.
    die($errorMessage);
}

if (!$conn->set_charset("utf8")) {
    error_log("Error loading character set utf8: " . $conn->error);
}

// CSRF Token fÃ¼r die Session generieren/sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generate_csrf_token();
}

// Globale Variablen fÃ¼r Benutzerinformationen fÃ¼r einfachen Zugriff bereitstellen
$is_logged_in = is_logged_in();
$current_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$current_username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
$current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
$is_admin = is_admin(); // PrÃ¼ft 'admin' oder 'owner'

// PrÃ¼fe Session-Version und Soft-Delete-Status in der DB (erlaubt Invalidierung anderer Sessions bei Passwort-Ã„nderung)
if ($is_logged_in && $current_user_id) {
    $stmt_check = $conn->prepare("SELECT session_version, deleted FROM users WHERE id = ? LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param('i', $current_user_id);
        $stmt_check->execute();
        $res = $stmt_check->get_result();
        $u = $res->fetch_assoc();
        $stmt_check->close();
        if ($u) {
            // Wenn Benutzer soft-deleted wurde, abmelden
            if (isset($u['deleted']) && $u['deleted']) {
                // LÃ¶sche Session und leite zur Anmeldung
                session_unset(); session_destroy();
                set_flash_message('error_user_not_found', 'error');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
            // Session-Version prÃ¼fen
            $session_version_in_session = isset($_SESSION['session_version']) ? (int)$_SESSION['session_version'] : 0;
            $session_version_in_db = isset($u['session_version']) ? (int)$u['session_version'] : 0;
            if ($session_version_in_db !== $session_version_in_session) {
                session_unset(); session_destroy();
                set_flash_message('error_session_invalidated', 'info');
                redirect((isset($current_language) ? $current_language : 'de') . '/login');
            }
        }
    }
}

?>