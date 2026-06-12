<?php
// /de/includes/functions_lang.php (ERGÄNZT)

// --- Sprach-Strings für Deutsch ---
const LANG_STRINGS_DE = [
    // Navigation & Titel
    'nav_dashboard' => 'Dashboard', 'nav_my_files' => 'Meine Dateien', 'nav_public_files' => 'Öffentliche Dateien',
    'nav_all_files' => 'Alle Dateien', 'nav_all_users' => 'Benutzer', 'nav_login' => 'Anmelden', 'nav_register' => 'Registrieren',
    'title_login' => 'Anmeldung', 'title_register' => 'Registrierung', 'title_dashboard' => 'Dashboard', 'title_upload' => 'Datei hochladen',
    'title_my_files' => 'Meine Dateien', 'title_public_files' => 'Öffentliche Dateien', 'title_all_files' => 'Alle Dateien (Admin)',
    'title_all_users' => 'Benutzerverwaltung', 'title_edit_user' => 'Benutzer bearbeiten', 'title_file_view' => 'Dateiansicht', 'title_profile' => 'Mein Profil', // ERGÄNZT
    'title_error' => 'Fehler',
    'nav_files' => 'Dateien', // NEU
    'nav_settings' => 'Einstellungen', // NEU (im Code nicht verwendet, könnte für Profil-Dropdown genutzt werden)
    'nav_profile' => 'Profil', // NEU
    'nav_admin' => 'Verwaltung', // NEU (für Admin-Dropdown)

    // Buttons
    'button_upload' => 'Hochladen', 'button_login' => 'Anmelden', 'button_register' => 'Registrieren', 'button_logout' => 'Abmelden',
    'button_toggle_dark_mode' => 'Dunkelmodus umschalten', 'button_search' => 'Suchen', 'button_save_changes' => 'Änderungen speichern',
    'button_change_password' => 'Passwort ändern', 'button_send_verification' => 'Verifizierungs-E-Mail senden', 'button_delete_account' => 'Konto löschen', 'button_make_public' => 'Veröffentlichen',
    'button_make_private' => 'Privat machen', 'button_delete' => 'Löschen', 'button_download' => 'Herunterladen',
    'button_view' => 'Ansehen', 'button_submit' => 'Absenden', 'button_back_dashboard' => 'Zurück zum Dashboard',
    'button_send_reset_code' => 'Reset-Code senden',
    'button_disable_2fa' => '2FA deaktivieren',
    'button_impersonate_user' => 'Als Benutzer anmelden',
    'button_upload_selected' => 'Ausgewählte hochladen',
    'button_login_webauthn' => 'Mit WebAuthn anmelden',

    // Formular Labels & Platzhalter
    'label_username' => 'Benutzername oder E-Mail:', 'label_password' => 'Passwort:', 'label_confirm_password' => 'Passwort bestätigen:',
    'label_new_password' => 'Neues Passwort:', 'label_confirm_new_password' => 'Neues Passwort bestätigen:', 'label_current_password' => 'Aktuelles Passwort:',
    'label_email' => 'E-Mail (optional):', 'label_role' => 'Rolle:', 'label_unlimited_upload' => 'Unbegrenzte Uploadgröße?', 'label_selected_files' => 'Ausgewählte Dateien:',
    'placeholder_search_files' => 'Dateien suchen...', 'placeholder_search_users' => 'Benutzer suchen...',
    'placeholder_search_files_users' => 'Dateiname oder Uploader suchen...',

    // Tabellenüberschriften
    'th_filename' => 'Dateiname', 'th_upload_date' => 'Uploaddatum', 'th_uploader' => 'Uploader', 'th_status' => 'Status',
    'th_actions' => 'Aktionen', 'th_size' => 'Größe', 'th_user_id' => 'ID', 'th_username' => 'Benutzername', 'th_email' => 'E-Mail',
    'th_role' => 'Rolle',

    // Status & Rollen
    'status_public' => 'Öffentlich', 'status_private' => 'Privat', 'role_user' => 'Benutzer', 'role_mod' => 'Moderator', 'role_admin' => 'Admin', 'role_owner' => 'Owner', 'role_root' => 'Root',

    // Nachrichten & Hinweise
    'text_no_files_found' => 'Keine Dateien gefunden.', 'text_no_files_found_search' => 'Keine Dateien für Ihre Suche gefunden.',
    'text_no_users_found' => 'Keine Benutzer gefunden.', 'text_no_users_found_search' => 'Keine Benutzer für Ihre Suche gefunden.',
    'text_public' => 'Öffentlich', 'text_private' => 'Privat', 'text_unknown_user' => 'Unbekannt', 'text_error' => 'Fehler',
    'text_confirm_delete_file' => 'Sind Sie sicher, dass Sie die Datei \'%s\' unwiderruflich löschen möchten?',
    'text_confirm_delete_user' => 'Sind Sie sicher, dass Sie den Benutzer \'%s\' unwiderruflich löschen möchten?',
    'text_drop_zone' => 'Dateien hierher ziehen oder klicken', 'text_no_files_selected' => 'Keine Dateien ausgewählt.',
    'text_max_file_size' => 'Max. Größe pro Datei: %s', 'text_no_account' => 'Noch kein Konto?',
    'text_already_have_account' => 'Bereits ein Konto?', 'text_login_now' => 'Jetzt anmelden',
    'preview_not_available' => 'Vorschau für diesen Dateityp nicht verfügbar.',
    'preview_not_supported_type' => 'Vorschau für Dateityp "%s" nicht direkt unterstützt.',
    'info_already_private' => 'Datei ist bereits privat.',

    // Footer Links
    'link_imprint' => 'Impressum', 'link_privacy' => 'Datenschutz',

    // Allgemeine Fehlermeldungen
    'error_db_error' => 'Ein Datenbankfehler ist aufgetreten.', 'error_db_prepare' => 'Fehler bei der Vorbereitung der Datenbankabfrage.',
    'error_db_connect' => 'Fehler bei der Verbindung zur Datenbank.', 'error_db_update' => 'Fehler beim Aktualisieren der Datenbank.',
    'error_db_insert' => 'Fehler beim Speichern in der Datenbank.', 'error_db_delete' => 'Fehler beim Löschen aus der Datenbank.',
    'error_db_fetch_dashboard' => 'Fehler beim Laden der Dashboard-Daten.',
    'error_invalid_id' => 'Ungültige ID übergeben.', 'error_invalid_data' => 'Ungültige Daten übermittelt.', 'error_invalid_backup_code' => 'Der eingegebene Backup-Code ist ungültig oder wurde bereits verwendet.',
    'error_invalid_token' => 'Ungültiger oder abgelaufener Token.',
    'error_invalid_file_id' => 'Ungültige Datei-ID.', 'error_file_not_found' => 'Datei nicht gefunden.',
    'error_user_not_found' => 'Benutzer nicht gefunden.', 'error_no_permission' => 'Keine Berechtigung für diese Aktion.',
    'error_cooldown_active' => 'Bitte warte einen Moment, bevor du eine neue Aktion ausführst.',
    'error_registration_failed' => 'Fehler bei der Registrierung. Bitte versuche es später noch einmal.',
    'error_no_permission_download_file' => 'Keine Berechtigung zum Herunterladen dieser Datei.',
    'error_csrf_token_invalid' => 'Sicherheitsüberprüfung fehlgeschlagen. Bitte versuchen Sie es erneut.',
    'error_all_fields_required' => 'Bitte füllen Sie alle Pflichtfelder aus.',
    'error_session_invalidated' => 'Deine Sitzung wurde abgemeldet. Bitte melde dich erneut an.',

    // Login/Register Fehlermeldungen
    'error_login_failed' => 'Anmeldung fehlgeschlagen: Benutzername oder Passwort falsch.', 'error_missing_credentials' => 'Bitte Benutzername und Passwort eingeben.',
    'error_passwords_dont_match' => 'Die Passwörter stimmen nicht überein.', 'error_password_too_short' => 'Das Passwort muss mindestens 8 Zeichen lang sein.',
    'error_username_taken' => 'Der Benutzername ist bereits vergeben.', 'error_registration_failed' => 'Registrierung fehlgeschlagen.',
    'error_no_email' => 'Keine E-Mail-Adresse zugewiesen.',

    // Upload Fehlermeldungen
    'error_upload_basedir_not_found' => 'FATAL: Basis-Upload-Verzeichnis nicht gefunden!', 'error_upload_basedir_permission' => 'FATAL: Keine Schreibrechte im Basis-Upload-Verzeichnis!',
    'error_upload_dir_creation' => 'Fehler beim Erstellen des Upload-Verzeichnisses.', 'error_upload_dir_permission' => 'Keine Schreibrechte im Upload-Verzeichnis.',
    'error_upload_cannot_proceed' => 'Upload kann aufgrund von Serverproblemen nicht fortgesetzt werden.',
    'error_file_too_large_php' => 'Datei "%s" überschreitet die server-seitige Größenbeschränkung.', 'error_upload_partial' => 'Datei "%s" wurde nur teilweise hochgeladen.',
    'error_upload_unknown' => 'Unbekannter Fehler beim Upload von Datei "%s".', 'error_upload_invalid_name' => 'Ungültiger oder leerer Dateiname beim Upload.',
    'error_upload_empty_file' => 'Datei "%s" ist leer und wurde ignoriert.', 'error_file_too_large' => 'Datei "%s" ist zu groß (Max: %s).',
    'error_invalid_file_type' => 'Dateityp "%2$s" von Datei "%1$s" ist nicht erlaubt.', 'error_file_exists_rename' => 'Datei "%s" existiert bereits und konnte nicht sinnvoll umbenannt werden.',
    'error_db_insert_file' => 'Fehler beim DB-Eintrag für Datei "%s".', 'error_db_prepare_file' => 'Fehler beim DB-Prepare für Datei "%s".',
    'error_failed_to_move_file' => 'Datei "%s" konnte nicht gespeichert werden.', 'error_no_files_selected' => 'Keine Dateien zum Hochladen ausgewählt.',

    // User Management Fehlermeldungen
    'error_cannot_change_own_role' => 'Sie können Ihre eigene Rolle nicht ändern.', 'error_permission_make_owner' => 'Nur ein Owner kann andere Benutzer zum Owner ernennen.',
    'error_permission_make_admin' => 'Nur ein Owner kann andere Benutzer zum Admin ernennen.', 'error_cannot_delete_self' => 'Sie können Ihr eigenes Konto nicht löschen.',
    'error_cannot_delete_owner' => 'Owner-Konten können nicht gelöscht werden.', 'error_permission_delete_admin' => 'Nur ein Owner kann Admin-Konten löschen.',

    // Datei/Vorschau Fehlermeldungen
    'error_file_physical_not_found' => 'Die Datei existiert nicht auf dem Server oder kann nicht gelesen werden.', 'error_mime_type_detection' => 'Fehler beim Bestimmen des Dateityps.',
    'error_reading_file' => 'Fehler beim Lesen des Datei-Inhalts.',

    // Erfolgsmeldungen
    'success_registration' => 'Registrierung erfolgreich. Bitte bestätige ggf. deine E-Mail.', 'success_login' => 'Anmeldung erfolgreich.', 'success_logout' => 'Abmeldung erfolgreich.',
    'success_upload_single' => 'Datei erfolgreich hochgeladen.', 'success_upload_multi' => '%d Dateien erfolgreich hochgeladen.',
    'success_file_deleted' => 'Datei \'%s\' erfolgreich gelöscht.',
    'success_user_deleted' => 'Benutzer \'%s\' erfolgreich gelöscht.',
    'success_status_changed' => 'Dateistatus erfolgreich geändert.', 'success_role_changed' => 'Benutzerrolle erfolgreich geändert.',
    'success_password_changed' => 'Passwort erfolgreich geändert.',
    'success_temp_code_sent' => 'Temporärer Code per E-Mail versandt.', 'success_username_changed' => 'Benutzername erfolgreich geändert.',
    'success_impersonation_started' => 'Impersonation aktiviert (30 Minuten).',
    'success_impersonation_ended' => 'Impersonation beendet. Du bist wieder als Administrator angemeldet.',
    'success_password_reset' => 'Passwort erfolgreich zurückgesetzt.',
    'link_forgot_password' => 'Passwort vergessen?',
    'success_email_verification_sent' => 'E-Mail zur Bestätigung wurde an %s gesendet.', 'success_email_verified' => 'E-Mail-Adresse erfolgreich bestätigt.', 'success_email_changed' => 'E-Mail-Adresse erfolgreich geändert.', 'success_email_change_requested' => 'E-Mail-Änderung angefordert. Überprüfe deine neue E-Mail-Adresse für den Bestätigungslink.',
    'error_email_verification_invalid' => 'Ungültiger oder abgelaufener Bestätigungslink.', 'error_email_already_taken' => 'Diese E-Mail-Adresse wird bereits verwendet.',
    'info_email_not_set' => 'Keine E-Mail hinterlegt.', 'info_email_unverified' => 'E-Mail ist noch nicht bestätigt.', 'info_email_verified' => 'E-Mail-Adresse bestätigt.',
    'success_test_mail_sent' => 'Testmail erfolgreich an %s gesendet.', 'error_test_mail_failed' => 'Testmail konnte nicht gesendet werden.',
    'test_mail_title' => 'Mail‑Diagnose & Test',
    'label_test_mail_to' => 'Empfänger‑E‑Mail:',
    'button_send_test_mail' => 'Testmail senden',
    'info_sendmail_path' => 'sendmail_path: %s',
    'info_phpmailer_available' => 'PHPMailer verfügbar: %s',

    // 2FA Nachrichten & Hinweise
    'info_2fa_email_sent' => 'Ein Sicherheitscode wurde per E‑Mail an deine Adresse gesendet.',
    'info_2fa_setup_email_sent' => 'Ein Bestätigungslink zur Aktivierung der 2‑Faktor‑Authentifizierung wurde an deine E‑Mail gesendet. Öffne den Link auf deinem Mobilgerät, um die Einrichtung abzuschließen.',
    'info_2fa_setup_code_sent' => 'Ein 6‑stelliger Bestätigungscode wurde an deine E‑Mail gesendet. Gib ihn hier auf der Seite ein.',
    'info_2fa_disabled_due_to_email_removal' => '2FA wurde deaktiviert, da die E-Mail-Adresse entfernt wurde.',
    'success_2fa_code_resent' => 'Bestätigungscode erneut versandt.',
    'error_2fa_no_active_token' => 'Kein aktiver Bestätigungscode vorhanden. Bitte starte den Vorgang neu.',
    'error_2fa_resend_wait' => 'Bitte warte kurz, bevor du den Code erneut anforderst.',
    'error_2fa_resend_limit' => 'Zu viele Anfragen. Versuche es später erneut.',
    'error_2fa_mail_failed' => 'Eine Bestätigungs‑E‑Mail konnte nicht versandt werden. Prüfe die Mail‑Konfiguration oder versuche es später.',
    'error_totp_invalid' => 'Der eingegebene Bestätigungscode ist ungültig. Bitte überprüfe deine Authenticator‑App und versuche es erneut.',
    'error_totp_expired' => 'Die TOTP-Session ist abgelaufen. Bitte starte die Einrichtung neu.',
    'error_2fa_invalid' => 'Der eingegebene Code ist ungültig. Bitte versuche es erneut.',
    'error_totp_unavailable' => 'TOTP (Authenticator‑App) ist auf diesem System derzeit nicht verfügbar.',
    'success_2fa_enabled' => 'Zwei‑Faktor‑Authentifizierung wurde aktiviert. Sichere deine Backup‑Codes.',
    'info_2fa_setup_pending' => '2FA wurde vorbereitet, aber noch nicht aktiviert. Teste die Anmeldung, um zu bestätigen.',
    'success_2fa_disabled' => 'Zwei‑Faktor‑Authentifizierung wurde deaktiviert.',
    'success_2fa_verified' => 'Zwei‑Faktor‑Authentifizierung bestätigt. Du bist jetzt angemeldet.',
    'success_2fa_email_resent' => 'Sicherheitscode erneut versandt. Überprüfe dein E-Mail-Postfach.',
    'error_2fa_email_cooldown' => 'Bitte warte mindestens %d Sekunde(n), bevor du den Code erneut anfordest.',
    'success_backup_codes_regen' => 'Neue Backup‑Codes wurden erzeugt. Sichere sie jetzt.',

    // Warnungen
    'warning_upload_partial' => '%d Datei(en) erfolgreich hochgeladen, aber bei %d Datei(en) gab es Probleme.',

    // Theme/Modus (NEU)
    'theme_mode' => 'Anzeigemodus',
    'theme_light' => 'Hell',
    'theme_dark' => 'Dunkel',
    'theme_system' => 'System folgen',
];

// --- Sprach-Funktionen ---
function lang($key, ...$args) {
    global $current_language; $lang_upper = strtoupper(isset($current_language) ? $current_language : 'DE'); $const_name = 'LANG_STRINGS_' . $lang_upper;
    if (defined($const_name) && isset(constant($const_name)[$key])) {
        $string = constant($const_name)[$key];
        if (!empty($args)) { $escaped_args = array_map(function($arg) { if (is_string($arg)) { return htmlspecialchars($arg, ENT_QUOTES, 'UTF-8'); } return $arg; }, $args); return vsprintf($string, $escaped_args); }
        return $string;
    } else { error_log("Sprachschlüssel '$key' nicht gefunden für Sprache '$lang_upper'."); return $key; }
}
function format_date_lang($date_string, $include_time = true) {
    try { if (empty($date_string) || $date_string === '0000-00-00 00:00:00') return '-'; $date = new DateTime($date_string); $now = new DateTime(); $today = new DateTime('today'); $yesterday = new DateTime('yesterday'); $time_format = $include_time ? ' H:i' : ''; $time_suffix = $include_time ? ' Uhr' : ''; if ($date >= $today) { return 'Heute' . ($include_time ? (', ' . $date->format('H:i') . $time_suffix) : ''); } elseif ($date >= $yesterday) { return 'Gestern' . ($include_time ? (', ' . $date->format('H:i') . $time_suffix) : ''); } else { return $date->format('d.m.Y' . $time_format) . $time_suffix; } } catch (Exception $e) { error_log(/*...*/); return 'Ungültig'; }
}
?>