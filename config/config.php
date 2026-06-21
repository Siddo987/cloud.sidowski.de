<?php
// /config/config.php

// --- Datenbank Zugangsdaten ---
$DB_SERVER = getenv('DB_SERVER') ?: '';
$DB_USERNAME = getenv('DB_USERNAME') ?: '';
$DB_PASSWORD = getenv('DB_PASSWORD') ?: ''; // kein Klartext-Default
$DB_NAME = getenv('DB_NAME') ?: '';
define('DB_SERVER', $DB_SERVER);
define('DB_USERNAME', $DB_USERNAME);
define('DB_PASSWORD', $DB_PASSWORD);
define('DB_NAME', $DB_NAME);

// --- Anwendungseinstellungen ---
$app_name = getenv('APP_NAME');
define('APP_NAME', $app_name !== false ? $app_name : 'Datei Wolke');
// Basis-URL deiner Anwendung (Leer lassen fÃ¼r relative Pfade, oder z.B. 'https://cloud.sidowski.de')
// Wichtig fÃ¼r absolute URLs in Redirects etc. KEIN abschlieÃŸender Slash!
// BASE_URL aus ENV oder Fallback
define('BASE_URL', getenv('BASE_URL') ?: '');

/**
 * Debug Modus:
 * true: Zeigt detaillierte PHP-Fehler im Browser an (NUR fÃ¼r Entwicklung!).
 * false: UnterdrÃ¼ckt Fehleranzeige im Browser (fÃ¼r Live-Betrieb), Fehler sollten aber geloggt werden.
 */
// Debug Mode (aus ENV steuern, Default=false)
if (getenv('DEBUG_MODE') !== false) {
    define('DEBUG_MODE', filter_var(getenv('DEBUG_MODE'), FILTER_VALIDATE_BOOLEAN));
} else {
    define('DEBUG_MODE', false);
}
// Zwei-Faktor Debug-Flag: aktiviert zusÃ¤tzliche Debug-Ausgaben fÃ¼r TOTP (nur fÃ¼r Entwicklung)
if (!defined('TWOFACTOR_DEBUG')) {
    if (getenv('TWOFACTOR_DEBUG') !== false) define('TWOFACTOR_DEBUG', filter_var(getenv('TWOFACTOR_DEBUG'), FILTER_VALIDATE_BOOLEAN));
    else define('TWOFACTOR_DEBUG', false);
}
// Explizit aktivieren fÃ¼r Debugging
if (!defined('TWOFACTOR_DEBUG')) define('TWOFACTOR_DEBUG', true);

// --- Upload Einstellungen ---

/**
 * WICHTIG: Der Pfad zum Haupt-Upload-Verzeichnis.
 */
$env_upload_dir = getenv('USER_UPLOAD_DIR');
define('USER_UPLOAD_DIR', $env_upload_dir !== false && $env_upload_dir !== '' ? dirname(__DIR__) . '/' . $env_upload_dir : dirname(__DIR__) . '/user_uploads');


// Erlaubte Dateiendungen (Kleinbuchstaben!)
$env_allowed_types = getenv('ALLOWED_FILE_TYPES');
if ($env_allowed_types !== false && $env_allowed_types !== '') {
    $ALLOWED_FILE_TYPES = array_map('trim', explode(',', strtolower($env_allowed_types)));
} else {
    $ALLOWED_FILE_TYPES = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt', 'rtf', 'csv',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico',
        'mp3', 'wav', 'ogg', 'm4a',
        'mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm',
        'zip', 'rar', '7z', 'tar', 'gz'
    ];
}

// Max. Upload-Größe pro Datei in Bytes
$env_max_size = getenv('MAX_FILE_SIZE');
define('MAX_FILE_SIZE', $env_max_size !== false && is_numeric($env_max_size) ? (int)$env_max_size : 100 * 1024 * 1024);

// --- Kontakt Einstellungen ---
define('CONTACT_PHONE', getenv('CONTACT_PHONE') ?: '');
define('CONTACT_EMAIL', getenv('CONTACT_EMAIL') ?: '');
