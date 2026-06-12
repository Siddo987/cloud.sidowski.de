<?php
// /includes/header.php
// Annahme: bootstrap.php wurde bereits included

// Globale Variablen holen, die wir brauchen
global $current_language, $conn, $is_logged_in, $is_admin, $current_username;
if (empty($current_language)) {
    die("Fataler Fehler: Sprache wurde nicht vor dem Header definiert.");
}

// --- KORREKTE REIHENFOLGE ---
// 1. Aktuelles Skript bestimmen
$current_script = basename($_SERVER['PHP_SELF'], '.php');

// 2. Sprachspezifische Funktionen laden (definiert lang())
$lang_functions_path = dirname(__DIR__) . '/' . $current_language . '/includes/functions_lang.php';
if (file_exists($lang_functions_path)) {
    require_once $lang_functions_path; // Funktion lang() ist jetzt verfügbar
} else {
    die("Fataler Fehler: Sprachdatei nicht gefunden: " . htmlspecialchars($lang_functions_path));
}

// 3. Sprachspezifische Konfiguration laden (setzt $pageTitle)
$local_config_path = dirname(__DIR__) . '/' . $current_language . '/includes/local_header_config.php';
global $pageTitle;
if (file_exists($local_config_path)) {
    require $local_config_path; // Nutzt $current_script und kann jetzt lang() verwenden
} else {
     error_log("Hinweis: Lokale Konfigurationsdatei nicht gefunden: " . htmlspecialchars($local_config_path));
     $pageTitle = defined('APP_NAME') ? APP_NAME : 'Datei Wolke'; // Fallback
}
// --- ENDE KORREKTE REIHENFOLGE ---


// Seitentitel final aufbereiten
$pageTitle = isset($pageTitle) ? $pageTitle : (defined('APP_NAME') ? APP_NAME : 'Datei Wolke');
$htmlTitle = htmlspecialchars($pageTitle) . ' - ' . htmlspecialchars(APP_NAME);

// Pfade für Assets und Links
$base_path = defined('BASE_URL') && BASE_URL ? rtrim(BASE_URL, '/') : '';
// Stelle sicher, dass die Pfade relativ zum Root sind, wenn BASE_URL leer ist, oder absolut, wenn sie gesetzt ist.
$css_path = $base_path . '/css/styles.css';
$js_path = $base_path . '/js/main.js';
$logo_link = $base_path . '/' . $current_language . '/' . ($is_logged_in ? 'dashboard' : 'login');
$css_file_on_server = dirname(__DIR__) . '/css/styles.css'; // Pfad relativ zu dieser Datei zum CSS für filemtime
$js_file_on_server = dirname(__DIR__) . '/js/main.js'; // Pfad relativ zu dieser Datei zum JS für filemtime

?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($current_language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $htmlTitle; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css_path); ?>?v=<?php echo file_exists($css_file_on_server) ? filemtime($css_file_on_server) : time(); ?>">
    <script>
        // Verhindert ein "Flackern" der Farben beim Laden der Seite (Flash of Unstyled Content)
        (function() {
            try {
                var pref = localStorage.getItem('themePreference') || 'system';
                var themeToSet = pref;
                if (pref === 'system') {
                    var prefersDarkOS = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    themeToSet = prefersDarkOS ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', themeToSet);
            } catch (e) {
                console.error("Fehler beim Laden des Themes:", e);
            }
        })();
    </script>
</head>
<body>

    <header>
        <div class="container">
            <div class="logo">
                <a href="<?php echo htmlspecialchars($logo_link); ?>">
                    <?php echo htmlspecialchars(APP_NAME); ?>
                </a>
            </div>
            <nav>
                <?php // Lade passende Navigation ?>
                <?php if ($is_logged_in) include __DIR__ . '/nav_logged_in.php'; else include __DIR__ . '/nav_logged_out.php'; ?>
                <?php // Der Theme Toggle Button wurde hier entfernt ?>
            </nav>
        </div>
    </header>

    <main class="container"> <?php // Hauptcontainer für Seiteninhalt ?>

        <?php // Hier beginnt der spezifische Inhalt der jeweiligen Seite ?>