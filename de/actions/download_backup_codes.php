<?php
// /de/actions/download_backup_codes.php
$current_language = 'de';
require_once __DIR__ . '/../../config/bootstrap.php';

if (!$is_logged_in) {
    redirect($current_language . '/login');
}

// Prüfen ob Backup-Codes in der Session liegen (die frisch generierten)
if (empty($_SESSION['pending_backup_codes']) || !is_array($_SESSION['pending_backup_codes'])) {
    set_flash_message('error_no_backup_codes', 'error');
    redirect($current_language . '/profil');
}

$codes = $_SESSION['pending_backup_codes'];

// Sobald sie abgerufen werden, aus der Session löschen (nur 1x Downloadbar!)
unset($_SESSION['pending_backup_codes']);

// Ausgabe als .txt Download
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="datei-wolke-backup-codes.txt"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "Backup-Codes für Datei Wolke\n";
echo "Benutzername: " . $current_username . "\n";
echo "Datum: " . date('d.m.Y H:i') . "\n";
echo str_repeat("-", 40) . "\n\n";

foreach ($codes as $index => $code) {
    echo ($index + 1) . ". " . $code . "\n";
}

echo "\n" . str_repeat("-", 40) . "\n";
echo "WICHTIG:\n";
echo "- Jeder Code kann nur genau EINMAL beim Login verwendet werden.\n";
echo "- Bewahre diese Datei an einem sicheren Ort auf.\n";
echo "- Wenn du neue Backup-Codes generierst, werden diese hier ungültig.\n";

exit();
