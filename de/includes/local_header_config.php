<?php
// /de/includes/local_header_config.php
// Wird von /includes/header.php geladen, nachdem $current_script definiert wurde.

// Zugriff auf die globale Variable $pageTitle, die dann im Header verwendet wird.
global $pageTitle;

// Nutze den Namen des aktuellen Skripts, um den passenden Titel zu finden.
switch ($current_script) {
    case 'login.php':
        $pageTitle = lang('title_login'); // Holt 'Anmeldung' aus functions_lang.php
        break;
    case 'register.php':
        $pageTitle = lang('title_register'); // Holt 'Registrierung'
        break;
    case 'dashboard.php':
        $pageTitle = lang('title_dashboard'); // Holt 'Dashboard'
        break;
    case 'upload.php':
        $pageTitle = lang('title_upload'); // Holt 'Datei hochladen'
        break;
    case 'own_files.php':
        $pageTitle = lang('title_my_files'); // Holt 'Meine Dateien'
        break;
    case 'public_files.php':
        $pageTitle = lang('title_public_files'); // Holt 'Öffentliche Dateien'
        break;
    case 'all_files.php':
        $pageTitle = lang('title_all_files'); // Holt 'Alle Dateien (Admin)'
        break;
    case 'all_users.php':
         $pageTitle = lang('title_all_users'); // Holt 'Benutzerverwaltung'
         break;
    case 'view_file.php':
         $pageTitle = lang('title_file_view'); // Holt 'Dateiansicht'
         break;
    // Füge hier weitere Fälle für zukünftige Seiten hinzu...
    // Beispiel:
    // case 'my_account.php':
    //     $pageTitle = lang('title_my_account');
    //     break;

    default:
        // Fallback-Titel, wenn kein spezifischer Titel gefunden wurde.
        // Verwendet den App-Namen aus der Konfiguration.
        $pageTitle = defined('APP_NAME') ? APP_NAME : 'Datei Wolke';
}
?>