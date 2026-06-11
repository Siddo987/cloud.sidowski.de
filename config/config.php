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
define('APP_NAME', 'Datei Wolke');
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
 * 1. PrÃ¼fe, ob dieser Pfad auf deinem Server KORREKT ist!
 * `dirname(__DIR__)` zeigt auf das Projekt-Hauptverzeichnis.
 * Der Pfad hier sollte also zu `/www/htdocs/w01f392f/cloud.sidowski.de/user_uploads` fÃ¼hren.
 * 2. Existiert das Verzeichnis `/user_uploads` im Hauptverzeichnis? Wenn nicht, erstellen!
 * 3. Hat der Webserver-Benutzer SCHREIB- und AUSFÃœHRUNGSRECHTE fÃ¼r dieses `user_uploads`-Verzeichnis?
 * -> `chmod 755 user_uploads` oder `chmod 775 user_uploads` auf dem Server ausfÃ¼hren.
 */
define('USER_UPLOAD_DIR', dirname(__DIR__) . '/user_uploads');


// Erlaubte Dateiendungen (Kleinbuchstaben!)
$ALLOWED_FILE_TYPES = [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'txt', 'rtf', 'csv',
    'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico',
    'mp3', 'wav', 'ogg', 'm4a',
    'mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm',
    'zip', 'rar', '7z', 'tar', 'gz'
];
// Max. Upload-GrÃ¶ÃŸe pro Datei in Bytes (100MB). Muss <= PHP-Limits sein!
define('MAX_FILE_SIZE', 100 * 1024 * 1024);


?>
