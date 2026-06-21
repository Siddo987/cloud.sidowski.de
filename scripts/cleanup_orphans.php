<?php
// scripts/cleanup_orphans.php
// Bereinigt verwaiste Datenbankeinträge (ohne physische Datei) und leert den Papierkorb nach 90 Tagen.

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/../config/bootstrap.php';

$log_file = __DIR__ . '/../log/cleanup_orphans.log';

function write_log($msg) {
    global $log_file;
    $date = date('Y-m-d H:i:s');
    $line = "[$date] $msg\n";
    echo $line;
    file_put_contents($log_file, $line, FILE_APPEND);
}

write_log("=== Starting Orphan Cleanup ===");

// ---------------------------------------------------------
// Case C: Automatische Papierkorb-Leerung (90 Tage)
// ---------------------------------------------------------
write_log("Running Case C: Deleting files in trash older than 90 days...");

$stmt = $conn->prepare("SELECT id, filename, physical_path, uploader_id FROM files WHERE deleted = 1 AND deleted_at <= DATE_SUB(NOW(), INTERVAL 90 DAY)");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $deleted_count = 0;
    while ($file = $result->fetch_assoc()) {
        $file_id = $file['id'];
        $filename = $file['filename'];
        $uploader_id = $file['uploader_id'];
        
        $user_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . $uploader_id;
        
        if (!empty($file['physical_path'])) {
            $file_path = $user_dir . '/' . $file['physical_path'];
        } else {
            $new_filepath = $user_dir . '/' . $file_id . '_' . basename($filename);
            $old_filepath = $user_dir . '/' . basename($filename);
            $file_path = file_exists($new_filepath) ? $new_filepath : $old_filepath;
        }

        // Physische Datei löschen, falls vorhanden
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                write_log("Deleted physical file: $file_path (File ID: $file_id)");
            } else {
                write_log("ERROR: Could not delete physical file: $file_path (File ID: $file_id)");
            }
        }

        // DB Eintrag endgültig löschen
        $conn->query("DELETE FROM files WHERE id = $file_id");
        $deleted_count++;
    }
    $stmt->close();
    write_log("Case C finished. Permanently deleted $deleted_count old trash entries.");
} else {
    write_log("ERROR: Could not prepare statement for Case C.");
}

// ---------------------------------------------------------
// Case A: Fehlende Dateien im Dateisystem
// ---------------------------------------------------------
write_log("Running Case A: Removing DB entries with missing physical files...");

$stmt = $conn->prepare("SELECT id, filename, physical_path, uploader_id FROM files WHERE deleted = 0");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $missing_count = 0;
    while ($file = $result->fetch_assoc()) {
        $file_id = $file['id'];
        $filename = $file['filename'];
        $uploader_id = $file['uploader_id'];
        
        $user_dir = rtrim(USER_UPLOAD_DIR, '/') . '/' . $uploader_id;
        
        if (!empty($file['physical_path'])) {
            $file_path = $user_dir . '/' . $file['physical_path'];
        } else {
            $new_filepath = $user_dir . '/' . $file_id . '_' . basename($filename);
            $old_filepath = $user_dir . '/' . basename($filename);
            
            if (file_exists($new_filepath)) {
                $file_path = $new_filepath;
            } elseif (file_exists($old_filepath)) {
                $file_path = $old_filepath;
            } else {
                $file_path = $new_filepath; // Fallback
            }
        }

        if (!file_exists($file_path)) {
            write_log("File ID $file_id ('$filename') is missing physically at: $file_path. Removing DB entry.");
            $conn->query("DELETE FROM files WHERE id = $file_id");
            $missing_count++;
        }
    }
    $stmt->close();
    write_log("Case A finished. Removed $missing_count orphaned DB entries.");
} else {
    write_log("ERROR: Could not prepare statement for Case A.");
}

write_log("=== Orphan Cleanup Finished ===\n");
