<?php
// /de/admin_migrate_files.php
require_once __DIR__ . '/../config/bootstrap.php';

// Sicherheit: Nur für Admins
if (!$is_admin) {
    die("<h1>Zugriff verweigert</h1><p>Diese Seite ist nur für Administratoren zugänglich.</p>");
}

echo "<h1>Migration alter Dateien</h1>";

// Sicherstellen, dass die Spalte existiert
$check_col = $conn->query("SHOW COLUMNS FROM files LIKE 'physical_path'");
if ($check_col && $check_col->num_rows === 0) {
    echo "<p>Lege Datenbank-Spalte 'physical_path' an...</p>";
    if ($conn->query("ALTER TABLE files ADD COLUMN physical_path VARCHAR(255) DEFAULT NULL")) {
        echo "<p style='color:green;'>Spalte erfolgreich angelegt!</p>";
    } else {
        die("<p style='color:red;'>Fehler beim Anlegen der Spalte: " . $conn->error . "</p>");
    }
}

echo "<p>Starte Migration auf die neue, zufällige Speicherarchitektur...</p>";

// Lade alle Dateien, die noch keinen physical_path haben
$query = "SELECT id, filename, uploader_id FROM files WHERE physical_path IS NULL OR physical_path = ''";
$result = $conn->query($query);

if (!$result) {
    die("<p style='color:red;'>Datenbankfehler: " . $conn->error . "</p>");
}

if ($result->num_rows === 0) {
    echo "<p style='color:green;'>Alle Dateien sind bereits auf dem neuesten Stand! Keine Migration erforderlich.</p>";
    exit;
}

echo "<ul>";
$success_count = 0;
$error_count = 0;

while ($file = $result->fetch_assoc()) {
    $file_id = $file['id'];
    $filename = $file['filename'];
    $uploader_id = $file['uploader_id'];
    
    // Ermittle den aktuellen Ordner des Users
    $user_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . $uploader_id;
    
    // Prüfen, ob die Datei im neuen Format (ID_Name) oder ganz alten Format (Name) existiert
    $old_filepath_id = $user_dir . '/' . $file_id . '_' . basename($filename);
    $old_filepath_no_id = $user_dir . '/' . basename($filename);
    
    $current_filepath = null;
    if (file_exists($old_filepath_id)) {
        $current_filepath = $old_filepath_id;
    } elseif (file_exists($old_filepath_no_id)) {
        $current_filepath = $old_filepath_no_id;
    } else {
        // Fallback: Suche nach einer Datei, die mit ID_ beginnt (falls die Datei im UI umbenannt wurde!)
        $matches = glob($user_dir . '/' . $file_id . '_*');
        if (!empty($matches) && is_file($matches[0])) {
            $current_filepath = $matches[0];
        }
    }
    
    if (!$current_filepath) {
        echo "<li style='color:red;'>Fehler (ID {$file_id}): Physische Datei auf dem Server nicht gefunden ('{$filename}'). DB-Eintrag wird übersprungen.</li>";
        $error_count++;
        continue;
    }
    
    // Generiere den neuen, sicheren Zufallspfad (PHP 5.x kompatibel)
    $rand1 = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $rand2 = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $rand3 = md5(uniqid(mt_rand(), true));
    if ($ext) {
        $rand3 .= '.' . $ext;
    }
    
    $physical_path = $rand1 . '/' . $rand2 . '/' . $rand3;
    $new_filepath = $user_dir . '/' . $physical_path;
    
    // Stelle sicher, dass die neuen Ordner existieren
    $dir_path = dirname($new_filepath);
    if (!is_dir($dir_path)) {
        if (!mkdir($dir_path, 0755, true)) {
            echo "<li style='color:red;'>Fehler (ID {$file_id}): Konnte Unterordner nicht erstellen.</li>";
            $error_count++;
            continue;
        }
    }
    
    // Datei verschieben (umbenennen)
    if (rename($current_filepath, $new_filepath)) {
        // Pfad in der Datenbank speichern
        $stmt = $conn->prepare("UPDATE files SET physical_path = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $physical_path, $file_id);
            if ($stmt->execute()) {
                echo "<li style='color:green;'>Erfolg (ID {$file_id}): '{$filename}' migriert nach '{$physical_path}'.</li>";
                $success_count++;
            } else {
                echo "<li style='color:orange;'>Warnung (ID {$file_id}): Datei verschoben, aber DB-Update schlug fehl (" . $stmt->error . ").</li>";
                $error_count++;
            }
            $stmt->close();
        } else {
            echo "<li style='color:red;'>Fehler (ID {$file_id}): DB Prepare schlug fehl.</li>";
            $error_count++;
        }
    } else {
        echo "<li style='color:red;'>Fehler (ID {$file_id}): Konnte physische Datei nicht verschieben ('{$current_filepath}' nach '{$new_filepath}').</li>";
        $error_count++;
    }
}

echo "</ul>";

echo "<h2>Zusammenfassung</h2>";
echo "<p><strong>Erfolgreich migriert:</strong> {$success_count}</p>";
echo "<p><strong>Fehler:</strong> {$error_count}</p>";

if ($error_count === 0 && $success_count > 0) {
    echo "<p style='color:green; font-weight:bold;'>Alle Altlasten wurden erfolgreich beseitigt!</p>";
}

echo "<br><br><a href='dashboard' class='button button-primary'>Zurück zum Dashboard</a>";
?>
